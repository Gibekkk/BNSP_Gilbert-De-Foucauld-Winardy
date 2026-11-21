<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input sewa baru (dipakai untuk pratinjau harga dan sebagai dasar
 * StoreRentalRequest). Aturan yang bergantung pada data — batas hari sewa harian
 * bulan mulai, rentang sisa baterai, tipe kendaraan ↔ tipe garasi, ketersediaan —
 * diperiksa di RentalService.
 */
class QuoteRentalRequest extends FormRequest
{
    /**
     * Akses penyewa sudah dijaga oleh middleware route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi bentuk input sewa.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer'],
            'garage_id' => ['required', 'string', 'size:4'],
            'is_monthly' => ['required', 'boolean'],
            'days' => ['exclude_if:is_monthly,1,true', 'required', 'integer', 'min:1', 'max:30'],
            'battery_level' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Pesan validasi berbahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Pilih kendaraan terlebih dahulu.',
            'garage_id.required' => 'Pilih garasi terlebih dahulu.',
            'garage_id.size' => 'Kode garasi tidak valid.',
            'is_monthly.required' => 'Pilih tipe sewa (harian / bulanan).',
            'days.required' => 'Jumlah hari wajib diisi untuk sewa harian.',
            'days.integer' => 'Jumlah hari harus angka bulat.',
            'days.min' => 'Jumlah hari minimal 1.',
            'days.max' => 'Jumlah hari melebihi batas bulan ini.',
            'battery_level.integer' => 'Sisa baterai harus angka bulat (kWh).',
            'battery_level.min' => 'Sisa baterai minimal 0 kWh.',
        ];
    }
}
