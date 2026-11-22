# Aplikasi Penyewaan Garasi

Aplikasi web untuk mengelola penyewaan slot garasi dan seluruh transaksi sewanya. Tersedia dua portal berbasis peran — **pelanggan (penyewa)** dan **admin**. Web ini dibuat dengan tujuan menyelesaikan uji praktik LSP atau BNSP untuk microcredential syarat kelulusan.

## Fitur Utama

### Portal Pelanggan
- Pendaftaran menggunakan NIK, ponsel, dan email (perlu verifikasi admin menggunakan NIK)
- Kelola kendaraan pribadi: tambah dan ubah data
- Jelajahi garasi tersedia serta pratinjau biaya sewa
- Ajukan sewa baru dan pantau riwayat beserta status sewaan

### Portal Admin
- Dasbor berisi indikator kondisi garasi dan transaksi terkini
- Verifikasi pendaftaran pelanggan: terima atau tolak
- Kelola garasi: tipe, kapasitas per tipe, dan penetapan harga slot (termasuk pengisian massal)
- Verifikasi pengajuan sewa baru: setujui atau tolak
- Perluasan sewa aktif dengan pratinjau biaya dan unggah bukti pembayaran (gambar, maksimal 5 MB)
- Laporan sewa yang dapat dikelompokkan per bulan, per hari, per tipe garasi, atau per penyewa, lengkap dengan filter tahun/bulan

## Teknologi

| Component | Choice |
| --- | --- |
| Framework | Laravel 13 (PHP 8.3+) |
| Auth | JWT (tymon/jwt-auth) + refresh token |
| Basis data | MySQL / MariaDB |
| Frontend | Blade view per modul, CSS vanilla |
| Build asset | Vite |
| Testing | PHPUnit (unit + feature), environment SQLite in-memory |

## Prasyarat

- PHP 8.3+ dengan ekstensi `mbstring`, `pdo_mysql`, `openssl`, `xml` atau dapat menggunakan flake Nix yang terlampir (`nix develop`)
- Composer 2.x
- Node.js 20+
- MySQL atau MariaDB berjalan di `127.0.0.1:3306` (atau sesuaikan)

## Instalasi

```bash
git clone <url-repo> && cd <nama-repo>

composer install

cp .env.example .env

php artisan key:generate
php artisan jwt:secret          # menulis JWT_SECRET ke .env

php artisan migrate --seed      # skema tabel + akun demo

npm install --ignore-scripts
npm run build

php artisan serve                 # menjalankan server lokal, Vite, dan listener log/antrian
```

Web dapat diakses di `http://localhost:8000`.

## Akun Demo (hasil seeder)

Kedua akun menggunakan kata sandi `password`:

| Email | Peran |
| --- | --- |
| `admin@example.com` | Admin |
| `pelanggan@example.com` | Penyewa contoh yang sudah diapprove akunnya |

Untuk memulai ulang kondisi bersih:

```bash
php artisan migrate:fresh --seed
```

## Menjalankan Uji (Test)

```bash
composer test                   # setara php artisan config:clear && php artisan test
# atau
php artisan test                # unit + feature
```

- `tests/Unit` untuk logika bisnis pada model dan service (contoh: perhitungan harga dan perpanjangan sewa).
- `tests/Feature` untuk alur end-to-end per fitur: autentikasi, verifikasi pelanggan, kelola garasi, persetujuan sewa, perpanjangan sewa, akses berkas bukti bayar, laporan.

Lingkungan uji memakai SQLite in-memory yang dapat diubah pada konfigurasi `phpunit.xml`.