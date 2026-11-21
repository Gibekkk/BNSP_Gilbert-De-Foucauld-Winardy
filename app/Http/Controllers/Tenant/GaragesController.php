<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;

/**
 * Read-only preview of available (non-used) garage slots for the tenant portal.
 */
class GaragesController extends Controller
{
    private const LABELS = [
        'S' => 'Sepeda',
        'C' => 'Mobil',
        'E' => 'Mobil Listrik',
    ];

    /**
     * List every still-free garage with its own pricing (prices live per garage now).
     */
    public function preview(GarageService $service): JsonResponse
    {
        // Garasi yang masa sewanya sudah habis harus tampil tersedia lagi.
        $service->syncUsage();

        $data = Garage::query()
            ->where('is_used', false)
            ->orderBy('id')
            ->get()
            ->map(fn (Garage $garage): array => [
                'key' => $garage->garage_type_id,
                'id' => $garage->id,
                'label' => self::LABELS[$garage->garage_type_id] ?? 'Tipe '.$garage->garage_type_id,
                'rent_price' => (int) $garage->rent_price,
                'charge_price_kwh' => $garage->charge_price_kwh === null ? null : (int) $garage->charge_price_kwh,
            ])
            ->values();

        return response()->json(['data' => $data]);
    }
}
