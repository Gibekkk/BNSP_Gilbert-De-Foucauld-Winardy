<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tambah/ubah kendaraan penyewa (plat wajib kecuali sepeda; baterai hanya mobil listrik).
 */
class StoreVehicleRequest extends FormRequest
{
    /**
     * Hak akses sudah dijaga oleh middleware route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type');

        return [
            'type' => ['required', Rule::in(['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik'])],
            'brand' => ['required', 'string', 'max:50'],
            'brand_type' => ['required', 'string', 'max:50'],
            // Sepeda tidak memakai plat; tipe lainnya wajib bernomor.
            'license_plate' => $type === 'sepeda' ? [] : ['required', 'string', 'min:2', 'max:15'],
            // Hanya mobil listrik yang memiliki kapasitas baterai (kWh).
            'battery_capacity' => $type === 'mobil_listrik' ? ['required', 'integer', 'min:1', 'max:300'] : [],
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
            'type.required' => 'Tipe kendaraan wajib dipilih.',
            'type.in' => 'Tipe kendaraan tidak dikenal.',
            'brand.required' => 'Merk/merek wajib diisi.',
            'brand.max' => 'Merk maksimal 50 karakter.',
            'brand_type.required' => 'Varian/model wajib diisi.',
            'brand_type.max' => 'Varian maksimal 50 karakter.',
            'license_plate.required' => 'Nomor plat wajib diisi.',
            'license_plate.min' => 'Nomor plat terlalu pendek.',
            'license_plate.max' => 'Nomor plat terlalu panjang (maks. 15 karakter).',
            'battery_capacity.required' => 'Kapasitas baterai wajib diisi untuk mobil listrik.',
            'battery_capacity.integer' => 'Kapasitas baterai harus berupa angka.',
            'battery_capacity.min' => 'Kapasitas baterai minimal 1 kWh.',
            'battery_capacity.max' => 'Kapasitas baterai maksimal 300 kWh.',
        ];
    }
}
