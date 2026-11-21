<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Models\User as UserModel;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant endpoints to read, add, and edit their own vehicles.
 */
class VehiclesController extends Controller
{
    /**
     * List the tenant's vehicles plus their profile header info.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        return response()->json([
            'customer' => [
                'name' => $user->customer?->name,
                'nik' => $user->customer?->nik,
            ],
            'data' => $this->ownVehicles($user)->get()->map(
                fn (Vehicle $vehicle): array => $this->shape($vehicle),
            ),
        ]);
    }

    /**
     * Add a new vehicle to the tenant's profile.
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();
        $attributes = $this->normalize($request->validated());

        $vehicle = Vehicle::query()->create(array_merge([
            'customer_id' => $user->customer_id,
        ], $attributes));

        return response()->json([
            'message' => 'Kendaraan berhasil ditambahkan.',
            'data' => $this->shape($vehicle),
        ], 201);
    }

    /**
     * Edit one of the tenant's own vehicles (404 for other tenants' rows).
     */
    public function update(StoreVehicleRequest $request, int $id): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $vehicle = $this->ownVehicles($user)->findOrFail($id);

        $attributes = $this->normalize($request->validated());

        $vehicle->forceFill($attributes)->save();

        return response()->json([
            'message' => 'Kendaraan berhasil diubah.',
            'data' => $this->shape($vehicle),
        ]);
    }

    /**
     * Query scope restricted to the caller's customer profile.
     */
    private function ownVehicles(UserModel $user): Builder
    {
        return Vehicle::query()
            ->where('customer_id', $user->customer_id)
            ->orderByDesc('id');
    }

    /**
     * Apply per-type rules: sepeda has no plate; battery kWh only on mobil_listrik.
     */
    private function normalize(array $attributes): array
    {
        $type = (string) $attributes['type'];

        return [
            'type' => $type,
            'license_plate' => $type === 'sepeda' ? null : ($attributes['license_plate'] ?? null),
            'brand' => (string) $attributes['brand'],
            'brand_type' => (string) $attributes['brand_type'],
            'battery_capacity' => $type === 'mobil_listrik' ? (int) ($attributes['battery_capacity'] ?? 0) : null,
        ];
    }

    /**
     * Shape one vehicle row for JSON responses.
     */
    private function shape(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'type' => $vehicle->type,
            'license_plate' => $vehicle->license_plate,
            'brand' => $vehicle->brand,
            'brand_type' => $vehicle->brand_type,
            'battery_capacity' => $vehicle->battery_capacity === null ? null : (int) $vehicle->battery_capacity,
            'created_at' => $vehicle->created_at?->toIso8601String() ?? '',
        ];
    }
}
