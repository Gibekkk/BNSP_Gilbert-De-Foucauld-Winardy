<?php

namespace App\Http\Requests;

use App\Models\Garage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGarageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan tarif mengikuti tipe garasi yang di-edit (diambil dari model terikat).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isElectric = ($this->route('garage') instanceof Garage)
            && $this->route('garage')->garage_type_id === 'E';

        return [
            'rent_price' => ['required', 'integer', 'min:1', 'max:100000000'],
            // S dan C: harus kosong/nihil. E: opsional saat update (jika diisi, tetap valid).
            'charge_price_kwh' => $isElectric
                ? ['nullable', 'integer', 'min:1', 'max:10000000']
                : ['nullable', 'prohib:garage_type_id,S,C'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rent_price.required' => 'Harga sewa harian wajib diisi.',
            'rent_price.integer' => 'Harga sewa harus berupa angka bulat.',
            'rent_price.min' => 'Harga sewa minimal Rp 1.',
            'rent_price.max' => 'Harga sewa maksimal Rp 100.000.000.',
            'charge_price_kwh.integer' => 'Tarif per kWh harus berupa angka bulat.',
            'charge_price_kwh.min' => 'Tarif per kWh minimal Rp 1/kWh.',
            'charge_price_kwh.max' => 'Tarif per kWh maksimal Rp 10.000.000/kWh.',
            'charge_price_kwh.prohib' => 'Tarif per kWh hanya berlaku untuk mobil listrik.',
        ];
    }
}
