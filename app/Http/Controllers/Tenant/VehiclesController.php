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
 * Endpoint penyewa untuk melihat, menambah, dan mengubah kendaraan miliknya.
 */
class VehiclesController extends Controller
{
    /**
     * Daftar kendaraan penyewa beserta info profil untuk header.
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
     * Tambah kendaraan baru ke profil penyewa.
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
     * Ubah kendaraan milik penyewa sendiri (404 untuk kendaraan penyewa lain).
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
     * Query yang dibatasi pada profil pelanggan pemanggil.
     */
    private function ownVehicles(UserModel $user): Builder
    {
        return Vehicle::query()
            ->where('customer_id', $user->customer_id)
            ->orderByDesc('id');
    }

    /**
     * Terapkan aturan per tipe: sepeda tanpa plat; kapasitas baterai hanya untuk mobil_listrik.
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
     * Bentuk JSON satu baris kendaraan.
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
