<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test kendaraan penyewa: aturan plat, kapasitas baterai (kWh), dan kepemilikan.
 */
class TenantVehicleTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    /**
     * Bekukan waktu dan palsukan disk sebelum setiap test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApi();
    }

    /**
     * Endpoint penyewa menolak tamu dan admin.
     */
    public function test_tenant_endpoints_reject_guests_and_admins(): void
    {
        $this->apiAs(null, 'GET', '/api/customer/vehicles')->assertUnauthorized();
        $this->apiAs($this->createAdmin(), 'GET', '/api/customer/vehicles')->assertForbidden();
    }

    /**
     * Plat wajib kecuali sepeda; kapasitas baterai hanya (dan wajib) untuk mobil listrik.
     */
    public function test_vehicle_rules_per_type(): void
    {
        $tenant = $this->createTenant();

        $this->apiAs($tenant, 'POST', '/api/customer/vehicles', ['type' => 'mobil', 'brand' => 'Toyota', 'brand_type' => 'Avanza'])
            ->assertUnprocessable()->assertJsonValidationErrors(['license_plate']);

        $this->apiAs($tenant, 'POST', '/api/customer/vehicles', ['type' => 'mobil_listrik', 'brand' => 'BYD', 'brand_type' => 'Seal', 'license_plate' => 'L 1 EV'])
            ->assertUnprocessable()->assertJsonValidationErrors(['battery_capacity']);

        $this->apiAs($tenant, 'POST', '/api/customer/vehicles', ['type' => 'mobil_listrik', 'brand' => 'BYD', 'brand_type' => 'Seal', 'license_plate' => 'L 1 EV', 'battery_capacity' => 301])
            ->assertUnprocessable()->assertJsonPath('errors.battery_capacity.0', 'Kapasitas baterai maksimal 300 kWh.');

        $this->apiAs($tenant, 'POST', '/api/customer/vehicles', ['type' => 'sepeda', 'brand' => 'Polygon', 'brand_type' => 'X', 'license_plate' => 'DIABAIKAN', 'battery_capacity' => 50])
            ->assertCreated()->assertJsonPath('data.license_plate', null)->assertJsonPath('data.battery_capacity', null);

        $this->apiAs($tenant, 'POST', '/api/customer/vehicles', ['type' => 'mobil_listrik', 'brand' => 'BYD', 'brand_type' => 'Seal', 'license_plate' => 'L 1 EV', 'battery_capacity' => 60])
            ->assertCreated()->assertJsonPath('data.battery_capacity', 60);
    }

    /**
     * Penyewa hanya melihat dan mengubah kendaraan miliknya sendiri.
     */
    public function test_vehicles_are_scoped_to_their_owner(): void
    {
        $owner = $this->createTenant('a@example.com');
        $other = $this->createTenant('b@example.com');
        $vehicle = $this->makeVehicle($owner, 'mobil');
        $this->makeVehicle($other, 'mobil');

        $this->assertCount(1, $this->apiAs($owner, 'GET', '/api/customer/vehicles')->assertOk()->json('data'));

        $payload = ['type' => 'mobil', 'brand' => 'Honda', 'brand_type' => 'Jazz', 'license_plate' => 'L 2 XY'];
        $this->apiAs($other, 'PATCH', '/api/customer/vehicles/'.$vehicle->id, $payload)->assertNotFound();
        $this->apiAs($owner, 'PATCH', '/api/customer/vehicles/'.$vehicle->id, $payload)->assertOk();

        $this->assertSame('Honda', Vehicle::query()->find($vehicle->id)->brand);
    }
}
