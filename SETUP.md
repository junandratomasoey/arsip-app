# Setup — Digital Arsip Dokumen Pekerjaan BBWS NT II

Proyek ini di-scaffold dari lingkungan cloud (tanpa akses Packagist), jadi
`vendor/` dan `node_modules/` belum ada — composer/npm install perlu
dijalankan di sisi Anda (Herd). Ikuti urutan ini persis, karena beberapa
langkah bergantung pada langkah sebelumnya.

## 1. Install dependency PHP

```bash
cd arsip-dokumen-pekerjaan
composer install
```

Ini akan menginstal Laravel 11, Livewire, Spatie Permission,
clickbar/laravel-magellan (helper PostGIS), simplesoftwareio/simple-qrcode,
dan laravel/breeze (dev dependency, untuk langkah 3).

## 2. Siapkan .env

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` di `.env` dengan
PostgreSQL Anda.

## 3. Generate scaffolding autentikasi (Breeze + Livewire)

Belum dijalankan karena butuh `vendor/` yang baru terisi di langkah 1:

```bash
php artisan breeze:install livewire
npm install
npm run build
```

Perintah ini akan menambahkan halaman login/register/profile berbasis
Livewire + Tailwind. Jawab prompt Breeze sesuai preferensi (dark mode,
Pest/PHPUnit — pilih PHPUnit karena proyek ini sudah pakai PHPUnit).

## 4. Siapkan database PostgreSQL + PostGIS

```sql
CREATE DATABASE arsip_dokumen_pekerjaan;
```

Ekstensi PostGIS **tidak perlu** diaktifkan manual — migration
`create_work_locations_table` sudah menjalankan
`CREATE EXTENSION IF NOT EXISTS postgis` secara otomatis saat migrate,
asalkan user database Anda punya hak `CREATEEXTENSION`/superuser. Kalau
memakai database terkelola yang membatasi ini, minta admin database
menjalankan `CREATE EXTENSION postgis;` sekali di awal.

## 5. Migrate & seed

```bash
php artisan migrate
php artisan db:seed
```

Seeder akan membuat:

- Struktur organisasi contoh (BBWS NT II → Bidang/Satker → PPK)
- Jenis pekerjaan, fase, status pekerjaan default
- Role & permission sesuai Bab VII dokumen perancangan
- Akun Super Admin: **admin@bbwsnt2.go.id** / **password**
  (ganti password ini setelah login pertama kali)

## 6. Jalankan

Kalau project sudah di `~/Herd/arsip-dokumen-pekerjaan`, Herd otomatis
mendeteksinya sebagai site `arsip-dokumen-pekerjaan.test`. Jalankan queue
& Vite dev server bila diperlukan:

```bash
php artisan queue:listen
npm run dev
```

## Catatan verifikasi

Skema database (migrations) sudah diuji langsung terhadap PostgreSQL 16 +
PostGIS 3.4 sungguhan (bukan cuma dibaca) selama proses pembuatan — self
referencing tree struktur organisasi, primary key UUID, kolom geometry +
index GIST, query radius spasial (`ST_DWithin`), relasi polymorphic tags,
serta rantai dokumen → file → versi, semuanya sudah dicoba dan berjalan
benar. Yang **belum** teruji di lingkungan pembuatan (karena Packagist
diblokir dari sana) adalah integrasi penuh lewat `php artisan migrate`
memakai Laravel asli — jadi setelah `composer install` di langkah 1,
jalankan `php artisan migrate` dan kabari kalau ada error, supaya bisa
langsung diperbaiki.

Satu hal spesifik untuk dicek: kolom `geometry` pada `work_locations`
sengaja ditulis dengan SQL mentah di migration (bukan lewat method
Blueprint dari clickbar/laravel-magellan), karena API persis paket
tersebut belum bisa dipastikan tanpa `vendor/` terpasang. Model
`WorkLocation` juga tidak memakai cast Point dari paket itu — dia punya
helper sendiri (`createFromLatLng()`, `latLng()`, `scopeWithinRadius()`)
yang terbukti bekerja di pengujian. Boleh dipakai apa adanya, atau nanti
disesuaikan ke cast resmi paket setelah terpasang — cek dokumentasi versi
yang benar-benar ter-install di `vendor/clickbar/laravel-magellan/README.md`.
