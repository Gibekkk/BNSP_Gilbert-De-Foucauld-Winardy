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
     * Harga disimpan di setiap garasi: rupiah bulat acak kelipatan
     * 1000 per hari; tarif per kWh hanya ada untuk tipe E.
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
     * State garasi yang sedang dipakai (used).
     */
    public function used(): static
    {
        return $this->state(['is_used' => true]);
    }
}
