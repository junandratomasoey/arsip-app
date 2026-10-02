# Deploy SIARPU NT II ke Shared Hosting (cPanel + GitHub)

Target:

| | |
|---|---|
| Subdomain | `https://dokumen.bbwsnt2.com` |
| Folder aplikasi (hasil clone GitHub) | `~/repositories/arsip-app` |
| Document root subdomain | `~/repositories/arsip-app/public` |
| Repo | `github.com/junandratomasoey/arsip-app`, branch `mysql-shared-hosting` |

Alur kerja setelah ini: **ubah di laptop → `git push` → di server `git pull` + `bash deploy.sh`**.

---

## 0. Kebutuhan hosting

- PHP **8.3+** untuk web *dan* CLI, ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, `xml`, `bcmath`
- MySQL 5.7+/8 atau MariaDB 10.3+
- Akses **Terminal** (atau SSH) dan **Cron Jobs** di cPanel
- Menu **Git Version Control** di cPanel

## 1. Di laptop: push branch ke GitHub

```bash
git push -u origin mysql-shared-hosting
```

## 2. Di cPanel: siapkan akses GitHub

**Repo publik:** lewati langkah ini.

**Repo privat** (disarankan untuk aplikasi instansi), pakai *deploy key* sekali saja:

1. cPanel > **Terminal**:
   ```bash
   ssh-keygen -t ed25519 -C "cpanel-dokumen" -f ~/.ssh/github_arsip -N ""
   cat ~/.ssh/github_arsip.pub
   ```
2. Salin isi kunci publik ke GitHub: repo > **Settings > Deploy keys > Add deploy key** (cukup *read-only*).
3. Beri tahu SSH kunci mana yang dipakai untuk GitHub:
   ```bash
   cat >> ~/.ssh/config <<'EOF'
   Host github.com
       HostName github.com
       User git
       IdentityFile ~/.ssh/github_arsip
       IdentitiesOnly yes
   EOF
   chmod 600 ~/.ssh/config
   ssh -T git@github.com     # jawab "yes"; harus muncul "successfully authenticated"
   ```

## 3. Clone repo

cPanel > **Git Version Control** > **Create**:

- Clone URL: `git@github.com:junandratomasoey/arsip-app.git` (privat) atau `https://github.com/junandratomasoey/arsip-app.git` (publik)
- Repository Path: `repositories/arsip-app`
- Repository Name: `arsip-app`

Setelah selesai, buka **Manage** > tab **Basic Information** > pilih branch **`mysql-shared-hosting`** > Update.

(Atau lewat Terminal: `git clone -b mysql-shared-hosting <url> ~/repositories/arsip-app`.)

## 4. Subdomain

cPanel > **Domains** > **Create A New Domain** (atau ubah yang sudah ada):

- Domain: `dokumen.bbwsnt2.com`
- **Uncheck** "Share document root"
- Document Root: `repositories/arsip-app/public`

Lalu cPanel > **SSL/TLS Status** > jalankan **AutoSSL** untuk subdomain ini.
Pastikan versi PHP subdomain = 8.3 di **MultiPHP Manager**.

## 5. Database

cPanel > **MySQL Databases**: buat database, user, beri **ALL PRIVILEGES**.
(Nama di cPanel otomatis berawalan `namauser_`.)

## 6. File `.env`

```bash
cd ~/repositories/arsip-app
cp .env.example .env
nano .env
```

Isi minimal:

```dotenv
APP_NAME="SIARPU NT II"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dokumen.bbwsnt2.com
APP_LOCALE=id
APP_TIMEZONE=Asia/Makassar

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=namauser_siarpu
DB_USERNAME=namauser_siarpu
DB_PASSWORD=isi-password-database

SESSION_SECURE_COOKIE=true
SIARPU_AGENCY_CODE=BBWSNT2
```

`.env` tidak pernah masuk GitHub (sudah di `.gitignore`), jadi aman berisi password.

## 7. Instalasi pertama

```bash
cd ~/repositories/arsip-app
php -v                                # harus 8.3+; jika tidak, lihat catatan PHP CLI di bawah
composer -V || curl -sS https://getcomposer.org/installer | php   # jika composer belum ada
composer install --no-dev --optimize-autoloader   # atau: php composer.phar install ...
php artisan key:generate
bash deploy.sh                        # migrate, permission, cache, storage:link
php artisan db:seed --force           # SEKALI SAJA: master data + akun admin
```

Buka `https://dokumen.bbwsnt2.com/login`, masuk dengan `admin@bbwsnt2.go.id` / `password`,
lalu **segera ganti password** lewat menu Profil.

## 8. Cron (antrean & pembersihan)

cPanel > **Cron Jobs** > Common Settings: *Once Per Minute*, Command:

```
cd /home/NAMAUSER/repositories/arsip-app && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Update berikutnya (rutin)

Di laptop:

```bash
git push
```

Di server, pilih salah satu:

- **Terminal:**
  ```bash
  cd ~/repositories/arsip-app && git pull && bash deploy.sh
  ```
- **cPanel UI:** Git Version Control > Manage > **Pull or Deploy** > *Update from Remote*, lalu *Deploy HEAD Commit* (menjalankan `deploy.sh` lewat `.cpanel.yml`).

`deploy.sh` aman dijalankan berulang: mode maintenance, `composer install`, migrasi, sinkron permission, cache ulang, lalu aplikasi aktif kembali.

Aset CSS/JS (`public/build`) ikut di-commit karena hosting tidak punya Node:
**selalu jalankan `npm run build` di laptop sebelum commit** bila mengubah tampilan.

---

## Catatan & masalah umum

**PHP CLI versinya lama.** Di cPanel, perintah `php` di Terminal bisa berbeda dari PHP web. Pakai path lengkap:
```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy.sh
/opt/cpanel/ea-php83/root/usr/bin/php artisan db:seed --force
```
Untuk cron, ganti `php` dengan path yang sama.

**Error 500 setelah deploy.** Lihat `storage/logs/laravel.log`. Penyebab tersering: `.env` belum lengkap, `APP_KEY` kosong, atau izin folder. Perbaiki izin:
```bash
chmod -R ug+rwX storage bootstrap/cache
```

**Gambar cover tidak tampil.** `public/storage` belum ada. Jalankan `php artisan storage:link`; jika hosting melarang symlink, minta admin hosting mengaktifkannya.

**Upload berkas besar gagal.** `public/.user.ini` menaikkan batas ke 100MB. Jika diabaikan hosting, atur `upload_max_filesize=100M` dan `post_max_size=110M` di **MultiPHP INI Editor**.

**Kuota disk.** Berkas dokumen disimpan di `storage/app/private` (tidak bisa diakses langsung lewat URL). Pantau kuota; bila mulai penuh, pindahkan ke object storage S3-compatible (`composer require league/flysystem-aws-s3-v3`, isi `AWS_*`, set `DOCUMENT_FILES_DISK=s3`).

**Backup.** Aktifkan backup database harian (cPanel > Backup / JetBackup) dan backup folder `storage/app` secara berkala; folder ini **tidak** ada di GitHub.

## Catatan porting dari PostgreSQL

- Titik Lokasi GPS memakai kolom `latitude`/`longitude` desimal (bukan PostGIS); pencarian radius memakai rumus Haversine.
- Pencarian teks memakai `like` (collation `utf8mb4_unicode_ci` tidak peka huruf besar/kecil).
- Fitur OCR ditunda: shared hosting tidak bisa memasang Tesseract.
