<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    private const BRANDS_BY_TYPE = [
        'sepeda' => ['Honda', 'Yamaha'],
        'sepeda_motor' => ['Honda', 'Yamaha'],
        'mobil' => ['Daihatsu', 'Toyota', 'Wuling'],
        'mobil_listrik' => ['BYD', 'Hyundai', 'Wuling'],
    ];

    /**
     * Nilai bawaan model (sebuah sepeda motor).
     */
    public function definition(): array
    {
        return $this->forType('sepeda_motor');
    }

    /**
     * Susun atribut yang tetap valid untuk satu tipe kendaraan
     * (aturan plat dan kapasitas baterai).
     */
    private function forType(string $type): array
    {
        $brands = self::BRANDS_BY_TYPE[$type];

        return [
            'customer_id' => Customer::factory(),
            'type' => $type,
            // Sepeda tanpa plat; yang lain memakai plat Jakarta (contoh B1234AB).
            'license_plate' => $type === 'sepeda'
                ? null
                : sprintf('B%d%s', random_int(100, 9999), strtoupper(fake()->regexify('[A-Z]{1,2}'))),
            'brand' => fake()->randomElement($brands),
            'brand_type' => fake()->randomElement(['City', 'Beat', 'Vario', 'Avanza', 'Innova', 'Bolt EV', 'Ioniq 5']),
            // Hanya mobil listrik membawa kapasitas baterai (kW, bilangan bulat).
            'battery_capacity' => $type === 'mobil_listrik' ? random_int(40, 80) : null,
        ];
    }

    /**
     * State sepeda: tanpa plat dan tanpa baterai.
     */
    public function sepeda(): static
    {
        return $this->state(fn (): array => $this->forType('sepeda'));
    }

    /**
     * State sepeda motor.
     */
    public function sepedaMotor(): static
    {
        return $this->state(fn (): array => $this->forType('sepeda_motor'));
    }

    /**
     * State mobil (bensin): memakai plat, tanpa baterai.
     */
    public function mobil(): static
    {
        return $this->state(fn (): array => $this->forType('mobil'));
    }

    /**
     * State mobil listrik: memakai plat dan kapasitas baterai (kW, bilangan bulat).
     */
    public function mobilListrik(): static
    {
        return $this->state(fn (): array => $this->forType('mobil_listrik'));
    }
}
