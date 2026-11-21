<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\User as UserModel;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register a tenant through the public flow so it starts pending.
     */
    private function registerTenant(string $email, string $nik): UserModel
    {
        $res = $this->postJson('/api/register', [
            'nik' => $nik,
            'phone' => '081234567890',
            'name' => 'Calon Pelanggan',
            'email' => $email,
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ]);

        $res->assertCreated();

        return UserModel::where('email', $email)->first();
    }

    /**
     * Log in the seeded admin and hand back the token.
     */
    private function adminToken(): string
    {
        $res = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        return $res->json('token');
    }

    /**
     * Log in a tenant (must be approved first) and hand back the token.
     */
    private function tenantToken(string $email): string
    {
        $user = UserModel::where('email', $email)->first();
        $user->customer()->update(['status' => Customer::STATUS_ACCEPTED]);

        $res = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'rahasia-123',
        ]);

        return $res->json('token');
    }

    public function test_dashboard_and_customers_require_a_valid_token(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/admin/customers')->assertStatus(401);
    }

    public function test_admin_endpoints_reject_tenant_tokens(): void
    {
        $tenant = $this->registerTenant('pelanggan@example.com', '3203152988070011');
        $token = $this->tenantToken($tenant->email);

        $this->getJson('/api/admin/dashboard', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
        $this->getJson('/api/admin/customers', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
    }

    public function test_customer_list_returns_email_and_supports_status_filter(): void
    {
        $pending = $this->registerTenant('satu@example.com', '3201150989011001');
        $accepted = $this->registerTenant('dua@example.com', '3201150486011002');
        $declined = $this->registerTenant('tiga@example.com', '3201151190021003');

        $accepted->customer()->update(['status' => Customer::STATUS_ACCEPTED]);
        $declined->customer()->update(['status' => Customer::STATUS_DECLINED]);
        $token = $this->adminToken();

        $res = $this->getJson('/api/admin/customers', ['Authorization' => 'Bearer '.$token]);
        $res->assertOk()->assertCount('data', 3);

        $one = collect($res->json('data'))->firstWhere('id', $pending->id);
        $this->assertSame('satu@example.com', $one['email']);
        $this->assertSame(Customer::STATUS_PENDING, $one['status']);

        $filtered = $this->getJson('/api/admin/customers?status=accepted', ['Authorization' => 'Bearer '.$token]);
        $filtered->assertOk()->assertCount('data', 1);

        $this->getJson('/api/admin/customers?status=not-a-status', ['Authorization' => 'Bearer '.$token])->assertStatus(422);
    }

    public function test_dashboard_stats_and_recent_reflect_current_state(): void
    {
        $a = $this->registerTenant('alpha@example.com', '3201150189011001');
        $b = $this->registerTenant('bravo@example.com', '3201150290021002');

        $a->customer()->update(['status' => Customer::STATUS_ACCEPTED]);
        $b->customer()->update(['status' => Customer::STATUS_DECLINED]);

        $owner = Customer::factory()->create();
        Vehicle::factory()->create(['id' => 1, 'customer_id' => $owner->id]);
        Garage::factory()->count(3)->create();

        $token = $this->adminToken();
        $res = $this->getJson('/api/admin/dashboard', ['Authorization' => 'Bearer '.$token]);

        $res->assertOk()
            ->assertJsonPath('stats.customers.total', 2)
            ->assertJsonPath('stats.customers.pending', 0)
            ->assertJsonPath('stats.customers.accepted', 1)
            ->assertJsonPath('stats.customers.declined', 1)
            ->assertJsonPath('stats.vehicles', 1)
            ->assertJsonPath('stats.garages.total', 3);

        $this->assertCount(2, $res->json('recent'));
    }

    public function test_approve_unlocks_tenant_login(): void
    {
        $tenant = $this->registerTenant('siapa@example.com', '3201152988070340');

        $denied = $this->postJson('/api/login', ['email' => $tenant->email, 'password' => 'rahasia-123']);
        $denied->assertStatus(403)->assertJsonLike(['message' => '*diverifikasi*']);

        $token = $this->adminToken();
        $this->postJson('/api/admin/customers/'.$tenant->id.'/approve', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.status', Customer::STATUS_ACCEPTED);

        $this->postJson('/api/login', ['email' => $tenant->email, 'password' => 'rahasia-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_decline_is_final_and_reregistration_is_allowed_again(): void
    {
        $email = 'ditolak@example.com';
        $nik = '3201152988070420';
        $tenant = $this->registerTenant($email, $nik);

        $token = $this->adminToken();

        $this->postJson('/api/admin/customers/'.$tenant->id.'/decline', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.status', Customer::STATUS_DECLINED);

        // Re-approval of a declined row must be refused (final).
        $this->postJson('/api/admin/customers/'.$tenant->id.'/approve', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422);

        // Logging into the declined account is refused with the terminal message.
        $denied = $this->postJson('/api/login', ['email' => $email, 'password' => 'rahasia-123']);
        $denied->assertStatus(403)->assertJsonLike(['message' => '*ditolak*']);

        // Re-registration with the SAME email and NIK must now succeed (uniques relaxed in code).
        $again = $this->registerTenant($email, $nik);
        $this->assertNotSame($tenant->id, $again->id);
        $this->assertSame('ditolak@example.com', $again->email);

        // The fresh account is pending: login is refused but with the pending message.
        $pending = $this->postJson('/api/login', ['email' => $email, 'password' => 'rahasia-123']);
        $pending->assertStatus(403)->assertJsonLike(['message' => '*diverifikasi*']);

        // While this fresh account is active, a third registration must be blocked.
        $blocked = $this->postJson('/api/register', [
            'nik' => $nik,
            'phone' => '081234567890',
            'name' => 'Calon Pelanggan',
            'email' => $email,
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ]);
        $blocked->assertStatus(422);
    }

    public function test_admin_can_not_decline_an_approved_customer(): void
    {
        $tenant = $this->registerTenant('ok@example.com', '3201152988070990');
        $tenant->customer()->update(['status' => Customer::STATUS_ACCEPTED]);

        $token = $this->adminToken();
        $this->postJson('/api/admin/customers/'.$tenant->id.'/decline', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422);
    }
}
