<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel garages. ID berformat prefix + 3 digit (contoh S001, C010).
     * Harga tidak disimpan di sini, melainkan di tabel garage_types.
     */
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->string('id', 4)->primary();
            $table->string('garage_type_id', 1);
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