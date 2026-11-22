<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test Sewa Baru oleh penyewa: garasi tersedia per tipe kendaraan,
 * pratinjau biaya, simpan dengan bukti pelunasan, dan aturan ketersediaan.
 * Tanggal hari ini dibekukan ke 21 November 2026 (bulan 30 hari).
 */
class TenantRentalTest extends TestCase
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
     * Garasi yang ditawarkan menyesuaikan tipe kendaraan dan hanya yang kosong hari ini.
     */
    public function test_available_garages_follow_vehicle_type_and_today(): void
    {
        $tenant = $this->createTenant();
        $s = $this->makeGarage('S', 5000);
        $this->makeGarage('C');
        $c2 = $this->makeGarage('C');
        $this->makeGarage('E', 75000, 2500);
        $this->makeRental($this->makeVehicle($this->createTenant('lain@example.com')), $c2);

        $bike = $this->makeVehicle($tenant, 'sepeda_motor');
        $res = $this->apiAs($tenant, 'GET', '/api/customer/garages/available?vehicle_id='.$bike->id)->assertOk();
        $this->assertSame([$s->id], array_column($res->json('data'), 'id'));
        $this->assertSame(['S'], array_column($res->json('allowed_types'), 'key'));
        $this->assertSame(29, $res->json('limits.max_daily_days'));
        $this->assertSame('2026-12-20', $res->json('limits.monthly_end_date'));

        $ev = $this->makeVehicle($tenant, 'mobil_listrik');
        $ids = array_column($this->apiAs($tenant, 'GET', '/api/customer/garages/available?vehicle_id='.$ev->id)->json('data'), 'id');
        $this->assertSame(['C001', 'E001'], $ids);

        $ids = array_column($this->apiAs($tenant, 'GET', '/api/customer/garages/available?vehicle_id='.$ev->id.'&type=E')->json('data'), 'id');
        $this->assertSame(['E001'], $ids);
    }

    /**
     * Pratinjau sewa harian: total = hari x harga harian, tanggal inklusif mulai hari ini.
     */
    public function test_quote_daily_rental(): void
    {
        $tenant = $this->createTenant();
        $garage = $this->makeGarage('C', 40000);
        $car = $this->makeVehicle($tenant);

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $car->id, 'garage_id' => $garage->id, 'is_monthly' => false, 'days' => 3])
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-11-21')
            ->assertJsonPath('data.end_date', '2026-11-23')
            ->assertJsonPath('data.total', 120000)
            ->assertJsonPath('data.charge_price', 0);
    }

    /**
     * Pratinjau sewa bulanan: 25 x harga harian, sepanjang jumlah hari bulan mulai.
     */
    public function test_quote_monthly_rental(): void
    {
        $tenant = $this->createTenant();
        $garage = $this->makeGarage('C', 40000);

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $this->makeVehicle($tenant)->id, 'garage_id' => $garage->id, 'is_monthly' => true])
            ->assertOk()
            ->assertJsonPath('data.days', 30)
            ->assertJsonPath('data.end_date', '2026-12-20')
            ->assertJsonPath('data.total', 1000000);
    }

    /**
     * Sewa harian di November maksimal 29 hari.
     */
    public function test_daily_rental_is_limited_by_the_start_month(): void
    {
        $tenant = $this->createTenant();
        $data = ['vehicle_id' => $this->makeVehicle($tenant)->id, 'garage_id' => $this->makeGarage('C')->id, 'is_monthly' => false];

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['days' => 30])
            ->assertUnprocessable()->assertJsonValidationErrors(['days']);
        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['days' => 29])->assertOk();
        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['days']);
    }

    /**
     * Mobil listrik di garasi E: biaya isi listrik dari sisa baterai (%) ditambahkan ke total.
     */
    public function test_electric_car_in_electric_garage_pays_charging_from_percentage(): void
    {
        $tenant = $this->createTenant();
        $garage = $this->makeGarage('E', 75000, 2500);
        $ev = $this->makeVehicle($tenant, 'mobil_listrik', ['battery_capacity' => 60]);
        $data = ['vehicle_id' => $ev->id, 'garage_id' => $garage->id, 'is_monthly' => false, 'days' => 1];

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['battery_level' => 20])
            ->assertOk()
            ->assertJsonPath('data.charge_energy', 48)
            ->assertJsonPath('data.charge_price', 120000)
            ->assertJsonPath('data.total', 195000);

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['battery_level']);
        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['battery_level' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors(['battery_level']);
    }

    /**
     * Mobil listrik di garasi mobil biasa (C) tanpa biaya isi listrik.
     */
    public function test_electric_car_in_car_garage_has_no_charging(): void
    {
        $tenant = $this->createTenant();
        $ev = $this->makeVehicle($tenant, 'mobil_listrik');

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $ev->id, 'garage_id' => $this->makeGarage('C', 40000)->id, 'is_monthly' => false, 'days' => 1, 'battery_level' => 10])
            ->assertOk()->assertJsonPath('data.charge_price', 0)->assertJsonPath('data.battery_level', null);
    }

    /**
     * Kendaraan hanya boleh di tipe garasi yang sesuai.
     */
    public function test_vehicle_must_match_the_garage_type(): void
    {
        $tenant = $this->createTenant();
        $data = ['garage_id' => $this->makeGarage('C')->id, 'is_monthly' => false, 'days' => 1];

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['vehicle_id' => $this->makeVehicle($tenant, 'sepeda')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['garage_id']);

        $data['garage_id'] = $this->makeGarage('E', 75000, 2500)->id;
        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', $data + ['vehicle_id' => $this->makeVehicle($tenant, 'mobil')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['garage_id']);
    }

    /**
     * Menyimpan sewa: status pending, garasi menjadi used, total termasuk listrik, bukti tersimpan.
     */
    public function test_store_creates_a_pending_rental_and_marks_garage_used(): void
    {
        $tenant = $this->createTenant();
        $garage = $this->makeGarage('E', 75000, 2500);
        $ev = $this->makeVehicle($tenant, 'mobil_listrik', ['battery_capacity' => 60]);

        $res = $this->uploadAs($tenant, '/api/customer/rentals', [
            'vehicle_id' => $ev->id, 'garage_id' => $garage->id, 'is_monthly' => '0', 'days' => '2',
            'battery_level' => '50', 'transaction_proof' => $this->proof(),
        ])->assertCreated()->assertJsonPath('data.status', Transaction::STATUS_PENDING);

        $transaction = Transaction::query()->findOrFail($res->json('data.id'));
        $this->assertSame(150000 + 75000, $transaction->rent_price_total);
        $this->assertSame(75000, $transaction->charge_price_total);
        $this->assertSame(50, $transaction->battery_level);
        $this->assertSame('2026-11-22', $transaction->end_date->toDateString());
        $this->assertTrue(Garage::query()->find($garage->id)->is_used);
        Storage::disk('local')->assertExists($transaction->transaction_proof);
    }

    /**
     * Bukti pelunasan wajib berupa gambar maksimal 5 MB.
     */
    public function test_store_requires_an_image_proof_up_to_5_mb(): void
    {
        $tenant = $this->createTenant();
        $data = ['vehicle_id' => $this->makeVehicle($tenant)->id, 'garage_id' => $this->makeGarage('C')->id, 'is_monthly' => '0', 'days' => '1'];

        $this->uploadAs($tenant, '/api/customer/rentals', $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction_proof']);
        $this->uploadAs($tenant, '/api/customer/rentals', $data + ['transaction_proof' => UploadedFile::fake()->createWithContent('bukti.pdf', '%PDF-1.4 contoh')])
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction_proof']);
        $this->uploadAs($tenant, '/api/customer/rentals', $data + ['transaction_proof' => $this->proof('besar.png', 5121)])
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction_proof']);
        $this->assertSame(0, Transaction::query()->count());
    }

    /**
     * Satu garasi hanya untuk satu kendaraan per hari.
     */
    public function test_a_garage_cannot_be_booked_twice_on_the_same_day(): void
    {
        $garage = $this->makeGarage('C');
        $this->makeRental($this->makeVehicle($this->createTenant('a@example.com')), $garage, ['status' => Transaction::STATUS_PENDING]);
        $tenant = $this->createTenant('b@example.com');

        $this->uploadAs($tenant, '/api/customer/rentals', [
            'vehicle_id' => $this->makeVehicle($tenant)->id, 'garage_id' => $garage->id, 'is_monthly' => '0', 'days' => '1', 'transaction_proof' => $this->proof(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['garage_id']);
    }

    /**
     * Satu kendaraan hanya di satu garasi pada satu waktu; penyewa boleh menyewa beberapa garasi untuk kendaraan berbeda.
     */
    public function test_one_vehicle_one_garage_but_many_garages_per_tenant(): void
    {
        $tenant = $this->createTenant();
        $car = $this->makeVehicle($tenant);
        $this->makeRental($car, $this->makeGarage('C'));
        $second = $this->makeGarage('C');

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $car->id, 'garage_id' => $second->id, 'is_monthly' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);

        $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $this->makeVehicle($tenant)->id, 'garage_id' => $second->id, 'is_monthly' => true])
            ->assertOk();
    }

    /**
     * Garasi yang sewanya sudah selesai atau ditolak dapat disewa lagi.
     */
    public function test_garage_can_be_rented_again_after_rental_ends_or_is_declined(): void
    {
        $tenant = $this->createTenant();
        $car = $this->makeVehicle($tenant);
        $ended = $this->makeGarage('C');
        $declined = $this->makeGarage('C');
        $this->makeRental($car, $ended, ['start_date' => '2026-11-01', 'end_date' => '2026-11-20']);
        $this->makeRental($this->makeVehicle($tenant), $declined, ['status' => Transaction::STATUS_DECLINED]);

        foreach ([$ended, $declined] as $garage) {
            $this->apiAs($tenant, 'POST', '/api/customer/rentals/quote', ['vehicle_id' => $car->id, 'garage_id' => $garage->id, 'is_monthly' => false, 'days' => 1])
                ->assertOk();
        }
    }

    /**
     * Penyewa tidak dapat memakai kendaraan milik penyewa lain; admin tidak dapat membuat sewa.
     */
    public function test_ownership_and_role_rules(): void
    {
        $owner = $this->createTenant('a@example.com');
        $other = $this->createTenant('b@example.com');
        $garage = $this->makeGarage('C');
        $data = ['vehicle_id' => $this->makeVehicle($owner)->id, 'garage_id' => $garage->id, 'is_monthly' => true];

        $this->apiAs($other, 'POST', '/api/customer/rentals/quote', $data)->assertNotFound();
        $this->apiAs($this->createAdmin(), 'POST', '/api/customer/rentals/quote', $data)->assertForbidden();
    }

    /**
     * "Sewaan saya" memisahkan sewa aktif dan riwayat (termasuk yang ditolak).
     */
    public function test_my_rentals_split_active_and_history(): void
    {
        $tenant = $this->createTenant();
        $this->makeRental($this->makeVehicle($tenant), $this->makeGarage('C'));
        $this->makeRental($this->makeVehicle($tenant), $this->makeGarage('C'), ['status' => Transaction::STATUS_PENDING]);
        $this->makeRental($this->makeVehicle($tenant), $this->makeGarage('C'), ['status' => Transaction::STATUS_DECLINED, 'status_note' => 'Nominal kurang']);
        $this->makeRental($this->makeVehicle($tenant), $this->makeGarage('C'), ['start_date' => '2026-10-01', 'end_date' => '2026-10-05']);
        $this->makeRental($this->makeVehicle($this->createTenant('lain@example.com')), $this->makeGarage('C'));

        $res = $this->apiAs($tenant, 'GET', '/api/customer/rentals')->assertOk();

        $this->assertCount(2, $res->json('active'));
        $this->assertCount(2, $res->json('history'));
        $this->assertContains('Nominal kurang', array_column($res->json('history'), 'status_note'));
    }
}
