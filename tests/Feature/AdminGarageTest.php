<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\GarageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test kelola garasi oleh admin: pembuatan berkelompok dengan kode
 * otomatis S/C/E + 3 digit, batas 999, tarif kWh, ubah harga, dan daftar.
 */
class AdminGarageTest extends TestCase
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
     * Endpoint garasi menolak pengguna tanpa token dan penyewa.
     */
    public function test_garage_endpoints_are_admin_only(): void
    {
        $this->apiAs(null, 'GET', '/api/admin/garages')->assertUnauthorized();
        $this->apiAs($this->createTenant(), 'POST', '/api/admin/garages/bulk', [])->assertForbidden();
    }

    /**
     * Pembuatan berkelompok menerbitkan kode berurutan per tipe (S001.., C001..).
     */
    public function test_bulk_create_issues_sequential_codes_per_type(): void
    {
        $admin = $this->createAdmin();

        $this->apiAs($admin, 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'S', 'quantity' => 3, 'rent_price' => 5000])
            ->assertCreated()->assertJsonPath('data.first_id', 'S001')->assertJsonPath('data.last_id', 'S003');

        $this->apiAs($admin, 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'S', 'quantity' => 2, 'rent_price' => 6000])
            ->assertCreated()->assertJsonPath('data.first_id', 'S004');

        $this->apiAs($admin, 'POST', '/api/admin/garages', ['garage_type_id' => 'C', 'rent_price' => 50000])
            ->assertCreated()->assertJsonPath('data.id', 'C001');

        $this->assertSame(5, GarageType::query()->find('S')->count);
        $this->assertSame(5000, Garage::query()->find('S001')->rent_price);
        $this->assertSame(6000, Garage::query()->find('S005')->rent_price);
    }

    /**
     * Kode ke-10 berformat 3 digit (C010).
     */
    public function test_tenth_garage_code_is_three_digits(): void
    {
        $this->apiAs($this->createAdmin(), 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'C', 'quantity' => 10, 'rent_price' => 50000])
            ->assertCreated()->assertJsonPath('data.last_id', 'C010');
    }

    /**
     * Tidak boleh melewati 999 garasi per tipe; tidak ada baris yang tersimpan bila gagal.
     */
    public function test_bulk_cannot_exceed_999_per_type(): void
    {
        $admin = $this->createAdmin();
        GarageType::query()->whereKey('E')->update(['count' => 998]);

        $this->apiAs($admin, 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'E', 'quantity' => 2, 'rent_price' => 75000, 'charge_price_kwh' => 2500])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
        $this->assertSame(0, Garage::query()->count());

        $this->apiAs($admin, 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'E', 'quantity' => 1, 'rent_price' => 75000, 'charge_price_kwh' => 2500])
            ->assertCreated()->assertJsonPath('data.first_id', 'E999');

        $this->apiAs($admin, 'POST', '/api/admin/garages/bulk', ['garage_type_id' => 'S', 'quantity' => 1000, 'rent_price' => 5000])
            ->assertUnprocessable();
    }

    /**
     * Tarif kWh wajib untuk garasi mobil listrik dan dilarang untuk tipe lain.
     */
    public function test_kwh_price_rules_follow_the_garage_type(): void
    {
        $admin = $this->createAdmin();

        $this->apiAs($admin, 'POST', '/api/admin/garages', ['garage_type_id' => 'E', 'rent_price' => 75000])
            ->assertUnprocessable()->assertJsonValidationErrors(['charge_price_kwh']);

        $this->apiAs($admin, 'POST', '/api/admin/garages', ['garage_type_id' => 'S', 'rent_price' => 5000, 'charge_price_kwh' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors(['charge_price_kwh']);

        $this->apiAs($admin, 'POST', '/api/admin/garages', ['garage_type_id' => 'E', 'rent_price' => 75000, 'charge_price_kwh' => 2500])
            ->assertCreated()->assertJsonPath('data.charge_price_kwh', 2500);
    }

    /**
     * Ubah harga hanya mengubah kolom harga.
     */
    public function test_update_changes_only_prices(): void
    {
        $admin = $this->createAdmin();
        $garage = $this->makeGarage('E', 75000, 2500);

        $this->apiAs($admin, 'PUT', '/api/admin/garages/'.$garage->id, ['rent_price' => 80000, 'charge_price_kwh' => 3000, 'garage_type_id' => 'S'])
            ->assertOk()->assertJsonPath('data.rent_price', 80000)->assertJsonPath('data.charge_price_kwh', 3000);

        $this->assertSame('E', $garage->fresh()->garage_type_id);
        $this->apiAs($admin, 'PUT', '/api/admin/garages/X999', ['rent_price' => 1])->assertNotFound();
    }

    /**
     * Daftar garasi dapat difilter per tipe, urut kode, dengan info sewa aktif.
     */
    public function test_list_filters_by_type_and_shows_active_rental(): void
    {
        $admin = $this->createAdmin();
        $tenant = $this->createTenant();
        $c1 = $this->makeGarage('C');
        $this->makeGarage('C');
        $this->makeGarage('S', 5000);
        $rental = $this->makeRental($this->makeVehicle($tenant), $c1);

        $res = $this->apiAs($admin, 'GET', '/api/admin/garages?type=C')->assertOk();

        $this->assertSame(['C001', 'C002'], array_column($res->json('data'), 'id'));
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertTrue($res->json('data.0.is_used'));
        $this->assertSame($rental->id, $res->json('data.0.active_rental.id'));
        $this->assertNull($res->json('data.1.active_rental'));

        $this->apiAs($admin, 'GET', '/api/admin/garages?type=Z')->assertUnprocessable();
    }

    /**
     * Ringkasan tipe garasi menampilkan jumlah terbit dan sisa kapasitas.
     */
    public function test_garage_types_show_issued_count_and_remaining_capacity(): void
    {
        $this->makeGarage('S', 5000);

        $types = collect($this->apiAs($this->createAdmin(), 'GET', '/api/admin/garage-types')->assertOk()->json('data'))->keyBy('key');

        $this->assertSame('Sepeda / Motor', $types['S']['label']);
        $this->assertSame(1, $types['S']['count']);
        $this->assertSame(998, $types['S']['remaining_capacity']);
    }
}
