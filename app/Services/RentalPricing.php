<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Aturan harga & tanggal sewa garasi (murni, tanpa akses basis data).
 *
 * Konvensi tanggal: start_date dan end_date sama-sama INKLUSIF.
 *  - Sewa harian N hari yang dimulai hari ini berakhir pada hari ini + (N - 1).
 *  - Sewa bulanan selalu berakhir pada hari terakhir bulan berjalan
 *    (mulai 21 Nov → selesai 30 Nov), dengan harga 25 x harga harian.
 */
class RentalPricing
{
    /** Pengali harga bulanan terhadap harga harian. */
    public const MONTHLY_MULTIPLIER = 25;

    /** Batas jumlah hari untuk sewa harian. */
    public const MAX_DAILY_DAYS = 30;

    /**
     * Harga sewa bulanan dari harga harian.
     */
    public function monthlyPrice(int $dailyPrice): int
    {
        return $dailyPrice * self::MONTHLY_MULTIPLIER;
    }

    /**
     * Tanggal selesai sewa harian (inklusif).
     */
    public function dailyEndDate(CarbonInterface $start, int $days): CarbonImmutable
    {
        if ($days < 1 || $days > self::MAX_DAILY_DAYS) {
            throw new InvalidArgumentException('Jumlah hari sewa harian harus 1 sampai '.self::MAX_DAILY_DAYS.'.');
        }

        return CarbonImmutable::parse($start)->startOfDay()->addDays($days - 1);
    }

    /**
     * Tanggal selesai sewa bulanan: hari terakhir bulan berjalan.
     */
    public function monthlyEndDate(CarbonInterface $start): CarbonImmutable
    {
        return CarbonImmutable::parse($start)->endOfMonth()->startOfDay();
    }

    /**
     * Biaya sewa awal.
     */
    public function rentTotal(int $dailyPrice, bool $isMonthly, int $days = 0): int
    {
        return $isMonthly ? $this->monthlyPrice($dailyPrice) : $dailyPrice * $days;
    }

    /**
     * Biaya isi listrik hingga penuh: (kapasitas - level saat masuk) x tarif per kWh.
     */
    public function chargeTotal(int $batteryCapacity, int $batteryLevel, int $pricePerKwh): int
    {
        return max(0, $batteryCapacity - $batteryLevel) * $pricePerKwh;
    }

    /**
     * Rincian biaya perpanjangan dari (currentEnd + 1 hari) sampai newEnd (inklusif).
     *
     * Rentang dipecah per bulan kalender. Bulan yang tercakup penuh (tanggal 1
     * sampai hari terakhir) dikenai harga bulanan 25 x harian; sisa hari pada
     * bulan yang tidak penuh dikenai harga harian per hari. Contoh harga harian
     * Rp10.000, selesai 30 Nov diperpanjang ke 31 Des → 1 bulan penuh = Rp250.000;
     * diperpanjang ke 15 Des → 15 hari = Rp150.000.
     *
     * @return array{days: int, total: int, segments: list<array{from: string, to: string, days: int, monthly: bool, amount: int}>}
     */
    public function extensionBreakdown(int $dailyPrice, CarbonInterface $currentEnd, CarbonInterface $newEnd): array
    {
        $current = CarbonImmutable::parse($currentEnd)->startOfDay();
        $target = CarbonImmutable::parse($newEnd)->startOfDay();

        if ($target->lessThanOrEqualTo($current)) {
            throw new InvalidArgumentException('Tanggal selesai baru harus setelah tanggal selesai saat ini.');
        }

        $segments = [];
        $cursor = $current->addDay();

        while ($cursor->lessThanOrEqualTo($target)) {
            $monthEnd = $cursor->endOfMonth()->startOfDay();
            $segmentEnd = $monthEnd->lessThan($target) ? $monthEnd : $target;
            $days = (int) $cursor->diffInDays($segmentEnd) + 1;
            $isFullMonth = $cursor->day === 1 && $segmentEnd->equalTo($monthEnd);

            $segments[] = [
                'from' => $cursor->toDateString(),
                'to' => $segmentEnd->toDateString(),
                'days' => $days,
                'monthly' => $isFullMonth,
                'amount' => $isFullMonth ? $this->monthlyPrice($dailyPrice) : $dailyPrice * $days,
            ];

            $cursor = $segmentEnd->addDay();
        }

        return [
            'days' => array_sum(array_column($segments, 'days')),
            'total' => array_sum(array_column($segments, 'amount')),
            'segments' => $segments,
        ];
    }
}
