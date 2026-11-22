<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test verifikasi pendaftar baru oleh admin (berdasarkan NIK, tanpa foto KTP).
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
     * Daftar pendaftar memuat hasil pembacaan NIK dan dapat difilter per status.
     */
    public function test_list_includes_nik_check_and_filters_by_status(): void
    {
        $admin = $this->createAdmin();
        Customer::factory()->create(['nik' => '3578010101900001', 'status' => Customer::STATUS_PENDING]);
        Customer::factory()->accepted()->create(['nik' => '3201152988071601']);

        $pending = $this->apiAs($admin, 'GET', '/api/admin/customers?status=pending')->assertOk()->json('data');
        $this->assertCount(1, $pending);
        $this->assertTrue($pending[0]['nik_check']['valid']);
        $this->assertSame('Jawa Timur', $pending[0]['nik_check']['province']);

        $accepted = $this->apiAs($admin, 'GET', '/api/admin/customers?status=accepted')->json('data');
        $this->assertFalse($accepted[0]['nik_check']['valid']);

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
