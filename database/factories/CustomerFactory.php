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
     * Nilai bawaan model.
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
     * State pelanggan yang sudah disetujui admin.
     */
    public function accepted(): static
    {
        return $this->state(['status' => Customer::STATUS_ACCEPTED]);
    }

    /**
     * State pelanggan yang NIK-nya ditolak admin.
     */
    public function declined(): static
    {
        return $this->state(['status' => Customer::STATUS_DECLINED]);
    }
}
