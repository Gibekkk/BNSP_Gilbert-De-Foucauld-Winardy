<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel vehicles (kendaraan milik penyewa).
     * license_plate kosong untuk sepeda, battery_capacity (kWh) hanya
     * diisi untuk mobil listrik.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('type', ['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik']);
            $table->string('license_plate', 15)->nullable();
            $table->string('brand', 50);
            $table->string('brand_type', 50);
            $table->unsignedInteger('battery_capacity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Menghapus tabel vehicles.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
