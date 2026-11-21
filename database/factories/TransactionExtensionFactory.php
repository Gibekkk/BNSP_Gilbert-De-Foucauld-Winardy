<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionExtension;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransactionExtension>
 */
class TransactionExtensionFactory extends Factory
{
    /**
     * Define the model's default state (perpanjangan oleh admin).
     */
    public function definition(): array
    {
        $current = now()->addDays(random_int(1, 30));

        return [
            'transaction_id' => Transaction::factory(),
            'extra_rent' => random_int(1, 50) * 100,
            'current_end_date' => $current->toDateString(),
            'new_end_date' => $current->copy()->addDays(random_int(1, 30))->toDateString(),
            'transaction_proof' => 'admin/'.Str::uuid().'.jpg',
        ];
    }
}
