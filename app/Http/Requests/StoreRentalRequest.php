<?php

namespace App\Http\Requests;

/**
 * Sewa baru oleh penyewa: input sama dengan pratinjau ditambah gambar
 * bukti pelunasan (jpg/jpeg/png/webp, maks. 5 MB).
 */
class StoreRentalRequest extends QuoteRentalRequest
{
    /**
     * Aturan pratinjau + bukti pelunasan wajib.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'transaction_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Pesan pratinjau + pesan bukti pelunasan.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'transaction_proof.required' => 'Bukti pelunasan wajib diunggah.',
            'transaction_proof.uploaded' => 'Bukti pelunasan gagal diunggah (melebihi batas upload server, maks. 5 MB).',
            'transaction_proof.image' => 'Bukti pelunasan harus berupa gambar.',
            'transaction_proof.mimes' => 'Bukti pelunasan harus JPG, PNG, atau WEBP.',
            'transaction_proof.max' => 'Ukuran bukti pelunasan maksimal 5 MB.',
        ];
    }
}
