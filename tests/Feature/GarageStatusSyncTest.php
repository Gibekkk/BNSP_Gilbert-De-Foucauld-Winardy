<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\GarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApi;
use Tests\TestCase;

/**
 * Feature test sinkronisasi status used garasi dengan sewa yang berjalan hari ini.
 */
class GarageStatusSyncTest extends TestCase
{
    use InteractsWithApi, RefreshDatabase;

    /**
     * Bekukan waktu dan palsukan disk sebelum setiap test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApi();
    }

    /**
     * Garasi dilepas setelah sewa selesai/ditolak, dan ditandai used selama sewa berjalan atau pending.
     */
    public function test_sync_releases_finished_and_declined_and_marks_running(): void
    {
        $tenant = $this->createTenant();
        $finished = $this->makeGarage('C');
        $declined = $this->makeGarage('C');
        $running = $this->makeGarage('C');
        $pending = $this->makeGarage('C');
        foreach ([$finished, $declined] as $g) {
            $g->forceFill(['is_used' => true])->save();
        }

        $this->makeRental($this->makeVehicle($tenant), $finished, ['start_date' => '2026-11-01', 'end_date' => '2026-11-20']);
        $this->makeRental($this->makeVehicle($tenant), $declined, ['status' => Transaction::STATUS_DECLINED]);
        $this->makeRental($this->makeVehicle($tenant), $running);
        $this->makeRental($this->makeVehicle($tenant), $pending, ['status' => Transaction::STATUS_PENDING]);

        $result = app(GarageService::class)->syncUsage();

        $this->assertSame(['released' => 2, 'occupied' => 2], $result);
        $this->assertFalse($finished->fresh()->is_used);
        $this->assertFalse($declined->fresh()->is_used);
        $this->assertTrue($running->fresh()->is_used);
        $this->assertTrue($pending->fresh()->is_used);
    }

    /**
     * Perintah artisan terjadwal menjalankan sinkronisasi yang sama.
     */
    public function test_artisan_command_runs_the_sync(): void
    {
        $garage = $this->makeGarage('C');
        $garage->forceFill(['is_used' => true])->save();

        $this->artisan('garages:sync-status')->assertSuccessful();

        $this->assertFalse($garage->fresh()->is_used);
    }
}
