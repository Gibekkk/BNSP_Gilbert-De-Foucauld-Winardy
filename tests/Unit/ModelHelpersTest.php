<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\GarageType;
use Tests\TestCase;

/**
 * Unit test method bantu pada model (tanpa basis data).
 */
class ModelHelpersTest extends TestCase
{
    /**
     * Nama tampilan tipe garasi, termasuk "Sepeda / Motor" untuk tipe S.
     */
    public function test_garage_type_labels(): void
    {
        $this->assertSame('Sepeda / Motor', GarageType::labelFor('S'));
        $this->assertSame('Mobil', GarageType::labelFor('C'));
        $this->assertSame('Mobil Listrik', GarageType::labelFor('E'));
        $this->assertSame('Tipe X', GarageType::labelFor('X'));
    }

    /**
     * Harga bulanan garasi selalu 25 x harga harian.
     */
    public function test_garage_monthly_price_is_25_times_daily(): void
    {
        $garage = new Garage(['rent_price' => 12000]);

        $this->assertSame(300000, $garage->monthlyPrice());
    }
}
