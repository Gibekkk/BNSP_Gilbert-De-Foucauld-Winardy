<?php

namespace App\Http\Requests;

class BulkStoreGarageRequest extends StoreGarageRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // integer saja: float atau angka decimal (mis. 2.5) ditolak aturan ini
        // maupun regex-nya; kapasitas riil dicek ulang di GarageService.
        $rules['quantity'] = ['required', 'integer', 'min:1', 'max:999', 'regex:/^\d+$/'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'quantity.required' => 'Jumlah garasi wajib diisi.',
            'quantity.integer' => 'Jumlah garasi harus berupa angka bulat.',
            'quantity.min' => 'Buat minimal 1 garasi.',
            'quantity.max' => 'Jumlah maksimal 999 per tipe (kapasitas total S001–S999).',
            'quantity.regex' => 'Jumlah garasi harus angka bulat, desimal/float tidak diizinkan.',
        ];
    }
}
