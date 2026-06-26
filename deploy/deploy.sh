#!/usr/bin/env bash
# deploy.sh — OnHost.cz produkční nasazení na s2.onhost.cz
# ─────────────────────────────────────────────────────────
# Použití:
#   bash deploy.sh            — standardní deploy (migrate, cache, reload)
#   bash deploy.sh --fresh    — první deploy / full reset (migrate:fresh + seed)
#   bash deploy.sh --check    — pouze doctor check, bez deploy
#
# Předpoklady:
#   - PHP 8.2 dostupné jako "php8.2" nebo "php"
#   - Composer dostupný globálně
#   - .env vyplněný na serveru (zkopírujte .env.production → .env)
#   - MySQL databáze dostupná
#   - www-data má přístup do APP_DIR
#
# Server: s2.onhost.cz
# Document root: /var/www/clients/client0/web1/web/public (ISPConfig)
#                nebo /var/www/onhost/public

set -euo pipefail

# ── Konfigurace ──────────────────────────────────────────────────────────────
# UPRAVTE APP_DIR podle ISPConfig web root!
# ISPConfig: /var/www/clients/clientN/webN/web
# nebo vlastní: /var/www/onhost
APP_DIR="${ONHOST_APP_DIR:-/var/www/onhost}"
PHP="${ONHOST_PHP:-php8.2}"
ARTISAN="$PHP $APP_DIR/artisan"
FRESH="${1:-}"

# ── Kontrola prostředí ───────────────────────────────────────────────────────
echo "==> OnHost.cz deploy — $(date '+%Y-%m-%d %H:%M:%S')"
echo "    APP_DIR: $APP_DIR"
echo "    PHP:     $($PHP --version | head -1)"

if [ ! -f "$APP_DIR/.env" ]; then
    if [ -f "$APP_DIR/.env.production" ]; then
        echo "==> Kopíruji .env.production → .env"
        cp "$APP_DIR/.env.production" "$APP_DIR/.env"
        echo "    POZOR: Zkontrolujte .env — zejm. APP_KEY musí být vyplněn!"
    else
        echo "CHYBA: Chybí .env na serveru. Zkopírujte .env.production → .env a vyplňte APP_KEY."
        exit 1
    fi
fi

# ── [1] Git pull ─────────────────────────────────────────────────────────────
echo ""
echo "==> [1/10] Git pull"
git -C "$APP_DIR" pull --ff-only

# ── [2] Composer install ─────────────────────────────────────────────────────
echo ""
echo "==> [2/10] Composer install (--no-dev)"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

# ── [3] Artisan key (pouze pokud chybí) ──────────────────────────────────────
if grep -q "^APP_KEY=$\|^APP_KEY=\"\"" "$APP_DIR/.env" 2>/dev/null; then
    echo ""
    echo "==> [3/10] Generuji APP_KEY"
    $ARTISAN key:generate --force
else
    echo ""
    echo "==> [3/10] APP_KEY already set — skip"
fi

# ── [4] Migrace ───────────────────────────────────────────────────────────────
echo ""
if [ "$FRESH" = "--fresh" ]; then
    echo "==> [4/10] migrate:fresh --seed (FRESH MODE — smaže všechna data!)"
    $ARTISAN migrate:fresh --force
    $ARTISAN db:seed --class=RoleSeeder --force
    $ARTISAN db:seed --class=ProductCatalogSeeder --force
    $ARTISAN db:seed --class=MockServerSeeder --force
    $ARTISAN db:seed --class=IntegrationSeeder --force
    $ARTISAN db:seed --class=AiPromptTemplateSeeder --force
else
    echo "==> [4/10] migrate --force"
    $ARTISAN migrate --force
    # Seedujeme pouze nové položky — safe idempotentní seedery
    $ARTISAN db:seed --class=RoleSeeder --force
    $ARTISAN db:seed --class=IntegrationSeeder --force
fi

# ── [5] Storage link ─────────────────────────────────────────────────────────
echo ""
echo "==> [5/10] Storage link"
$ARTISAN storage:link 2>/dev/null || echo "    (storage link already exists)"

# ── [6] Oprávnění ─────────────────────────────────────────────────────────────
echo ""
echo "==> [6/10] Oprávnění storage + bootstrap/cache"
chown -R www-data:www-data \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache" 2>/dev/null || true
chmod -R 775 \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

# ── [7] Cache ─────────────────────────────────────────────────────────────────
echo ""
echo "==> [7/10] Artisan cache"
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN view:cache
$ARTISAN event:cache
echo "    Cache vytvořen."

# ── [8] Queue worker reload ──────────────────────────────────────────────────
echo ""
echo "==> [8/10] Supervisor reload queue worker"
if command -v supervisorctl &>/dev/null; then
    supervisorctl reread   2>/dev/null || true
    supervisorctl update   2>/dev/null || true
    supervisorctl restart  "onhost-queue:*" 2>/dev/null && echo "    Queue worker restarted." \
        || echo "    UPOZORNĚNÍ: Queue worker nebyl restartován — spusťte ručně."
else
    echo "    Supervisor nedostupný — restartujte queue worker ručně."
fi

# ── [9] Apache reload ────────────────────────────────────────────────────────
echo ""
echo "==> [9/10] Apache reload"
if command -v apache2ctl &>/dev/null; then
    apache2ctl configtest 2>&1 | grep -E "Syntax|Error" || true
    systemctl reload apache2 && echo "    Apache reloaded." \
        || echo "    VAROVÁNÍ: Apache reload selhal."
elif command -v apachectl &>/dev/null; then
    apachectl graceful && echo "    Apache graceful reload." || true
else
    echo "    Apache příkaz nenalezen — reload přeskočen."
fi

# ── [10] Production check ─────────────────────────────────────────────────────
echo ""
echo "==> [10/10] onhost:doctor --production"
$ARTISAN onhost:doctor --production 2>&1
DOCTOR_EXIT=${PIPESTATUS[0]:-$?}

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  OnHost.cz deploy dokončen — $(date '+%Y-%m-%d %H:%M:%S')"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "  URL:        https://onhost.cz"
echo "  Admin:      https://onhost.cz/admin"
echo "  Queue log:  $APP_DIR/storage/logs/laravel.log"
echo ""
echo "  Ověřte:"
echo "  1. https://onhost.cz                 → public web"
echo "  2. https://onhost.cz/admin           → admin panel"
echo "  3. https://onhost.cz/panel           → zákazník panel"
echo "  4. supervisorctl status              → queue worker"
echo "  5. php artisan schedule:list         → cron jobs"
echo "  6. php artisan queue:failed          → failed jobs"
echo ""
if [ "${DOCTOR_EXIT:-0}" -ne 0 ]; then
    echo "  !! Doctor hlásí varování — zkontrolujte výstup výše."
fi
