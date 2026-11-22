<?php

namespace Tests\Unit;

use App\Support\NikInspector;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit test pembacaan struktur NIK untuk bantuan verifikasi pelanggan.
 */
class NikInspectorTest extends TestCase
{
    private NikInspector $inspector;

    private CarbonImmutable $today;

    /**
     * Siapkan inspector dan tanggal acuan tetap.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new NikInspector;
        $this->today = CarbonImmutable::parse('2026-11-21');
    }

    /**
     * NIK laki-laki yang valid diuraikan menjadi provinsi, tanggal lahir, dan usia.
     */
    public function test_valid_nik_for_a_man_is_decoded(): void
    {
        $result = $this->inspector->inspect('3578010101900001', $this->today);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['issues']);
        $this->assertSame('Jawa Timur', $result['province']);
        $this->assertSame('78', $result['regency_code']);
        $this->assertSame('01', $result['district_code']);
        $this->assertSame('1990-01-01', $result['birth_date']);
        $this->assertSame('Laki-laki', $result['gender']);
        $this->assertSame(36, $result['age']);
    }

    /**
     * Tanggal lahir perempuan ditambah 40 pada NIK.
     */
    public function test_female_birth_day_has_forty_added(): void
    {
        $result = $this->inspector->inspect('3578014202920002', $this->today);

        $this->assertTrue($result['valid']);
        $this->assertSame('1992-02-02', $result['birth_date']);
        $this->assertSame('Perempuan', $result['gender']);
    }

    /**
     * NIK yang bukan 16 digit angka langsung ditolak.
     */
    public function test_nik_must_be_sixteen_digits(): void
    {
        foreach (['35780101019000', '35780101019000012', '35780101019000AB'] as $nik) {
            $result = $this->inspector->inspect($nik, $this->today);
            $this->assertFalse($result['valid'], $nik);
            $this->assertSame(['NIK harus 16 digit angka.'], $result['issues']);
        }
    }

    /**
     * Bulan lahir 88 (tanggal tidak valid) ditandai.
     */
    public function test_invalid_birth_date_is_flagged(): void
    {
        $result = $this->inspector->inspect('3201152988071601', $this->today);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['birth_date']);
        $this->assertContains('Tanggal lahir pada NIK tidak valid (digit 7–12).', $result['issues']);
    }

    /**
     * Kode provinsi yang tidak dikenal ditandai.
     */
    public function test_unknown_province_is_flagged(): void
    {
        $result = $this->inspector->inspect('9901010101900001', $this->today);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['province']);
        $this->assertContains('Kode provinsi 99 tidak dikenal.', $result['issues']);
    }

    /**
     * Pemilik di bawah 17 tahun ditandai.
     */
    public function test_underage_owner_is_flagged(): void
    {
        $result = $this->inspector->inspect('3578010101150001', $this->today);

        $this->assertFalse($result['valid']);
        $this->assertSame(11, $result['age']);
    }

    /**
     * Kode wilayah 00 dan nomor urut 0000 ditandai.
     */
    public function test_zero_region_code_and_zero_serial_are_flagged(): void
    {
        $result = $this->inspector->inspect('3578000101900000', $this->today);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['issues']);
    }
}
