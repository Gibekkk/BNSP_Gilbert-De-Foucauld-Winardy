<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\GarageType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bisnis penerbitan garasi baru dan perubahan harganya.
 *
 * Setiap tipe maksimal menerbitkan 999 garasi selamanya: urutan (garage_types.count)
 * hanya naik dan tidak pernah dipakai ulang, walaupun baris garasi dihapus. Semua
 * penulisan berjalan dalam transaksi DB dengan baris tipe dikunci (lock for update)
 * sehingga pembuatan bersamaan tidak bentrok atau melewati batas.
 */
class GarageService
{
    /**
     * Jumlah maksimum garasi yang dapat diterbitkan per tipe (S001..S999, dst).
     */
    public const MAX_PER_TYPE = 999;

    /**
     * Sisa garasi yang masih dapat diterbitkan untuk satu tipe.
     */
    public function remainingCapacity(string $typeId): int
    {
        $count = (int) GarageType::query()->whereKey($typeId)->value('count');

        return max(0, self::MAX_PER_TYPE - $count);
    }

    /**
     * Terbitkan tepat satu garasi baru untuk tipe tertentu.
     */
    public function createOne(string $typeId, int $rentPrice, ?int $chargePriceKwh): Garage
    {
        return DB::transaction(
            fn (): Garage => $this->issue($typeId, 1, $rentPrice, $chargePriceKwh)->first(),
        );
    }

    /**
     * Terbitkan beberapa garasi baru sekaligus untuk tipe tertentu (semua atau tidak sama sekali).
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
     * Ubah hanya kolom harga dari garasi yang sudah ada.
     */
    public function update(Garage $garage, int $rentPrice, ?int $chargePriceKwh): Garage
    {
        return DB::transaction(function () use ($garage, $rentPrice, $chargePriceKwh): Garage {
            /** @var Garage $fresh */
            $fresh = Garage::query()->whereKey($garage->getKey())->lockForUpdate()->firstOrFail();

            // Tipe S dan C tidak pernah memiliki tarif kWh; tipe E mempertahankan
            // tarif tersimpan bila tidak diisi.
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
     * Sinkronkan garages.is_used dengan sewa yang berjalan hari ini.
     *
     * Garasi berstatus "used" selama memiliki transaksi dengan
     * start_date <= hari ini <= end_date. Tanpa ini garasi akan tetap
     * "used" selamanya setelah sewanya selesai. Dijalankan harian lewat scheduler
     * (routes/console.php) dan sebelum layar yang menampilkan status garasi.
     *
     * @return array{released: int, occupied: int}
     */
    public function syncUsage(): array
    {
        $today = today()->toDateString();

        $active = fn ($query) => $query
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);

        $released = Garage::query()
            ->where('is_used', true)
            ->whereDoesntHave('transactions', $active)
            ->update(['is_used' => false]);

        $occupied = Garage::query()
            ->where('is_used', false)
            ->whereHas('transactions', $active)
            ->update(['is_used' => true]);

        return ['released' => $released, 'occupied' => $occupied];
    }

    /**
     * Terbitkan $quantity garasi memakai urutan tipe yang sedang dikunci.
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

        // Tipe S dan C selalu tanpa tarif kWh, apa pun inputnya.
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

        // insert() sengaja melewati mass assignment (bentuk baris ditulis eksplisit).
        foreach (array_chunk($rows, 500) as $chunk) {
            Garage::insert($chunk);
        }

        // Urutan bertambah tepat sejumlah garasi yang diterbitkan, dalam transaksi yang sama.
        $type->forceFill(['count' => $base + $quantity])->save();

        return Garage::query()
            ->where('garage_type_id', $typeId)
            ->whereIn('id', array_column($rows, 'id'))
            ->orderBy('id')
            ->get();
    }
}
