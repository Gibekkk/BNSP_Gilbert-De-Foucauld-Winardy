<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel customers (data penyewa).
     * Status diubah admin saat verifikasi NIK secara manual.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->char('nik', 16)->unique();
            $table->string('phone', 20);
            $table->string('name', 100);
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Menghapus tabel customers.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
