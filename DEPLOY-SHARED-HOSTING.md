# Deploy SIARPU NT II ke Shared Hosting (cPanel, MySQL)

Branch `mysql-shared-hosting` adalah versi SIARPU yang berjalan di MySQL/MariaDB
tanpa PostGIS, siap untuk shared hosting.

## 1. Kebutuhan hosting

- PHP **8.3** atau lebih baru (composer.json mengunci platform ke PHP 8.3)
- MySQL 5.7+ / 8.x atau MariaDB 10.3+
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, `xml`, `ctype`, `bcmath`
- Akses Terminal/SSH di cPanel (disarankan) dan Cron Jobs

## 2. Persiapan di laptop (sekali setiap ada perubahan)

```bash
composer update          # wajib sekali setelah pindah ke branch ini
npm run build            # hasil public/build ikut di-commit (hosting tidak punya Node)
php artisan test --filter=MysqlSmokeTest   # pastikan semua halaman jalan di MySQL
git push
```

## 3. Struktur folder di hosting

Simpan aplikasi **di luar** `public_html`, hanya isi folder `public` yang terbuka ke web:

```
/home/USER/siarpu/          <- git clone repo di sini
/home/USER/public_html/     <- arahkan document root domain/subdomain ke /home/USER/siarpu/public
```

Cara termudah: di cPanel > Domains, set Document Root subdomain ke `siarpu/public`.
Jika document root tidak bisa diubah, salin isi `public/` ke `public_html/` lalu ubah dua
path di `public_html/index.php` menjadi `__DIR__.'/../siarpu/vendor/autoload.php'` dan
`__DIR__.'/../siarpu/bootstrap/app.php'`.

## 4. Instalasi pertama

```bash
cd ~/siarpu
git clone -b mysql-shared-hosting <url-repo> .
composer install --no-dev --optimize-autoloader
cp .env.example .env      # isi APP_URL, DB_*, APP_ENV=production, APP_DEBUG=false
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # lalu SEGERA ganti password admin seed
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Jika `storage:link` gagal (symlink dimatikan hosting), buat symlink lewat File Manager
atau ganti cover pekerjaan ke disk lain.

## 5. Cron (pengganti queue worker)

cPanel > Cron Jobs, setiap menit:

```
* * * * * cd /home/USER/siarpu && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler (`routes/console.php`) memproses antrean sampai kosong lalu berhenti,
serta membersihkan job gagal dan token API kedaluwarsa.

## 6. Batas unggah

`public/.user.ini` menaikkan batas unggah ke 100MB. Jika hosting mengabaikannya,
atur `upload_max_filesize=100M` dan `post_max_size=110M` lewat MultiPHP INI Editor.
Livewire sendiri sudah diatur menerima s.d. 100MB (`config/livewire.php`,
bisa diubah lewat `LIVEWIRE_MAX_UPLOAD_KB`).

## 7. Penyimpanan berkas dokumen

Default `DOCUMENT_FILES_DISK=local` menyimpan berkas di `storage/app/private`
(tidak bisa diakses langsung dari web; unduhan selalu lewat cek permission).
Kuota disk shared hosting terbatas, jadi rencanakan pindah ke object storage
S3-compatible (Cloudflare R2/Wasabi) begitu arsip membesar:
`composer require league/flysystem-aws-s3-v3`, lalu isi `AWS_*` + `AWS_ENDPOINT`
dan set `DOCUMENT_FILES_DISK=s3`.

## 8. Update rutin

```bash
cd ~/siarpu && git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Catatan porting dari PostgreSQL

- Titik Lokasi GPS kini memakai kolom `latitude`/`longitude` desimal (bukan PostGIS);
  pencarian radius memakai rumus Haversine. API model `WorkLocation` tidak berubah.
- Pencarian teks memakai `like` (collation `utf8mb4_unicode_ci` sudah tidak peka huruf besar/kecil).
- Fitur OCR tetap ditunda: shared hosting tidak bisa memasang Tesseract.
