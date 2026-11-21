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
 * Status verifikasi oleh admin (tidak mass-assignable, diubah lewat
 * RentalVerificationService): pending → approved / declined (final).
 * Sewa pending tetap menahan garasinya; sewa declined melepas garasi.
 *
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 */
#[Fillable(['garage_id', 'vehicle_id', 'start_date', 'end_date', 'is_monthly', 'battery_level', 'rent_price_total', 'charge_price_total', 'transaction_proof'])]
class Transaction extends Model
{
    /** Baru diajukan penyewa, menunggu verifikasi admin. */
    public const STATUS_PENDING = 'pending';

    /** Disetujui admin. */
    public const STATUS_APPROVED = 'approved';

    /** Ditolak admin (final); garasi dilepas. */
    public const STATUS_DECLINED = 'declined';

    /** Semua status yang valid. */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_DECLINED];

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
     * Sewa yang menahan garasi & kendaraan: pending atau approved (bukan declined).
     */
    public function scopeHoldingGarage(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DECLINED);
    }

    /**
     * Sewa yang sudah disetujui admin.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Sewa yang menunggu verifikasi admin.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
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
            'verified_at' => 'datetime',
        ];
    }
}
