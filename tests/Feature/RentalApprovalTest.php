<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test verifikasi sewa oleh admin: setujui / tolak (final), garasi
 * dilepas bila ditolak.
 */
class RentalApprovalTest extends TestCase
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
     * Buat satu sewa pending pada garasi C baru.
     */
    private function pendingRental(): Transaction
    {
        $garage = $this->makeGarage('C');
        $garage->forceFill(['is_used' => true])->save();

        return $this->makeRental($this->makeVehicle($this->createTenant()), $garage, ['status' => Transaction::STATUS_PENDING, 'verified_at' => null]);
    }

    /**
     * Daftar pengajuan per status beserta jumlahnya; khusus admin.
     */
    public function test_list_by_status_with_counts(): void
    {
        $admin = $this->createAdmin();
        $pending = $this->pendingRental();

        $res = $this->apiAs($admin, 'GET', '/api/admin/rental-approvals')->assertOk();
        $this->assertSame([$pending->id], array_column($res->json('data'), 'id'));
        $this->assertSame(1, $res->json('counts.pending'));

        $this->apiAs($admin, 'GET', '/api/admin/rental-approvals?status=salah')->assertUnprocessable();
        $this->apiAs($pending->vehicle->customer->user, 'GET', '/api/admin/rental-approvals')->assertForbidden();
    }

    /**
     * Admin menyetujui sewa; keputusan final.
     */
    public function test_approve_is_final(): void
    {
        $admin = $this->createAdmin();
        $rental = $this->pendingRental();

        $this->apiAs($admin, 'POST', '/api/admin/rental-approvals/'.$rental->id.'/approve')
            ->assertOk()->assertJsonPath('data.status', Transaction::STATUS_APPROVED);

        $this->assertNotNull($rental->fresh()->verified_at);
        $this->apiAs($admin, 'POST', '/api/admin/rental-approvals/'.$rental->id.'/approve')->assertUnprocessable();
        $this->apiAs($admin, 'POST', '/api/admin/rental-approvals/'.$rental->id.'/decline')->assertUnprocessable();
    }

    /**
     * Admin menolak sewa dengan alasan; garasi kembali tersedia.
     */
    public function test_decline_stores_reason_and_frees_the_garage(): void
    {
        $rental = $this->pendingRental();

        $this->apiAs($this->createAdmin(), 'POST', '/api/admin/rental-approvals/'.$rental->id.'/decline', ['reason' => 'Nominal transfer kurang.'])
            ->assertOk()->assertJsonPath('data.status', Transaction::STATUS_DECLINED);

        $this->assertSame('Nominal transfer kurang.', $rental->fresh()->status_note);
        $this->assertFalse($rental->garage->fresh()->is_used);
    }

    /**
     * Penyewa tidak dapat menyetujui sewa.
     */
    public function test_tenant_cannot_approve(): void
    {
        $rental = $this->pendingRental();

        $this->apiAs($rental->vehicle->customer->user, 'POST', '/api/admin/rental-approvals/'.$rental->id.'/approve')->assertForbidden();
    }
}
