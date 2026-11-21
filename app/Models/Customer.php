<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nik', 'phone', 'name', 'status'])]
class Customer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Satu-satunya akun user yang terhubung ke pelanggan ini (1:1).
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Kendaraan-kendaraan milik pelanggan ini.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
