<?php

namespace App\Models;

use Database\Factories\TransactionExtensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_id', 'extra_rent', 'current_end_date', 'new_end_date', 'transaction_proof'])]
class TransactionExtension extends Model
{
    /** @use HasFactory<TransactionExtensionFactory> */
    use HasFactory;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the attributes that should be cast.
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
