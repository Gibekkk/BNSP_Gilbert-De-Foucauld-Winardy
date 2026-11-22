<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\NikInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint admin untuk meninjau, menyetujui, dan menolak pendaftaran penyewa.
 */
class CustomerController extends Controller
{
    /**
     * Daftar semua pendaftaran pelanggan (terbaru dulu) beserta email akunnya dan
     * hasil pembacaan struktur NIK untuk membantu verifikasi manual.
     */
    public function index(Request $request, NikInspector $inspector): JsonResponse
    {
        $status = $request->query('status');

        if ($status !== null && ! in_array($status, [Customer::STATUS_PENDING, Customer::STATUS_ACCEPTED, Customer::STATUS_DECLINED], true)) {
            return response()->json([
                'message' => 'Filter status tidak dikenal.',
                'errors' => ['status' => ['Filter status tidak dikenal.']],
            ], 422);
        }

        // Jumlah pendaftaran yang pernah ditolak per NIK (petunjuk tambahan untuk admin).
        $declinedByNik = Customer::query()
            ->where('status', Customer::STATUS_DECLINED)
            ->selectRaw('nik, COUNT(*) as total')
            ->groupBy('nik')
            ->pluck('total', 'nik');

        $customers = Customer::query()
            ->with('user:id,email')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'nik' => $customer->nik,
                'phone' => $customer->phone,
                'name' => $customer->name,
                'status' => $customer->status,
                'email' => $customer->user?->email ?? '–',
                'created_at' => $customer->created_at?->toIso8601String() ?? '',
                'nik_check' => $inspector->inspect($customer->nik),
                // Penolakan sebelumnya untuk NIK yang sama (tidak termasuk baris ini sendiri).
                'declined_before' => max(0, (int) ($declinedByNik[$customer->nik] ?? 0)
                    - ($customer->status === Customer::STATUS_DECLINED ? 1 : 0)),
            ]);

        return response()->json(['data' => $customers]);
    }

    /**
     * Setujui pendaftaran pending agar penyewa dapat login.
     */
    public function approve(int $id): JsonResponse
    {
        $customer = $this->findOr404($id);

        if ($customer->status === Customer::STATUS_DECLINED) {
            return response()->json(
                ['message' => 'Penolakan bersifat final dan tidak dapat dibatalkan melalui halaman ini.'],
                422,
            );
        }

        $customer->forceFill(['status' => Customer::STATUS_ACCEPTED])->save();

        return response()->json([
            'message' => 'Calon pelanggan disetujui; akun kini dapat masuk.',
            'data' => ['id' => $customer->id, 'status' => $customer->status],
        ]);
    }

    /**
     * Tolak pendaftaran pending (final — tidak dapat disetujui kembali).
     */
    public function decline(int $id): JsonResponse
    {
        $customer = $this->findOr404($id);

        if ($customer->status !== Customer::STATUS_PENDING) {
            return response()->json(
                ['message' => 'Hanya pendaftaran berstatus pending (menunggu verifikasi) yang dapat ditolak.'],
                422,
            );
        }

        $customer->forceFill(['status' => Customer::STATUS_DECLINED])->save();

        return response()->json([
            'message' => 'Penolakan disimpan secara final. Calon pelanggan tetap dapat mendaftarkan ulang.',
            'data' => ['id' => $customer->id, 'status' => $customer->status],
        ]);
    }

    /**
     * Cari pelanggan berdasarkan id atau hentikan dengan JSON 404.
     */
    private function findOr404(int $id): Customer
    {
        return Customer::query()->findOrFail($id);
    }
}
