<?php

namespace App\Models;

use Database\Factories\GarageTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipe garasi (S, C, E) sekaligus penghitung urutan kode garasi.
 */
#[Fillable(['count'])]
class GarageType extends Model
{
    /** @use HasFactory<GarageTypeFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Nama tampilan per tipe garasi (satu sumber untuk semua layar dan laporan).
     * Tipe S juga menampung sepeda motor, sehingga bernama "Sepeda / Motor".
     */
    public const LABELS = [
        'S' => 'Sepeda / Motor',
        'C' => 'Mobil',
        'E' => 'Mobil Listrik',
    ];

    /**
     * Nama tampilan dari kode tipe garasi (cadangan: "Tipe X").
     */
    public static function labelFor(?string $id): string
    {
        return self::LABELS[$id] ?? 'Tipe '.$id;
    }

    /**
     * Garasi-garasi dengan tipe ini.
     */
    public function garages(): HasMany
    {
        return $this->hasMany(Garage::class, 'garage_type_id');
    }

    /**
     * Konversi tipe (cast) atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }
}
