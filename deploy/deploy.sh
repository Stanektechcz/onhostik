#!/usr/bin/env bash
# deploy.sh — OnHost.cz produkční nasazení
# Spusťte jako www-data nebo s sudo pro reload apache
# Použití: bash deploy.sh [--fresh]   (--fresh: smaže cache tabulky a re-seeduje)

set -euo pipefail

APP_DIR="/var/www/onhost"
PHP="php8.2"
ARTISAN="$PHP $APP_DIR/artisan"

echo "==> [1/9] Pull z repozitáře"
git -C "$APP_DIR" pull --ff-only

echo "==> [2/9] Composer install (prod, no-dev)"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "==> [3/9] Kopírování .env.production → .env (pouze pokud .env neexistuje)"
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.production" "$APP_DIR/.env"
    $ARTISAN key:generate --force
    echo "UPOZORNĚNÍ: .env vytvořen z .env.production — vyplňte přihlašovací údaje!"
fi

echo "==> [4/9] Migrace databáze"
$ARTISAN migrate --force

echo "==> [5/9] Seedování (produktový katalog, role, integrace)"
$ARTISAN db:seed --class=RoleSeeder --force
$ARTISAN db:seed --class=ProductCatalogSeeder --force
$ARTISAN db:seed --class=IntegrationSeeder --force

echo "==> [6/9] Optimalizace (config/route/view cache)"
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN view:cache
$ARTISAN event:cache

echo "==> [7/9] Storage link"
$ARTISAN storage:link || true

echo "==> [8/9] Oprávnění"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> [9/9] Production readiness check"
$ARTISAN onhost:doctor --production
DOCTOR_EXIT=$?
if [ "$DOCTOR_EXIT" -ne 0 ]; then
    echo ""
    echo "VAROVÁNÍ: onhost:doctor --production skončil s chybami."
    echo "         Zkontrolujte výstup výše před spuštěním ostrého provozu."
fi

echo ""
echo "==> Nasazení dokončeno."
echo "    Zkontrolujte: $APP_DIR/storage/logs/laravel.log"
echo "    Queue worker: supervisorctl restart onhost-queue:*"
