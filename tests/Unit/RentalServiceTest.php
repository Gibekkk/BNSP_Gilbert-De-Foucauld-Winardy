<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\Vehicle;
use App\Services\RentalService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit test aturan sewa di RentalService (durasi, harga, isi listrik,
 * perpanjangan, dan kecocokan tipe kendaraan ↔ tipe garasi).
 * Tidak menyentuh basis data.
 */
class RentalServiceTest extends TestCase
{
    private RentalService $rentals;

    /**
     * Siapkan service yang diuji.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->rentals = new RentalService;
    }

    /**
     * Tanggal mulai sewa selalu hari ini.
     */
    public function test_start_date_is_always_today(): void
    {
        $this->travelTo('2026-11-21 15:30:00');

        $this->assertSame('2026-11-21', $this->rentals->startDate()->toDateString());
    }

    /**
     * Data: tanggal mulai → batas hari sewa harian (jumlah hari bulan - 1).
     *
     * @return array<string, array{string, int}>
     */
    public static function maxDailyDaysProvider(): array
    {
        return [
            'bulan 30 hari (November)' => ['2026-11-21', 29],
            'bulan 31 hari (Mei)' => ['2026-05-15', 30],
            'Februari biasa' => ['2026-02-15', 27],
            'Februari kabisat' => ['2028-02-10', 28],
        ];
    }

    /**
     * Sewa harian maksimal (jumlah hari bulan mulai - 1).
     */
    #[DataProvider('maxDailyDaysProvider')]
    public function test_max_daily_days_follows_the_start_month(string $start, int $expected): void
    {
        $this->assertSame($expected, $this->rentals->maxDailyDays(CarbonImmutable::parse($start)));
    }

    /**
     * Data: tanggal mulai → lama & tanggal selesai sewa bulanan.
     *
     * @return array<string, array{string, int, string}>
     */
    public static function monthlyProvider(): array
    {
        return [
            '15 Februari (28 hari)' => ['2026-02-15', 28, '2026-03-14'],
            '27 Februari (28 hari)' => ['2026-02-27', 28, '2026-03-26'],
            'Februari kabisat (29 hari)' => ['2028-02-15', 29, '2028-03-14'],
            '15 Mei (31 hari)' => ['2026-05-15', 31, '2026-06-14'],
            '21 November (30 hari)' => ['2026-11-21', 30, '2026-12-20'],
        ];
    }

    /**
     * Sewa bulanan sepanjang jumlah hari bulan MULAI, tanggal inklusif.
     */
    #[DataProvider('monthlyProvider')]
    public function test_monthly_length_and_end_date(string $start, int $days, string $end): void
    {
        $startDate = CarbonImmutable::parse($start);

        $this->assertSame($days, $this->rentals->monthlyLengthInDays($startDate));
        $this->assertSame($days, $this->rentals->rentalDays($startDate, true, null));
        $this->assertSame($end, $this->rentals->endDate($startDate, $days)->toDateString());
    }

    /**
     * Tanggal inklusif: sewa 1 hari selesai di hari yang sama.
     */
    public function test_end_date_is_inclusive(): void
    {
        $start = CarbonImmutable::parse('2026-11-21');

        $this->assertSame('2026-11-21', $this->rentals->endDate($start, 1)->toDateString());
        $this->assertSame('2026-11-23', $this->rentals->endDate($start, 3)->toDateString());
    }

    /**
     * Jumlah hari sewa harian di dalam batas diterima apa adanya.
     */
    public function test_rental_days_accepts_valid_daily_days(): void
    {
        $start = CarbonImmutable::parse('2026-11-21');

        $this->assertSame(1, $this->rentals->rentalDays($start, false, 1));
        $this->assertSame(29, $this->rentals->rentalDays($start, false, 29));
    }

    /**
     * Data: jumlah hari sewa harian yang tidak valid untuk November.
     *
     * @return array<string, array{int|null}>
     */
    public static function invalidDaysProvider(): array
    {
        return ['kosong' => [null], 'nol' => [0], 'negatif' => [-1], 'melebihi batas (30)' => [30]];
    }

    /**
     * Jumlah hari di luar batas ditolak.
     */
    #[DataProvider('invalidDaysProvider')]
    public function test_rental_days_rejects_invalid_daily_days(?int $days): void
    {
        $this->expectException(ValidationException::class);

        $this->rentals->rentalDays(CarbonImmutable::parse('2026-11-21'), false, $days);
    }

    /**
     * Harga sewa: harian = hari x harga harian; bulanan = 25 x harga harian.
     */
    public function test_rent_price_rules(): void
    {
        $this->assertSame(250000, $this->rentals->monthlyPrice(10000));
        $this->assertSame(30000, $this->rentals->rentPrice(10000, false, 3));
        $this->assertSame(250000, $this->rentals->rentPrice(10000, true, 30));
    }

    /**
     * Data: sisa baterai (%) → energi (kWh) dan biaya, kapasitas 60 kWh, tarif Rp2.500.
     *
     * @return array<string, array{int, float, int}>
     */
    public static function chargeProvider(): array
    {
        return [
            'sisa 20%' => [20, 48.0, 120000],
            'kosong (0%)' => [0, 60.0, 150000],
            'penuh (100%)' => [100, 0.0, 0],
            'sisa 33% (pecahan kWh)' => [33, 40.2, 100500],
        ];
    }

    /**
     * Biaya isi listrik dihitung dari sisa baterai dalam persen.
     */
    #[DataProvider('chargeProvider')]
    public function test_charge_is_computed_from_the_remaining_percentage(int $percent, float $energy, int $price): void
    {
        $this->assertEqualsWithDelta($energy, $this->rentals->chargeEnergy(60, $percent), 0.001);
        $this->assertSame($price, $this->rentals->chargePrice(60, $percent, 2500));
    }

    /**
     * Data: sisa baterai yang tidak valid.
     *
     * @return array<string, array{int|null}>
     */
    public static function invalidPercentProvider(): array
    {
        return ['kosong' => [null], 'negatif' => [-1], 'lebih dari 100' => [101]];
    }

    /**
     * Sisa baterai wajib 0..100 %.
     */
    #[DataProvider('invalidPercentProvider')]
    public function test_charge_rejects_invalid_percentage(?int $percent): void
    {
        $this->expectException(ValidationException::class);

        $this->rentals->chargePrice(60, $percent, 2500);
    }

    /**
     * Isi listrik hanya untuk mobil listrik di garasi mobil listrik (E).
     */
    public function test_requires_charging_only_for_electric_car_in_electric_garage(): void
    {
        $ev = new Vehicle(['type' => 'mobil_listrik']);
        $car = new Vehicle(['type' => 'mobil']);
        $garageE = new Garage(['garage_type_id' => 'E']);
        $garageC = new Garage(['garage_type_id' => 'C']);

        $this->assertTrue($this->rentals->requiresCharging($ev, $garageE));
        $this->assertFalse($this->rentals->requiresCharging($ev, $garageC));
        $this->assertFalse($this->rentals->requiresCharging($car, $garageC));
    }

    /**
     * Tipe garasi yang diizinkan per tipe kendaraan.
     */
    public function test_allowed_garage_types_per_vehicle_type(): void
    {
        $this->assertSame(['S'], $this->rentals->allowedGarageTypes('sepeda'));
        $this->assertSame(['S'], $this->rentals->allowedGarageTypes('sepeda_motor'));
        $this->assertSame(['C'], $this->rentals->allowedGarageTypes('mobil'));
        $this->assertSame(['C', 'E'], $this->rentals->allowedGarageTypes('mobil_listrik'));
        $this->assertSame([], $this->rentals->allowedGarageTypes('truk'));
    }

    /**
     * Perpanjangan: blok bulan (panjang = jumlah hari bulan tempat blok dimulai)
     * seharga 25 x harian, sisa hari dengan harga harian.
     */
    public function test_extension_uses_month_blocks_then_daily_days(): void
    {
        $result = $this->rentals->extensionBreakdown(
            10000,
            CarbonImmutable::parse('2026-02-14'),
            CarbonImmutable::parse('2026-03-20'),
        );

        $this->assertSame(34, $result['days']);
        $this->assertSame(310000, $result['total']);
        $this->assertCount(2, $result['segments']);
        $this->assertSame(['from' => '2026-02-15', 'to' => '2026-03-14', 'days' => 28, 'monthly' => true, 'amount' => 250000], $result['segments'][0]);
        $this->assertSame(['from' => '2026-03-15', 'to' => '2026-03-20', 'days' => 6, 'monthly' => false, 'amount' => 60000], $result['segments'][1]);
    }

    /**
     * Perpanjangan pendek hanya dihitung harian.
     */
    public function test_short_extension_is_charged_daily(): void
    {
        $result = $this->rentals->extensionBreakdown(10000, CarbonImmutable::parse('2026-11-23'), CarbonImmutable::parse('2026-11-28'));

        $this->assertSame(5, $result['days']);
        $this->assertSame(50000, $result['total']);
        $this->assertFalse($result['segments'][0]['monthly']);
    }

    /**
     * Tidak ada batas maksimum perpanjangan (beberapa blok bulan berturut-turut).
     */
    public function test_long_extension_has_no_maximum(): void
    {
        // 1 Jan s.d. 31 Mar: blok Jan (31), blok Feb (28), blok Mar (31) = 3 x 25 x harian.
        $result = $this->rentals->extensionBreakdown(10000, CarbonImmutable::parse('2026-12-31'), CarbonImmutable::parse('2027-03-31'));

        $this->assertSame(90, $result['days']);
        $this->assertSame(750000, $result['total']);
        $this->assertSame([31, 28, 31], array_column($result['segments'], 'days'));
    }

    /**
     * Sewa tidak dapat diperpendek atau "diperpanjang" ke tanggal yang sama.
     */
    public function test_extension_cannot_shorten_or_keep_the_same_end_date(): void
    {
        $this->expectException(ValidationException::class);

        $this->rentals->extensionBreakdown(10000, CarbonImmutable::parse('2026-11-23'), CarbonImmutable::parse('2026-11-23'));
    }
}
