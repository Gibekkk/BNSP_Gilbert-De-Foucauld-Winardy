<?php

namespace Database\Factories;

use App\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    /**
     * Urutan berjalan memastikan ID unik dalam satu proses (contoh S001, C002).
     */
    protected static int $sequence = 0;

    /**
     * Pricing lives on the garage itself: random integer rupiah in multiples of
     * 1000 per day; the kWh rate only exists for type E.
     */
    public function definition(): array
    {
        self::$sequence++;

        $prefix = fake()->randomElement(['S', 'C', 'E']);

        return [
            'id' => sprintf('%s%s', $prefix, str_pad((string) self::$sequence, 3, '0', STR_PAD_LEFT)),
            'garage_type_id' => $prefix,
            'is_used' => false,
            'rent_price' => random_int(10, 400) * 1000,
            'charge_price_kwh' => $prefix === 'E' ? random_int(20, 60) * 100 : null,
        ];
    }

    /**
     * Indicate that the garage is currently being used.
     */
    public function used(): static
    {
        return $this->state(['is_used' => true]);
    }
}
