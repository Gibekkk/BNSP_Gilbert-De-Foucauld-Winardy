<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GaragesController;
use App\Http\Controllers\Admin\GarageTypesController;
use App\Http\Controllers\Admin\RentalsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Tenant\GaragesController as TenantGaragesController;
use App\Http\Controllers\Tenant\VehiclesController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Sengaja di luar auth:api: middleware itu menolak token kedaluwarsa,
// padahal justru token kedaluwarsa (masih dalam refresh_ttl) yang ditukar di sini.
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:api', 'admin'])->group(function () {
    // Template dashboard admin (layar pertama setelah login admin).
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);

    // Verifikasi pendaftaran pelanggan dari tabel DataTables.
    Route::get('/admin/customers', [CustomerController::class, 'index']);
    Route::post('/admin/customers/{id}/approve', [CustomerController::class, 'approve']);
    Route::post('/admin/customers/{id}/decline', [CustomerController::class, 'decline']);

    // Kelola garasi: kapasitas per tipe + daftar/buat/ubah harga tiap slot.
    Route::get('/admin/garage-types', [GarageTypesController::class, 'index']);
    Route::get('/admin/garages', [GaragesController::class, 'index']);
    Route::post('/admin/garages', [GaragesController::class, 'store']);
    Route::post('/admin/garages/bulk', [GaragesController::class, 'bulk']);
    Route::put('/admin/garages/{garage}', [GaragesController::class, 'update']);

    // Perpanjang status garasi: sewa aktif hari ini, pratinjau biaya, simpan + bukti bayar.
    Route::get('/admin/rentals', [RentalsController::class, 'index']);
    Route::get('/admin/rentals/{transaction}/quote', [RentalsController::class, 'quote']);
    Route::post('/admin/rentals/{transaction}/extend', [RentalsController::class, 'extend']);

    // Laporan sewa garasi: ?group=monthly|daily|type|tenant&year=&month=
    Route::get('/admin/reports', [ReportsController::class, 'index']);
});

Route::middleware(['auth:api', 'tenant'])->group(function () {
    // Portal pelanggan: kelola kendaraan + template garasi (stub).
    Route::get('/customer/vehicles', [VehiclesController::class, 'index']);
    Route::post('/customer/vehicles', [VehiclesController::class, 'store']);
    Route::patch('/customer/vehicles/{id}', [VehiclesController::class, 'update']);
    Route::get('/customer/garage-preview', [TenantGaragesController::class, 'preview']);
});
