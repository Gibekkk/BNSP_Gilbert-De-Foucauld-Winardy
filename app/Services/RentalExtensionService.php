<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionExtension;
use App\Support\ProofFile;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Perpanjangan sewa oleh admin ("Perpanjang Status Garasi").
 *
 * Aturan:
 * - Hanya sewa yang sedang berjalan hari ini yang dapat diperpanjang.
 * - Hanya tanggal selesai yang berubah (bukan garasi, kendaraan, atau tipe garasi),
 * dan hanya boleh mundur — sewa tidak dapat diperpendek. Tidak ada batas maksimum.
 * - Hari perpanjangan tidak boleh bertabrakan dengan transaksi lain pada
 * garasi (atau kendaraan) yang sama, sehingga garasi tetap terkunci selama
 * seluruh masa perpanjangan.
 * - Sisa biaya dihitung RentalService::extensionBreakdown() (blok bulan
 * seharga 25 x harian, sisa hari dengan harga harian).
 * - Admin mengunggah bukti bayar; riwayat disimpan di
 * transaction_extensions dan transactions.end_date dimundurkan.
 */
class RentalExtensionService
{
    /** Folder (disk privat "local") untuk bukti bayar perpanjangan dari admin. */
    public const PROOF_DIRECTORY = 'proofs/extensions';

    /**
     * Suntikkan aturan sewa bersama (RentalService).
     */
    public function __construct(private readonly RentalService $rentals) {}

    /**
     * Hitung sisa biaya tanpa menyimpan (pratinjau di layar admin).
     *
     * @return array{current_end_date: string, new_end_date: string, daily_price: int, days: int, total: int, segments: list<array<string, mixed>>}
     *
     * @throws ValidationException on any broken rule
     */
    public function quote(Transaction $transaction, string $newEndDate): array
    {
        $this->assertActive($transaction);

        $transaction->loadMissing('garage');
        $currentEnd = CarbonImmutable::parse($transaction->end_date);
        $newEnd = $this->parseDate($newEndDate);
        $dailyPrice = (int) $transaction->garage->rent_price;

        $breakdown = $this->rentals->extensionBreakdown($dailyPrice, $currentEnd, $newEnd);

        // Garasi (dan kendaraan) harus kosong pada setiap hari perpanjangan.
        $from = $currentEnd->addDay();
        $this->rentals->assertGarageFree($transaction->garage_id, $from, $newEnd, $transaction->id);
        $this->rentals->assertVehicleFree((int) $transaction->vehicle_id, $from, $newEnd, $transaction->id);

        return [
            'current_end_date' => $currentEnd->toDateString(),
            'new_end_date' => $newEnd->toDateString(),
            'daily_price' => $dailyPrice,
            'days' => $breakdown['days'],
            'total' => $breakdown['total'],
            'segments' => $breakdown['segments'],
        ];
    }

    /**
     * Simpan perpanjangan beserta bukti bayarnya (semua atau tidak sama sekali).
     *
     * @throws ValidationException on any broken rule
     */
    public function extend(Transaction $transaction, string $newEndDate, UploadedFile $proof): TransactionExtension
    {
        $path = ProofFile::store($proof, self::PROOF_DIRECTORY);

        try {
            return DB::transaction(function () use ($transaction, $newEndDate, $path): TransactionExtension {
                /** @var Transaction $locked */
                $locked = Transaction::query()->with('garage')->lockForUpdate()->findOrFail($transaction->getKey());

                // Kunci juga baris garasi agar tidak ada sewa baru yang menyelip.
                $locked->garage()->lockForUpdate()->first();

                // Hitung ulang di dalam lock agar tidak memakai end_date yang basi.
                $quote = $this->quote($locked, $newEndDate);

                $extension = $locked->extensions()->create([
                    'extra_rent' => $quote['total'],
                    'current_end_date' => $quote['current_end_date'],
                    'new_end_date' => $quote['new_end_date'],
                    'transaction_proof' => $path,
                ]);

                $locked->forceFill(['end_date' => $quote['new_end_date']])->save();
                $locked->garage->forceFill(['is_used' => true])->save();

                return $extension;
            });
        } catch (Throwable $e) {
            // Jangan tinggalkan file yatim bila penyimpanan gagal.
            ProofFile::delete($path);

            throw $e;
        }
    }

    /**
     * Tolak transaksi yang belum disetujui admin atau tidak sedang berjalan hari ini.
     *
     * @throws ValidationException
     */
    private function assertActive(Transaction $transaction): void
    {
        if ($transaction->status !== Transaction::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'transaction' => 'Hanya sewa yang sudah disetujui admin yang dapat diperpanjang.',
            ]);
        }

        $today = today();

        if ($transaction->start_date->greaterThan($today) || $transaction->end_date->lessThan($today)) {
            throw ValidationException::withMessages([
                'transaction' => 'Hanya sewa yang sedang berjalan hari ini yang dapat diperpanjang.',
            ]);
        }
    }

    /**
     * Baca tanggal dengan format ketat Y-m-d.
     *
     * @throws ValidationException when the format is invalid
     */
    private function parseDate(string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidArgumentException) {
            $date = false;
        }

        if ($date === false || $date->toDateString() !== $value) {
            throw ValidationException::withMessages(['new_end_date' => 'Format tanggal tidak valid (YYYY-MM-DD).']);
        }

        return $date;
    }
}
