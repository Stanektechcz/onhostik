#!/usr/bin/env bash
# ONhost staging on aaPanel — /www/wwwroot/staging.onhost.cz, PHP 8.5, Node 24.
# Get it on the server (as root; the repository is public, plain HTTPS):
#   curl -fsSL https://raw.githubusercontent.com/Stanektechcz/onhostik/development/infra/aapanel/staging.sh -o /root/onhost-staging.sh
# `install` then fetches the repository over HTTPS into /www/wwwroot/staging.onhost.cz (install.sh: root's own
# repository in /var/lib/onhost-deploy, the site folder is its work tree — never a .git www could rewrite).
#
# A wrapper around the repository's own scripts (infra/aapanel/install.sh and the gated deployer
# /usr/local/sbin/onhost-deploy); it adds no deploy logic of its own. Run as root, one mode at a time:
#
#   bash onhost-staging.sh setup [SHA]     the whole first launch: check, contain+park the old staging, db, install,
#                                          deployer (expected-env/expected-nonok written), start, first release
#   bash onhost-staging.sh check           read-only: PHP 8.5, Node 24, extensions, services, what runs today
#   bash onhost-staging.sh contain         stop the OLD staging (units, lanes, cron check) — nothing deleted
#   bash onhost-staging.sh park            move the old tree, /etc/onhost and the state dir aside (undo printed)
#   bash onhost-staging.sh db              new PostgreSQL role + database (password kept root-only until `env`)
#   bash onhost-staging.sh install [SHA]   install.sh run 1, fills app.env (`env`), install.sh run 2, Node 24 build
#   bash onhost-staging.sh env             (re)write the staging values into /etc/onhost/app.env (secrets never printed)
#   bash onhost-staging.sh deployer [SHA]  install/upgrade the gated deployer + expected-units/egress-blocked/path-b
#   bash onhost-staging.sh start           enable scheduler + default + mails lanes (provider lanes stay masked)
#   bash onhost-staging.sh deploy [SHA]    a release through the gated deployer (default: origin/development tip)
#   bash onhost-staging.sh status          versions, units, doctor rows that are not OK, last deploy, /up
#
# First launch:  check → contain → park → db → install → deployer → (expected-env + expected-nonok by hand, see
# the printed hints) → start → staff (printed command) → deploy.   Every later update:  deploy <sha>.
# docs/runbooks/staging-launch.md is the source of truth; stop at the first ✖.
set -euo pipefail

SITE=staging.onhost.cz
APP=/www/wwwroot/$SITE
PHP=${PHP:-/www/server/php/85/bin/php}
PHP_FPM_RELOAD=${PHP_FPM_RELOAD:-/etc/init.d/php-fpm-85 reload}
STATE=/var/lib/onhost-deploy/$SITE
R=$STATE/repo.git
DG=/usr/local/lib/onhost-deploy/deploy-gate.php
REPO=https://github.com/Stanektechcz/onhostik.git
NEWDB=${NEWDB:-onhost_staging_b}
NEWROLE=${NEWROLE:-onhost_b}
WORK=/var/cache/onhost-deploy/$SITE
LANES="provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes"
OPERATOR=${DEPLOY_OPERATOR:-$(whoami)}
export PHP PHP_FPM_RELOAD

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
ok()  { printf '  \033[32m✔\033[0m %s\n' "$*"; }
bad() { printf '  \033[1;31m✖ %s\033[0m\n' "$*"; FAIL=1; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 2; }
www() { setpriv --reuid=www --regid=www --init-groups -- setsid --wait "$@" </dev/null; }
art() { (cd "$APP" && www "$PHP" artisan "$@"); }
[ "$(id -u)" = 0 ] || die "run as root"

node24() { # aaPanel's Node version manager, nvm, or the system node — whichever is v24
  local n
  for n in /www/server/nodejs/v24*/bin/node /root/.nvm/versions/node/v24*/bin/node "$(command -v node 2>/dev/null || true)"; do
    [ -n "$n" ] && [ -x "$n" ] && "$n" -v 2>/dev/null | grep -q '^v24\.' && { echo "$n"; return 0; }
  done
  return 1
}

npm_build() { # the frontend bundle, as www with Node 24 (the application serves without it; failure is reported only)
  local n; n=$(node24) || { echo "  (no Node 24 — frontend build skipped)"; return 0; }
  (cd "$APP" && www env PATH="$(dirname "$n"):/usr/bin:/bin" HOME="$WORK/home" npm ci --no-audit --no-fund >/dev/null \
    && www env PATH="$(dirname "$n"):/usr/bin:/bin" HOME="$WORK/home" npm run build >/dev/null) \
    && ok "frontend built (Node $("$n" -v))" || echo "  ✖ frontend build failed (not needed to serve)"
}

latest_sha() { git ls-remote "$REPO" refs/heads/development | cut -f1; }

check() {
  FAIL=0
  say "PHP 8.5 ($PHP)"
  if [ -x "$PHP" ]; then ok "$("$PHP" -r 'echo PHP_VERSION;')"; else bad "missing — aaPanel → App Store → PHP 8.5"; fi
  for e in pdo_pgsql intl bcmath mbstring redis fileinfo zip gd "Zend OPcache"; do
    "$PHP" -m 2>/dev/null | grep -qix "$e" && ok "ext $e" || bad "ext $e missing (aaPanel → PHP 8.5 → Install extensions)"
  done
  "$PHP" -r 'exit(preg_match("/\b(proc_open|exec|shell_exec)\b/", ini_get("disable_functions")) ? 1 : 0);' \
    && ok "proc_open/exec allowed" || bad "remove proc_open, exec, shell_exec from disable_functions"
  [ -x "${PHP_FPM_RELOAD%% *}" ] && ok "FPM reload: $PHP_FPM_RELOAD" || bad "no ${PHP_FPM_RELOAD%% *} — set PHP_FPM_RELOAD=…"
  say "Node 24"
  if N=$(node24); then ok "$N $("$N" -v)"; else bad "no Node v24 — aaPanel → App Store → Node.js version manager → v24"; fi
  say "Tools and host"
  for c in git curl setpriv setsid nft psql pg_dump openssl; do command -v $c >/dev/null && ok "$c" || bad "$c missing"; done
  ok "$(git --version)"
  [ "$(sysctl -n fs.protected_hardlinks)" = 1 ] && ok "fs.protected_hardlinks=1" || bad "fs.protected_hardlinks must be 1"
  www id >/dev/null 2>&1 && ok "setpriv/setsid to www works" || bad "setpriv/setsid --wait cannot switch to www"
  grep -q '^requirepass' /www/server/redis/redis.conf 2>/dev/null && ok "Redis requirepass set" || bad "Redis has no requirepass"
  su - postgres -c "psql -tAc 'show server_version'" 2>/dev/null | sed 's/^/  PostgreSQL /' || bad "PostgreSQL not reachable as postgres"
  say "What runs today (old staging)"
  systemctl list-units --all --plain --no-legend 'onhost-*' || true
  ps -eo user,pid,cmd | grep -E 'artisan|queue:work|console-relay' | grep -v grep || echo "  (no artisan processes)"
  { crontab -l 2>/dev/null; crontab -l -u www 2>/dev/null; } | grep artisan || echo "  (no artisan cron)"
  [ -f "$APP/VERSION" ] && echo "  old VERSION: $(cut -d' ' -f1 "$APP/VERSION")"
  su - postgres -c "psql -tAc \"select datname from pg_database where datname not like 'template%'\"" 2>/dev/null | sed 's/^/  db: /'
  echo; [ "${FAIL:-0}" = 0 ] && ok "check passed" || die "fix the ✖ lines first"
}

contain() {
  say "Stopping the old staging (nothing is deleted)"
  for u in $( { systemctl list-units --all --plain --no-legend 'onhost-*' | awk '{print $1}'; for w in /etc/systemd/system/*.wants/onhost-*; do [ -e "$w" ] && basename "$w"; done; } | sort -u ); do
    systemctl disable --now "$u" && ok "stopped $u"
  done
  for q in $LANES; do systemctl mask "onhost-queue@$q.service" >/dev/null 2>&1 || true; done; ok "provider lanes masked"
  if [ -f "$APP/artisan" ]; then art down || true; art onhost:provisioning:freeze "staging containment" || true; fi
  echo "  ➜ aaPanel → Cron: switch off every task that calls artisan (schedule:run)."
  if ps -eo cmd | grep -E 'artisan|queue:work' | grep -v grep; then bad "something still runs"; else ok "no artisan process"; fi
  echo "  ➜ Recommended: revoke the old staging's panel keys at each issuer (aaPanel API key, ISPConfig remote user, Pterodactyl, Proxmox token, WEDOS)."
}

park() {
  local P; P=/www/onhost-staging-old-$(date +%Y%m%d-%H%M)
  say "Parking the old staging in $P"
  ps -eo cmd | grep -E 'artisan|queue:work' | grep -qv grep && die "run 'contain' first — something still runs"
  install -d -m 0700 "$P"
  [ "$(stat -c %d "$APP")" = "$(stat -c %d "$P")" ] || die "$P is on another disk than $APP — mv would copy"
  chattr -i "$APP/.user.ini" 2>/dev/null || true
  [ -e "$APP" ] && mv -T "$APP" "$P/wwwroot"
  [ -e /etc/onhost ] && mv -T /etc/onhost "$P/etc-onhost"
  [ -e "$STATE" ] && mv -T "$STATE" "$P/state"
  install -d -o www -g www -m 0755 "$APP"
  ok "parked; undo: rm -rf $APP && mv -T $P/wwwroot $APP && mv -T $P/etc-onhost /etc/onhost && mv -T $P/state $STATE"
}

db() {
  say "PostgreSQL role $NEWROLE + database $NEWDB"
  su - postgres -c "psql -tAc \"select 1 from pg_roles where rolname='$NEWROLE'\"" | grep -q 1 && die "role $NEWROLE exists — set NEWROLE=… or drop it knowingly"
  local pw; pw=$(openssl rand -hex 24)
  printf "create role %s login createdb password '%s';\n" "$NEWROLE" "$pw" | su - postgres -c "psql -q"
  su - postgres -c "createdb -O $NEWROLE -E UTF8 $NEWDB"
  (umask 077; printf '%s' "$pw" > /root/.onhost-staging-dbpw)
  ok "created; password kept in /root/.onhost-staging-dbpw (0600) until 'env' writes it into app.env"
}

setkey() { ( umask 077; f=/etc/onhost/app.env
  if grep -q "^$1=" $f; then awk -v k="$1" -v v="$2" 'index($0, k"=")==1 { if (!d) print k"="v; d=1; next } { print }' $f > $f.new
  else { cat $f; printf '%s=%s\n' "$1" "$2"; } > $f.new; fi
  cat $f.new > $f && rm -f $f.new ); }

envfill() {
  [ -f /etc/onhost/app.env ] || die "no /etc/onhost/app.env — run 'install' first"
  say "Filling /etc/onhost/app.env with staging values"
  local k v
  while IFS='=' read -r k v; do setkey "$k" "$v"; done <<'EOF'
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.onhost.cz
SANCTUM_STATEFUL_DOMAINS=staging.onhost.cz
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=2
REDIS_CACHE_DB=3
REDIS_PREFIX=onhost_staging_b_
CACHE_PREFIX=onhost_staging_b
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
ONHOST_SECRETS_DRIVER=db
MAIL_MAILER=log
MAIL_HOST=
MAIL_PASSWORD=
PAYMENT_GATEWAY=comgate
COMGATE_TEST=true
COMGATE_RECURRING=false
GOPAY_RECURRING=false
STRIPE_RECURRING=false
PEPPOL_SENDER_ID=
WEDOS_TEST_MODE=true
ONHOST_BANK_FIO_TOKEN=
ONHOST_VIES_ENABLED=false
ONHOST_FOUR_EYES=false
ONHOST_PLATFORM_BACKUP_DISK=local
ONHOST_PG_BIN=/www/server/pgsql/bin
ONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0
ONHOST_EGRESS_ALLOW_CIDRS=
ONHOST_CONSOLE_RELAY_URL=
ONHOST_CONSOLE_RELAY_KEY=
ONHOST_STAFF_REACH_ENFORCED=false
ONHOST_TOKEN_ORGANIZATION_REQUIRED=false
EOF
  setkey DB_DATABASE "$NEWDB"; setkey DB_USERNAME "$NEWROLE"
  if [ -f /root/.onhost-staging-dbpw ]; then setkey DB_PASSWORD "$(cat /root/.onhost-staging-dbpw)"; shred -u /root/.onhost-staging-dbpw; ok "DB password written"; fi
  setkey REDIS_PASSWORD "$(awk '/^requirepass/ {print $2}' /www/server/redis/redis.conf)"
  ok "done ($(grep -cE '^(DB_PASSWORD|REDIS_PASSWORD)=.+' /etc/onhost/app.env)/2 secrets set)"
  echo "  ➜ by hand (nano /etc/onhost/app.env): COMGATE_MERCHANT/COMGATE_SECRET (TEST merchant, or empty), TURNSTILE_* keys, company + bank data for PDFs"
}

install_app() {
  local sha=${1:-}; [ -n "$sha" ] || sha=$(latest_sha)
  say "install.sh at $sha (PHP $PHP)"
  curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/$sha/infra/aapanel/install.sh" -o /root/onhost-install.sh
  sha256sum /root/onhost-install.sh
  echo "  ➜ compare with the release record (.ai/releases/*-${sha:0:7}.md); Ctrl-C within 10 s if it differs"; sleep 10
  if [ ! -f /etc/onhost/app.env ]; then
    QUEUES='default mails' REF=$sha EXPECTED_SHA=$sha START_UNITS=0 bash /root/onhost-install.sh || true
    envfill
  fi
  QUEUES='default mails' REF=$sha EXPECTED_SHA=$sha START_UNITS=0 bash /root/onhost-install.sh
  for q in $LANES; do systemctl mask "onhost-queue@$q.service" >/dev/null 2>&1 || true; done
  art onhost:provisioning:freeze "staging phase 1 - no panels"; touch "$STATE/expect-freeze"
  npm_build
  ok "installed; next: $0 deployer $sha"
}

deployer() {
  local sha=${1:-}; [ -n "$sha" ] || sha=$(cut -d' ' -f1 "$APP/VERSION")
  say "Gated deployer from $sha"
  env GIT_DIR=$R GIT_CONFIG_GLOBAL=/dev/null git fetch -q --tags origin
  env GIT_DIR=$R git show "$sha:infra/aapanel/install-deployer.sh" > /root/install-deployer.sh
  sha256sum /root/install-deployer.sh
  if [ -x /usr/local/sbin/onhost-deploy ]; then SHA=$sha bash /root/install-deployer.sh; else SHA=$sha FIRST=1 bash /root/install-deployer.sh; fi
  ok "source-sha $(cat /usr/local/lib/onhost-deploy/source-sha)"
  chmod 0600 "$STATE/expected-units"; sed 's/^/  expected unit: /' "$STATE/expected-units"
  [ -f "$STATE/egress-blocked" ] || install -m 0600 /dev/null "$STATE/egress-blocked"
  [ -f "$STATE/path-b" ] || install -m 0600 /dev/null "$STATE/path-b"
  [ -f "$STATE/expected-env" ] || write_expected_env
  if "$PHP" "$DG" env-assert --file /etc/onhost/app.env --spec "$STATE/expected-env" >/dev/null; then ok "app.env matches expected-env"
  else die "app.env does not match $STATE/expected-env — see: $PHP $DG env-assert --file /etc/onhost/app.env --spec $STATE/expected-env"; fi
  if [ ! -f "$STATE/expected-nonok" ]; then
    # staging phase 1 runs without panels, so some doctor rows are non-OK by design: the rows non-OK TODAY are accepted
    # once (a snapshot); a row that turns non-OK later still stops a release (docs/runbooks/staging-launch.md O11)
    art onhost:doctor --json > /root/doctor-s4b.json 2>/dev/null || true
    (umask 077; "$PHP" "$DG" nonok --report /root/doctor-s4b.json --production 0 > "$STATE/expected-nonok")
    ok "expected-nonok: $(wc -l < "$STATE/expected-nonok") rows accepted as non-OK on staging ($STATE/expected-nonok)"
  fi
}

# The environment the deployer asserts on every release (staging-launch.md S3), with this script's values. Families
# (PREFIX_*=) keep every live-credential key empty; every other key of app.env is pinned empty when it is empty now,
# and named (any value) when it was set — by `env` or by hand (Comgate test merchant, Turnstile, company data).
write_expected_env() {
  (umask 077; cat > "$STATE/expected-env" <<'EOF'
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.onhost.cz
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
ONHOST_SECRETS_DRIVER=db
REDIS_PREFIX=onhost_staging_b_
CACHE_PREFIX=onhost_staging_b
DB_DATABASE!=onhost
MAIL_MAILER=log
MAIL_HOST=
MAIL_PASSWORD=
PAYMENT_GATEWAY=comgate
COMGATE_TEST=true
COMGATE_RECURRING=false
GOPAY_RECURRING=false
STRIPE_RECURRING=false
PEPPOL_SENDER_ID=
WEDOS_TEST_MODE=true
ONHOST_ACME_DIRECTORY~=acme-staging-v02
ONHOST_BANK_FIO_TOKEN=
ONHOST_VIES_ENABLED~=^(false)?$
ONHOST_FOUR_EYES=false
ONHOST_PLATFORM_BACKUP_DISK=local
ONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0
ONHOST_EGRESS_ALLOW_CIDRS=
ONHOST_STAFF_REACH_ENFORCED=false
ONHOST_TOKEN_ORGANIZATION_REQUIRED=false
ONHOST_CONSOLE_RELAY_URL=
ONHOST_CONSOLE_RELAY_KEY=
ONHOST_NODE_BOOTSTRAP_SSH_KEY=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
SENTRY_DSN=
LOG_SLACK_WEBHOOK_URL=
HTTP_PROXY=
HTTPS_PROXY=
ALL_PROXY=
http_proxy=
https_proxy=
all_proxy=
AI_ANTHROPIC_*=
AI_OPENAI_*=
GOPAY_*=
STRIPE_*=
PEPPOL_*=
OIDC_CLIENT_*=
DISCORD_BOT_*=
ONHOST_DISCORD_*=
ONHOST_ONCALL_*=
CLOUDFLARE_*=
OPENBAO_*=
OTEL_EXPORTER_OTLP_*=
SLACK_*=
POSTMARK_*=
RESEND_*=
PROXMOX_*=
PBS_*=
ISPCONFIG_*=
AAPANEL_*=
PTERODACTYL_*=
POWERDNS_*=
RKE2_*=
WEDOS_MAIN_*=
SUBREG_*=
EOF
  )
  local k v
  "$PHP" "$DG" env-assert --file /etc/onhost/app.env --spec "$STATE/expected-env" 2>/dev/null \
    | sed -n 's/^UNLISTED \([A-Za-z0-9_]*\):.*/\1/p' | sort -u | while read -r k; do
      v=$("$PHP" "$DG" parse-env --file /etc/onhost/app.env --key "$k" 2>/dev/null || true)
      if [ -z "$v" ]; then echo "$k="; else echo "$k"; fi
    done >> "$STATE/expected-env"
  echo '*UNLISTED=' >> "$STATE/expected-env"
  ok "expected-env written ($STATE/expected-env)"
}

start() {
  say "Starting scheduler + default + mails (provider lanes stay masked)"
  systemctl unmask --runtime onhost-scheduler.service 2>/dev/null || true
  systemctl enable --now onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service
  echo "  provider lanes: $(systemctl is-enabled onhost-queue@provider-aapanel.service 2>/dev/null || true)"
  echo "  ➜ first staff account (hidden password prompt), then log out and back in:
     setpriv --reuid=www --regid=www --init-groups -- setsid --wait $PHP $APP/artisan onhost:staff:create <email> --name=\"<name>\" --role=platform_owner"
}

deploy() {
  local sha=${1:-}; [ -n "$sha" ] || sha=$(latest_sha)
  [[ $sha =~ ^[0-9a-f]{40}$ ]] || die "give the full 40-character SHA"
  say "Release $sha through the gated deployer (PHP $PHP)"
  local rc=0
  REF=$sha EXPECTED_SHA=$sha DEPLOY_OPERATOR="$OPERATOR" /usr/local/sbin/onhost-deploy || rc=$?
  case $rc in
    0) ok "released: $(cat "$APP/VERSION")";;
    2) die "rc 2: refused before anything changed (a newer deployer in the target? run: $0 deployer $sha)";;
    3) die "rc 3: drain/backup/verify failed — nothing switched";;
    4) die "rc 4: build failed after the switch — run the rollback command printed above";;
    5) die "rc 5: doctor gate failed — site stays in maintenance; fix or extend expected-nonok, run again";;
    7) die "rc 7: a unit did not come back — see the recovery printed above";;
    *) die "rc $rc — see the output above";;
  esac
  npm_build
  echo "  ➜ in 6+ minutes: $0 status"
}

status() {
  say "Status"
  echo "  version: $(cat "$APP/VERSION" 2>/dev/null)"; echo "  php: $("$PHP" -r 'echo PHP_VERSION;')"
  if N=$(node24); then echo "  node: $("$N" -v)"; fi
  systemctl list-units --plain --no-legend 'onhost-*' || true
  tail -1 "$STATE/deploy.log" 2>/dev/null || true
  art onhost:doctor --json 2>/dev/null > /root/doctor-status.json || true
  "$PHP" -r '$d=json_decode(file_get_contents("/root/doctor-status.json"),true)?:[]; array_walk_recursive($d,function(){}); foreach(($d["checks"]??$d) as $c){ if(!is_array($c)) continue; $n=($c["area"]??"")."|".($c["check"]??$c["name"]??""); $s=$c["status"]??""; if($s!=="OK"||str_contains($n,"alive")||str_contains($n,"scheduler")) echo "  $s  $n\n"; }' || true
  curl -s -o /dev/null -w '  /up over loopback: %{http_code}\n' --resolve $SITE:443:127.0.0.1 https://$SITE/up || true
}

setup() { # the whole first launch in one go; stops at the first ✖
  check
  if [ -d "$APP" ] && [ -n "$(ls -A "$APP" 2>/dev/null)" ] && [ ! -f "$STATE/installed" ]; then contain; park; fi
  [ -d "$APP" ] || install -d -o www -g www -m 0755 "$APP"
  su - postgres -c "psql -tAc \"select 1 from pg_roles where rolname='$NEWROLE'\"" | grep -q 1 || db
  [ -f "$STATE/installed" ] || install_app "${1:-}"
  deployer "${1:-}"
  start
  deploy "$(cut -d' ' -f1 "$APP/VERSION")"
}

case "${1:-}" in
  setup) setup "${2:-}";;
  check) check;; contain) contain;; park) park;; db) db;; install) install_app "${2:-}";; env) envfill;;
  deployer) deployer "${2:-}";; start) start;; deploy) deploy "${2:-}";; status) status;;
  *) sed -n '2,27p' "$0"; exit 1;;
esac
