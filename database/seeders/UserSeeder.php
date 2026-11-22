<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder akun pengguna saja, tanpa data garasi, kendaraan, maupun sewa,
 * agar demonstrasi dimulai dari kondisi bersih.
 *
 * Akun yang dibuat (password: "password"):
 *  - admin@example.com     : admin
 *  - pelanggan@example.com : penyewa contoh yang sudah disetujui admin
 */
class UserSeeder extends Seeder
{
    /**
     * Buat akun admin dan satu penyewa contoh (dilewati bila sudah ada).
     */
    public function run(): void
    {
        if (! User::query()->where('email', 'admin@example.com')->exists()) {
            User::factory()->admin()->create([
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ]);
        }

        if (User::query()->where('email', 'pelanggan@example.com')->exists()) {
            return;
        }

        // NIK dengan struktur valid: Jawa Barat, lahir 29-08-1997, laki-laki.
        $customer = Customer::factory()->accepted()->create([
            'nik' => '3201152908971601',
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
