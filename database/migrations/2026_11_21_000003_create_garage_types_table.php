<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel garage_types.
     * id adalah prefix (S = sepeda, C = mobil, E = mobil listrik),
     * count adalah nomor urut terakhir yang sudah dipakai untuk ID garasi.
     * rent_price (harga harian) dan charge_price_kwh berlaku untuk semua
     * garasi pada tipe tersebut dan ditimpa setiap admin membuat garasi baru.
     * Harga dalam rupiah (integer).
     */
    public function up(): void
    {
        Schema::create('garage_types', function (Blueprint $table) {
            $table->string('id', 1)->primary();
            $table->unsignedSmallInteger('count')->default(0);
            $table->unsignedInteger('rent_price')->default(0);
            $table->unsignedInteger('charge_price_kwh')->nullable();
        });

        DB::table('garage_types')->insert([
            ['id' => 'S', 'count' => 0, 'rent_price' => 0, 'charge_price_kwh' => null],
            ['id' => 'C', 'count' => 0, 'rent_price' => 0, 'charge_price_kwh' => null],
            ['id' => 'E', 'count' => 0, 'rent_price' => 0, 'charge_price_kwh' => null],
        ]);
    }

    /**
     * Menghapus tabel garage_types.
     */
    public function down(): void
    {
        Schema::dropIfExists('garage_types');
    }
};