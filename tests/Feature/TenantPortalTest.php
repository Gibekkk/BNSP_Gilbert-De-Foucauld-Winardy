<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User as UserModel;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantPortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register a tenant through the public flow (starts pending).
     */
    private function registerTenant(string $email, string $nik): UserModel
    {
        $res = $this->postJson('/api/register', [
            'nik' => $nik,
            'phone' => '081234567890',
            'name' => 'Pak Penyewa',
            'email' => $email,
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ]);

        $res->assertCreated();

        return UserModel::where('email', $email)->first();
    }

    /**
     * Log in and hand back the JWT.
     */
    private function tokenOf(string $email, string $password): string
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk()->json('token');
    }

    /**
     * Approve the tenant via a known admin account.
     */
    private function makeApprovedTenant(string $email, string $nik): UserModel
    {
        $tenant = $this->registerTenant($email, $nik);

        $adminEmail = 'boss-'.bin2hex(random_bytes(4)).'@example.com';
        UserModel::factory()->admin()->create([
            'email' => $adminEmail,
            'password' => 'rahasia-246',
        ]);

        $adminToken = $this->tokenOf($adminEmail, 'rahasia-246');

        $this->postJson('/api/admin/customers/'.$tenant->id.'/approve', [], ['Authorization' => 'Bearer '.$adminToken])
            ->assertOk()
            ->assertJsonPath('data.status', Customer::STATUS_ACCEPTED);

        return $tenant;
    }

    public function test_tenant_endpoints_reject_anonymous_users_and_admins(): void
    {
        $this->getJson('/api/customer/vehicles')->assertStatus(401);
        $this->getJson('/api/customer/garage-preview')->assertStatus(401);

        $adminEmail = 'chief-'.bin2hex(random_bytes(4)).'@example.com';
        UserModel::factory()->admin()->create([
            'email' => $adminEmail,
            'password' => 'rahasia-246',
        ]);
        $token = $this->tokenOf($adminEmail, 'rahasia-246');

        $this->getJson('/api/customer/vehicles', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
        $this->getJson('/api/customer/garage-preview', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
    }

    public function test_vehicle_creation_applies_rules_per_type(): void
    {
        $this->makeApprovedTenant('penyewa@mail.id', '3201152988071010');
        $token = $this->tokenOf('penyewa@mail.id', 'rahasia-123');
        $headers = ['Authorization' => 'Bearer '.$token];

        // Sepeda motor: tanpa pelat harus 422.
        $this->postJson('/api/customer/vehicles', [
            'type' => 'sepeda_motor',
            'brand' => 'Yamaha',
            'brand_type' => 'NMAX 155',
        ], $headers)->assertStatus(422);

        // Sepeda motor: plat tersimpan, kWh dipaksa null.
        $this->postJson('/api/customer/vehicles', [
            'type' => 'sepeda_motor',
            'brand' => 'Yamaha',
            'brand_type' => 'NMAX 155',
            'license_plate' => 'D 9001 XYZ',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.license_plate', 'D 9001 XYZ')
            ->assertJsonPath('data.battery_capacity', null);

        // Mobil listrik: kWh wajib, pelat juga wajib.
        $this->postJson('/api/customer/vehicles', [
            'type' => 'mobil_listrik',
            'brand' => 'Neta',
            'brand_type' => 'V',
            'license_plate' => 'B 900 EV',
        ], $headers)->assertStatus(422);

        $evId = $this->postJson('/api/customer/vehicles', [
            'type' => 'mobil_listrik',
            'brand' => 'Neta',
            'brand_type' => 'V',
            'license_plate' => 'B 900 EV',
            'battery_capacity' => 60,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.battery_capacity', 60)
            ->json('data.id');

        // Sepeda: pelat dan kWh dibuang oleh server meski dikirim klien.
        $bikeId = $this->postJson('/api/customer/vehicles', [
            'type' => 'sepeda',
            'brand' => 'Schwinn',
            'brand_type' => 'Urban 28',
            'license_plate' => 'ABC-9',
            'battery_capacity' => 5,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.license_plate', null)
            ->assertJsonPath('data.battery_capacity', null)
            ->json('data.id');

        // Konfirmasi nilai akhir di database.
        $ev = Vehicle::find($evId);
        $this->assertSame(60, (int) $ev->battery_capacity);
        $this->assertSame('B 900 EV', $ev->license_plate);

        $bike = Vehicle::find($bikeId);
        $this->assertNull($bike->license_plate);
        $this->assertNull($bike->battery_capacity);
    }

    public function test_vehicle_listing_scoped_to_owner_and_update_only_by_owner(): void
    {
        $tenantA = $this->makeApprovedTenant('penyewa2@mail.id', '3201152988071020');
        $tenantB = $this->makeApprovedTenant('penyewa3@mail.id', '3201152988071030');

        $tokenA = $this->tokenOf('penyewa2@mail.id', 'rahasia-123');
        $tokenB = $this->tokenOf('penyewa3@mail.id', 'rahasia-123');

        $myId = $this->postJson('/api/customer/vehicles', [
            'type' => 'sepeda_motor',
            'brand' => 'Honda',
            'brand_type' => 'Vario 160',
            'license_plate' => 'D 4321 QWE',
        ], ['Authorization' => 'Bearer '.$tokenA])->assertCreated()->json('data.id');

        // Kendaraan tenant lain tidak boleh muncul di daftar kami.
        Vehicle::factory()->create(['id' => 99, 'customer_id' => $tenantB->id]);

        $list = $this->getJson('/api/customer/vehicles', ['Authorization' => 'Bearer '.$tokenA])
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $list);
        $this->assertSame($myId, $list[0]['id']);

        // Pemilik dapat mengubah kendaraannya.
        $this->patchJson('/api/customer/vehicles/'.$myId, [
            'type' => 'sepeda_motor',
            'brand' => 'Yamaha',
            'brand_type' => 'Aerox 155',
            'license_plate' => 'D 4321 QWE',
        ], ['Authorization' => 'Bearer '.$tokenA])
            ->assertOk()
            ->assertJsonPath('data.brand_type', 'Aerox 155');

        $this->assertSame('Aerox 155', Vehicle::find($myId)->brand_type);

        // Tenant lain gagal mengubah kendaraan milik kami (404).
        $this->patchJson('/api/customer/vehicles/'.$myId, [
            'type' => 'sepeda_motor',
            'brand' => 'Lain',
            'brand_type' => 'X',
            'license_plate' => 'D 0000 AA',
        ], ['Authorization' => 'Bearer '.$tokenB])->assertStatus(404);
    }

    public function test_garage_preview_lists_seeded_types_with_pricing(): void
    {
        $this->makeApprovedTenant('penyewa4@mail.id', '3201152988071040');
        $token = $this->tokenOf('penyewa4@mail.id', 'rahasia-123');

        DB::table('garage_types')->where('id', 'S')->update(['rent_price' => 1500, 'charge_price_kwh' => null]);
        DB::table('garage_types')->where('id', 'C')->update(['rent_price' => 5000, 'charge_price_kwh' => null]);
        DB::table('garage_types')->where('id', 'E')->update(['rent_price' => 8000, 'charge_price_kwh' => 1600]);

        $data = $this->getJson('/api/customer/garage-preview', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->json('data');

        $byKey = collect($data)->keyBy('key');
        $this->assertCount(3, $data);
        $this->assertSame('Sepeda', $byKey['S']['label']);
        $this->assertSame(1500, (int) $byKey['S']['rent_price']);
        $this->assertNull($byKey['S']['charge_price_kwh']);
        $this->assertSame(8000, (int) $byKey['E']['rent_price']);
        $this->assertSame(1600, (int) $byKey['E']['charge_price_kwh']);
    }
}
