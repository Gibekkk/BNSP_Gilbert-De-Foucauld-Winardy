<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kendaraan milik penyewa (sepeda, sepeda motor, mobil, mobil listrik).
 * battery_capacity = kapasitas baterai mobil listrik dalam kWh (tidak pernah dikurangi).
 */
#[Fillable(['customer_id', 'type', 'license_plate', 'brand', 'brand_type', 'battery_capacity'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * Pelanggan pemilik kendaraan ini.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Transaksi sewa yang memakai kendaraan ini.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
