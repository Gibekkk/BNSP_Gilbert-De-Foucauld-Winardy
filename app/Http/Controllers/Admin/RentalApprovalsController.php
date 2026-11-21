<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GarageType;
use App\Models\Transaction;
use App\Services\RentalVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin "Verifikasi sewa": daftar pengajuan sewa baru, lalu setujui atau tolak.
 */
class RentalApprovalsController extends Controller
{
    /** Nama tampilan tipe kendaraan. */
    private const VEHICLE_LABELS = [
        'sepeda' => 'Sepeda',
        'sepeda_motor' => 'Sepeda motor',
        'mobil' => 'Mobil',
        'mobil_listrik' => 'Mobil listrik',
    ];

    /** Jumlah baris maksimum untuk filter selain pending. */
    private const LIMIT = 200;

    /**
     * Daftar sewa per status (default: pending, terlama dulu agar diproses berurutan),
     * beserta jumlah sewa per status untuk lencana di layar.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Transaction::STATUSES)],
        ], [
            'status.in' => 'Filter status tidak dikenal.',
        ]);

        $status = $data['status'] ?? Transaction::STATUS_PENDING;

        $rows = Transaction::query()
            ->where('status', $status)
            ->with(['garage', 'vehicle.customer'])
            ->when(
                $status === Transaction::STATUS_PENDING,
                fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                fn ($q) => $q->orderByDesc('verified_at')->orderByDesc('id')->limit(self::LIMIT),
            )
            ->get()
            ->map(fn (Transaction $t): array => $this->shape($t))
            ->values();

        $counts = Transaction::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status' => $status,
            'counts' => collect(Transaction::STATUSES)->mapWithKeys(fn (string $s): array => [$s => (int) ($counts[$s] ?? 0)]),
            'data' => $rows,
        ]);
    }

    /**
     * Setujui satu sewa pending.
     */
    public function approve(Transaction $transaction, RentalVerificationService $service): JsonResponse
    {
        $approved = $service->approve($transaction);

        return response()->json([
            'message' => 'Sewa garasi '.$approved->garage_id.' disetujui.',
            'data' => ['id' => $approved->id, 'status' => $approved->status],
        ]);
    }

    /**
     * Tolak satu sewa pending (alasan opsional, ditampilkan ke penyewa); garasi dilepas.
     */
    public function decline(Request $request, Transaction $transaction, RentalVerificationService $service): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'reason.max' => 'Alasan penolakan maksimal 255 karakter.',
        ]);

        $declined = $service->decline($transaction, $data['reason'] ?? null);

        return response()->json([
            'message' => 'Sewa garasi '.$declined->garage_id.' ditolak. Garasi kembali tersedia.',
            'data' => ['id' => $declined->id, 'status' => $declined->status],
        ]);
    }

    /**
     * Bentuk JSON satu pengajuan sewa untuk layar verifikasi.
     *
     * @return array<string, mixed>
     */
    private function shape(Transaction $t): array
    {
        $vehicle = $t->vehicle;
        $customer = $vehicle?->customer;

        return [
            'id' => $t->id,
            'status' => $t->status,
            'status_note' => $t->status_note,
            'verified_at' => $t->verified_at?->toIso8601String(),
            'submitted_at' => $t->created_at?->toIso8601String(),
            'garage_id' => $t->garage_id,
            'garage_label' => GarageType::labelFor($t->garage?->garage_type_id),
            'daily_price' => (int) $t->garage?->rent_price,
            'customer' => [
                'name' => $customer?->name ?? '-',
                'nik' => $customer?->nik,
                'phone' => $customer?->phone,
            ],
            'vehicle' => [
                'type_label' => self::VEHICLE_LABELS[$vehicle?->type] ?? '-',
                'license_plate' => $vehicle?->license_plate,
                'brand' => trim(($vehicle?->brand ?? '').' '.($vehicle?->brand_type ?? '')),
                'battery_capacity' => $vehicle?->battery_capacity,
            ],
            'is_monthly' => (bool) $t->is_monthly,
            'start_date' => $t->start_date->toDateString(),
            'end_date' => $t->end_date->toDateString(),
            'battery_level' => $t->battery_level,
            'rent_price_total' => (int) $t->rent_price_total,
            'charge_price_total' => (int) $t->charge_price_total,
            'transaction_proof_url' => $t->transaction_proof ? Storage::disk('public')->url($t->transaction_proof) : null,
        ];
    }
}
