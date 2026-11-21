<?php

namespace Database\Factories;

use App\Models\GarageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GarageType>
 *
 * Tipe garasi hanya menyimpan kodenya dan jumlah garasi yang pernah diterbitkan;
 * harga disimpan di masing-masing garasi.
 */
class GarageTypeFactory extends Factory
{
    /**
     * State tipe baru (hanya kode + penghitung penerbitan).
     */
    public function definition(): array
    {
        return [
            'id' => 'S',
            'count' => 0,
        ];
    }

    /**
     * State tipe Mobil (C).
     */
    public function mobil(): static
    {
        return $this->state(['id' => 'C']);
    }

    /**
     * State tipe Mobil Listrik (E).
     */
    public function mobilListrik(): static
    {
        return $this->state(['id' => 'E']);
    }
}
