<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Garage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Helper bersama untuk feature test API: membuat akun, token JWT, garasi,
 * kendaraan, dan file bukti palsu, serta mengirim request sebagai user tertentu.
 */
trait InteractsWithApi
{
    /** Tanggal "hari ini" tetap untuk semua test (bulan 30 hari → sewa harian maks. 29). */
    protected string $today = '2026-11-21 10:00:00';

    /**
     * Bekukan waktu dan palsukan disk bukti pembayaran sebelum setiap test.
     */
    protected function setUpApi(): void
    {
        $this->travelTo($this->today);
        Storage::fake('local', ['serve' => true]);
    }

    /**
     * Buat akun admin.
     */
    protected function createAdmin(string $email = 'admin@example.com'): User
    {
        return User::factory()->admin()->create(['email' => $email]);
    }

    /**
     * Buat akun penyewa dengan status pelanggan tertentu (default: accepted).
     */
    protected function createTenant(string $email = 'penyewa@example.com', string $status = Customer::STATUS_ACCEPTED): User
    {
        $customer = Customer::factory()->create(['status' => $status]);

        return User::factory()->create([
            'email' => $email,
            'is_admin' => false,
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Token JWT untuk user (tanpa melewati endpoint login).
     */
    protected function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    /**
     * Kirim request JSON sebagai user tertentu (null = tanpa token).
     *
     * Guard di-reset setiap request agar user dari request sebelumnya
     * tidak "menempel" (guard JWT menyimpan user di memori).
     */
    protected function apiAs(?User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->resetAuth();

        $headers = $user ? ['Authorization' => 'Bearer '.$this->tokenFor($user)] : [];

        return $this->json($method, $uri, $data, $headers);
    }

    /**
     * Lupakan user & token JWT dari request sebelumnya. Guard JWT dan service
     * jwt-auth menyimpan token di memori aplikasi, sehingga tanpa ini request
     * berikutnya dalam satu test bisa terbaca sebagai user sebelumnya.
     */
    protected function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
    }

    /**
     * Kirim request multipart (dengan file unggahan) sebagai user tertentu.
     */
    protected function uploadAs(User $user, string $uri, array $data): TestResponse
    {
        $this->resetAuth();

        return $this->post($uri, $data, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->tokenFor($user),
        ]);
    }

    /**
     * Buat transaksi sewa langsung di basis data (default: disetujui, mulai hari ini, 3 hari).
     */
    protected function makeRental(Vehicle $vehicle, Garage $garage, array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'is_monthly' => false,
            'battery_level' => null,
            'rent_price_total' => (int) $garage->rent_price * 3,
            'charge_price_total' => 0,
            'transaction_proof' => 'proofs/rentals/contoh.jpg',
            'status' => Transaction::STATUS_APPROVED,
        ], $overrides));
    }

    /**
     * Terbitkan garasi lewat GarageService (kode S001/C001/E001 sesuai aturan).
     */
    protected function makeGarage(string $type = 'C', int $rentPrice = 50000, ?int $chargePriceKwh = null): Garage
    {
        if ($type === 'E' && $chargePriceKwh === null) {
            $chargePriceKwh = 2500;
        }

        return app(GarageService::class)->createOne($type, $rentPrice, $chargePriceKwh);
    }

    /**
     * Buat kendaraan milik penyewa.
     */
    protected function makeVehicle(User $tenant, string $type = 'mobil', array $overrides = []): Vehicle
    {
        $defaults = [
            'customer_id' => $tenant->customer_id,
            'type' => $type,
            'license_plate' => $type === 'sepeda' ? null : 'L '.random_int(1000, 9999).' AB',
            'brand' => 'Merek',
            'brand_type' => 'Model',
            'battery_capacity' => $type === 'mobil_listrik' ? 60 : null,
        ];

        return Vehicle::query()->create(array_merge($defaults, $overrides));
    }

    /**
     * Gambar bukti pembayaran palsu (PNG asli 1x1, tidak memerlukan ekstensi GD).
     * Ukuran yang dilaporkan dapat diatur untuk menguji batas 5 MB.
     */
    protected function proof(string $name = 'bukti.png', int $kilobytes = 100): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png)->size($kilobytes);
    }
}
