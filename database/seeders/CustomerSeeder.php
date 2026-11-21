<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    /**
     * Seed one demo customer (already accepted) with its login account.
     * Login: pelanggan@example.com / password
     */
    public function run(): void
    {
        if (User::query()->where('email', 'pelanggan@example.com')->exists()) {
            return;
        }

        $customer = Customer::factory()->accepted()->create([
            'nik' => '3201152988071601',
            'phone' => '081234567890',
            'name' => 'Pelanggan Contoh',
        ]);

        User::factory()->create([
            'email' => 'pelanggan@example.com',
            'password' => Hash::make('password'),
            'customer_id' => $customer->id,
            'is_admin' => false,
        ]);
    }
}
