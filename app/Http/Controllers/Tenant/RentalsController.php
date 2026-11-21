<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRentalRequest;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Garage;
use App\Models\GarageType;
use App\Models\Transaction;
use App\Models\TransactionExtension;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Sewa milik penyewa: daftar "Sewaan saya", pratinjau harga, dan sewa baru (Sewa Baru).
 * Penyewa tidak dapat memperpanjang; perpanjangan khusus admin.
 */
class RentalsController extends Controller
{
    /**
     * Sewa milik penyewa (terbaru dulu), dipisah menjadi aktif (berjalan hari ini atau setelahnya) dan riwayat.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $today = today();

        $rentals = Transaction::query()
            ->whereIn('vehicle_id', $user->customer->vehicles()->select('id'))
            ->with(['garage', 'vehicle', 'extensions' => fn ($q) => $q->orderBy('id')])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Transaction $t): array => $this->shape($t));

        return response()->json([
            'active' => $rentals->filter(fn (array $r): bool => $r['end_date'] >= $today->toDateString())->values(),
            'history' => $rentals->filter(fn (array $r): bool => $r['end_date'] < $today->toDateString())->values(),
        ]);
    }

    /**
     * Pratinjau harga: total dihitung sistem, penyewa tidak pernah mengetiknya.
     */
    public function quote(QuoteRentalRequest $request, RentalService $rentals): JsonResponse
    {
        [$vehicle, $garage] = $this->resolve($request);

        return response()->json([
            'data' => $rentals->quote(
                $vehicle,
                $garage,
                $request->boolean('is_monthly'),
                $request->validated('days') === null ? null : (int) $request->validated('days'),
                $request->validated('battery_level') === null ? null : (int) $request->validated('battery_level'),
            ),
        ]);
    }

    /**
     * Simpan sewa baru beserta bukti pelunasan; status garasi menjadi "used".
     */
    public function store(StoreRentalRequest $request, RentalService $rentals): JsonResponse
    {
        [$vehicle, $garage] = $this->resolve($request);

        $transaction = $rentals->create(
            $vehicle,
            $garage,
            $request->boolean('is_monthly'),
            $request->validated('days') === null ? null : (int) $request->validated('days'),
            $request->validated('battery_level') === null ? null : (int) $request->validated('battery_level'),
            $request->file('transaction_proof'),
        );

        $transaction->load(['garage', 'vehicle', 'extensions']);

        return response()->json([
            'message' => sprintf(
                'Sewa garasi %s berhasil disimpan (%s s.d. %s). Total Rp%s.',
                $transaction->garage_id,
                $transaction->start_date->format('d/m/Y'),
                $transaction->end_date->format('d/m/Y'),
                number_format((int) $transaction->rent_price_total, 0, ',', '.'),
            ),
            'data' => $this->shape($transaction),
        ], 201);
    }

    /**
     * Ambil kendaraan milik penyewa (404 untuk milik orang lain) dan garasinya.
     *
     * @return array{0: Vehicle, 1: Garage}
     */
    private function resolve(QuoteRentalRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Vehicle $vehicle */
        $vehicle = $user->customer->vehicles()->findOrFail((int) $request->validated('vehicle_id'));
        /** @var Garage $garage */
        $garage = Garage::query()->findOrFail((string) $request->validated('garage_id'));

        return [$vehicle, $garage];
    }

    /**
     * Bentuk JSON satu sewa untuk penyewa.
     *
     * @return array<string, mixed>
     */
    private function shape(Transaction $t): array
    {
        $extensionTotal = (int) $t->extensions->sum('extra_rent');

        return [
            'id' => $t->id,
            'garage_id' => $t->garage_id,
            'garage_label' => GarageType::labelFor($t->garage?->garage_type_id),
            'vehicle' => [
                'type' => $t->vehicle?->type,
                'license_plate' => $t->vehicle?->license_plate,
                'brand' => trim(($t->vehicle?->brand ?? '').' '.($t->vehicle?->brand_type ?? '')),
            ],
            'is_monthly' => (bool) $t->is_monthly,
            'start_date' => $t->start_date->toDateString(),
            'end_date' => $t->end_date->toDateString(),
            'battery_level' => $t->battery_level,
            'rent_price_total' => (int) $t->rent_price_total,
            'charge_price_total' => (int) $t->charge_price_total,
            'extension_total' => $extensionTotal,
            'grand_total' => (int) $t->rent_price_total + $extensionTotal,
            'transaction_proof_url' => $t->transaction_proof ? Storage::disk('public')->url($t->transaction_proof) : null,
            'extensions' => $t->extensions->map(fn (TransactionExtension $e): array => [
                'current_end_date' => $e->current_end_date->toDateString(),
                'new_end_date' => $e->new_end_date->toDateString(),
                'extra_rent' => (int) $e->extra_rent,
            ])->values(),
        ];
    }
}
