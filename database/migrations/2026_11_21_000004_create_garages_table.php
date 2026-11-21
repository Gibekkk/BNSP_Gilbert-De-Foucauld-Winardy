<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel garages. ID berformat prefix + 3 digit (contoh S001, C010).
     * Setiap garasi menyimpan harganya sendiri dalam rupiah (integer):
     * rent_price adalah harga sewa harian, charge_price_kwh hanya diisi
     * untuk garasi mobil listrik (tipe E).
     */
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->string('id', 4)->primary();
            $table->string('garage_type_id', 1);
            $table->unsignedInteger('rent_price');
            $table->unsignedInteger('charge_price_kwh')->nullable();
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->foreign('garage_type_id')->references('id')->on('garage_types');
        });
    }

    /**
     * Menghapus tabel garages.
     */
    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
