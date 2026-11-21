<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Garage;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Aggregate numbers for the admin dashboard template.
 */
class DashboardController extends Controller
{
    /**
     * Return counts per status, totals, and the ten newest registrations.
     */
    public function index(): JsonResponse
    {
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
            ],
            'recent' => $this->latest(10),
        ]);
    }

    /**
     * Fetch the newest registrations with their account email.
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
