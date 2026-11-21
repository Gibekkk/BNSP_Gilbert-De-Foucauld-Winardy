<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pendaftaran penyewa: NIK, nama lengkap, telepon, email, password.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Endpoint publik, semua orang boleh mengirim.
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
        return [
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'phone' => ['required', 'string', 'regex:/^(\+?62|0)8[0-9]{7,12}$/'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            'nik.required' => 'NIK wajib diisi.',
            'nik.regex' => 'NIK harus 16 digit angka.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.regex' => 'Gunakan format nomor Indonesia, contoh 081234567890.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
