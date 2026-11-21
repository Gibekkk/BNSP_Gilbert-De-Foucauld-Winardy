<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\GarageType;
use App\Models\Transaction;
use App\Models\TransactionExtension;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves every model factory persists a valid, self-consistent row.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CustomerFactory creates pending customers with valid NIK and phone.
     */
    public function test_it_creates_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame(Customer::STATUS_PENDING, $customer->status);
        $this->assertMatchesRegularExpression('/^\d{16}$/', (string) $customer->nik);
        $this->assertMatchesRegularExpression('/^(\+?62|0)8[0-9]{7,12}$/', $customer->phone);

        Customer::factory()->accepted()->create();
        $latest = Customer::query()->latest('id')->firstOrFail();
        $this->assertSame(Customer::STATUS_ACCEPTED, $latest->status);
    }

    /**
     * GarageTypeFactory keeps only id+count; pricing now lives per garage.
     */
    public function test_it_provides_attributes_for_the_seeded_type_rows(): void
    {
        $electric = GarageType::query()->updateOrCreate(
            ['id' => 'E'],
            GarageType::factory()->mobilListrik()->make()->getAttributes(),
        );

        $mobil = GarageType::query()->updateOrCreate(
            ['id' => 'C'],
            GarageType::factory()->mobil()->make()->getAttributes(),
        );

        $this->assertSame(3, GarageType::query()->count());
        $this->assertSame('E', $electric->id);
        $this->assertSame(0, (int) $electric->count);
        $this->assertSame('C', $mobil->id);

        // Prices are per-garage: E carries a kWh rate, other types never do.
        $ev = Garage::factory()->create([
            'id' => 'E777', 'garage_type_id' => 'E', 'rent_price' => 80000, 'charge_price_kwh' => 550,
        ]);
        $car = Garage::factory()->create([
            'id' => 'C777', 'garage_type_id' => 'C', 'rent_price' => 25000, 'charge_price_kwh' => null,
        ]);

        $this->assertNotNull($ev->fresh()->charge_price_kwh);
        $this->assertNull($car->fresh()->charge_price_kwh);
    }

    /**
     * GarageFactory yields unique IDs whose prefix matches the garage type.
     */
    public function test_it_creates_garages_consistent_with_their_type(): void
    {
        $first = Garage::factory()->create();
        $second = Garage::factory()->used()->create();

        $this->assertDatabaseCount('garages', 2);
        $this->assertNotSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('/^[SCE]\d{3,}$/', $first->id);
        $this->assertSame(substr($first->id, 0, 1), $first->garage_type_id);
        $this->assertTrue((bool) $second->fresh()->is_used);
    }

    /**
     * VehicleFactory keeps plate and battery rules consistent per type.
     */
    public function test_it_creates_vehicles_with_valid_type_rules(): void
    {
        $motor = Vehicle::factory()->create();
        $bike = Vehicle::factory()->sepeda()->create();
        $ev = Vehicle::factory()->mobilListrik()->create();

        $this->assertDatabaseCount('vehicles', 3);
        $this->assertSame('sepeda_motor', $motor->type);
        $this->assertNotSame('', (string) $motor->license_plate);
        $this->assertNull($motor->battery_capacity);

        $this->assertNull($bike->license_plate);
        $this->assertNull($bike->battery_capacity);

        $this->assertNotSame('', (string) $ev->license_plate);
        $this->assertMatchesRegularExpression('/^\d+$/', (string) $ev->battery_capacity);
        $this->assertGreaterThan(0, $ev->battery_capacity);

        foreach ([$motor, $bike, $ev] as $vehicle) {
            $this->assertNotNull(Customer::query()->find($vehicle->customer_id));
        }
    }

    /**
     * TransactionFactory references an existing garage and vehicle with valid dates.
     */
    public function test_it_creates_a_valid_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertFalse((bool) $transaction->is_monthly);
        $this->assertNull($transaction->battery_level);
        $this->assertGreaterThan(0, $transaction->rent_price_total);
        $this->assertGreaterThanOrEqual($transaction->start_date, $transaction->end_date);
        $this->assertStringContainsString('.jpg', $transaction->transaction_proof);
        $this->assertNotNull(Garage::query()->find($transaction->garage_id));
        $this->assertNotNull(Vehicle::query()->find($transaction->vehicle_id));

        $monthly = Transaction::factory()->monthly()->create();
        $this->assertTrue((bool) $monthly->is_monthly);
    }

    /**
     * TransactionExtensionFactory always extends to a later end date.
     */
    public function test_it_creates_an_extension_with_a_later_end_date(): void
    {
        $extension = TransactionExtension::factory()->create();

        $this->assertDatabaseCount('transaction_extensions', 1);
        $this->assertTrue($extension->new_end_date > $extension->current_end_date);
        $this->assertGreaterThan(0, $extension->extra_rent);
        $this->assertStringContainsString('.jpg', $extension->transaction_proof);
        $this->assertNotNull(Transaction::query()->find($extension->transaction_id));
    }
}
