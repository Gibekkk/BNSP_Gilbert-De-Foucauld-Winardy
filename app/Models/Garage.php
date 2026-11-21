<?php

namespace App\Models;

use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['garage_type_id', 'is_used'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public function garageType(): BelongsTo
    {
        return $this->belongsTo(GarageType::class, 'garage_type_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
        ];
    }
}
