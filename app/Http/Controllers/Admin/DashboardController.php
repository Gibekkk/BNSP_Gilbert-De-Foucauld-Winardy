<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Garage;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Angka ringkasan untuk dasbor admin.
 */
class DashboardController extends Controller
{
    /**
     * Kembalikan jumlah pelanggan per status, total garasi/sewa, dan sepuluh pendaftaran terbaru.
     */
    public function index(GarageService $garages): JsonResponse
    {
        // Pastikan status used mencerminkan sewa yang berjalan hari ini.
        $garages->syncUsage();

        return response()->json([
            'stats' => [
                'customers' => [
                    'total' => Customer::query()->count(),
                    'pending' => Customer::query()->where('status', Customer::STATUS_PENDING)->count(),
                    'accepted' => Customer::query()->where('status', Customer::STATUS_ACCEPTED)->count(),
                    'declined' => Customer::query()->where('status', Customer::STATUS_DECLINED)->count(),
                ],
                'vehicles' => Vehicle::query()->count(),
                'garages' => [
                    'total' => Garage::query()->count(),
                    'used' => Garage::query()->where('is_used', true)->count(),
                    'free' => Garage::query()->where('is_used', false)->count(),
                ],
                'rentals' => [
                    'active_today' => Transaction::query()->activeOn()->count(),
                    'income_this_month' => $this->incomeThisMonth(),
                ],
            ],
            'recent' => $this->latest(10),
        ]);
    }

    /**
     * Pendapatan sewa (termasuk isi listrik) + perpanjangan dari sewa yang dimulai bulan ini
     * (pendapatan dihitung pada start_date sewa, sama seperti laporan).
     */
    private function incomeThisMonth(): int
    {
        $rentals = Transaction::query()
            ->startedBetween(today()->startOfMonth(), today()->endOfMonth())
            ->withSum('extensions', 'extra_rent')
            ->get();

        return (int) $rentals->sum(
            fn (Transaction $t): int => (int) $t->rent_price_total + (int) $t->extensions_sum_extra_rent,
        );
    }

    /**
     * Ambil pendaftaran terbaru beserta email akunnya.
     */
    private function latest(int $limit): Collection
    {
        return Customer::query()
            ->with('user:id,email')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'nik' => $customer->nik,
                'email' => $customer->user?->email ?? '–',
                'status' => $customer->status,
            ]);
    }
}
