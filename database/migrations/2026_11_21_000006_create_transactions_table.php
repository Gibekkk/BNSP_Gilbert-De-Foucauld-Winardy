<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel transactions (sewa garasi).
     * battery_level (kWh) diisi penyewa untuk mobil listrik di garasi listrik.
     * rent_price_total dalam rupiah (integer), transaction_proof berisi path gambar.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('garage_id', 4);
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_monthly')->default(false);
            $table->unsignedInteger('battery_level')->nullable();
            $table->unsignedInteger('rent_price_total');
            $table->string('transaction_proof');
            $table->timestamps();

            $table->foreign('garage_id')->references('id')->on('garages');
            $table->index(['garage_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Menghapus tabel transactions.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
