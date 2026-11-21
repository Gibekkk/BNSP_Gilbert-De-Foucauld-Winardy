<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use App\Services\RentalPricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data demo untuk presentasi: garasi tiap tipe, beberapa penyewa beserta
 * kendaraannya, serta riwayat sewa ±14 bulan terakhir (sebagian masih aktif
 * hari ini, sebagian pernah diperpanjang) agar laporan dan fitur perpanjang
 * sewa langsung terisi.
 *
 * Semua data mematuhi aturan bisnis: tipe kendaraan ↔ tipe garasi, sewa
 * harian maks. 30 hari, sewa bulanan berakhir di akhir bulan, satu garasi
 * tidak pernah disewa ganda pada tanggal yang sama. Hasil acak bersifat
 * deterministik (seed tetap) sehingga demo selalu sama.
 */
class DemoRentalSeeder extends Seeder
{
    private const PROOF_PATH = 'proofs/demo-bukti-bayar.png';

    /** PNG 1x1 sebagai contoh bukti bayar. */
    private const PROOF_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function run(GarageService $garages, RentalPricing $pricing): void
    {
        if (Garage::query()->exists()) {
            $this->command?->warn('Garasi sudah ada — DemoRentalSeeder dilewati.');

            return;
        }

        mt_srand(20261121);
        Storage::disk('public')->put(self::PROOF_PATH, base64_decode(self::PROOF_PNG));

        // 1) Garasi: dibuat lewat GarageService agar kode S001/C001/E001 sesuai aturan.
        $garages->createBulk('S', 6, 5000, null);
        $garages->createBulk('C', 6, 50000, null);
        $garages->createBulk('E', 4, 75000, 2500);

        // 2) Penyewa (semua sudah disetujui, satu masih menunggu verifikasi).
        $customers = collect([
            $this->customer('budi@example.com', '3578010101900001', 'Budi Santoso', '081211110001'),
            $this->customer('siti@example.com', '3578014202920002', 'Siti Rahmawati', '081211110002'),
            $this->customer('andi@example.com', '3578010303880003', 'Andi Wijaya', '081211110003'),
        ]);

        $demo = Customer::query()->whereHas('user', fn ($q) => $q->where('email', 'pelanggan@example.com'))->first();
        if ($demo) {
            $customers->prepend($demo);
        }

        $this->customer('dewi@example.com', '3578015505950004', 'Dewi Lestari', '081211110004', Customer::STATUS_PENDING);

        // 3) Setiap garasi punya satu kendaraan "langganan" yang sesuai tipenya,
        //    sehingga satu kendaraan tidak pernah berada di dua garasi sekaligus.
        $today = CarbonImmutable::today();

        Garage::query()->orderBy('id')->get()->each(function (Garage $garage, int $index) use ($customers, $pricing, $today): void {
            $owner = $customers[$index % $customers->count()];
            $vehicle = $this->vehicleFor($garage, $owner, $index);

            $cursor = $today->subDays(420 + mt_rand(0, 20));

            while (true) {
                $start = $cursor->addDays(mt_rand(0, 12));
                if ($start->greaterThan($today)) {
                    break;
                }

                $isMonthly = mt_rand(1, 100) <= 25;
                $days = mt_rand(1, RentalPricing::MAX_DAILY_DAYS);
                $end = $isMonthly ? $pricing->monthlyEndDate($start) : $pricing->dailyEndDate($start, $days);
                $daily = (int) $garage->rent_price;

                $charge = 0;
                $level = null;
                if ($garage->garage_type_id === 'E' && $vehicle->type === 'mobil_listrik') {
                    $level = mt_rand(5, max(5, (int) $vehicle->battery_capacity - 5));
                    $charge = $pricing->chargeTotal((int) $vehicle->battery_capacity, $level, (int) $garage->charge_price_kwh);
                }

                $transaction = Transaction::query()->create([
                    'garage_id' => $garage->id,
                    'vehicle_id' => $vehicle->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'is_monthly' => $isMonthly,
                    'battery_level' => $level,
                    'rent_price_total' => $pricing->rentTotal($daily, $isMonthly, $days),
                    'charge_price_total' => $charge,
                    'transaction_proof' => self::PROOF_PATH,
                ]);

                // ±15% sewa pernah diperpanjang admin.
                if (mt_rand(1, 100) <= 15) {
                    $newEnd = $end->addDays(mt_rand(1, 20));
                    $quote = $pricing->extensionBreakdown($daily, $end, $newEnd);

                    $transaction->extensions()->create([
                        'extra_rent' => $quote['total'],
                        'current_end_date' => $end->toDateString(),
                        'new_end_date' => $newEnd->toDateString(),
                        'transaction_proof' => self::PROOF_PATH,
                    ]);
                    $transaction->forceFill(['end_date' => $newEnd->toDateString()])->save();
                    $end = $newEnd;
                }

                $cursor = $end->addDay();
            }
        });

        // 4) Status used mengikuti sewa yang berjalan hari ini.
        $garages->syncUsage();
    }

    /**
     * Buat penyewa beserta akun login (password: "password").
     */
    private function customer(string $email, string $nik, string $name, string $phone, string $status = Customer::STATUS_ACCEPTED): Customer
    {
        $customer = Customer::query()->create([
            'nik' => $nik,
            'phone' => $phone,
            'name' => $name,
            'status' => $status,
        ]);

        User::query()->create([
            'email' => $email,
            'password' => 'password',
            'is_admin' => false,
            'customer_id' => $customer->id,
        ]);

        return $customer;
    }

    /**
     * Kendaraan yang sesuai tipe garasi: S → sepeda/sepeda motor, C → mobil
     * (dua terakhir mobil listrik, yang juga boleh di garasi mobil), E → mobil listrik.
     */
    private function vehicleFor(Garage $garage, Customer $owner, int $index): Vehicle
    {
        $number = $index + 1;
        $plate = sprintf('L %04d %s', 1000 + $number * 37, ['AB', 'CD', 'EF', 'GH'][$index % 4]);

        $attributes = match (true) {
            $garage->garage_type_id === 'S' && $number % 2 === 0 => ['type' => 'sepeda_motor', 'brand' => 'Honda', 'brand_type' => 'Vario 125', 'license_plate' => $plate],
            $garage->garage_type_id === 'S' => ['type' => 'sepeda', 'brand' => 'Polygon', 'brand_type' => 'Heist 2', 'license_plate' => null],
            $garage->garage_type_id === 'C' && in_array($garage->id, ['C005', 'C006'], true) => ['type' => 'mobil_listrik', 'brand' => 'Wuling', 'brand_type' => 'Air ev', 'license_plate' => $plate, 'battery_capacity' => 27],
            $garage->garage_type_id === 'C' => ['type' => 'mobil', 'brand' => 'Toyota', 'brand_type' => 'Avanza', 'license_plate' => $plate],
            default => ['type' => 'mobil_listrik', 'brand' => 'Hyundai', 'brand_type' => 'Ioniq 5', 'license_plate' => $plate, 'battery_capacity' => 72],
        };

        return Vehicle::query()->create(array_merge(['customer_id' => $owner->id, 'battery_capacity' => null], $attributes));
    }
}
