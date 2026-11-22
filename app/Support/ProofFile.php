<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan gambar bukti pembayaran (bukti pelunasan sewa & bukti bayar perpanjangan).
 *
 * Bukti pembayaran bersifat pribadi, sehingga disimpan di disk "local"
 * (storage/app/private) — bukan di folder publik. Gambar dibuka lewat URL
 * bertanda tangan (signed URL) yang berlaku sementara, dilayani oleh route
 * bawaan Laravel untuk disk local ('serve' => true). Dengan begitu tidak perlu
 * `php artisan storage:link`, dan URL tanpa tanda tangan ditolak (403).
 */
class ProofFile
{
    /** Disk penyimpanan bukti pembayaran. */
    public const DISK = 'local';

    /** Disk lama (sebelum bukti dipindah ke disk privat), hanya untuk dibaca. */
    private const LEGACY_DISK = 'public';

    /** Masa berlaku URL bertanda tangan, dalam menit. */
    public const URL_TTL_MINUTES = 30;

    /**
     * Simpan file unggahan ke folder tertentu dan kembalikan path relatifnya.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * Hapus file bukti (dipakai saat penyimpanan transaksi gagal).
     */
    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * URL sementara untuk menampilkan bukti; null bila file tidak ada.
     * File lama yang masih tersimpan di disk public tetap dapat dibuka.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
        }

        if (Storage::disk(self::LEGACY_DISK)->exists($path)) {
            return Storage::disk(self::LEGACY_DISK)->url($path);
        }

        return null;
    }
}
