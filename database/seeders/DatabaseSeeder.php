<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder utama: hanya akun pengguna (admin + penyewa contoh).
 * Garasi, kendaraan, dan sewa dibuat langsung saat demonstrasi.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Isi basis data aplikasi dengan akun pengguna saja.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
    }
}
