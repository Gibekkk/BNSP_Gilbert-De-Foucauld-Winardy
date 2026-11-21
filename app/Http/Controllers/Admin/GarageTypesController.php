<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GarageType;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;

/**
 * Admin overview of the garage types: issued count and remaining capacity.
 */
class GarageTypesController extends Controller
{
    private const LABELS = [
        'S' => 'Sepeda',
        'C' => 'Mobil',
        'E' => 'Mobil Listrik',
    ];

    /**
     * List S, C and E with their issued count and remaining capacity.
     */
    public function index(): JsonResponse
    {
        $data = GarageType::query()->orderBy('id')->get()->map(function (GarageType $type): array {
            $count = (int) $type->count;

            return [
                'key' => $type->id,
                'label' => self::LABELS[$type->id] ?? 'Tipe '.$type->id,
                'count' => $count,
                'remaining_capacity' => max(0, GarageService::MAX_PER_TYPE - $count),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
