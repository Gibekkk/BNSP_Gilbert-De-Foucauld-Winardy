<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtendRentalRequest;
use App\Models\GarageType;
use App\Models\Transaction;
use App\Models\TransactionExtension;
use App\Services\GarageService;
use App\Services\RentalExtensionService;
use App\Support\ProofFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin "Perpanjang Status Garasi": daftar garasi yang sedang disewa hari ini,
 * pratinjau sisa biaya, dan penyimpanan perpanjangan beserta bukti bayar.
 */
class RentalsController extends Controller
{
    /** Jumlah baris maksimum riwayat perpanjangan. */
    private const HISTORY_LIMIT = 100;

    private const VEHICLE_LABELS = [
        'sepeda' => 'Sepeda',
        'sepeda_motor' => 'Sepeda motor',
        'mobil' => 'Mobil',
        'mobil_listrik' => 'Mobil listrik',
    ];

    /**
     * Garasi yang sedang disewa pada hari ini, urut kode garasi.
     */
    public function index(GarageService $garages): JsonResponse
    {
        $garages->syncUsage();

        $rows = Transaction::query()
            ->approved()
            ->activeOn()
            ->with(['garage', 'vehicle.customer', 'extensions' => fn ($q) => $q->orderBy('id')])
            ->orderBy('garage_id')
            ->get()
            ->map(fn (Transaction $t): array => $this->shape($t))
            ->values();

        return response()->json(['data' => $rows, 'today' => today()->toDateString()]);
    }

    /**
     * Riwayat semua perpanjangan sewa (terbaru dulu), termasuk sewa yang sudah selesai.
     */
    public function history(): JsonResponse
    {
        $rows = TransactionExtension::query()
            ->with(['transaction.garage', 'transaction.vehicle.customer'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(function (TransactionExtension $e): array {
                $t = $e->transaction;

                return [
                    'id' => $e->id,
                    'transaction_id' => $t?->id,
                    'garage_id' => $t?->garage_id,
                    'garage_label' => GarageType::labelFor($t?->garage?->garage_type_id),
                    'customer_name' => $t?->vehicle?->customer?->name ?? '-',
                    'vehicle' => trim(($t?->vehicle?->license_plate ?? '').' '.($t?->vehicle?->brand ?? '').' '.($t?->vehicle?->brand_type ?? '')),
                    'current_end_date' => $e->current_end_date->toDateString(),
                    'new_end_date' => $e->new_end_date->toDateString(),
                    'extra_rent' => (int) $e->extra_rent,
                    'transaction_proof_url' => $this->url($e->transaction_proof),
                    'created_at' => $e->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * Pratinjau sisa biaya untuk tanggal selesai baru (tidak menyimpan).
     */
    public function quote(Request $request, Transaction $transaction, RentalExtensionService $service): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'new_end_date' => ['required', 'date_format:Y-m-d'],
        ], [
            'new_end_date.required' => 'Tanggal selesai baru wajib diisi.',
            'new_end_date.date_format' => 'Format tanggal tidak valid (YYYY-MM-DD).',
        ])->validate();

        return response()->json(['data' => $service->quote($transaction, $data['new_end_date'])]);
    }

    /**
     * Simpan perpanjangan: ubah tanggal selesai + unggah bukti bayar.
     */
    public function extend(ExtendRentalRequest $request, Transaction $transaction, RentalExtensionService $service): JsonResponse
    {
        $extension = $service->extend(
            $transaction,
            (string) $request->validated('new_end_date'),
            $request->file('transaction_proof'),
        );

        $transaction->refresh()->load(['garage', 'vehicle.customer', 'extensions' => fn ($q) => $q->orderBy('id')]);

        return response()->json([
            'message' => sprintf(
                'Sewa garasi %s diperpanjang sampai %s. Sisa biaya Rp%s tercatat.',
                $transaction->garage_id,
                $extension->new_end_date->format('d/m/Y'),
                number_format($extension->extra_rent, 0, ',', '.'),
            ),
            'data' => $this->shape($transaction),
        ], 201);
    }

    /**
     * Bentuk JSON satu sewa aktif.
     *
     * @return array<string, mixed>
     */
    private function shape(Transaction $t): array
    {
        $garage = $t->garage;
        $vehicle = $t->vehicle;
        $customer = $vehicle?->customer;

        return [
            'id' => $t->id,
            'garage_id' => $t->garage_id,
            'garage_type' => $garage?->garage_type_id,
            'garage_label' => ($garage ? GarageType::labelFor($garage->garage_type_id) : '-'),
            'daily_price' => (int) $garage?->rent_price,
            'customer' => [
                'id' => $customer?->id,
                'name' => $customer?->name ?? '-',
                'nik' => $customer?->nik,
                'phone' => $customer?->phone,
            ],
            'vehicle' => [
                'type' => $vehicle?->type,
                'type_label' => self::VEHICLE_LABELS[$vehicle?->type] ?? '-',
                'license_plate' => $vehicle?->license_plate,
                'brand' => trim(($vehicle?->brand ?? '').' '.($vehicle?->brand_type ?? '')),
            ],
            'is_monthly' => (bool) $t->is_monthly,
            'start_date' => $t->start_date->toDateString(),
            'end_date' => $t->end_date->toDateString(),
            'rent_price_total' => (int) $t->rent_price_total,
            'charge_price_total' => (int) $t->charge_price_total,
            'transaction_proof_url' => $this->url($t->transaction_proof),
            'extensions' => $t->extensions->map(fn (TransactionExtension $e): array => [
                'id' => $e->id,
                'current_end_date' => $e->current_end_date->toDateString(),
                'new_end_date' => $e->new_end_date->toDateString(),
                'extra_rent' => (int) $e->extra_rent,
                'transaction_proof_url' => $this->url($e->transaction_proof),
                'created_at' => $e->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * URL sementara (bertanda tangan) untuk membuka file bukti.
     */
    private function url(?string $path): ?string
    {
        return ProofFile::url($path);
    }
}
