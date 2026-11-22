<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionExtension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test laporan sewa (per bulan, per hari, per tipe, per penyewa) dan dasbor admin.
 * Hanya sewa disetujui yang dihitung, pada tanggal mulai sewa.
 */
class ReportAndDashboardTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    /**
     * Bekukan waktu, palsukan disk, dan siapkan data contoh sebelum setiap test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApi();
        $this->seedRentals();
    }

    /**
     * Data contoh:
     *  - Budi, garasi C, 5 Nov 2026: sewa 100.000 + perpanjangan 30.000 (approved)
     *  - Budi, garasi E, 21 Nov 2026: 150.000 termasuk listrik 50.000 (approved)
     *  - Siti, garasi S, 10 Okt 2026: 20.000 (approved)
     *  - Siti, garasi S, 21 Nov 2026: 99.000 (pending → tidak dihitung)
     */
    private function seedRentals(): void
    {
        $budi = $this->createTenant('budi@example.com');
        $siti = $this->createTenant('siti@example.com');
        $budi->customer->update(['name' => 'Budi']);
        $siti->customer->update(['name' => 'Siti']);

        $c = $this->makeRental($this->makeVehicle($budi), $this->makeGarage('C'), [
            'start_date' => '2026-11-05', 'end_date' => '2026-11-07', 'rent_price_total' => 100000,
        ]);
        TransactionExtension::factory()->create(['transaction_id' => $c->id, 'extra_rent' => 30000]);

        $this->makeRental($this->makeVehicle($budi, 'mobil_listrik'), $this->makeGarage('E', 75000, 2500), [
            'rent_price_total' => 150000, 'charge_price_total' => 50000, 'battery_level' => 20,
        ]);
        $this->makeRental($this->makeVehicle($siti, 'sepeda'), $this->makeGarage('S', 5000), [
            'start_date' => '2026-10-10', 'end_date' => '2026-10-13', 'rent_price_total' => 20000,
        ]);
        $this->makeRental($this->makeVehicle($siti, 'sepeda_motor'), $this->makeGarage('S', 5000), [
            'rent_price_total' => 99000, 'status' => Transaction::STATUS_PENDING,
        ]);
    }

    /**
     * Ambil laporan sebagai admin.
     *
     * @return array<string, mixed>
     */
    private function report(string $query): array
    {
        return $this->apiAs($this->createAdmin(uniqid().'@example.com'), 'GET', '/api/admin/reports?'.$query)->assertOk()->json('data');
    }

    /**
     * Laporan per bulan: 12 baris, hanya approved, listrik & perpanjangan terpisah.
     */
    public function test_monthly_report(): void
    {
        $data = $this->report('group=monthly&year=2026');

        $this->assertCount(12, $data['rows']);
        $nov = $data['rows'][10];
        $this->assertSame(2, $nov['rentals']);
        $this->assertSame(200000, $nov['rent_income']);
        $this->assertSame(50000, $nov['charge_income']);
        $this->assertSame(30000, $nov['extension_income']);
        $this->assertSame(280000, $nov['total']);
        $this->assertSame(20000, $data['rows'][9]['total']);
        $this->assertSame(300000, $data['totals']['total']);
    }

    /**
     * Laporan per hari dalam bulan: satu baris per tanggal.
     */
    public function test_daily_report(): void
    {
        $data = $this->report('group=daily&year=2026&month=11');

        $this->assertCount(30, $data['rows']);
        $this->assertSame(130000, $data['rows'][4]['total']);
        $this->assertSame(150000, $data['rows'][20]['total']);
    }

    /**
     * Laporan per tipe garasi.
     */
    public function test_report_by_garage_type(): void
    {
        $rows = collect($this->report('group=type')['rows'])->keyBy('key');

        $this->assertSame('Sepeda / Motor (S)', $rows['S']['label']);
        $this->assertSame(20000, $rows['S']['total']);
        $this->assertSame(130000, $rows['C']['total']);
        $this->assertSame(150000, $rows['E']['total']);

        $november = collect($this->report('group=type&year=2026&month=11')['rows'])->keyBy('key');
        $this->assertSame(0, $november['S']['total']);
    }

    /**
     * Laporan per penyewa, urut total terbesar.
     */
    public function test_report_by_tenant(): void
    {
        $rows = $this->report('group=tenant&year=2026')['rows'];

        $this->assertCount(2, $rows);
        $this->assertStringStartsWith('Budi', $rows[0]['label']);
        $this->assertSame(280000, $rows[0]['total']);
        $this->assertSame(20000, $rows[1]['total']);
    }

    /**
     * Parameter laporan divalidasi.
     */
    public function test_report_parameters_are_validated(): void
    {
        $admin = $this->createAdmin();

        $this->apiAs($admin, 'GET', '/api/admin/reports?group=salah')->assertUnprocessable();
        $this->apiAs($admin, 'GET', '/api/admin/reports?group=monthly')->assertUnprocessable()->assertJsonValidationErrors(['year']);
        $this->apiAs($admin, 'GET', '/api/admin/reports?group=daily&year=2026')->assertUnprocessable()->assertJsonValidationErrors(['month']);
    }

    /**
     * Dasbor admin: sewa aktif disetujui, pengajuan pending, dan pendapatan bulan ini.
     */
    public function test_dashboard_stats(): void
    {
        $stats = $this->apiAs($this->createAdmin(), 'GET', '/api/admin/dashboard')->assertOk()->json('stats');

        $this->assertSame(1, $stats['rentals']['active_today']);
        $this->assertSame(1, $stats['rentals']['pending']);
        $this->assertSame(280000, $stats['rentals']['income_this_month']);
        $this->assertSame(4, $stats['garages']['total']);
    }
}
