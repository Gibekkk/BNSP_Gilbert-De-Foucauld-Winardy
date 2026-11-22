<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Support\ProofFile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Aturan bisnis sewa garasi (spesifikasi + klarifikasi sesi tanya jawab asesor).
 *
 * Setiap aturan berupa method publik kecil agar dapat diuji unit secara terpisah;
 * quote() dan create() hanya menggabungkannya.
 *
 * Tanggal bersifat INKLUSIF: end_date = start_date + (hari - 1), sehingga sewa 1 hari
 * memiliki start_date = end_date. Nominal uang dalam rupiah (integer).
 *
 * - Tanggal mulai selalu hari ini.
 * - Sewa harian: 1 .. (jumlah hari bulan mulai - 1) hari, harga = hari x harga harian.
 * - Sewa bulanan: tepat satu bulan = jumlah hari pada bulan MULAI
 * (15 Feb → 28/29 hari, 15 Mei → 31 hari), harga = 25 x harga harian.
 * - Mobil listrik di garasi mobil listrik (E) wajib diisi hingga penuh:
 * battery_capacity (kWh) x (100 - battery_level %) / 100 x charge_price_kwh,
 * dibulatkan ke rupiah terdekat dan ditambahkan ke rent_price_total.
 * - Satu garasi hanya untuk satu kendaraan per hari dan satu kendaraan hanya di satu garasi per hari:
 * rentang tanggal yang bertabrakan ditolak; menyewa lagi di kemudian hari tetap boleh.
 */
class RentalService
{
    /** Harga bulanan = pengali ini x harga harian. */
    public const MONTHLY_MULTIPLIER = 25;

    /** Folder (disk privat "local") untuk bukti pelunasan dari penyewa. */
    public const PROOF_DIRECTORY = 'proofs/rentals';

    /** Tipe garasi yang boleh dipakai setiap tipe kendaraan (S = sepeda/motor, C = mobil, E = mobil listrik). */
    public const ALLOWED_GARAGE_TYPES = [
        'sepeda' => ['S'],
        'sepeda_motor' => ['S'],
        'mobil' => ['C'],
        'mobil_listrik' => ['C', 'E'],
    ];

    /* ===================== Duration ===================== */

    /**
     * Tanggal mulai sewa baru: selalu hari ini (tidak dipilih pengguna, tidak pernah di masa lalu).
     */
    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    /**
     * Jumlah hari maksimum sewa harian: jumlah hari bulan mulai dikurangi satu
     * (bulan 30 hari → 29, bulan 31 hari → 30, Februari → 27 atau 28).
     */
    public function maxDailyDays(CarbonInterface $start): int
    {
        return CarbonImmutable::parse($start)->daysInMonth - 1;
    }

    /**
     * Lama sewa bulanan dalam hari: jumlah hari pada bulan tanggal mulai.
     */
    public function monthlyLengthInDays(CarbonInterface $start): int
    {
        return CarbonImmutable::parse($start)->daysInMonth;
    }

    /**
     * Tanggal selesai inklusif: mulai + (hari - 1).
     */
    public function endDate(CarbonInterface $start, int $days): CarbonImmutable
    {
        return CarbonImmutable::parse($start)->startOfDay()->addDays($days - 1);
    }

    /**
     * Jumlah hari yang disewa, sekaligus memvalidasi batas sewa harian.
     *
     * @throws ValidationException when a daily rental has no or too many days
     */
    public function rentalDays(CarbonInterface $start, bool $isMonthly, ?int $days): int
    {
        if ($isMonthly) {
            return $this->monthlyLengthInDays($start);
        }

        $max = $this->maxDailyDays($start);

        if ($days === null || $days < 1 || $days > $max) {
            throw ValidationException::withMessages([
                'days' => sprintf('Jumlah hari sewa harian harus 1 sampai %d hari untuk bulan ini.', $max),
            ]);
        }

        return $days;
    }

    /* ===================== Price ===================== */

    /**
     * Harga bulanan dari harga harian.
     */
    public function monthlyPrice(int $dailyPrice): int
    {
        return $dailyPrice * self::MONTHLY_MULTIPLIER;
    }

    /**
     * Bagian biaya sewa: hari x harian, atau 25 x harian untuk bulanan.
     */
    public function rentPrice(int $dailyPrice, bool $isMonthly, int $days): int
    {
        return $isMonthly ? $this->monthlyPrice($dailyPrice) : $dailyPrice * $days;
    }

    /**
     * True bila sewa wajib mengisi baterai hingga penuh
     * (mobil listrik yang diparkir di garasi mobil listrik).
     */
    public function requiresCharging(Vehicle $vehicle, Garage $garage): bool
    {
        return $vehicle->type === 'mobil_listrik' && $garage->garage_type_id === 'E';
    }

    /**
     * Energi yang perlu diisi hingga penuh (kWh):
     * kapasitas baterai (kWh) x (100 - sisa baterai %) / 100.
     *
     * @throws ValidationException bila sisa baterai di luar 0..100 %
     */
    public function chargeEnergy(int $batteryCapacity, ?int $batteryPercent): float
    {
        $this->assertBatteryPercent($batteryPercent);

        return $batteryCapacity * (100 - $batteryPercent) / 100;
    }

    /**
     * Biaya isi listrik hingga penuh, dihitung dari sisa baterai (persen):
     * energi yang diisi x tarif per kWh, dibulatkan ke rupiah terdekat.
     * Contoh: kapasitas 60 kWh, sisa 20 %, tarif Rp2.500
     * → 60 x 80 % = 48 kWh x Rp2.500 = Rp120.000.
     *
     * @throws ValidationException bila sisa baterai di luar 0..100 %
     */
    public function chargePrice(int $batteryCapacity, ?int $batteryPercent, int $pricePerKwh): int
    {
        $this->assertBatteryPercent($batteryPercent);

        return (int) round($batteryCapacity * (100 - $batteryPercent) * $pricePerKwh / 100);
    }

    /**
     * Sisa baterai wajib diisi sebagai persentase bulat 0 sampai 100.
     *
     * @throws ValidationException
     */
    public function assertBatteryPercent(?int $batteryPercent): void
    {
        if ($batteryPercent === null || $batteryPercent < 0 || $batteryPercent > 100) {
            throw ValidationException::withMessages([
                'battery_level' => 'Sisa baterai wajib diisi dalam persen, 0 sampai 100 %.',
            ]);
        }
    }

    /**
     * Biaya tambahan perpanjangan dari $currentEnd sampai $newEnd (khusus admin).
     *
     * Aturannya sama dengan sewa baru, diterapkan per blok: mulai dari hari setelah
     * tanggal selesai saat ini; selama sisa hari mencakup satu "blok bulan" penuh
     * — yang panjangnya sama dengan jumlah hari pada bulan tempat blok itu
     * DIMULAI — kenakan 25 x harga harian lalu lanjut setelah blok tersebut; sisa
     * hari dikenakan harga harian. Tidak ada batas maksimum lama perpanjangan.
     *
     * Contoh (harian Rp10.000): selesai 14 Feb, diperpanjang ke 20 Mar → blok mulai
     * 15 Feb, 28 hari (15 Feb..14 Mar) = Rp250.000, lalu 6 hari = Rp60.000.
     *
     * @return array{days: int, total: int, segments: list<array{from: string, to: string, days: int, monthly: bool, amount: int}>}
     *
     * @throws ValidationException when $newEnd is not after $currentEnd
     */
    public function extensionBreakdown(int $dailyPrice, CarbonInterface $currentEnd, CarbonInterface $newEnd): array
    {
        $current = CarbonImmutable::parse($currentEnd)->startOfDay();
        $target = CarbonImmutable::parse($newEnd)->startOfDay();

        if ($target->lessThanOrEqualTo($current)) {
            throw ValidationException::withMessages([
                'new_end_date' => 'Tanggal selesai baru harus setelah '.$current->format('d/m/Y').'. Sewa tidak dapat diperpendek.',
            ]);
        }

        $segments = [];
        $cursor = $current->addDay();
        $remaining = (int) $current->diffInDays($target);

        while ($remaining >= ($block = $cursor->daysInMonth)) {
            $segments[] = $this->segment($cursor, $block, true, $this->monthlyPrice($dailyPrice));
            $cursor = $cursor->addDays($block);
            $remaining -= $block;
        }

        if ($remaining > 0) {
            $segments[] = $this->segment($cursor, $remaining, false, $dailyPrice * $remaining);
        }

        return [
            'days' => array_sum(array_column($segments, 'days')),
            'total' => array_sum(array_column($segments, 'amount')),
            'segments' => $segments,
        ];
    }

    /* ===================== Compatibility & availability ===================== */

    /**
     * Tipe garasi yang boleh dipakai suatu tipe kendaraan.
     *
     * @return list<string>
     */
    public function allowedGarageTypes(string $vehicleType): array
    {
        return self::ALLOWED_GARAGE_TYPES[$vehicleType] ?? [];
    }

    /**
     * True bila tidak ada transaksi pada garasi yang bertabrakan dengan [from, to]
     * (end_date sudah termasuk perpanjangan).
     */
    public function garageIsFree(string $garageId, CarbonInterface $from, CarbonInterface $to, ?int $exceptTransactionId = null): bool
    {
        return ! $this->overlapping($from, $to, $exceptTransactionId)->where('garage_id', $garageId)->exists();
    }

    /**
     * True bila kendaraan tidak sedang berada di garasi mana pun selama [from, to]
     * (satu kendaraan hanya di satu garasi pada satu waktu).
     */
    public function vehicleIsFree(int $vehicleId, CarbonInterface $from, CarbonInterface $to, ?int $exceptTransactionId = null): bool
    {
        return ! $this->overlapping($from, $to, $exceptTransactionId)->where('vehicle_id', $vehicleId)->exists();
    }

    /**
     * Garasi dengan tipe yang diminta yang kosong hari ini (tanpa transaksi yang bertabrakan).
     *
     * @param  list<string>  $types
     * @return Collection<int, Garage>
     */
    public function availableGarages(array $types): Collection
    {
        $today = $this->startDate()->toDateString();

        return Garage::query()
            ->whereIn('garage_type_id', $types)
            ->whereDoesntHave('transactions', fn ($q) => $q
                ->holdingGarage()
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today))
            ->orderBy('id')
            ->get();
    }

    /* ===================== Rental flow ===================== */

    /**
     * Hitung sewa baru tanpa menyimpan (ditampilkan ke penyewa sebelum disimpan).
     *
     * battery_level adalah sisa baterai dalam persen (0..100); charge_energy adalah
     * energi yang diisi (kWh) untuk ditampilkan di rincian biaya.
     *
     * @return array{start_date: string, end_date: string, days: int, is_monthly: bool, daily_price: int, rent_price: int, charge_price: int, charge_energy: float|null, battery_level: int|null, total: int}
     *
     * @throws ValidationException on any broken rule
     */
    public function quote(Vehicle $vehicle, Garage $garage, bool $isMonthly, ?int $days, ?int $batteryLevel): array
    {
        if (! in_array($garage->garage_type_id, $this->allowedGarageTypes($vehicle->type), true)) {
            throw ValidationException::withMessages([
                'garage_id' => 'Tipe garasi ini tidak sesuai untuk kendaraan tersebut.',
            ]);
        }

        $start = $this->startDate();
        $rentalDays = $this->rentalDays($start, $isMonthly, $days);
        $end = $this->endDate($start, $rentalDays);
        $daily = (int) $garage->rent_price;

        $charging = $this->requiresCharging($vehicle, $garage);
        $charge = $charging
            ? $this->chargePrice((int) $vehicle->battery_capacity, $batteryLevel, (int) $garage->charge_price_kwh)
            : 0;
        $rent = $this->rentPrice($daily, $isMonthly, $rentalDays);

        // Garasi dan kendaraan harus kosong pada setiap hari yang disewa.
        $this->assertGarageFree($garage->id, $start, $end);
        $this->assertVehicleFree($vehicle->id, $start, $end);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $rentalDays,
            'is_monthly' => $isMonthly,
            'daily_price' => $daily,
            'rent_price' => $rent,
            'charge_price' => $charge,
            'charge_energy' => $charging ? round($this->chargeEnergy((int) $vehicle->battery_capacity, $batteryLevel), 2) : null,
            'battery_level' => $charging ? $batteryLevel : null,
            'total' => $rent + $charge,
        ];
    }

    /**
     * Simpan sewa baru beserta bukti pelunasan penyewa (semua atau tidak sama sekali).
     *
     * Baris garasi dan kendaraan dikunci agar dua permintaan tidak dapat memesan
     * garasi (atau kendaraan) yang sama pada hari yang bertabrakan. Garasi
     * ditandai "used" setelah tersimpan.
     *
     * @throws ValidationException on any broken rule or a taken garage/vehicle
     */
    public function create(Vehicle $vehicle, Garage $garage, bool $isMonthly, ?int $days, ?int $batteryLevel, UploadedFile $proof): Transaction
    {
        $path = ProofFile::store($proof, self::PROOF_DIRECTORY);

        try {
            return DB::transaction(function () use ($vehicle, $garage, $isMonthly, $days, $batteryLevel, $path): Transaction {
                /** @var Garage $lockedGarage */
                $lockedGarage = Garage::query()->lockForUpdate()->findOrFail($garage->getKey());
                /** @var Vehicle $lockedVehicle */
                $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->getKey());

                // quote() memeriksa ulang semua aturan (termasuk ketersediaan) di dalam lock.
                $quote = $this->quote($lockedVehicle, $lockedGarage, $isMonthly, $days, $batteryLevel);

                // Sewa baru selalu berstatus pending sampai diverifikasi admin.
                $transaction = Transaction::query()->forceCreate([
                    'garage_id' => $lockedGarage->id,
                    'vehicle_id' => $lockedVehicle->id,
                    'start_date' => $quote['start_date'],
                    'end_date' => $quote['end_date'],
                    'is_monthly' => $isMonthly,
                    'battery_level' => $quote['battery_level'],
                    // Biaya isi listrik diakumulasikan ke total sewa; charge_price_total menyimpan bagiannya.
                    'rent_price_total' => $quote['total'],
                    'charge_price_total' => $quote['charge_price'],
                    'transaction_proof' => $path,
                    'status' => Transaction::STATUS_PENDING,
                ]);

                // Garasi langsung ditahan (used) walau masih menunggu verifikasi admin.
                $lockedGarage->forceFill(['is_used' => true])->save();

                return $transaction;
            });
        } catch (Throwable $e) {
            ProofFile::delete($path);

            throw $e;
        }
    }

    /**
     * Tolak bila garasi sudah terisi kendaraan pada salah satu hari di [from, to].
     *
     * @throws ValidationException
     */
    public function assertGarageFree(string $garageId, CarbonInterface $from, CarbonInterface $to, ?int $exceptTransactionId = null): void
    {
        if (! $this->garageIsFree($garageId, $from, $to, $exceptTransactionId)) {
            throw ValidationException::withMessages([
                'garage_id' => sprintf('Garasi %s sudah terisi pada rentang tanggal tersebut.', $garageId),
            ]);
        }
    }

    /**
     * Tolak bila kendaraan sudah berada di garasi pada salah satu hari di [from, to].
     *
     * @throws ValidationException
     */
    public function assertVehicleFree(int $vehicleId, CarbonInterface $from, CarbonInterface $to, ?int $exceptTransactionId = null): void
    {
        if (! $this->vehicleIsFree($vehicleId, $from, $to, $exceptTransactionId)) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Kendaraan ini sedang berada di garasi lain pada rentang tanggal tersebut.',
            ]);
        }
    }

    /**
     * Transaksi yang rentang [start_date, end_date]-nya bertabrakan dengan [from, to]
     * (hanya yang masih menahan garasi: pending atau approved).
     */
    private function overlapping(CarbonInterface $from, CarbonInterface $to, ?int $exceptTransactionId): Builder
    {
        // Sewa yang ditolak admin tidak lagi menahan garasi maupun kendaraan.
        return Transaction::query()
            ->holdingGarage()
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->when($exceptTransactionId, fn ($q) => $q->whereKeyNot($exceptTransactionId));
    }

    /**
     * Satu blok perpanjangan beserta harganya.
     *
     * @return array{from: string, to: string, days: int, monthly: bool, amount: int}
     */
    private function segment(CarbonImmutable $from, int $days, bool $monthly, int $amount): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $from->addDays($days - 1)->toDateString(),
            'days' => $days,
            'monthly' => $monthly,
            'amount' => $amount,
        ];
    }
}
