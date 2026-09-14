#!/usr/bin/env bash
# ONhost control plane — release on the aaPanel host (docs/runbooks/release-and-rollback.md): backup first, pull the
# branch, install, migrate, cache, restart the workers and the scheduler, run the doctor. Run as root.
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/onhost.cz}"
BRANCH="${BRANCH:-development}"
PHP="${PHP:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
RUN_USER="${RUN_USER:-www}"
SKIP_BACKUP="${SKIP_BACKUP:-0}"

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
cd "$APP_DIR"
art() { sudo -u "$RUN_USER" -H "$PHP" artisan "$@"; }

if [ "$SKIP_BACKUP" != "1" ]; then
  say "Platform backup before the release"
  art onhost:platform:backup
fi

say "Code ($BRANCH)"
git fetch --prune origin
git checkout -q "$BRANCH"
git reset -q --hard "origin/$BRANCH"
git log --oneline -1

say "Composer"
sudo -u "$RUN_USER" -H "$COMPOSER" install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader

say "Migrations (additive, backward compatible for one release)"
art migrate --force
art db:seed --class=NotificationTemplateSeeder --force

say "Caches and contract"
art config:cache
art route:cache
art event:cache
art onhost:openapi >/dev/null || true

say "Workers and scheduler"
art queue:restart
systemctl restart onhost-scheduler.service
systemctl restart 'onhost-queue@*.service' 2>/dev/null || for u in $(systemctl list-units --plain --no-legend 'onhost-queue@*' | awk '{print $1}'); do systemctl restart "$u"; done

say "Health"
curl -fsS -o /dev/null -w 'GET /up → %{http_code}\n' "http://127.0.0.1/up" -H "Host: $(grep -E '^APP_URL=' .env | sed -E 's#APP_URL=https?://##')" || true
art onhost:doctor || true
