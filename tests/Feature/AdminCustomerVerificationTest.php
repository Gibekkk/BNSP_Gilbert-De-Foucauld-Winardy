<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test verifikasi pendaftar baru oleh admin: admin memeriksa NIK yang
 * dimasukkan lalu menerima atau menolak (tanpa foto KTP).
 */
class AdminCustomerVerificationTest extends TestCase
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
     * Hanya admin yang dapat membuka daftar pendaftar.
     */
    public function test_only_admin_can_access_customer_verification(): void
    {
        $this->apiAs(null, 'GET', '/api/admin/customers')->assertUnauthorized();
        $this->apiAs($this->createTenant(), 'GET', '/api/admin/customers')->assertForbidden();
        $this->apiAs($this->createAdmin(), 'GET', '/api/admin/customers')->assertOk();
    }

    /**
     * Daftar pendaftar menampilkan NIK lengkap dan email, serta dapat difilter per status.
     */
    public function test_list_shows_nik_and_email_and_filters_by_status(): void
    {
        $admin = $this->createAdmin();
        $pending = $this->createTenant('budi@example.com', Customer::STATUS_PENDING);
        $this->createTenant('siti@example.com');

        $rows = $this->apiAs($admin, 'GET', '/api/admin/customers?status=pending')->assertOk()->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame($pending->customer->nik, $rows[0]['nik']);
        $this->assertSame('budi@example.com', $rows[0]['email']);

        $this->assertCount(2, $this->apiAs($admin, 'GET', '/api/admin/customers')->json('data'));
        $this->apiAs($admin, 'GET', '/api/admin/customers?status=salah')->assertUnprocessable();
    }

    /**
     * Admin menyetujui pendaftar sehingga penyewa dapat login.
     */
    public function test_approve_lets_the_tenant_log_in(): void
    {
        $tenant = $this->createTenant('budi@example.com', Customer::STATUS_PENDING);
        $tenant->forceFill(['password' => 'rahasia123'])->save();

        $this->apiAs($this->createAdmin(), 'POST', '/api/admin/customers/'.$tenant->customer_id.'/approve')
            ->assertOk()->assertJsonPath('data.status', Customer::STATUS_ACCEPTED);

        $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertOk();
    }

    /**
     * Penolakan bersifat final dan hanya untuk status pending.
     */
    public function test_decline_is_final(): void
    {
        $admin = $this->createAdmin();
        $pending = $this->createTenant('a@example.com', Customer::STATUS_PENDING);
        $accepted = $this->createTenant('b@example.com');

        $this->apiAs($admin, 'POST', '/api/admin/customers/'.$pending->customer_id.'/decline')
            ->assertOk()->assertJsonPath('data.status', Customer::STATUS_DECLINED);

        $this->apiAs($admin, 'POST', '/api/admin/customers/'.$pending->customer_id.'/approve')->assertUnprocessable();
        $this->apiAs($admin, 'POST', '/api/admin/customers/'.$accepted->customer_id.'/decline')->assertUnprocessable();
        $this->apiAs($admin, 'POST', '/api/admin/customers/9999/approve')->assertNotFound();
    }
}
