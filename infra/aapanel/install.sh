#!/usr/bin/env bash
# ONhost control plane — first installation on an aaPanel host (docs/runbooks/deploy-aapanel.md).
# Run once as root after aaPanel has PHP 8.3, PostgreSQL, Redis and the site (staging.onhost.cz by default) created; every later release
# goes through deploy.sh. Idempotent: re-running repairs permissions, units and caches without touching data.
set -euo pipefail

SITE="${SITE:-staging.onhost.cz}"                     # the aaPanel site (staging.onhost.cz for testing, onhost.cz for production)
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"           # aaPanel site root (the repository checkout; nginx serves $APP_DIR/public)
REPO="${REPO:-https://github.com/Stanektechcz/onhostik.git}"
BRANCH="${BRANCH:-development}"
PHP="${PHP:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
RUN_USER="${RUN_USER:-www}"                            # aaPanel's PHP-FPM user
ENV_DIR="${ENV_DIR:-/etc/onhost}"
QUEUES="${QUEUES:-default mails provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes}"

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
need() { command -v "$1" >/dev/null 2>&1 || { echo "missing: $1" >&2; exit 1; }; }

say "Checking the host"
need git; [ -x "$PHP" ] || { echo "PHP not found at $PHP (aaPanel: App Store → PHP 8.3)" >&2; exit 1; }
"$PHP" -m | grep -qiE '^(pdo_pgsql)$' || { echo "PHP 8.3 needs pdo_pgsql (aaPanel → PHP 8.3 → Install extensions)" >&2; exit 1; }
for ext in intl bcmath mbstring openssl redis fileinfo zip gd; do "$PHP" -m | grep -qi "^${ext}$" || echo "warning: PHP extension ${ext} missing — install it in aaPanel (PHP 8.3 → extensions)"; done
[ -x "$COMPOSER" ] || { say "Installing Composer"; "$PHP" -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" && "$PHP" /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm -f /tmp/composer-setup.php; }

say "Checkout in $APP_DIR ($BRANCH)"
if [ ! -d "$APP_DIR/.git" ]; then
  mkdir -p "$(dirname "$APP_DIR")"
  git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" || true

say "Environment file $ENV_DIR/app.env"
mkdir -p "$ENV_DIR"
if [ ! -f "$ENV_DIR/app.env" ]; then
  cp .env.example "$ENV_DIR/app.env"
  chmod 600 "$ENV_DIR/app.env"
  echo "   → fill $ENV_DIR/app.env (see docs/runbooks/deploy-aapanel.md § 'Údaje k doplnění'), then run this script again"
fi
ln -sfn "$ENV_DIR/app.env" "$APP_DIR/.env"

say "Composer (production, no dev packages)"
sudo -u "$RUN_USER" -H "$COMPOSER" install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader

if ! grep -qE '^APP_KEY=base64:' "$ENV_DIR/app.env"; then
  say "Application key"
  sudo -u "$RUN_USER" -H "$PHP" artisan key:generate --force
fi

say "Permissions"
chown -R "$RUN_USER:$RUN_USER" "$APP_DIR"
chmod -R u+rwX,g+rX,o-rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

say "Database: migrations and seed (catalogue, tax rules, notification templates, legal entity from the env)"
sudo -u "$RUN_USER" -H "$PHP" artisan migrate --force
sudo -u "$RUN_USER" -H "$PHP" artisan db:seed --force
sudo -u "$RUN_USER" -H "$PHP" artisan db:seed --class=NotificationTemplateSeeder --force

say "systemd: queue workers, scheduler"
for unit in onhost-queue@.service onhost-scheduler.service; do
  sed -e "s#/var/www/onhost#${APP_DIR}#g" -e "s#/usr/bin/php#${PHP}#g" -e "s#User=onhost#User=${RUN_USER}#" -e "s#Group=onhost#Group=${RUN_USER}#" -e "s#/etc/onhost/app.env#${ENV_DIR}/app.env#" "$APP_DIR/infra/systemd/$unit" > "/etc/systemd/system/$unit"
done
systemctl daemon-reload
systemctl enable --now onhost-scheduler.service
for q in $QUEUES; do systemctl enable --now "onhost-queue@${q}.service"; done

say "Caches"
sudo -u "$RUN_USER" -H "$PHP" artisan config:cache
sudo -u "$RUN_USER" -H "$PHP" artisan route:cache
sudo -u "$RUN_USER" -H "$PHP" artisan event:cache
sudo -u "$RUN_USER" -H "$PHP" artisan onhost:openapi >/dev/null || true

say "nginx site snippet"
echo "   → paste infra/aapanel/nginx-site.conf into aaPanel → Website → ${SITE} → Config (see the runbook); root = $APP_DIR/public"

say "Doctor"
sudo -u "$RUN_USER" -H "$PHP" artisan onhost:doctor || true
echo
echo "Next: docs/runbooks/deploy-aapanel.md — provider keys (onhost:integrations:secret), platform secrets (onhost:secrets:set), first backup (onhost:platform:backup)."
