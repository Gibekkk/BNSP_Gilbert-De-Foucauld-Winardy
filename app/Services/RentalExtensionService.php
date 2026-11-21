<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionExtension;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Perpanjangan sewa garasi oleh admin.
 *
 * Aturan:
 *  - Hanya sewa yang sedang berjalan hari ini yang dapat diperpanjang.
 *  - Tanggal selesai hanya boleh dimundurkan (tidak bisa memperpendek sewa).
 *  - Sisa biaya dihitung oleh RentalPricing::extensionBreakdown() memakai
 *    harga harian garasi saat ini.
 *  - Bukti bayar wajib diunggah; riwayat disimpan di transaction_extensions
 *    dan transactions.end_date diperbarui ke tanggal selesai yang baru.
 */
class RentalExtensionService
{
    public const PROOF_DIRECTORY = 'proofs/extensions';

    public function __construct(private readonly RentalPricing $pricing)
    {
    }

    /**
     * Hitung sisa biaya tanpa menyimpan apa pun (untuk pratinjau di layar admin).
     *
     * @return array{current_end_date: string, new_end_date: string, daily_price: int, days: int, total: int, segments: list<array<string, mixed>>}
     */
    public function quote(Transaction $transaction, string $newEndDate): array
    {
        $this->assertActive($transaction);

        $transaction->loadMissing('garage');
        $currentEnd = CarbonImmutable::parse($transaction->end_date);
        $newEnd = $this->parseNewEnd($newEndDate, $currentEnd);
        $dailyPrice = (int) $transaction->garage->rent_price;

        $breakdown = $this->pricing->extensionBreakdown($dailyPrice, $currentEnd, $newEnd);

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
     * Simpan perpanjangan beserta bukti bayarnya (all-or-nothing).
     */
    public function extend(Transaction $transaction, string $newEndDate, UploadedFile $proof): TransactionExtension
    {
        $path = $proof->store(self::PROOF_DIRECTORY, 'public');

        try {
            return DB::transaction(function () use ($transaction, $newEndDate, $path): TransactionExtension {
                /** @var Transaction $locked */
                $locked = Transaction::query()->with('garage')->lockForUpdate()->findOrFail($transaction->getKey());

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
            Storage::disk('public')->delete($path);

            throw $e;
        }
    }

    /**
     * Tolak transaksi yang tidak sedang berjalan hari ini.
     */
    private function assertActive(Transaction $transaction): void
    {
        $today = today();

        if ($transaction->start_date->greaterThan($today) || $transaction->end_date->lessThan($today)) {
            throw ValidationException::withMessages([
                'transaction' => 'Hanya sewa yang sedang berjalan hari ini yang dapat diperpanjang.',
            ]);
        }
    }

    /**
     * Validasi tanggal selesai baru: format Y-m-d dan harus setelah tanggal selesai saat ini.
     */
    private function parseNewEnd(string $value, CarbonImmutable $currentEnd): CarbonImmutable
    {
        try {
            $newEnd = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidArgumentException) {
            $newEnd = false;
        }

        if ($newEnd === false || $newEnd->toDateString() !== $value) {
            throw ValidationException::withMessages(['new_end_date' => 'Format tanggal tidak valid (YYYY-MM-DD).']);
        }

        if ($newEnd->lessThanOrEqualTo($currentEnd)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'Tanggal selesai baru harus setelah '.$currentEnd->translatedFormat('d M Y').'. Sewa tidak dapat diperpendek.',
            ]);
        }

        return $newEnd;
    }
}
