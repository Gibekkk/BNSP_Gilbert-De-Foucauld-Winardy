<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi perpanjangan sewa oleh admin. Aturan "harus setelah tanggal
 * selesai saat ini" dicek oleh RentalExtensionService karena bergantung
 * pada data transaksi.
 */
class ExtendRentalRequest extends FormRequest
{
    /**
     * Akses admin sudah dijaga oleh middleware route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi (bukti: gambar jpg/jpeg/png/webp, maks. 5 MB).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_end_date' => ['required', 'date_format:Y-m-d'],
            'transaction_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'new_end_date.required' => 'Tanggal selesai baru wajib diisi.',
            'new_end_date.date_format' => 'Format tanggal tidak valid (YYYY-MM-DD).',
            'transaction_proof.required' => 'Bukti bayar wajib diunggah.',
            'transaction_proof.uploaded' => 'Bukti bayar gagal diunggah (melebihi batas upload server, maks. 5 MB).',
            'transaction_proof.image' => 'Bukti bayar harus berupa gambar.',
            'transaction_proof.mimes' => 'Bukti bayar harus JPG, PNG, atau WEBP.',
            'transaction_proof.max' => 'Ukuran bukti bayar maksimal 5 MB.',
        ];
    }
}
