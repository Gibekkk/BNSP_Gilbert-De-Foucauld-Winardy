<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi sewa baru oleh admin: setujui atau tolak.
 *
 * Aturan:
 *  - Hanya sewa berstatus pending yang dapat diverifikasi; keputusan bersifat
 *    final (approved / declined tidak dapat diubah lagi).
 *  - Selama pending, garasi tetap ditahan agar tidak dipesan penyewa lain.
 *  - Disetujui → sewa dihitung di laporan dan dapat diperpanjang admin.
 *  - Ditolak → garasi dan kendaraan langsung dilepas; alasan (opsional)
 *    disimpan di status_note dan ditampilkan ke penyewa.
 */
class RentalVerificationService
{
    /**
     * Suntikkan service garasi untuk melepas status used setelah penolakan.
     */
    public function __construct(private readonly GarageService $garages) {}

    /**
     * Setujui sewa yang masih pending.
     *
     * @throws ValidationException bila sewa sudah diverifikasi sebelumnya
     */
    public function approve(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            $locked = $this->lockPending($transaction);

            $locked->forceFill([
                'status' => Transaction::STATUS_APPROVED,
                'status_note' => null,
                'verified_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /**
     * Tolak sewa yang masih pending dan lepaskan garasinya.
     *
     * @throws ValidationException bila sewa sudah diverifikasi sebelumnya
     */
    public function decline(Transaction $transaction, ?string $reason): Transaction
    {
        $declined = DB::transaction(function () use ($transaction, $reason): Transaction {
            $locked = $this->lockPending($transaction);

            $locked->forceFill([
                'status' => Transaction::STATUS_DECLINED,
                'status_note' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'verified_at' => now(),
            ])->save();

            return $locked;
        });

        // Garasi kembali tersedia (bila tidak ada sewa lain yang menahannya hari ini).
        $this->garages->syncUsage();

        return $declined;
    }

    /**
     * Kunci baris transaksi dan pastikan statusnya masih pending.
     *
     * @throws ValidationException
     */
    private function lockPending(Transaction $transaction): Transaction
    {
        /** @var Transaction $locked */
        $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

        if ($locked->status !== Transaction::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Sewa ini sudah diverifikasi sebelumnya (status: '.$locked->status.'). Keputusan bersifat final.',
            ]);
        }

        return $locked;
    }
}
