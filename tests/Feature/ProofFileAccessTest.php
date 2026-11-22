<?php

namespace Tests\Feature;

use App\Support\ProofFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature test akses gambar bukti pembayaran: tersimpan privat, dibuka lewat
 * URL bertanda tangan sementara, URL tanpa tanda tangan ditolak (403).
 */
class ProofFileAccessTest extends TestCase
{
    private string $root;

    /**
     * Arahkan disk "local" asli ke folder sementara (bukan Storage::fake,
     * agar route URL bertanda tangan bawaan Laravel ikut diuji).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/proofs-'.uniqid());
        config(['filesystems.disks.local.root' => $this->root]);
        Storage::forgetDisk('local');
    }

    /**
     * Hapus folder sementara.
     */
    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /**
     * Gambar dapat dibuka dengan URL bertanda tangan dan ditolak tanpa tanda tangan.
     */
    public function test_signed_url_opens_the_image_and_unsigned_url_is_forbidden(): void
    {
        Storage::disk('local')->put('proofs/rentals/bukti.png', 'isi-gambar');

        $url = ProofFile::url('proofs/rentals/bukti.png');
        $this->assertNotNull($url);
        $this->assertStringContainsString('/private-storage/proofs/rentals/bukti.png', $url);
        $this->assertStringContainsString('signature=', $url);

        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path.'?'.parse_url($url, PHP_URL_QUERY))->assertOk();
        $this->get($path)->assertForbidden();
    }

    /**
     * Path kosong atau file yang tidak ada menghasilkan null.
     */
    public function test_missing_file_has_no_url(): void
    {
        $this->assertNull(ProofFile::url(null));
        $this->assertNull(ProofFile::url(''));
        $this->assertNull(ProofFile::url('proofs/tidak-ada.png'));
    }
}
