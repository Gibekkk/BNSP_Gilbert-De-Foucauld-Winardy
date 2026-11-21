<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memisahkan biaya isi listrik dari biaya sewa agar laporan dapat
     * menampilkan pendapatan sewa dan pendapatan listrik secara terpisah.
     * rent_price_total = biaya sewa saja; charge_price_total = biaya isi
     * listrik hingga penuh (hanya mobil listrik di garasi tipe E, selain itu 0).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('charge_price_total')->default(0)->after('rent_price_total');
        });
    }

    /**
     * Menghapus kolom charge_price_total.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('charge_price_total');
        });
    }
};
