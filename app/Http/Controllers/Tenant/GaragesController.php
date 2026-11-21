<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\GarageType;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Garasi yang dapat disewa penyewa hari ini untuk salah satu kendaraannya.
 */
class GaragesController extends Controller
{
    /**
     * Garasi kosong (hari ini) dengan tipe yang diizinkan untuk kendaraan terpilih,
     * beserta batas sewa untuk tanggal mulai hari ini.
     */
    public function available(Request $request, RentalService $rentals, GarageService $garages): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'vehicle_id' => ['required', 'integer'],
            'type' => ['nullable', 'in:S,C,E'],
        ], [
            'vehicle_id.required' => 'Pilih kendaraan terlebih dahulu.',
        ])->validate();

        /** @var User $user */
        $user = $request->user();
        $vehicle = $user->customer->vehicles()->findOrFail((int) $data['vehicle_id']);

        // Pastikan status "used" akurat sebelum ditampilkan.
        $garages->syncUsage();

        $allowed = $rentals->allowedGarageTypes($vehicle->type);
        $types = isset($data['type']) ? array_values(array_intersect($allowed, [$data['type']])) : $allowed;
        $start = $rentals->startDate();

        return response()->json([
            'vehicle_free' => $rentals->vehicleIsFree($vehicle->id, $start, $start),
            'allowed_types' => array_map(fn (string $t): array => ['key' => $t, 'label' => GarageType::labelFor($t)], $allowed),
            'limits' => [
                'start_date' => $start->toDateString(),
                'max_daily_days' => $rentals->maxDailyDays($start),
                'monthly_days' => $rentals->monthlyLengthInDays($start),
                'monthly_end_date' => $rentals->endDate($start, $rentals->monthlyLengthInDays($start))->toDateString(),
            ],
            'data' => $rentals->availableGarages($types)->map(fn (Garage $garage): array => [
                'id' => $garage->id,
                'type' => $garage->garage_type_id,
                'label' => GarageType::labelFor($garage->garage_type_id),
                'rent_price' => (int) $garage->rent_price,
                'monthly_price' => $rentals->monthlyPrice((int) $garage->rent_price),
                'charge_price_kwh' => $garage->charge_price_kwh === null ? null : (int) $garage->charge_price_kwh,
            ])->values(),
        ]);
    }
}
