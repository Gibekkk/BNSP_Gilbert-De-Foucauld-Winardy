<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel garage_types yang berfungsi sebagai penghitung kode garasi.
     * id adalah prefix (S = sepeda, C = mobil, E = mobil listrik),
     * count adalah nomor urut terakhir yang sudah diterbitkan (maksimum 999).
     * Harga tidak disimpan di sini, melainkan di tabel garages.
     */
    public function up(): void
    {
        Schema::create('garage_types', function (Blueprint $table) {
            $table->string('id', 1)->primary();
            $table->unsignedSmallInteger('count')->default(0);
        });

        DB::table('garage_types')->insert([
            ['id' => 'S', 'count' => 0],
            ['id' => 'C', 'count' => 0],
            ['id' => 'E', 'count' => 0],
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
