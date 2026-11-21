<?php

namespace App\Models;

use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu garasi (kode S001/C001/E001) beserta harga sewa harian dan tarif kWh-nya.
 */
#[Fillable(['id', 'garage_type_id', 'is_used', 'rent_price', 'charge_price_kwh'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Tipe garasi ini (S, C, atau E).
     */
    public function garageType(): BelongsTo
    {
        return $this->belongsTo(GarageType::class, 'garage_type_id');
    }

    /**
     * Semua transaksi sewa pada garasi ini.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Transaksi yang sedang berjalan hari ini pada garasi ini (paling banyak satu,
     * karena garasi berstatus used tidak dapat disewa lagi).
     */
    public function activeTransaction(): HasOne
    {
        $today = today()->toDateString();

        return $this->hasOne(Transaction::class)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date');
    }

    /**
     * Harga bulanan: selalu 25x harga sewa harian garasi ini.
     */
    public function monthlyPrice(): int
    {
        return (int) $this->rent_price * 25;
    }

    /**
     * Konversi tipe (cast) atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'rent_price' => 'integer',
            'charge_price_kwh' => 'integer',
        ];
    }
}
