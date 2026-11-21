<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan status verifikasi sewa oleh admin.
     *  - pending  : baru diajukan penyewa, menunggu admin (garasi tetap ditahan)
     *  - approved : disetujui admin (dihitung di laporan, dapat diperpanjang)
     *  - declined : ditolak admin (garasi dilepas), status_note berisi alasan
     * verified_at diisi saat admin menyetujui / menolak.
     * Transaksi lama dianggap sudah disetujui.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending')->after('transaction_proof');
            $table->string('status_note', 255)->nullable()->after('status');
            $table->timestamp('verified_at')->nullable()->after('status_note');
            $table->index('status');
        });

        DB::table('transactions')->update(['status' => 'approved', 'verified_at' => now()]);
    }

    /**
     * Menghapus kolom status verifikasi sewa.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_note', 'verified_at']);
        });
    }
};
