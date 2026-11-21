<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu transaksi sewa garasi.
 *
 * Nominal uang disimpan dalam rupiah (integer):
 *  - rent_price_total   : total biaya sewa baru = sewa (harian x hari, atau 25 x harian
 *                         untuk bulanan) + biaya isi listrik (akumulatif)
 *  - charge_price_total : bagian biaya isi listrik di dalam rent_price_total (0 bila tidak berlaku)
 * Biaya perpanjangan disimpan terpisah di transaction_extensions; end_date
 * selalu berisi tanggal selesai terkini (sudah termasuk perpanjangan).
 *
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 */
#[Fillable(['garage_id', 'vehicle_id', 'start_date', 'end_date', 'is_monthly', 'battery_level', 'rent_price_total', 'charge_price_total', 'transaction_proof'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Garasi yang disewa pada transaksi ini.
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class, 'garage_id');
    }

    /**
     * Kendaraan yang diparkir pada transaksi ini.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Riwayat perpanjangan sewa oleh admin.
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(TransactionExtension::class);
    }

    /**
     * Sewa yang sedang berjalan pada tanggal tertentu (default: hari ini),
     * yaitu start_date <= tanggal <= end_date.
     */
    public function scopeActiveOn(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $day = ($date ?? today())->toDateString();

        return $query->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day);
    }

    /**
     * Sewa yang dimulai di antara dua tanggal (inklusif), dipakai laporan.
     */
    public function scopeStartedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereDate('start_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $to->toDateString());
    }

    /**
     * Konversi tipe (cast) atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_monthly' => 'boolean',
            'battery_level' => 'integer',
            'rent_price_total' => 'integer',
            'charge_price_total' => 'integer',
        ];
    }
}
