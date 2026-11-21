<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel transaction_extensions (perpanjangan sewa oleh admin).
     * extra_rent dalam rupiah (integer), bukti bayar diupload oleh admin.
     */
    public function up(): void
    {
        Schema::create('transaction_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->unsignedInteger('extra_rent');
            $table->date('current_end_date');
            $table->date('new_end_date');
            $table->string('transaction_proof');
            $table->timestamps();
        });
    }

    /**
     * Menghapus tabel transaction_extensions.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_extensions');
    }
};
