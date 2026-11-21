<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder utama: akun admin, pelanggan contoh, dan data demo sewa.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Isi basis data aplikasi: akun admin, pelanggan contoh, dan data demo sewa.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->call(CustomerSeeder::class);
        $this->call(DemoRentalSeeder::class);
    }
}
