<?php

namespace App\Models;

use Database\Factories\TransactionExtensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu perpanjangan sewa oleh admin beserta biaya tambahan dan bukti bayarnya.
 */
#[Fillable(['transaction_id', 'extra_rent', 'current_end_date', 'new_end_date', 'transaction_proof'])]
class TransactionExtension extends Model
{
    /** @use HasFactory<TransactionExtensionFactory> */
    use HasFactory;

    /**
     * Transaksi sewa yang diperpanjang.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Konversi tipe (cast) atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_end_date' => 'date',
            'new_end_date' => 'date',
            'extra_rent' => 'integer',
        ];
    }
}
