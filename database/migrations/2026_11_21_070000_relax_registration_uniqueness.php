<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unike email dan NIK tidak lagi dipaksakan oleh database, tetapi oleh
     * kode pada saat pendaftaran, sehingga akun yang ditolak (declined)
     * tetap dapat mendaftarkan diri lagi dengan identitas yang sama.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        Schema::table('customers', fn (Blueprint $table) => $table->dropUnique(['nik']));
    }

    /**
     * Kembalikan constraint unik pada email dan NIK.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['email']);
        });

        Schema::table('customers', fn (Blueprint $table) => $table->unique(['nik']));
    }
};
