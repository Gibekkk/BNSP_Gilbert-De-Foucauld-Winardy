<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * Admin "Laporan sewa garasi": per bulan, per hari, per tipe garasi, per penyewa.
 */
class ReportsController extends Controller
{
    /**
     * Satu endpoint untuk keempat jenis laporan (lihat ReportRequest).
     */
    public function index(ReportRequest $request, ReportService $reports): JsonResponse
    {
        $year = $request->validated('year') === null ? null : (int) $request->validated('year');
        $month = $request->validated('month') === null ? null : (int) $request->validated('month');
        $group = (string) $request->validated('group');

        $report = match ($group) {
            ReportService::GROUP_MONTHLY => $reports->monthly((int) $year),
            ReportService::GROUP_DAILY => $reports->daily((int) $year, (int) $month),
            ReportService::GROUP_TYPE => $reports->byType($year, $month),
            ReportService::GROUP_TENANT => $reports->byTenant($year, $month),
        };

        return response()->json([
            'group' => $group,
            'years' => $reports->availableYears(),
            'data' => $report,
        ]);
    }
}
