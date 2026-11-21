<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\GarageType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business rules for issuing and re-pricing garage slots.
 *
 * A type may issue at most 999 garages, ever: the sequence (garage_types.count)
 * only goes up and is never reused, even if slot rows are removed later. All
 * writes run inside a DB transaction with the type row locked for update so
 * concurrent creates cannot collide or exceed the cap.
 */
class GarageService
{
    /**
     * Maximum number of garages ever issuable per type (S001..S999, etc).
     */
    public const MAX_PER_TYPE = 999;

    /**
     * Garages still available to be issued for a type.
     */
    public function remainingCapacity(string $typeId): int
    {
        $count = (int) GarageType::query()->whereKey($typeId)->value('count');

        return max(0, self::MAX_PER_TYPE - $count);
    }

    /**
     * Issue exactly one new garage slot for the given type.
     */
    public function createOne(string $typeId, int $rentPrice, ?int $chargePriceKwh): Garage
    {
        return DB::transaction(
            fn (): Garage => $this->issue($typeId, 1, $rentPrice, $chargePriceKwh)->first(),
        );
    }

    /**
     * Issue several new garage slots for the given type (all-or-nothing).
     *
     * @return Collection<int, Garage>
     */
    public function createBulk(string $typeId, int $quantity, int $rentPrice, ?int $chargePriceKwh): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->issue($typeId, $quantity, $rentPrice, $chargePriceKwh),
        );
    }

    /**
     * Change only the price fields of an existing garage.
     */
    public function update(Garage $garage, int $rentPrice, ?int $chargePriceKwh): Garage
    {
        return DB::transaction(function () use ($garage, $rentPrice, $chargePriceKwh): Garage {
            /** @var Garage $fresh */
            $fresh = Garage::query()->whereKey($garage->getKey())->lockForUpdate()->firstOrFail();

            // Types S and C never carry a kWh rate; type E keeps its stored
            // rate when the caller omits one.
            $charge = null;

            if ($fresh->garage_type_id === 'E') {
                $charge = $chargePriceKwh ?? (int) $fresh->charge_price_kwh;
            }

            $fresh->forceFill([
                'rent_price' => $rentPrice,
                'charge_price_kwh' => $charge,
            ])->save();

            return $fresh;
        });
    }

    /**
     * Issue $quantity slots under the locked sequence of one type.
     *
     * @return Collection<int, Garage>
     */
    private function issue(string $typeId, int $quantity, int $rentPrice, ?int $chargePriceKwh): Collection
    {
        /** @var GarageType|null $type */
        $type = GarageType::query()->whereKey($typeId)->lockForUpdate()->first();

        if ($type === null) {
            throw ValidationException::withMessages([
                'garage_type_id' => ['Tipe garasi tidak dikenal: '.$typeId.''],
            ]);
        }

        $base = (int) $type->count;

        if ($base + $quantity > self::MAX_PER_TYPE) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Kapasitas tipe %s tersisa %d garasi.',
                    $typeId,
                    max(0, self::MAX_PER_TYPE - $base),
                ),
            ]);
        }

        // Types S and C are forced to a null kWh rate regardless of input.
        if ($typeId !== 'E') {
            $chargePriceKwh = null;
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $quantity; $i++) {
            $number = $base + $i + 1;

            $rows[] = [
                'id' => $typeId.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'garage_type_id' => $typeId,
                'rent_price' => $rentPrice,
                'charge_price_kwh' => $chargePriceKwh,
                'is_used' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insert() bypasses mass assignment on purpose (explicit row shape).
        foreach (array_chunk($rows, 500) as $chunk) {
            Garage::insert($chunk);
        }

        // The sequence advances by exactly the amount issued, in the same transaction.
        $type->forceFill(['count' => $base + $quantity])->save();

        return Garage::query()
            ->where('garage_type_id', $typeId)
            ->whereIn('id', array_column($rows, 'id'))
            ->orderBy('id')
            ->get();
    }
}
