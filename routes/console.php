<?php

use App\Services\GarageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lepas garasi yang masa sewanya sudah habis (dan tandai yang sedang disewa).
Artisan::command('garages:sync-status', function (GarageService $service) {
    $result = $service->syncUsage();
    $this->info(sprintf('%d garasi dilepas, %d garasi ditandai used.', $result['released'], $result['occupied']));
})->purpose('Sinkronkan status used garasi dengan sewa yang berjalan hari ini');

Schedule::command('garages:sync-status')->dailyAt('00:01');
