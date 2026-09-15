#!/usr/bin/env bash
# ONhost websocket console relay on an aaPanel server (audit §5z): live consoles of game servers and VPS in the panel.
#   APP_DIR=/www/wwwroot/staging.onhost.cz bash infra/aapanel/relay-install.sh
# Finds Node.js 20+ (system or aaPanel's Node version manager), generates the shared relay key in /etc/onhost/app.env
# when it is empty (never printed), installs the relay into /opt/onhost-console-relay as a systemd service on
# 127.0.0.1:8090 and checks that nginx proxies /relay/ws/ to it. Safe to run again (it updates the files and restarts).
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/staging.onhost.cz}"
ENV_FILE="${ENV_FILE:-/etc/onhost/app.env}"
PHP="${PHP:-/www/server/php/83/bin/php}"
RELAY_DIR=/opt/onhost-console-relay
say() { printf '\n==> %s\n' "$*"; }
envval() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- | sed -E 's/[[:space:]]+#.*$//; s/^"//; s/"$//'; }
setenv() { # KEY VALUE — replace the line or append it; the value never reaches the terminal
  if grep -qE "^$1=" "$ENV_FILE"; then sed -i -E "s|^$1=.*$|$1=$2|" "$ENV_FILE"; else printf '%s=%s\n' "$1" "$2" >> "$ENV_FILE"; fi
}

[ "$(id -u)" -eq 0 ] || { echo "run as root" >&2; exit 1; }
[ -f "$ENV_FILE" ] || { echo "$ENV_FILE not found — install the application first (infra/aapanel/install.sh)" >&2; exit 1; }

say "Node.js"
NODE="${NODE:-$(command -v node || true)}"
if [ -z "$NODE" ] || ! "$NODE" -e 'process.exit(parseInt(process.versions.node) >= 20 ? 0 : 1)' 2>/dev/null; then
  NODE="$(ls -d /www/server/nodejs/v*/bin/node 2>/dev/null | sort -V | tail -1 || true)"
fi
if [ -z "$NODE" ] || ! "$NODE" -e 'process.exit(parseInt(process.versions.node) >= 20 ? 0 : 1)' 2>/dev/null; then
  echo "Node.js 20+ not found: install it in aaPanel → App Store → Node.js version manager (v20 or v22), then run again" >&2; exit 1
fi
NPM="$(dirname "$NODE")/npm"; [ -x "$NPM" ] || NPM="$(command -v npm)"
echo "   $NODE ($("$NODE" -v))"

say "Relay key and URL in $ENV_FILE"
SITE_URL="$(envval APP_URL)"; HOST="${SITE_URL#https://}"; HOST="${HOST#http://}"; HOST="${HOST%%/*}"
if [ -z "$(envval ONHOST_CONSOLE_RELAY_KEY)" ]; then
  setenv ONHOST_CONSOLE_RELAY_KEY "$(openssl rand -hex 32)"; echo "   generated a new relay key"
else
  echo "   relay key already set"
fi
[ -n "$(envval ONHOST_CONSOLE_RELAY_URL)" ] || { setenv ONHOST_CONSOLE_RELAY_URL "wss://${HOST}/relay"; echo "   ONHOST_CONSOLE_RELAY_URL=wss://${HOST}/relay"; }

say "Install into $RELAY_DIR"
mkdir -p "$RELAY_DIR"
cp "$APP_DIR"/infra/console-relay/server.mjs "$APP_DIR"/infra/console-relay/package.json "$RELAY_DIR"/
id -u onhost-relay >/dev/null 2>&1 || useradd -r -s /usr/sbin/nologin onhost-relay
(cd "$RELAY_DIR" && PATH="$(dirname "$NODE"):$PATH" "$NPM" install --omit=dev --no-audit --no-fund >/dev/null)
chown -R onhost-relay:onhost-relay "$RELAY_DIR"
umask 077
# the relay resolves console descriptors at https://<site>/console/ws/<token> (nginx and TLS stay in front of the application)
printf 'ONHOST_API=%s\nONHOST_CONSOLE_RELAY_KEY=%s\nPORT=8090\n' "$SITE_URL" "$(envval ONHOST_CONSOLE_RELAY_KEY)" > /etc/onhost/relay.env
chmod 600 /etc/onhost/relay.env

say "systemd unit"
sed -e "s|^ExecStart=.*$|ExecStart=${NODE} server.mjs|" "$APP_DIR"/infra/systemd/onhost-console-relay.service > /etc/systemd/system/onhost-console-relay.service
systemctl daemon-reload
systemctl enable onhost-console-relay >/dev/null 2>&1
systemctl restart onhost-console-relay
sleep 2
if curl -fsS http://127.0.0.1:8090/healthz >/dev/null; then echo "   relay listening on 127.0.0.1:8090"; else echo "   relay did not answer — journalctl -u onhost-console-relay -n 50" >&2; exit 1; fi

say "nginx location /relay/ws/"
VHOST="/www/server/panel/vhost/nginx/${HOST}.conf"
if [ -f "$VHOST" ] && grep -q "location /relay/ws/" "$VHOST"; then
  echo "   present in $VHOST"
else
  echo "   MISSING in $VHOST: paste the 'location /relay/ws/ { … }' block from $APP_DIR/infra/aapanel/nginx-site.conf"
  echo "   into aaPanel → Website → ${HOST} → Config (inside server { }), save, then: nginx -t && nginx -s reload"
fi

say "Application cache"
cd "$APP_DIR" && "$PHP" artisan config:cache >/dev/null && chown -R www:www storage bootstrap/cache
echo "   done — open a game server in the panel → Konzole"
