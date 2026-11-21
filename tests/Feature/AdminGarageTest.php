<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\GarageType;
use App\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin garage endpoints: issuance sequence, per-type capacity, per-garage prices.
 */
class AdminGarageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register a tenant through the public flow (starts pending).
     */
    private function registerTenant(string $email, string $nik): void
    {
        $this->postJson('/api/register', [
            'nik' => $nik,
            'phone' => '081234567890',
            'name' => 'Calon Pelanggan',
            'email' => $email,
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertCreated();
    }

    /**
     * Approve a tenant and hand back its login token.
     */
    private function tenantToken(string $email, string $nik): string
    {
        $this->registerTenant($email, $nik);

        UserModel::where('email', $email)->first()
            ->customer()->update(['status' => Customer::STATUS_ACCEPTED]);

        $res = $this->postJson('/api/login', ['email' => $email, 'password' => 'rahasia-123']);

        return $res->json('token');
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

    /** (a) Unauthenticated and tenant tokens are refused on every garage endpoint. */
    public function test_garage_endpoints_reject_unauthenticated_and_tenant_tokens(): void
    {
        $payload = ['garage_type_id' => 'S', 'rent_price' => 45000, 'quantity' => 2];

        $this->getJson('/api/admin/garage-types')->assertStatus(401);
        $this->getJson('/api/admin/garages')->assertStatus(401);
        $this->postJson('/api/admin/garages', $payload)->assertStatus(401);
        $this->postJson('/api/admin/garages/bulk', $payload)->assertStatus(401);
        $this->putJson('/api/admin/garages/S999', ['rent_price' => 50000])->assertStatus(401);

        $tenant = $this->tenantToken('pelanggan@example.com', '3203152988070021');
        $headers = ['Authorization' => 'Bearer '.$tenant];

        $this->getJson('/api/admin/garage-types', $headers)->assertStatus(403);
        $this->getJson('/api/admin/garages', $headers)->assertStatus(403);
        $this->postJson('/api/admin/garages', $payload, $headers)->assertStatus(403);
        $this->postJson('/api/admin/garages/bulk', $payload, $headers)->assertStatus(403);
        $this->putJson('/api/admin/garages/S999', ['rent_price' => 50000], $headers)->assertStatus(403);

        // Nothing may have been created along the way.
        $this->assertSame(0, Garage::query()->count());
    }

    /** (b) createOne issues S001 then S002 and advances the type counter. */
    public function test_create_one_issues_sequential_ids_and_advances_count(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $first = $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'S',
            'rent_price' => 45000,
        ], $headers);

        $first->assertStatus(201)->assertJsonPath('data.id', 'S001');
        $this->assertSame(1, (int) GarageType::query()->whereKey('S')->value('count'));

        $second = $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'S',
            'rent_price' => 50000,
        ], $headers);

        $second->assertStatus(201)->assertJsonPath('data.id', 'S002')->assertJsonPath('data.charge_price_kwh', null);
        $this->assertSame(2, (int) GarageType::query()->whereKey('S')->value('count'));

        $slot = Garage::query()->whereKey('S001')->firstOrFail();
        $this->assertFalse((bool) $slot->is_used);
        $this->assertSame(45000, (int) $slot->rent_price);
    }

    /** (c) Bulk creation issues consecutive IDs and every slot stores the submitted prices. */
    public function test_bulk_create_issues_consecutive_slots_storing_submitted_prices(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $res = $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'C',
            'quantity' => 5,
            'rent_price' => 120000,
        ], $headers);

        $res->assertStatus(201)
            ->assertJsonPath('data.first_id', 'C001')
            ->assertJsonPath('data.last_id', 'C005')
            ->assertJsonPath('data.created', 5);

        $this->assertSame(5, (int) GarageType::query()->whereKey('C')->value('count'));

        foreach ([1, 2, 3, 4, 5] as $i) {
            $id = sprintf('C%03d', $i);
            $row = Garage::query()->whereKey($id)->firstOrFail();
            $this->assertSame(120000, (int) $row->rent_price);
            $this->assertNull($row->charge_price_kwh);
        }
    }

    /** (d) Above cap: 422 with the remaining capacity and NOTHING inserted or counted. */
    public function test_bulk_cannot_exceed_type_capacity_and_inserts_nothing(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        GarageType::query()->whereKey('S')->update(['count' => 990]);

        $res = $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'S',
            'quantity' => 10,
            'rent_price' => 40000,
        ], $headers);

        $res->assertStatus(422)->assertJsonLike(['message' => '*Kapasitas tipe S tersisa 9*']);

        // All-or-nothing: zero rows for S and the counter untouched.
        $this->assertSame(0, Garage::query()->where('garage_type_id', 'S')->count());
        $this->assertSame(990, (int) GarageType::query()->whereKey('S')->value('count'));

        // Floats/decimal strings as quantity are rejected by validation.
        foreach ([2.5, '3.0', '-1'] as $badQuantity) {
            $this->postJson('/api/admin/garages/bulk', [
                'garage_type_id' => 'S',
                'quantity' => $badQuantity,
                'rent_price' => 40000,
            ], $headers)->assertStatus(422);
        }

        $this->assertSame(990, (int) GarageType::query()->whereKey('S')->value('count'));
    }

    /** (d) Exactly the remaining capacity succeeds and fully exhausts the type. */
    public function test_bulk_up_to_exact_remaining_capacity_succeeds_then_is_depleted(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        GarageType::query()->whereKey('S')->update(['count' => 990]);

        $res = $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'S',
            'quantity' => 9,
            'rent_price' => 40000,
        ], $headers);

        $res->assertStatus(201)
            ->assertJsonPath('data.first_id', 'S991')
            ->assertJsonPath('data.last_id', 'S999')
            ->assertJsonPath('data.created', 9);

        // Fully issued: the very next request must fail with zero remaining.
        $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'S',
            'rent_price' => 40000,
        ], $headers)->assertStatus(422)->assertJsonLike(['message' => '*tersisa 0*']);

        $this->assertSame(999, (int) GarageType::query()->whereKey('S')->value('count'));
    }

    /** (e) Types keep independent counters and sequences. */
    public function test_type_counters_are_independent(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->postJson('/api/admin/garages', ['garage_type_id' => 'S', 'rent_price' => 30000], $headers)
            ->assertStatus(201)->assertJsonPath('data.id', 'S001');

        $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'C',
            'quantity' => 3,
            'rent_price' => 90000,
        ], $headers)->assertStatus(201);

        // The C batch neither shifted S nor E counters.
        $this->postJson('/api/admin/garages', ['garage_type_id' => 'S', 'rent_price' => 30000], $headers)
            ->assertStatus(201)->assertJsonPath('data.id', 'S002');

        $this->assertSame(2, (int) GarageType::query()->whereKey('S')->value('count'));
        $this->assertSame(3, (int) GarageType::query()->whereKey('C')->value('count'));
        $this->assertSame(0, (int) GarageType::query()->whereKey('E')->value('count'));

        // Remaining capacity endpoint reflects the same numbers.
        $types = $this->getJson('/api/admin/garage-types', $headers);
        $types->assertOk();

        foreach ($types->json('data') as $row) {
            if ($row['key'] === 'S') {
                $this->assertSame(997, (int) $row['remaining_capacity']);
            }
        }
    }

    /** (f) Charge rules per type: required for E, rejected for S/C. */
    public function test_charge_rules_follow_the_garage_type(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        // E without the kWh rate is refused and creates nothing.
        $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'E',
            'rent_price' => 80000,
        ], $headers)->assertStatus(422);

        // E with the rate stores it on the slot.
        $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'E',
            'rent_price' => 80000,
            'charge_price_kwh' => 5200,
        ], $headers)->assertStatus(201)->assertJsonPath('data.id', 'E001');

        $this->assertSame(5200, (int) Garage::query()->whereKey('E001')->firstOrFail()->charge_price_kwh);

        // Non-electric types must not carry a rate — the value is refused.
        $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'S',
            'rent_price' => 30000,
            'charge_price_kwh' => 5200,
        ], $headers)->assertStatus(422);

        $this->assertSame(0, Garage::query()->where('garage_type_id', 'S')->count());
    }

    /** (g) Update changes only the two price fields; foreign keys are ignored. */
    public function test_update_changes_only_price_fields_and_ignores_foreign_keys(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->postJson('/api/admin/garages', [
            'garage_type_id' => 'E',
            'rent_price' => 60000,
            'charge_price_kwh' => 500,
        ], $headers)->assertStatus(201);

        $res = $this->putJson('/api/admin/garages/E001', [
            'rent_price' => 70000,
            'charge_price_kwh' => 480,
            'garage_type_id' => 'S',
            'is_used' => true,
            'id' => 'C100',
        ], $headers);

        $res->assertOk()
            ->assertJsonPath('data.id', 'E001')
            ->assertJsonPath('data.type', 'E')
            ->assertJsonPath('data.is_used', false);

        $fresh = Garage::query()->whereKey('E001')->firstOrFail();
        $this->assertSame(70000, (int) $fresh->rent_price);
        $this->assertSame(480, (int) $fresh->charge_price_kwh);
        $this->assertFalse((bool) $fresh->is_used);
        $this->assertSame('E', $fresh->garage_type_id);

        // Re-pricing with only rent keeps the stored kWh rate on an electric slot.
        $this->putJson('/api/admin/garages/E001', ['rent_price' => 75000], $headers)
            ->assertOk()
            ->assertJsonPath('data.rent_price', 75000)
            ->assertJsonPath('data.charge_price_kwh', 480);
    }

    /** (g) Updating an unknown id returns 404. */
    public function test_update_of_unknown_id_returns_404(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->putJson('/api/admin/garages/C123', ['rent_price' => 90000], $headers)->assertStatus(404);
    }

    /** (h) monthlyPrice() is exactly 25x the daily rent. */
    public function test_monthly_price_is_25x_daily_rent(): void
    {
        $garage = Garage::factory()->create([
            'id' => 'C888',
            'garage_type_id' => 'C',
            'rent_price' => 60000,
            'charge_price_kwh' => null,
        ]);

        $this->assertSame(1500000, $garage->monthlyPrice());
    }

    /** (i) Later batches never reprice earlier ones; each slot keeps its own prices. */
    public function test_later_batch_does_not_reprice_earlier_slots(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'S', 'quantity' => 3, 'rent_price' => 50000,
        ], $headers)->assertStatus(201);

        $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'S', 'quantity' => 4, 'rent_price' => 90000,
        ], $headers)->assertStatus(201)
            ->assertJsonPath('data.first_id', 'S004')
            ->assertJsonPath('data.last_id', 'S007');

        for ($i = 1; $i <= 3; $i++) {
            $this->assertSame(50000, (int) Garage::query()->whereKey(sprintf('S%03d', $i))->firstOrFail()->rent_price);
        }

        for ($i = 4; $i <= 7; $i++) {
            $this->assertSame(90000, (int) Garage::query()->whereKey(sprintf('S%03d', $i))->firstOrFail()->rent_price);
        }
    }

    /** Bonus: list endpoint filters by type, orders by id and paginates. */
    public function test_list_filters_by_type_orders_by_id_and_paginates(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->postJson('/api/admin/garages/bulk', [
            'garage_type_id' => 'C', 'quantity' => 27, 'rent_price' => 70000,
        ], $headers)->assertStatus(201);

        $page1 = $this->getJson('/api/admin/garages?type=C&page=1', $headers);
        $page1->assertOk()
            ->assertCount('data', 25)
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', 'C001');

        $page2 = $this->getJson('/api/admin/garages?type=C&page=2', $headers);
        $page2->assertOk()->assertCount('data', 2)->assertJsonPath('data.0.id', 'C026');

        // All C slots keep the single submitted price.
        foreach ($page1->json('data') as $row) {
            $this->assertSame(70000, (int) $row['rent_price']);
            $this->assertFalse((bool) $row['is_used']);
        }
    }
}
