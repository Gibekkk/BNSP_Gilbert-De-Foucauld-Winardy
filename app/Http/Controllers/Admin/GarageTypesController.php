<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GarageType;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;

/**
 * Ringkasan tipe garasi untuk admin: jumlah terbit dan sisa kapasitas.
 */
class GarageTypesController extends Controller
{

    /**
     * Daftar tipe S, C, dan E beserta jumlah terbit dan sisa kapasitasnya.
     */
    public function index(): JsonResponse
    {
        $data = GarageType::query()->orderBy('id')->get()->map(function (GarageType $type): array {
            $count = (int) $type->count;

            return [
                'key' => $type->id,
                'label' => GarageType::labelFor($type->id),
                'count' => $count,
                'remaining_capacity' => max(0, GarageService::MAX_PER_TYPE - $count),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
