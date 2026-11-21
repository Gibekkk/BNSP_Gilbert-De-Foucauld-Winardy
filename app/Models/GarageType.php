<?php

namespace App\Models;

use Database\Factories\GarageTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['count', 'rent_price', 'charge_price_kwh'])]
class GarageType extends Model
{
    /** @use HasFactory<GarageTypeFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function garages(): HasMany
    {
        return $this->hasMany(Garage::class, 'garage_type_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rent_price' => 'integer',
            'charge_price_kwh' => 'integer:null',
        ];
    }
}
