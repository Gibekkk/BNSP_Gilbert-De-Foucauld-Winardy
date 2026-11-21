<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Models\Transaction;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state (sewa harian mulai hari ini).
     */
    public function definition(): array
    {
        return [
            'garage_id' => Garage::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(random_int(3, 60))->toDateString(),
            'is_monthly' => false,
            'battery_level' => null,
            'rent_price_total' => random_int(10, 200) * 1000,
            'transaction_proof' => 'penyewa/'.Str::uuid().'.jpg',
            'status' => Transaction::STATUS_APPROVED,
            'verified_at' => now(),
        ];
    }

    /**
     * State sewa bulanan (satu bulan sepanjang jumlah hari bulan mulai).
     */
    public function monthly(): static
    {
        return $this->state([
            'is_monthly' => true,
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    /**
     * State sewa yang masih menunggu verifikasi admin.
     */
    public function pending(): static
    {
        return $this->state(['status' => Transaction::STATUS_PENDING, 'verified_at' => null]);
    }

    /**
     * State sewa yang ditolak admin.
     */
    public function declined(): static
    {
        return $this->state(['status' => Transaction::STATUS_DECLINED, 'status_note' => 'Bukti tidak valid.']);
    }
}
