<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            // Format: 08 + 7–12 digit angka, cocok dengan aturan validasi pendaftaran.
            'phone' => '08'.fake()->numberBetween(1000000, 9999999999),
            'name' => fake()->name(),
            'status' => Customer::STATUS_PENDING,
        ];
    }

    /**
     * Indicate that the customer has been accepted by the admin.
     */
    public function accepted(): static
    {
        return $this->state(['status' => Customer::STATUS_ACCEPTED]);
    }

    /**
     * Indicate that the customer's NIK was declined by the admin.
     */
    public function declined(): static
    {
        return $this->state(['status' => Customer::STATUS_DECLINED]);
    }
}
