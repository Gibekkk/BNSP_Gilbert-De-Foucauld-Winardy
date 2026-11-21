<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGarageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isElectric = $this->input('garage_type_id') === 'E';

        return [
            'garage_type_id' => ['required', Rule::in(['S', 'C', 'E'])],
            'rent_price' => ['required', 'integer', 'min:1', 'max:100000000'],
            // Wajib untuk mobil listrik; harus kosong/null untuk tipe lain.
            'charge_price_kwh' => $isElectric
                ? ['required', 'integer', 'min:1', 'max:10000000']
                : ['nullable', 'prohib:garage_type_id,S,C'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'garage_type_id.required' => 'Tipe garasi wajib dipilih.',
            'garage_type_id.in' => 'Tipe garasi tidak dikenal (gunakan S, C, atau E).',
            'rent_price.required' => 'Harga sewa harian wajib diisi.',
            'rent_price.integer' => 'Harga sewa harus berupa angka bulat.',
            'rent_price.min' => 'Harga sewa minimal Rp 1.',
            'rent_price.max' => 'Harga sewa maksimal Rp 100.000.000.',
            'charge_price_kwh.required' => 'Tarif per kWh wajib diisi untuk mobil listrik.',
            'charge_price_kwh.integer' => 'Tarif per kWh harus berupa angka bulat.',
            'charge_price_kwh.min' => 'Tarif per kWh minimal Rp 1/kWh.',
            'charge_price_kwh.max' => 'Tarif per kWh maksimal Rp 10.000.000/kWh.',
            'charge_price_kwh.prohib' => 'Tarif per kWh hanya berlaku untuk mobil listrik.',
        ];
    }
}
