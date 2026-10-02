#!/usr/bin/env bash
#
# Skrip deploy SIARPU NT II di shared hosting (cPanel).
# Dijalankan di server dari folder repo, setelah `git pull`:
#
#   cd ~/repositories/arsip-app && bash deploy.sh
#
# Juga dipanggil otomatis oleh .cpanel.yml saat "Deploy HEAD Commit"
# di menu cPanel > Git Version Control.
#
# Aman dijalankan berulang kali. Tidak menjalankan seeder (akun admin
# hanya dibuat sekali saat instalasi pertama, lihat DEPLOY-SHARED-HOSTING.md).

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

# PHP CLI di cPanel sering berbeda versi dengan PHP untuk web. Pakai
# PHP_BIN bila diset, jika tidak cari ea-php83/ea-php84, terakhir `php`.
if [ -z "${PHP_BIN:-}" ]; then
    for candidate in /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/php php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    echo "!! PHP CLI ($PHP_BIN) versinya $("$PHP_BIN" -r 'echo PHP_VERSION;'), butuh 8.3+."
    echo "   Jalankan ulang dengan: PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy.sh"
    exit 1
fi

# Composer: pakai yang terpasang di hosting, atau composer.phar di folder repo.
if command -v composer >/dev/null 2>&1; then
    COMPOSER=("$PHP_BIN" "$(command -v composer)")
elif [ -f "$APP_DIR/composer.phar" ]; then
    COMPOSER=("$PHP_BIN" "$APP_DIR/composer.phar")
else
    echo "!! Composer tidak ditemukan. Unduh sekali dengan:"
    echo "   cd $APP_DIR && curl -sS https://getcomposer.org/installer | $PHP_BIN"
    exit 1
fi

if [ ! -f .env ]; then
    echo "!! File .env belum ada. Salin dari .env.example lalu isi dulu (lihat DEPLOY-SHARED-HOSTING.md)."
    exit 1
fi

echo "==> PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')"

echo "==> Mode maintenance"
"$PHP_BIN" artisan down --retry=15 || true

echo "==> composer install"
"${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Migrasi database"
"$PHP_BIN" artisan migrate --force

echo "==> Sinkron permission (aman diulang)"
"$PHP_BIN" artisan db:seed --class=RolePermissionSeeder --force

if [ ! -e public/storage ]; then
    echo "==> storage:link"
    "$PHP_BIN" artisan storage:link || echo "   (symlink gagal - buat manual, lihat panduan)"
fi

echo "==> Cache konfigurasi, route, view"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "==> Aplikasi aktif kembali"
"$PHP_BIN" artisan up

echo "Selesai: $(git log -1 --pretty='%h %s')"
