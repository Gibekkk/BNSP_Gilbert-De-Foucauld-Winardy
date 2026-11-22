<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test autentikasi: pendaftaran penyewa, login satu endpoint untuk
 * kedua peran, profil, logout, dan refresh token.
 */
class AuthTest extends TestCase
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
     * Data pendaftaran yang valid.
     *
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3578010101900001',
            'phone' => '081234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    /**
     * Pendaftaran membuat pelanggan berstatus pending beserta akunnya.
     */
    public function test_register_creates_a_pending_customer(): void
    {
        $this->postJson('/api/register', $this->registration())
            ->assertCreated()
            ->assertJsonPath('user.email', 'budi@example.com');

        $this->assertDatabaseHas('customers', ['nik' => '3578010101900001', 'status' => Customer::STATUS_PENDING]);
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'is_admin' => false]);
    }

    /**
     * Data pendaftaran divalidasi (NIK 16 digit, telepon, email, konfirmasi password).
     */
    public function test_register_validates_input(): void
    {
        $this->postJson('/api/register', $this->registration([
            'nik' => '123',
            'phone' => 'abc',
            'email' => 'bukan-email',
            'password_confirmation' => 'beda',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['nik', 'phone', 'email', 'password']);
    }

    /**
     * Email dan NIK yang masih aktif tidak dapat dipakai mendaftar lagi.
     */
    public function test_register_rejects_duplicate_email_and_nik(): void
    {
        $this->postJson('/api/register', $this->registration())->assertCreated();

        $this->postJson('/api/register', $this->registration(['nik' => '3578010101900002']))
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/register', $this->registration(['email' => 'lain@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors(['nik']);
    }

    /**
     * Penyewa pending tidak dapat login sampai disetujui admin.
     */
    public function test_pending_tenant_cannot_log_in(): void
    {
        $this->postJson('/api/register', $this->registration())->assertCreated();

        $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
            ->assertForbidden();
    }

    /**
     * Penyewa yang disetujui dan admin login lewat endpoint yang sama, dibedakan oleh is_admin.
     */
    public function test_accepted_tenant_and_admin_share_one_login_endpoint(): void
    {
        $tenant = $this->createTenant('budi@example.com');
        $tenant->forceFill(['password' => 'rahasia123'])->save();
        $admin = $this->createAdmin();
        $admin->forceFill(['password' => 'rahasia123'])->save();

        $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
            ->assertOk()->assertJsonPath('user.is_admin', false)->assertJsonStructure(['token']);

        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'rahasia123'])
            ->assertOk()->assertJsonPath('user.is_admin', true);
    }

    /**
     * Password yang salah ditolak dengan 401.
     */
    public function test_wrong_password_is_rejected(): void
    {
        $this->createAdmin();

        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'salah'])
            ->assertUnauthorized();
    }

    /**
     * Penyewa yang ditolak tidak dapat login, tetapi boleh mendaftar ulang.
     */
    public function test_declined_tenant_cannot_log_in_but_may_register_again(): void
    {
        $this->postJson('/api/register', $this->registration())->assertCreated();
        Customer::query()->update(['status' => Customer::STATUS_DECLINED]);

        $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
            ->assertForbidden();

        $this->postJson('/api/register', $this->registration())->assertCreated();
        $this->assertSame(2, User::query()->where('email', 'budi@example.com')->count());
    }

    /**
     * /api/me mengembalikan profil dengan NIK disamarkan; tanpa token 401.
     */
    public function test_me_returns_the_profile_with_masked_nik(): void
    {
        $tenant = $this->createTenant();

        $this->apiAs(null, 'GET', '/api/me')->assertUnauthorized();

        $nik = $tenant->customer->nik;
        $this->apiAs($tenant, 'GET', '/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $tenant->email)
            ->assertJsonPath('user.customer.nik', str_repeat('*', 14).substr($nik, -2));
    }

    /**
     * Token yang sudah logout tidak dapat dipakai lagi.
     */
    public function test_logout_invalidates_the_token(): void
    {
        $token = $this->tokenFor($this->createAdmin());

        $this->postJson('/api/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->resetAuth();
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    /**
     * Refresh menukar token dengan yang baru; token rusak ditolak.
     */
    public function test_refresh_issues_a_new_token(): void
    {
        $token = $this->tokenFor($this->createAdmin());

        $new = $this->postJson('/api/refresh', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJsonStructure(['token', 'expires_in'])->json('token');

        $this->assertNotSame($token, $new);

        $this->postJson('/api/refresh', [], ['Authorization' => 'Bearer token-rusak'])->assertUnauthorized();
    }
}
