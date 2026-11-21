<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Laporan persewaan garasi.
 *
 * Setiap transaksi (termasuk seluruh biaya perpanjangannya) dihitung pada
 * tanggal MULAI sewanya (start_date). Pengelompokan dilakukan di PHP agar
 * kode yang sama berjalan di MySQL maupun SQLite.
 *
 * Kolom setiap baris laporan:
 *  rentals, rent_income, charge_income, extension_income, total
 */
class ReportService
{
    public const GROUP_MONTHLY = 'monthly';

    public const GROUP_DAILY = 'daily';

    public const GROUP_TYPE = 'type';

    public const GROUP_TENANT = 'tenant';

    public const GROUPS = [self::GROUP_MONTHLY, self::GROUP_DAILY, self::GROUP_TYPE, self::GROUP_TENANT];

    private const TYPE_LABELS = ['S' => 'Sepeda', 'C' => 'Mobil', 'E' => 'Mobil Listrik'];

    private const MONTH_LABELS = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /**
     * Per bulan dalam tahun tertentu (selalu 12 baris).
     *
     * @return array{period: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function monthly(int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1);
        $groups = $this->transactions($from, $from->endOfYear())
            ->groupBy(fn (Transaction $t): int => $t->start_date->month);

        $rows = [];
        foreach (self::MONTH_LABELS as $month => $label) {
            $rows[] = $this->row((string) $month, $label, $groups->get($month, collect()));
        }

        return $this->result('Tahun '.$year, $rows);
    }

    /**
     * Per hari dalam bulan tertentu (satu baris per tanggal).
     *
     * @return array{period: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function daily(int $year, int $month): array
    {
        $from = CarbonImmutable::create($year, $month, 1);
        $groups = $this->transactions($from, $from->endOfMonth())
            ->groupBy(fn (Transaction $t): int => $t->start_date->day);

        $rows = [];
        for ($day = 1; $day <= $from->daysInMonth; $day++) {
            $date = $from->setDay($day);
            $rows[] = $this->row($date->toDateString(), $date->format('d/m/Y'), $groups->get($day, collect()));
        }

        return $this->result(self::MONTH_LABELS[$month].' '.$year, $rows);
    }

    /**
     * Per tipe garasi (S, C, E), opsional dibatasi tahun / bulan.
     *
     * @return array{period: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function byType(?int $year = null, ?int $month = null): array
    {
        [$from, $to, $period] = $this->range($year, $month);
        $groups = $this->transactions($from, $to)
            ->groupBy(fn (Transaction $t): string => $t->garage->garage_type_id);

        $rows = [];
        foreach (self::TYPE_LABELS as $type => $label) {
            $rows[] = $this->row($type, $label.' ('.$type.')', $groups->get($type, collect()));
        }

        return $this->result($period, $rows);
    }

    /**
     * Per penyewa (hanya penyewa yang memiliki transaksi), urut total terbesar.
     *
     * @return array{period: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function byTenant(?int $year = null, ?int $month = null): array
    {
        [$from, $to, $period] = $this->range($year, $month);

        $rows = $this->transactions($from, $to)
            ->groupBy(fn (Transaction $t): int => (int) $t->vehicle->customer_id)
            ->map(function (Collection $items, int $customerId): array {
                $customer = $items->first()->vehicle->customer;
                $label = $customer ? $customer->name.' · NIK '.$customer->nik : 'Pelanggan #'.$customerId;

                return $this->row((string) $customerId, $label, $items);
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return $this->result($period, $rows);
    }

    /**
     * Tahun-tahun yang memiliki transaksi (selalu termasuk tahun ini), terbaru dulu.
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $first = Transaction::query()->min('start_date');
        $current = (int) today()->year;
        $start = $first ? min((int) CarbonImmutable::parse($first)->year, $current) : $current;

        return range($current, $start);
    }

    /**
     * Transaksi yang dimulai dalam rentang tanggal, lengkap dengan total perpanjangan.
     *
     * @return Collection<int, Transaction>
     */
    private function transactions(?CarbonImmutable $from, ?CarbonImmutable $to): Collection
    {
        return Transaction::query()
            ->with(['garage:id,garage_type_id', 'vehicle:id,customer_id', 'vehicle.customer:id,name,nik'])
            ->withSum('extensions', 'extra_rent')
            ->when($from && $to, fn ($query) => $query->startedBetween($from, $to))
            ->get();
    }

    /**
     * Satu baris laporan dari sekumpulan transaksi.
     *
     * @param  Collection<int, Transaction>  $items
     * @return array<string, mixed>
     */
    private function row(string $key, string $label, Collection $items): array
    {
        $rent = (int) $items->sum('rent_price_total');
        $charge = (int) $items->sum('charge_price_total');
        $extension = (int) $items->sum(fn (Transaction $t): int => (int) $t->extensions_sum_extra_rent);

        return [
            'key' => $key,
            'label' => $label,
            'rentals' => $items->count(),
            'rent_income' => $rent,
            'charge_income' => $charge,
            'extension_income' => $extension,
            'total' => $rent + $charge + $extension,
        ];
    }

    /**
     * Bungkus baris laporan beserta baris total.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{period: string, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function result(string $period, array $rows): array
    {
        $totals = [];
        foreach (['rentals', 'rent_income', 'charge_income', 'extension_income', 'total'] as $column) {
            $totals[$column] = (int) array_sum(array_column($rows, $column));
        }

        return ['period' => $period, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Rentang tanggal opsional untuk laporan per tipe / per penyewa.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null, 2: string}
     */
    private function range(?int $year, ?int $month): array
    {
        if ($year === null) {
            return [null, null, 'Semua periode'];
        }

        if ($month === null) {
            $from = CarbonImmutable::create($year, 1, 1);

            return [$from, $from->endOfYear(), 'Tahun '.$year];
        }

        $from = CarbonImmutable::create($year, $month, 1);

        return [$from, $from->endOfMonth(), self::MONTH_LABELS[$month].' '.$year];
    }
}
