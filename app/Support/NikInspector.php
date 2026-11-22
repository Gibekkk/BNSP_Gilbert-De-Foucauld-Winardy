<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pembaca struktur NIK (Nomor Induk Kependudukan, 16 digit) untuk membantu
 * admin memverifikasi pendaftar secara manual. Tidak ada unggahan foto KTP.
 *
 * Struktur NIK:
 *  - digit 1–2   : kode provinsi
 *  - digit 3–4   : kode kabupaten/kota
 *  - digit 5–6   : kode kecamatan
 *  - digit 7–8   : tanggal lahir (perempuan: tanggal + 40)
 *  - digit 9–10  : bulan lahir
 *  - digit 11–12 : dua digit terakhir tahun lahir
 *  - digit 13–16 : nomor urut
 *
 * Hasil pemeriksaan hanya bersifat bantuan; keputusan tetap di tangan admin.
 */
class NikInspector
{
    /** Usia minimum pemilik KTP-el. */
    public const MIN_AGE = 17;

    /** Kode provinsi yang dikenal (Kemendagri). */
    public const PROVINCES = [
        '11' => 'Aceh', '12' => 'Sumatera Utara', '13' => 'Sumatera Barat', '14' => 'Riau', '15' => 'Jambi',
        '16' => 'Sumatera Selatan', '17' => 'Bengkulu', '18' => 'Lampung', '19' => 'Kepulauan Bangka Belitung',
        '21' => 'Kepulauan Riau', '31' => 'DKI Jakarta', '32' => 'Jawa Barat', '33' => 'Jawa Tengah',
        '34' => 'DI Yogyakarta', '35' => 'Jawa Timur', '36' => 'Banten', '51' => 'Bali', '52' => 'Nusa Tenggara Barat',
        '53' => 'Nusa Tenggara Timur', '61' => 'Kalimantan Barat', '62' => 'Kalimantan Tengah',
        '63' => 'Kalimantan Selatan', '64' => 'Kalimantan Timur', '65' => 'Kalimantan Utara', '71' => 'Sulawesi Utara',
        '72' => 'Sulawesi Tengah', '73' => 'Sulawesi Selatan', '74' => 'Sulawesi Tenggara', '75' => 'Gorontalo',
        '76' => 'Sulawesi Barat', '81' => 'Maluku', '82' => 'Maluku Utara', '91' => 'Papua', '92' => 'Papua Barat',
        '93' => 'Papua Selatan', '94' => 'Papua Tengah', '95' => 'Papua Pegunungan', '96' => 'Papua Barat Daya',
    ];

    /**
     * Uraikan NIK dan kumpulkan temuan yang perlu diperhatikan admin.
     *
     * @return array{valid: bool, issues: list<string>, province_code: string|null, province: string|null,
     *               regency_code: string|null, district_code: string|null, birth_date: string|null,
     *               gender: string|null, age: int|null}
     */
    public function inspect(string $nik, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::parse($today ?? today())->startOfDay();
        $result = [
            'valid' => false,
            'issues' => [],
            'province_code' => null,
            'province' => null,
            'regency_code' => null,
            'district_code' => null,
            'birth_date' => null,
            'gender' => null,
            'age' => null,
        ];

        if (preg_match('/^\d{16}$/', $nik) !== 1) {
            $result['issues'][] = 'NIK harus 16 digit angka.';

            return $result;
        }

        $result['province_code'] = substr($nik, 0, 2);
        $result['regency_code'] = substr($nik, 2, 2);
        $result['district_code'] = substr($nik, 4, 2);
        $result['province'] = self::PROVINCES[$result['province_code']] ?? null;

        if ($result['province'] === null) {
            $result['issues'][] = 'Kode provinsi '.$result['province_code'].' tidak dikenal.';
        }

        if ($result['regency_code'] === '00' || $result['district_code'] === '00') {
            $result['issues'][] = 'Kode kabupaten/kota atau kecamatan tidak boleh 00.';
        }

        $this->inspectBirth($nik, $today, $result);

        if (substr($nik, 12, 4) === '0000') {
            $result['issues'][] = 'Nomor urut tidak boleh 0000.';
        }

        $result['valid'] = $result['issues'] === [];

        return $result;
    }

    /**
     * Baca tanggal lahir, jenis kelamin, dan usia dari digit 7–12.
     *
     * @param  array<string, mixed>  $result
     */
    private function inspectBirth(string $nik, CarbonImmutable $today, array &$result): void
    {
        $day = (int) substr($nik, 6, 2);
        $month = (int) substr($nik, 8, 2);
        $yy = (int) substr($nik, 10, 2);

        $female = $day > 40;
        $day = $female ? $day - 40 : $day;

        // Dua digit tahun: bila lebih besar dari tahun berjalan, berarti abad 19xx.
        $year = $yy > (int) $today->format('y') ? 1900 + $yy : 2000 + $yy;

        if (! checkdate($month, $day, $year)) {
            $result['issues'][] = 'Tanggal lahir pada NIK tidak valid (digit 7–12).';

            return;
        }

        $birth = CarbonImmutable::create($year, $month, $day);
        $result['birth_date'] = $birth->toDateString();
        $result['gender'] = $female ? 'Perempuan' : 'Laki-laki';
        $result['age'] = (int) $birth->diffInYears($today);

        if ($result['age'] < self::MIN_AGE) {
            $result['issues'][] = 'Usia '.$result['age'].' tahun, di bawah usia minimum KTP ('.self::MIN_AGE.' tahun).';
        }
    }
}
