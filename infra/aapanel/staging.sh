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
#   bash onhost-staging.sh setup [SHA]     the whole first launch at SHA (default: the development tip, resolved once,
#                                          recorded in the state dir's setup-sha and printed): check, contain+park the
#                                          old staging, db, install, deployer, start, first release
#   bash onhost-staging.sh check           read-only: PHP 8.5, Node 24, extensions, services, usranalyse, what runs today
#   bash onhost-staging.sh contain         stop the OLD staging (units, lanes, cron check) — nothing deleted
#   bash onhost-staging.sh park            move the old tree, /etc/onhost and the state dir aside (undo printed)
#   bash onhost-staging.sh db              new PostgreSQL role + database (password kept root-only until `env`)
#   bash onhost-staging.sh install [SHA]   install.sh run 1, fills app.env (`env`), install.sh run 2, harden, Node 24 build
#   bash onhost-staging.sh env             (re)write the staging values into /etc/onhost/app.env (secrets never printed)
#   bash onhost-staging.sh deployer [SHA]  install/upgrade the gated deployer + expected-units/egress-blocked/path-b
#   bash onhost-staging.sh harden          aaPanel host fixes: ProtectSystem=no drop-ins while usranalyse is preloaded,
#                                          public/build to www, storage+bootstrap/cache www's, code root's, app.env 0640
#   bash onhost-staging.sh start           enable scheduler + default + mails lanes (provider lanes stay masked)
#   bash onhost-staging.sh deploy [SHA]    harden, a release through the gated deployer, Node 24 build (default: the tip)
#   bash onhost-staging.sh status          versions, units, doctor rows that are not OK, last deploy, /up
#
# A release that fails anywhere in setup, install or deploy is PARKED: <state>/releases/<sha>.parked says at which
# stage and with which exit code, <state>/releases/current keeps naming the last good release (the tree is updated in
# place by the deployer — there is no release directory or symlink to flip back), the rollback command is printed and
# the script exits non-zero (the deployer's own code, 8 for the frontend build). docs/runbooks/staging-aapanel.md.
# First launch: setup (or the modes one by one).   Every later update: deploy <sha>.   Stop at the first ✖.
set -euo pipefail

SITE=staging.onhost.cz
APP_DIR=${APP_DIR:-/www/wwwroot/$SITE}
PHP=${PHP:-/www/server/php/85/bin/php}
PHP_FPM_RELOAD=${PHP_FPM_RELOAD:-/etc/init.d/php-fpm-85 reload}
PHP_FPM_UNIT=${PHP_FPM_UNIT:-php-fpm-85.service}
STATE=${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/$SITE}
DEPLOY_STATE_DIR=$STATE   # install.sh's name, used by the functions the two scripts share
R=$STATE/repo.git
ENV_DIR=${ENV_DIR:-/etc/onhost}
DG=/usr/local/lib/onhost-deploy/deploy-gate.php
DEPLOYER_BIN=${DEPLOYER_BIN:-/usr/local/sbin/onhost-deploy}
REPO=${REPO:-https://github.com/Stanektechcz/onhostik.git}
NEWDB=${NEWDB:-onhost_staging_b}
NEWROLE=${NEWROLE:-onhost_b}
RUN_USER=${RUN_USER:-www}
DEPLOY_OWNER_UID=${DEPLOY_OWNER_UID:-0}                 # who owns the code (root; the tests run unprivileged)
WORK=${DEPLOY_WORK_DIR:-/var/cache/onhost-deploy/$SITE}
DEPLOY_WORK_DIR=$WORK
DEPLOY_SAFE_PATH=${DEPLOY_SAFE_PATH:-/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin}
SYSTEMD_DIR=${SYSTEMD_DIR:-/etc/systemd/system}
USRANALYSE_PRELOAD_FILE=${USRANALYSE_PRELOAD_FILE:-/etc/ld.so.preload}
USRANALYSE_DROPIN=10-aapanel-usranalyse.conf
LANES="provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes"
OPERATOR=${DEPLOY_OPERATOR:-$(whoami)}
SELF=$(realpath "${BASH_SOURCE[0]}" 2>/dev/null || echo "${BASH_SOURCE[0]}")
RELEASE_SHA=""     # the commit this run releases (setup, install, deploy): parked when the run fails
RELEASE_STAGE=""
RELEASE_PREVIOUS=""  # the release that was current when this run began (the deployer rewrites last-good.json on rc 0)
export PHP PHP_FPM_RELOAD

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
ok()  { printf '  \033[32m✔\033[0m %s\n' "$*"; }
bad() { printf '  \033[1;31m✖ %s\033[0m\n' "$*"; FAIL=1; }
warn() { printf '\033[1;33mWARN %s\033[0m\n' "$*" >&2; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 2; }
fail() { local rc=$1; shift; printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit "$rc"; }
www() { setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups -- setsid --wait "$@" </dev/null; }
art() { (cd "$APP_DIR" && www "$PHP" artisan "$@"); }

# The deployer's function, character for character (DeployGateTest compares them): the site's PHP-side commands run as
# the run user in a session of their own, with a fixed environment.
as_run() {
  setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups -- \
    setsid --wait env -i PATH="$DEPLOY_SAFE_PATH" HOME="$DEPLOY_WORK_DIR/home" "$@" </dev/null 9>&-
}
# storage and bootstrap/cache belong to the run user: entries not its own are handed over from inside their directory
# (never a recursive chown of a tree www can write), and `o-rwx` is set by the run user itself. The deployer's two
# functions, character for character.
tree_is_real() { # the two directories are real directories where the checkout says they are
  local d real_app
  real_app="$(realpath "$APP_DIR")" || return 1
  for d in storage bootstrap bootstrap/cache; do
    [ ! -L "$APP_DIR/$d" ] && [ -d "$APP_DIR/$d" ] && [ "$(realpath "$APP_DIR/$d")" = "$real_app/$d" ] || return 1
  done
}
repair_ownership() {
  tree_is_real || { warn "storage, bootstrap or bootstrap/cache is a symlink or missing: ownership NOT repaired"; return 1; }
  PATH="$DEPLOY_SAFE_PATH" find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" ! -user "$RUN_USER" -execdir chown -h "$RUN_USER:$RUN_USER" {} + \
    && as_run find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" ! -type l -perm /o=rwx -exec chmod o-rwx {} +
}

# aaPanel's security module (/usr/local/usranalyse/lib/libusranalyse.so, loaded into every process through
# /etc/ld.so.preload) crashes every process of the run user that systemd starts under any ProtectSystem= (staging,
# 2026-09-28: SIGSEGV right after start, deploy rc 7; ReadWritePaths for the module's paths did not help, root or no
# ProtectSystem did). While it is preloaded, a drop-in sets ProtectSystem=no for the units the run user's processes run
# in; once it is gone the drop-in is removed and the unit's own ProtectSystem=strict applies again (a hardening is not
# given up where nothing needs it). NoNewPrivileges and PrivateTmp stay. Identical in install.sh and staging.sh.
#
# Security review M2: the platform's own units (onhost-*, unprivileged, run as the run user) get back part of what
# ProtectSystem gave up, without making / /usr /etc read-only: no writes to kernel tunables, no module loading, a
# read-only cgroup tree, no set-uid/set-gid files, one execution domain, no capabilities at all. Three of these
# (ProtectKernelTunables, ProtectKernelModules, ProtectControlGroups) still use systemd's mount namespace, so each is
# verified on the host with the module loaded (docs/runbooks/staging-aapanel.md). A directive that brings the crash back
# is named, one per line, in $DEPLOY_STATE_DIR/usranalyse-omit and left out from then on (a hand edit of the drop-in
# would be rewritten). Any other unit (PHP-FPM) is a precaution: a drop-in only while the unit exists, ProtectSystem=no
# alone — its master runs as root and needs its capabilities to switch to the run user.
usranalyse_loaded() { grep -qsE '^[^#]*usranalyse' "$USRANALYSE_PRELOAD_FILE"; }
usranalyse_dropin_text() { # $1 = unit
  local line
  printf '%s\n' "# infra/aapanel: $USRANALYSE_PRELOAD_FILE loads aaPanel's libusranalyse.so, which crashes the run user's" \
    '# processes under any ProtectSystem= (staging 2026-09-28, deploy rc 7). Removed again once the module is gone.' \
    '[Service]' 'ProtectSystem=no'
  case $1 in onhost-*) ;; *) return 0 ;; esac
  echo "# verified on this host? docs/runbooks/staging-aapanel.md; a directive that crashes goes into $DEPLOY_STATE_DIR/usranalyse-omit"
  for line in ProtectKernelTunables=yes ProtectKernelModules=yes ProtectControlGroups=yes RestrictSUIDSGID=yes LockPersonality=yes CapabilityBoundingSet=; do
    grep -qsxF "${line%%=*}" "$DEPLOY_STATE_DIR/usranalyse-omit" || echo "$line"
  done
}
usranalyse_dropins() { # $@ = units
  local u d want changed=0
  for u in "$@"; do
    d="$SYSTEMD_DIR/$u.d"
    want=0
    if usranalyse_loaded; then
      case $u in onhost-*) want=1 ;; *) systemctl cat "$u" >/dev/null 2>&1 && want=1 ;; esac
    fi
    if [ "$want" = 1 ]; then
      mkdir -p "$d" && usranalyse_dropin_text "$u" > "$d/$USRANALYSE_DROPIN.new" || return 1
      if cmp -s "$d/$USRANALYSE_DROPIN.new" "$d/$USRANALYSE_DROPIN"; then
        rm -f "$d/$USRANALYSE_DROPIN.new"
      else
        mv -f "$d/$USRANALYSE_DROPIN.new" "$d/$USRANALYSE_DROPIN" || return 1
        changed=1
      fi
    elif [ -e "$d/$USRANALYSE_DROPIN" ]; then
      rm -f "$d/$USRANALYSE_DROPIN" || return 1
      changed=1
    fi
  done
  [ "$changed" = 0 ] || systemctl daemon-reload
}
units_of_run_user() { echo "onhost-queue@.service onhost-scheduler.service $PHP_FPM_UNIT"; }

node24() { # aaPanel's Node version manager, nvm, or the system node — whichever is v24
  local n
  for n in /www/server/nodejs/v24*/bin/node /root/.nvm/versions/node/v24*/bin/node "$(command -v node 2>/dev/null || true)"; do
    [ -n "$n" ] && [ -x "$n" ] && "$n" -v 2>/dev/null | grep -q '^v24\.' && { echo "$n"; return 0; }
  done
  return 1
}

npm_build() { # the frontend bundle, as the run user with Node 24, into public/build (the run user's: web_build_dir)
  local n; n=$(node24) || { echo "  ✖ no Node 24 — the frontend bundle (public/build) cannot be built" >&2; return 1; }
  (cd "$APP_DIR" && www env PATH="$(dirname "$n"):/usr/bin:/bin" HOME="$WORK/home" npm_config_cache="$WORK/npm-cache" npm ci --no-audit --no-fund >/dev/null \
    && www env PATH="$(dirname "$n"):/usr/bin:/bin" HOME="$WORK/home" npm_config_cache="$WORK/npm-cache" npm run build >/dev/null) || return 1
  ok "frontend built (Node $("$n" -v))"
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
  if usranalyse_loaded; then
    echo "  ➜ aaPanel's usranalyse is preloaded ($USRANALYSE_PRELOAD_FILE): 'harden' (setup, install, deploy run it) writes ProtectSystem=no drop-ins"
  else
    ok "usranalyse not preloaded (the units keep ProtectSystem=strict)"
  fi
  say "What runs today (old staging)"
  systemctl list-units --all --plain --no-legend 'onhost-*' || true
  ps -eo user,pid,cmd | grep -E 'artisan|queue:work|console-relay' | grep -v grep || echo "  (no artisan processes)"
  { crontab -l 2>/dev/null; crontab -l -u www 2>/dev/null; } | grep artisan || echo "  (no artisan cron)"
  [ -f "$APP_DIR/VERSION" ] && echo "  old VERSION: $(cut -d' ' -f1 "$APP_DIR/VERSION")"
  su - postgres -c "psql -tAc \"select datname from pg_database where datname not like 'template%'\"" 2>/dev/null | sed 's/^/  db: /'
  echo; [ "${FAIL:-0}" = 0 ] && ok "check passed" || die "fix the ✖ lines first"
}

contain() {
  say "Stopping the old staging (nothing is deleted)"
  for u in $( { systemctl list-units --all --plain --no-legend 'onhost-*' | awk '{print $1}'; for w in /etc/systemd/system/*.wants/onhost-*; do [ -e "$w" ] && basename "$w"; done; } | sort -u ); do
    systemctl disable --now "$u" && ok "stopped $u"
  done
  for q in $LANES; do systemctl mask "onhost-queue@$q.service" >/dev/null 2>&1 || true; done; ok "provider lanes masked"
  if [ -f "$APP_DIR/artisan" ]; then art down || true; art onhost:provisioning:freeze "staging containment" || true; fi
  echo "  ➜ aaPanel → Cron: switch off every task that calls artisan (schedule:run)."
  if ps -eo cmd | grep -E "$APP_DIR.*(artisan|queue:work)|onhost-(queue|scheduler)" | grep -v grep; then bad "something still runs"; else ok "no artisan process"; fi
  echo "  ➜ Recommended: revoke the old staging's panel keys at each issuer (aaPanel API key, ISPConfig remote user, Pterodactyl, Proxmox token, WEDOS)."
}

park() {
  local P; P=/www/onhost-staging-old-$(date +%Y%m%d-%H%M)
  say "Parking the old staging in $P"
  ps -eo cmd | grep -E "$APP_DIR.*(artisan|queue:work)|onhost-(queue|scheduler)" | grep -qv grep && die "run 'contain' first — something still runs"
  install -d -m 0700 "$P"
  [ "$(stat -c %d "$APP_DIR")" = "$(stat -c %d "$P")" ] || die "$P is on another disk than $APP_DIR — mv would copy"
  chattr -i "$APP_DIR/.user.ini" 2>/dev/null || true
  [ -e "$APP_DIR" ] && mv -T "$APP_DIR" "$P/wwwroot"
  [ -e "$ENV_DIR" ] && mv -T "$ENV_DIR" "$P/etc-onhost"
  [ -e "$STATE" ] && mv -T "$STATE" "$P/state"
  install -d -o "$RUN_USER" -g "$RUN_USER" -m 0755 "$APP_DIR"
  ok "parked; undo: rm -rf $APP_DIR && mv -T $P/wwwroot $APP_DIR && mv -T $P/etc-onhost $ENV_DIR && mv -T $P/state $STATE"
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

# the value reaches awk in its environment (root-only /proc/<pid>/environ), never on its command line (world-readable
# /proc/<pid>/cmdline), and ENVIRON keeps its backslashes where awk -v turned them into escapes (security review M1)
setkey() { ( umask 077; f=$ENV_DIR/app.env
  if grep -q "^$1=" "$f"; then K="$1" V="$2" awk 'index($0, ENVIRON["K"]"=")==1 { if (!d) print ENVIRON["K"]"="ENVIRON["V"]; d=1; next } { print }' "$f" > "$f.new"
  else { cat "$f"; printf '%s=%s\n' "$1" "$2"; } > "$f.new"; fi
  cat "$f.new" > "$f" && rm -f "$f.new" ); }

envfill() {
  [ -f "$ENV_DIR/app.env" ] || die "no $ENV_DIR/app.env — run 'install' first"
  say "Filling $ENV_DIR/app.env with staging values"
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
  ok "done ($(grep -cE '^(DB_PASSWORD|REDIS_PASSWORD)=.+' "$ENV_DIR/app.env")/2 secrets set)"
  echo "  ➜ by hand (nano $ENV_DIR/app.env): COMGATE_MERCHANT/COMGATE_SECRET (TEST merchant, or empty), TURNSTILE_* keys, company + bank data for PDFs"
}

install_app() {
  local sha=${1:-}; [ -n "$sha" ] || sha=$(latest_sha)
  [[ $sha =~ ^[0-9a-f]{40}$ ]] || die "give the full 40-character SHA"
  [ -n "$RELEASE_SHA" ] || { RELEASE_SHA=$sha; RELEASE_PREVIOUS=$(current_release); }
  say "install.sh at $sha (PHP $PHP)"
  curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/$sha/infra/aapanel/install.sh" -o /root/onhost-install.sh
  sha256sum /root/onhost-install.sh
  echo "  ➜ compare with the release record (.ai/releases/*-${sha:0:7}.md); Ctrl-C within 10 s if it differs"; sleep 10
  if [ ! -f "$ENV_DIR/app.env" ]; then
    QUEUES='default mails' REF=$sha EXPECTED_SHA=$sha START_UNITS=0 bash /root/onhost-install.sh
  fi
  # run 1 wrote app.env from .env.example; a run cut short after it still needs the staging values and the password
  grep -qE '^DB_PASSWORD=.+' "$ENV_DIR/app.env" || envfill
  QUEUES='default mails' REF=$sha EXPECTED_SHA=$sha START_UNITS=0 bash /root/onhost-install.sh
  for q in $LANES; do systemctl mask "onhost-queue@$q.service" >/dev/null 2>&1 || true; done
  art onhost:provisioning:freeze "staging phase 1 - no panels"; touch "$STATE/expect-freeze"
  harden
  npm_build || fail 8 "frontend build failed (public/build): the pages that load the bundle cannot render"
  ok "installed; next: bash $SELF deployer $sha"
}

deployer() {
  local sha=${1:-}; [ -n "$sha" ] || sha=$(cut -d' ' -f1 "$APP_DIR/VERSION")
  [[ $sha =~ ^[0-9a-f]{40}$ ]] || die "give the full 40-character SHA (it is read with git show <sha>:...)"
  say "Gated deployer from $sha"
  env GIT_DIR="$R" GIT_CONFIG_GLOBAL=/dev/null git fetch -q --tags origin
  env GIT_DIR="$R" git show "$sha:infra/aapanel/install-deployer.sh" > /root/install-deployer.sh
  sha256sum /root/install-deployer.sh
  if [ -x "$DEPLOYER_BIN" ]; then SHA=$sha bash /root/install-deployer.sh; else SHA=$sha FIRST=1 bash /root/install-deployer.sh; fi
  ok "source-sha $(cat /usr/local/lib/onhost-deploy/source-sha)"
  chmod 0600 "$STATE/expected-units"; sed 's/^/  expected unit: /' "$STATE/expected-units"
  [ -f "$STATE/egress-blocked" ] || install -m 0600 /dev/null "$STATE/egress-blocked"
  [ -f "$STATE/path-b" ] || install -m 0600 /dev/null "$STATE/path-b"
  blank_families
  write_expected_env   # derived from this script's values and app.env on every run
  if "$PHP" "$DG" env-assert --file "$ENV_DIR/app.env" --spec "$STATE/expected-env" >/dev/null 2>&1; then ok "app.env matches expected-env"
  else "$PHP" "$DG" env-assert --file "$ENV_DIR/app.env" --spec "$STATE/expected-env" 2>&1 | grep -v "^OK" || true; die "app.env does not match $STATE/expected-env (the lines above name the keys)"; fi
  if [ ! -f "$STATE/expected-nonok" ]; then
    # staging phase 1 runs without panels, so some doctor rows are non-OK by design: the rows non-OK TODAY are accepted
    # once (a snapshot); a row that turns non-OK later still stops a release (docs/runbooks/staging-launch.md O11)
    (umask 077; art onhost:doctor --json > /root/doctor-s4b.json 2>/dev/null) || true
    (umask 077; "$PHP" "$DG" nonok --report /root/doctor-s4b.json --production 0 > "$STATE/expected-nonok")
    ok "expected-nonok: $(wc -l < "$STATE/expected-nonok") rows accepted as non-OK on staging ($STATE/expected-nonok)"
  fi
}

# The environment the deployer asserts on every release (staging-launch.md S3), with this script's values. Families
# (PREFIX_*=) keep every live-credential key empty; every other key of app.env is pinned empty when it is empty now,
# and named (any value) when it was set — by `env` or by hand (Comgate test merchant, Turnstile, company data).
# The live-credential families must be empty on staging (the spec's PREFIX_*= lines); .env.example gives some of
# their keys a value (a model name, a db:// secret reference, an escalation tunable), so they are emptied here.
blank_families() {
  local p k
  for p in AI_ANTHROPIC_ AI_OPENAI_ GOPAY_ STRIPE_ PEPPOL_ OIDC_CLIENT_ DISCORD_BOT_ ONHOST_DISCORD_ ONHOST_ONCALL_ CLOUDFLARE_ OPENBAO_ OTEL_EXPORTER_OTLP_ SLACK_ POSTMARK_ RESEND_ PROXMOX_ PBS_ ISPCONFIG_ AAPANEL_ PTERODACTYL_ POWERDNS_ RKE2_ WEDOS_MAIN_ SUBREG_; do
    for k in $(grep -oE "^${p}[A-Z0-9_]*=" "$ENV_DIR/app.env" | tr -d '=' || true); do
      case $k in GOPAY_RECURRING|STRIPE_RECURRING) continue;; esac
      setkey "$k" ""
    done
  done
}

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
  local k v unlisted
  # env-assert names the unlisted keys only once the allow-list line is there, so it goes in first
  echo '*UNLISTED=' >> "$STATE/expected-env"
  unlisted=$("$PHP" "$DG" env-assert --file "$ENV_DIR/app.env" --spec "$STATE/expected-env" 2>&1 \
    | sed -n 's/^UNLISTED \([A-Za-z0-9_]*\):.*/\1/p' | sort -u || true)
  for k in $unlisted; do
    v=$("$PHP" "$DG" parse-env --file "$ENV_DIR/app.env" --key "$k" </dev/null 2>/dev/null || true)
    if [ -z "$v" ]; then echo "$k="; else echo "$k"; fi
  done >> "$STATE/expected-env"
  ok "expected-env written ($STATE/expected-env)"
}

# ── harden: what an aaPanel host needs before the units start and before vite writes (E0, audit 2026-10 P0-5/P1-16) ──
harden() {
  say "aaPanel host: systemd drop-ins, ownership and modes"
  if usranalyse_loaded; then
    echo "  ➜ aaPanel's usranalyse is preloaded ($USRANALYSE_PRELOAD_FILE): ProtectSystem=no drop-ins for onhost-queue@ and onhost-scheduler"
    echo "    (with the compensating hardening; omit list: $STATE/usranalyse-omit) and, only if it runs under systemd, $PHP_FPM_UNIT"
  fi
  # shellcheck disable=SC2046 # unit names are plain words
  usranalyse_dropins $(units_of_run_user) || die "cannot write the systemd drop-ins under $SYSTEMD_DIR"
  if usranalyse_loaded; then
    ok "drop-ins $SYSTEMD_DIR/<unit>.d/$USRANALYSE_DROPIN (ProtectSystem=no; each applies at its unit's next start)"
  else
    ok "no usranalyse: no drop-in (ProtectSystem=strict stays)"
  fi
  web_build_dir || die "public/build cannot be handed to $RUN_USER"
  ok "public/build belongs to $RUN_USER (vite writes it); public/ stays root's"
  repair_ownership || die "storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR"
  as_run find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" ! -type l -user "$RUN_USER" ! -perm -u=rw -exec chmod u+rwX {} + \
    || die "storage or bootstrap/cache cannot be made writable for $RUN_USER"
  ok "storage and bootstrap/cache: $RUN_USER's, writable by it, nothing for others"
  code_read_only || die "the code tree is not read-only for $RUN_USER (the lines above name what)"
  ok "code: uid $DEPLOY_OWNER_UID's, writable by nobody else"
  env_file_mode
  ok "$ENV_DIR/app.env: root:$RUN_USER 0640 in a 0750 directory, and $APP_DIR/.env is that file"
}

web_build_dir() { # vite writes public/build as the run user (npm run build); public/ itself stays root's
  local real_app
  real_app=$(realpath "$APP_DIR") || return 1
  if [ -L "$APP_DIR/public" ] || [ ! -d "$APP_DIR/public" ] || [ "$(realpath "$APP_DIR/public")" != "$real_app/public" ]; then
    echo "  ✖ $APP_DIR/public is not a real directory of the tree" >&2; return 1
  fi
  if [ -L "$APP_DIR/public/build" ]; then echo "  ✖ $APP_DIR/public/build is a symlink: nothing handed over" >&2; return 1; fi
  [ -e "$APP_DIR/public/build" ] || mkdir -m 0755 "$APP_DIR/public/build" || return 1
  [ -d "$APP_DIR/public/build" ] || { echo "  ✖ $APP_DIR/public/build is not a directory" >&2; return 1; }
  chown -h "$RUN_USER:$RUN_USER" "$APP_DIR/public/build" || return 1
  # what an older root build left inside, handed over entry by entry (never followed: -P, -execdir, -h)
  PATH="$DEPLOY_SAFE_PATH" find -P "$APP_DIR/public/build" -mindepth 1 ! -user "$RUN_USER" -execdir chown -h "$RUN_USER:$RUN_USER" {} +
}

# The tracked code is root's and nobody else writes it (the run user keeps storage, bootstrap/cache, public/build,
# vendor and node_modules). Only inside top-level directories that are root's and not writable by others: the site
# directory itself is www's (aaPanel), so www can replace a top-level entry anyway and nothing there is worth a race. A
# top-level code directory that is not root's is reported, not taken over. Entries are re-owned with chown -h from
# inside their directory; modes are fixed in pre-order (\;) so a directory is closed before its entries are read.
# chmod follows a link (security review M3): only entries that are root's and no link when find looks at them are
# chmodded, from inside their directory, and every directory above them is root's and closed by then, so nobody else
# can swap the name for a link in between.
code_read_only() {
  local d top bad="" keep=( \( -path "$APP_DIR/bootstrap/cache" -o -path "$APP_DIR/public/build" \) -prune -o )
  top=$(GIT_DIR="$R" GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git ls-tree -d --name-only HEAD) \
    || { echo "  ✖ cannot list the tracked directories from $R" >&2; return 1; }
  for d in $top; do
    case $d in storage|vendor|node_modules) continue;; esac
    [ -d "$APP_DIR/$d" ] && [ ! -L "$APP_DIR/$d" ] || continue
    if [ -n "$(find -P "$APP_DIR/$d" -maxdepth 0 \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print)" ]; then bad="$bad $d"; continue; fi
    PATH="$DEPLOY_SAFE_PATH" find -P "$APP_DIR/$d" -mindepth 1 "${keep[@]}" ! -type l ! -uid "$DEPLOY_OWNER_UID" -execdir chown -h "$DEPLOY_OWNER_UID" {} + || return 1
    PATH="$DEPLOY_SAFE_PATH" find -P "$APP_DIR/$d" -mindepth 1 "${keep[@]}" ! -type l -uid "$DEPLOY_OWNER_UID" -perm /022 -execdir chmod go-w {} \; || return 1
  done
  [ -z "$bad" ] || { echo "  ✖ top-level code directories not uid $DEPLOY_OWNER_UID's or writable by others:$bad — find out what wrote them, then chown root / chmod go-w by hand" >&2; return 1; }
}

env_file_mode() { # root writes it, the run user's PHP reads it, nobody else (install.sh's convention); it is never opened here
  [ -f "$ENV_DIR/app.env" ] && [ ! -L "$ENV_DIR/app.env" ] || die "$ENV_DIR/app.env is missing or a symlink"
  chown root:"$RUN_USER" "$ENV_DIR" "$ENV_DIR/app.env" && chmod 0750 "$ENV_DIR" && chmod 0640 "$ENV_DIR/app.env" \
    || die "cannot set root:$RUN_USER 0640 on $ENV_DIR/app.env"
  [ "$APP_DIR/.env" -ef "$ENV_DIR/app.env" ] || die "$APP_DIR/.env is not $ENV_DIR/app.env (install.sh links it)"
}

start() {
  say "Starting scheduler + default + mails (provider lanes stay masked)"
  # shellcheck disable=SC2046 # unit names are plain words
  usranalyse_dropins $(units_of_run_user) || die "cannot write the systemd drop-ins under $SYSTEMD_DIR"
  systemctl unmask --runtime onhost-scheduler.service 2>/dev/null || true
  systemctl enable --now onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service
  echo "  provider lanes: $(systemctl is-enabled onhost-queue@provider-aapanel.service 2>/dev/null || true)"
  echo "  ➜ first staff account (hidden password prompt), then log out and back in:
     setpriv --reuid=www --regid=www --init-groups -- setsid --wait $PHP $APP_DIR/artisan onhost:staff:create <email> --name=\"<name>\" --role=platform_owner"
}

# ── releases: current and parked (there is no release directory: the deployer updates the tree in place) ────────────
current_release() { # what this script last released, else what the deployer last called good; never VERSION (it names a parked switch too)
  local c
  c=$(cat "$STATE/releases/current" 2>/dev/null || true)
  [[ $c =~ ^[0-9a-f]{40}$ ]] || c=$(sed -n 's/.*"sha":"\([0-9a-f]\{40\}\)".*/\1/p' "$STATE/last-good.json" 2>/dev/null | head -n 1)
  printf '%s' "$c"
}

release_good() { # $1 = sha: current from now on; an earlier park of it is kept as history
  local dir=$STATE/releases
  (umask 077; mkdir -p "$dir") || die "cannot create $dir"
  if [ -f "$dir/$1.parked" ]; then mv -f "$dir/$1.parked" "$dir/$1.parked-$(date -u +%Y%m%dT%H%M%SZ).cleared"; fi
  (umask 077; printf '%s\n' "$1" > "$dir/current.new") && mv -f "$dir/current.new" "$dir/current" || die "cannot record $1 as current"
  RELEASE_SHA=""
  ok "current release: $1 ($dir/current)"
}

park_release() { # $1 sha, $2 rc, $3 stage — the release failed: marked parked, the current release is left as it is
  local dir=$STATE/releases prev=$RELEASE_PREVIOUS
  [ -n "$prev" ] || prev=$(current_release)
  (umask 077; mkdir -p "$dir" \
    && printf 'sha=%s\nrc=%s\nstage=%s\nat=%s\noperator=%s\nprevious=%s\n' "$1" "$2" "${3:-unknown}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$OPERATOR" "${prev:-none}" > "$dir/$1.parked.new" \
    && mv -f "$dir/$1.parked.new" "$dir/$1.parked") || printf 'cannot write %s\n' "$dir/$1.parked" >&2
  printf '\n\033[1;31m✖ release %s PARKED (stage %s, rc %s): %s\033[0m\n' "$1" "${3:-unknown}" "$2" "$dir/$1.parked" >&2
  if [ -n "$prev" ] && [ "$prev" != "$1" ]; then
    printf '  the last good release stays %s (the tree may hold the parked code until it is deployed again):\n    bash %s deploy %s\n' "$prev" "$SELF" "$prev" >&2
  else
    printf '  no earlier good release is recorded: fix the cause and run the same command again\n' >&2
  fi
  [ "${3:-}" != deployer ] || printf '  the deployer printed its own recovery above (maintenance and drained units follow its exit code)\n' >&2
  # after the switch the target's migrations may have run: the old code then meets the new schema (security review M5)
  case "${3:-}:$2" in
    deployer:4|deployer:5|deployer:6|deployer:7|frontend:*)
      printf '  WARNING: the migrations of %s may already have run. Going back deploys the old code onto the NEW schema\n' "$1" >&2
      printf '  (migrations are additive for one release); a database restore needs the owner: docs/runbooks/release-and-rollback.md, Rollback\n' >&2 ;;
  esac
}

on_exit() {
  local rc=$?
  set +e
  trap - EXIT
  if [ "$rc" -ne 0 ] && [ -n "$RELEASE_SHA" ]; then park_release "$RELEASE_SHA" "$rc" "$RELEASE_STAGE"; fi
  exit "$rc"
}

deploy() {
  local sha=${1:-} rc=0
  [ -n "$sha" ] || sha=$(latest_sha)
  [[ $sha =~ ^[0-9a-f]{40}$ ]] || die "give the full 40-character SHA"
  [ -n "$RELEASE_PREVIOUS" ] || RELEASE_PREVIOUS=$(current_release)
  RELEASE_SHA=$sha
  RELEASE_STAGE=harden; harden   # the drop-ins before the deployer restarts the units, public/build before vite
  RELEASE_STAGE=deployer
  say "Release $sha through the gated deployer (PHP $PHP)"
  REF=$sha EXPECTED_SHA=$sha DEPLOY_OPERATOR="$OPERATOR" "$DEPLOYER_BIN" || rc=$?
  case $rc in
    0) ;;
    2) fail "$rc" "rc 2: refused before anything changed (a newer deployer in the target? run: bash $SELF deployer $sha)";;
    3) fail "$rc" "rc 3: drain/backup/verify failed — nothing switched";;
    4) fail "$rc" "rc 4: build failed after the switch — run the rollback command printed above";;
    5) fail "$rc" "rc 5: doctor gate failed — site stays in maintenance; fix or extend expected-nonok, run again";;
    7) fail "$rc" "rc 7: a unit did not come back — journalctl -u <unit>; with usranalyse: is the drop-in there? (bash $SELF harden)";;
    *) fail "$rc" "rc $rc — see the output above";;
  esac
  [ "$(cut -d' ' -f1 "$APP_DIR/VERSION" 2>/dev/null)" = "$sha" ] || fail 4 "the deployer answered 0 but $APP_DIR/VERSION does not name $sha"
  ok "released: $(cat "$APP_DIR/VERSION")"
  RELEASE_STAGE=frontend
  npm_build || fail 8 "frontend build failed: the code of $sha is live, public/build is not this release's (the pages that load the bundle break)"
  release_good "$sha"
  echo "  ➜ in 6+ minutes: bash $SELF status"
}

status() {
  say "Status"
  echo "  version: $(cat "$APP_DIR/VERSION" 2>/dev/null)"; echo "  php: $("$PHP" -r 'echo PHP_VERSION;')"
  echo "  current release: $(current_release)"
  for p in "$STATE"/releases/*.parked; do [ -e "$p" ] && echo "  PARKED: $(tr '\n' ' ' < "$p")"; done
  if N=$(node24); then echo "  node: $("$N" -v)"; fi
  usranalyse_loaded && echo "  usranalyse preloaded; drop-ins: $(ls "$SYSTEMD_DIR"/*.d/"$USRANALYSE_DROPIN" 2>/dev/null | tr '\n' ' ')"
  systemctl list-units --plain --no-legend 'onhost-*' || true
  tail -1 "$STATE/deploy.log" 2>/dev/null || true
  (umask 077; art onhost:doctor --json 2>/dev/null > /root/doctor-status.json) || true
  "$PHP" -r '$d=json_decode(file_get_contents("/root/doctor-status.json"),true)?:[]; array_walk_recursive($d,function(){}); foreach(($d["checks"]??$d) as $c){ if(!is_array($c)) continue; $n=($c["area"]??"")."|".($c["check"]??$c["name"]??""); $s=$c["status"]??""; if($s!=="OK"||str_contains($n,"alive")||str_contains($n,"scheduler")) echo "  $s  $n\n"; }' || true
  curl -s -o /dev/null -w '  /up over loopback: %{http_code}\n' --resolve $SITE:443:127.0.0.1 https://$SITE/up || true
}

# a tree of this script's own, unfinished or finished, is never contained and parked as "the old staging" (audit
# 2026-10 P0-6: a re-run after a cut-short install parked the new tree and /etc/onhost, and the db password with it)
own_install() { [ -f "$STATE/installed" ] || [ -f "$STATE/installing" ] || [ -f "$STATE/setup-sha" ]; }

record_setup() { # $1 = sha: what this setup deploys, written before anything is installed
  local earlier
  earlier=$(sed -n 's/^target=//p' "$STATE/setup-sha" 2>/dev/null || true)
  [ -z "$earlier" ] || [ "$earlier" = "$1" ] || echo "  (an earlier unfinished setup targeted $earlier)"
  (umask 077; mkdir -p "$STATE") && chmod 700 "$STATE" \
    && (umask 077; printf 'target=%s\nstarted=%s\noperator=%s\n' "$1" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$OPERATOR" > "$STATE/setup-sha.new") \
    && mv -f "$STATE/setup-sha.new" "$STATE/setup-sha" || die "cannot record the setup target in $STATE/setup-sha"
}

setup() { # the whole first launch at one commit, resolved once; stops at the first ✖ and parks that commit
  local sha=${1:-} origin="given"
  if [ -z "$sha" ]; then sha=$(latest_sha); origin="tip of development"; fi
  [[ $sha =~ ^[0-9a-f]{40}$ ]] || die "no setup target: give the full 40-character SHA (development tip read as '$sha')"
  say "setup target: $sha ($origin)"
  check   # read-only: a host that fails it has changed nothing, so nothing is parked (security review L9)
  RELEASE_SHA=$sha
  if [ -d "$APP_DIR" ] && [ -n "$(ls -A "$APP_DIR" 2>/dev/null)" ] && ! own_install; then RELEASE_STAGE=contain; contain; park; fi
  [ -d "$APP_DIR" ] || install -d -o "$RUN_USER" -g "$RUN_USER" -m 0755 "$APP_DIR"
  record_setup "$sha"
  RELEASE_PREVIOUS=$(current_release)   # after a park of the old staging: its last good release is not ours
  RELEASE_STAGE=db
  su - postgres -c "psql -tAc \"select 1 from pg_roles where rolname='$NEWROLE'\"" | grep -q 1 || db
  RELEASE_STAGE=install; [ -f "$STATE/installed" ] || install_app "$sha"
  RELEASE_STAGE=deployer; deployer "$sha"
  RELEASE_STAGE=start; start
  deploy "$sha"
  printf 'deployed=%s\nfinished=%s\n' "$sha" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$STATE/setup-sha"
  say "Setup done: deployed commit $sha (recorded in $STATE/setup-sha)"
}

main() {
  [ "$(id -u)" = 0 ] || die "run as root"
  cd / || die "cannot enter /"   # a working directory deleted under the shell makes git fatal (staging 2026-09-28)
  trap on_exit EXIT
  case "${1:-}" in
    setup) setup "${2:-}";;
    check) check;; contain) contain;; park) park;; db) db;; install) install_app "${2:-}";; env) envfill;;
    deployer) deployer "${2:-}";; harden) harden;; start) start;; deploy) deploy "${2:-}";; status) status;;
    *) sed -n '2,32p' "$SELF"; exit 1;;
  esac
}

# sourced (the tests replace host steps), nothing runs; executed, the mode runs
[ "${BASH_SOURCE[0]}" != "$0" ] || main "$@"
