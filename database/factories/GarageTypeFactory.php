<?php

namespace Database\Factories;

use App\Models\GarageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarageType>
 *
 * A garage type only knows its key and how many slots it has issued ever;
 * pricing lives on the individual garages.
 */
class GarageTypeFactory extends Factory
{
    /**
     * Fresh type state (id + issuance counter only).
     */
    public function definition(): array
    {
        return [
            'id' => 'S',
            'count' => 0,
        ];
    }

    /**
     * Mobil (C).
     */
    public function mobil(): static
    {
        return $this->state(['id' => 'C']);
    }

    /**
     * Mobil listrik (E).
     */
    public function mobilListrik(): static
    {
        return $this->state(['id' => 'E']);
    }
}
