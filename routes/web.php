<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', fn () => view('auth.login'))->name('login');

Route::get('/register', fn () => view('auth.register'))->name('register');

// Portal admin: dasbor (layar pertama setelah masuk) + verifikasi pelanggan + kelola garasi.
Route::get('/admin', fn () => view('admin.dashboard'))->name('admin');
Route::get('/admin/customers', fn () => view('admin.customers'))->name('admin.customers');
Route::get('/admin/garages', fn () => view('admin.garages'))->name('admin.garages');

// Portal pelanggan: tab Garasi (default) + Kendaraan.
Route::get('/customer', fn () => view('customer.home'))->name('customer');
