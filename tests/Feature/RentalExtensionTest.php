<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test perpanjangan sewa oleh admin: hanya sewa disetujui yang berjalan
 * hari ini, hanya tanggal selesai yang mundur, tanpa bentrok, dengan bukti bayar.
 */
class RentalExtensionTest extends TestCase
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
     * Sewa harian disetujui di garasi C (Rp40.000/hari), 21–23 November.
     */
    private function activeRental(array $overrides = []): Transaction
    {
        return $this->makeRental($this->makeVehicle($this->createTenant()), $this->makeGarage('C', 40000), $overrides);
    }

    /**
     * Daftar perpanjangan hanya memuat sewa disetujui yang berjalan hari ini.
     */
    public function test_list_contains_only_approved_rentals_running_today(): void
    {
        $admin = $this->createAdmin();
        $active = $this->activeRental();
        $this->makeRental($this->makeVehicle($this->createTenant('p@example.com')), $this->makeGarage('C'), ['status' => Transaction::STATUS_PENDING]);
        $this->makeRental($this->makeVehicle($this->createTenant('o@example.com')), $this->makeGarage('C'), ['start_date' => '2026-11-01', 'end_date' => '2026-11-10']);

        $ids = array_column($this->apiAs($admin, 'GET', '/api/admin/rentals')->assertOk()->json('data'), 'id');

        $this->assertSame([$active->id], $ids);
    }

    /**
     * Pratinjau sisa biaya mengikuti aturan blok bulan.
     */
    public function test_quote_computes_remaining_cost(): void
    {
        $rental = $this->activeRental();

        $this->apiAs($this->createAdmin(), 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-11-28')
            ->assertOk()->assertJsonPath('data.days', 5)->assertJsonPath('data.total', 200000);

        // 24 Nov + 30 hari (blok November) = s.d. 23 Des → 25 x 40.000; lalu 2 hari.
        $this->apiAs($this->createAdmin('admin2@example.com'), 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-12-25')
            ->assertOk()->assertJsonPath('data.days', 32)->assertJsonPath('data.total', 1000000 + 80000);
    }

    /**
     * Sewa tidak dapat diperpendek dan format tanggal divalidasi.
     */
    public function test_cannot_shorten_or_use_an_invalid_date(): void
    {
        $admin = $this->createAdmin();
        $rental = $this->activeRental();

        $this->apiAs($admin, 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-11-23')
            ->assertUnprocessable()->assertJsonValidationErrors(['new_end_date']);
        $this->apiAs($admin, 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=23-12-2026')
            ->assertUnprocessable()->assertJsonValidationErrors(['new_end_date']);
    }

    /**
     * Sewa yang belum disetujui tidak dapat diperpanjang.
     */
    public function test_pending_rental_cannot_be_extended(): void
    {
        $rental = $this->activeRental(['status' => Transaction::STATUS_PENDING]);

        $this->apiAs($this->createAdmin(), 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-11-28')
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction']);
    }

    /**
     * Perpanjangan ditolak bila bertabrakan dengan transaksi lain pada garasi yang sama.
     */
    public function test_extension_cannot_overlap_another_rental_on_the_garage(): void
    {
        $rental = $this->activeRental();
        $this->makeRental($this->makeVehicle($this->createTenant('lain@example.com')), $rental->garage, [
            'start_date' => '2026-11-27', 'end_date' => '2026-11-30',
        ]);

        $admin = $this->createAdmin();
        $this->apiAs($admin, 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-11-28')
            ->assertUnprocessable()->assertJsonValidationErrors(['garage_id']);
        $this->apiAs($admin, 'GET', '/api/admin/rentals/'.$rental->id.'/quote?new_end_date=2026-11-26')->assertOk();
    }

    /**
     * Simpan perpanjangan: riwayat tersimpan, end_date maju, bukti tersimpan, muncul di riwayat.
     */
    public function test_extend_saves_history_and_moves_end_date(): void
    {
        $admin = $this->createAdmin();
        $rental = $this->activeRental();

        $this->uploadAs($admin, '/api/admin/rentals/'.$rental->id.'/extend', ['new_end_date' => '2026-11-28'])
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction_proof']);

        $this->uploadAs($admin, '/api/admin/rentals/'.$rental->id.'/extend', ['new_end_date' => '2026-11-28', 'transaction_proof' => $this->proof()])
            ->assertCreated()->assertJsonPath('data.end_date', '2026-11-28');

        $this->assertDatabaseHas('transaction_extensions', [
            'transaction_id' => $rental->id, 'extra_rent' => 200000,
        ]);
        $this->assertSame('2026-11-23', $rental->extensions()->first()->current_end_date->toDateString());

        $history = $this->apiAs($admin, 'GET', '/api/admin/rentals/extensions')->assertOk()->json('data');
        $this->assertSame($rental->id, $history[0]['transaction_id']);
    }

    /**
     * Hanya admin yang dapat memperpanjang.
     */
    public function test_only_admin_can_extend(): void
    {
        $rental = $this->activeRental();

        $this->uploadAs($rental->vehicle->customer->user, '/api/admin/rentals/'.$rental->id.'/extend', ['new_end_date' => '2026-11-28', 'transaction_proof' => $this->proof()])
            ->assertForbidden();
    }
}
