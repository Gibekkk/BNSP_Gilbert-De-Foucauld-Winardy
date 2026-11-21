<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStoreGarageRequest;
use App\Http\Requests\StoreGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin garage management: list slots, issue new ones (single or bulk), and
 * re-price an existing slot. All state changes go through GarageService.
 */
class GaragesController extends Controller
{
    private const LABELS = [
        'S' => 'Sepeda',
        'C' => 'Mobil',
        'E' => 'Mobil Listrik',
    ];

    private const PER_PAGE = 25;

    /**
     * Paginated garage list (25 per page, ordered by id), optionally filtered by type.
     */
    public function index(Request $request, GarageService $service): JsonResponse
    {
        // Lepas garasi yang masa sewanya sudah habis sebelum ditampilkan.
        $service->syncUsage();

        $type = trim((string) $request->query('type', ''));

        if ($type !== '') {
            Validator::make(['type' => $type], ['type' => ['required', 'in:S,C,E']])->validate();
        }

        $page = max(1, (int) $request->query('page', 1));

        $scope = fn (Builder $query): Builder => $type !== ''
            ? $query->where('garage_type_id', $type)
            : $query;

        $garages = Garage::query()->with('activeTransaction:id,garage_id,end_date')->when(true, $scope)->orderBy('id')->forPage($page, self::PER_PAGE)->get();

        return response()->json([
            'data' => $garages->map(fn (Garage $g): array => $this->shape($g))->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => Garage::query()->when(true, $scope)->count(),
                'last_page' => (int) ceil(Garage::query()->when(true, $scope)->count() / self::PER_PAGE),
            ],
        ]);
    }

    /**
     * Create a single garage slot.
     */
    public function store(StoreGarageRequest $request, GarageService $service): JsonResponse
    {
        $garage = $service->createOne(
            (string) $request->validated('garage_type_id'),
            (int) $request->validated('rent_price'),
            $request->validated('charge_price_kwh') === null ? null : (int) $request->validated('charge_price_kwh'),
        );

        return response()->json([
            'message' => 'Garasi '.$garage->id.' berhasil dibuat.',
            'data' => $this->shape($garage),
        ], 201);
    }

    /**
     * Create several garage slots in one all-or-nothing operation.
     */
    public function bulk(BulkStoreGarageRequest $request, GarageService $service): JsonResponse
    {
        $garages = $service->createBulk(
            (string) $request->validated('garage_type_id'),
            (int) $request->validated('quantity'),
            (int) $request->validated('rent_price'),
            $request->validated('charge_price_kwh') === null ? null : (int) $request->validated('charge_price_kwh'),
        );

        $first = $garages->first();
        $last = $garages->last();

        return response()->json([
            'message' => sprintf(
                '%d garasi berhasil dibuat (%s s.d. %s).',
                count($garages),
                $first->id,
                $last->id,
            ),
            'data' => [
                'first_id' => $first?->id,
                'last_id' => $last?->id,
                'created' => count($garages),
            ],
        ], 201);
    }

    /**
     * Re-price one garage (rent_price and/or charge_price_kwh only).
     */
    public function update(UpdateGarageRequest $request, Garage $garage, GarageService $service): JsonResponse
    {
        $updated = $service->update(
            $garage,
            (int) $request->validated('rent_price'),
            $request->validated('charge_price_kwh') === null ? null : (int) $request->validated('charge_price_kwh'),
        );

        return response()->json([
            'message' => 'Harga garasi '.$updated->id.' diperbarui.',
            'data' => $this->shape($updated),
        ]);
    }

    /**
     * Public JSON shape of one garage row.
     */
    private function shape(Garage $garage): array
    {
        return [
            'id' => $garage->id,
            'type' => $garage->garage_type_id,
            'label' => self::LABELS[$garage->garage_type_id] ?? 'Tipe '.$garage->garage_type_id,
            'rent_price' => (int) $garage->rent_price,
            'charge_price_kwh' => $garage->charge_price_kwh === null ? null : (int) $garage->charge_price_kwh,
            'is_used' => (bool) $garage->is_used,
            'rented_until' => $garage->relationLoaded('activeTransaction')
                ? $garage->activeTransaction?->end_date?->toDateString()
                : null,
        ];
    }
}
