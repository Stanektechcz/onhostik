#!/usr/bin/env bash
# ONhost control plane — FIRST installation on an aaPanel host (docs/runbooks/deploy-aapanel.md). Run as root after
# aaPanel has PHP 8.3, PostgreSQL, Redis and the site (staging.onhost.cz by default) created:
#
#   REF=<sha|tag> EXPECTED_SHA=<40-hex sha> [START_UNITS=0] bash onhost-install.sh
#
# Run 1 clones the repository at EXPECTED_SHA, writes /etc/onhost/app.env from .env.example and stops; run 2 installs
# (composer, key, migrations, seed, systemd units, caches). Every later release goes through the gated deployer
# (install-deployer.sh → /usr/local/sbin/onhost-deploy). An installed site — marked `installed` in the state dir, or
# a pre-marker install recognised by its APP_KEY — is refused: re-seeding a live database is not a repair.
#
#   INSTALL_REPAIR=1 bash onhost-install.sh      (an installed site only; no REF/EXPECTED_SHA)
#
# repairs storage/bootstrap ownership and re-renders the systemd units from the checked-out revision in the root-owned
# .git. It never migrates or seeds, and it does NOT change whether a unit runs or starts at boot: with START_UNITS=0
# (the repair's default) units are rendered only — never enabled, started or restarted. Review round 3 stopped the
# repair from starting units; the pre-mortem of 2026-09-27 found that `enable` alone put every provider lane of a
# contained staging back into the boot sequence (the next reboot restarted the workers that talk to live panels).
# START_UNITS=1 enables and starts them. A first install writes $DEPLOY_STATE_DIR/expected-units (the scheduler and
# every lane of QUEUES) for the gated deployer; staging phase 1 installs with QUEUES='default mails'.
set -euo pipefail

SITE="${SITE:-staging.onhost.cz}"                     # the aaPanel site (staging.onhost.cz for testing, onhost.cz for production)
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"           # aaPanel site root (the repository checkout; nginx serves $APP_DIR/public)
REPO="${REPO:-https://github.com/Stanektechcz/onhostik.git}"
PHP="${PHP:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
RUN_USER="${RUN_USER:-www}"                            # aaPanel's PHP-FPM user
ENV_DIR="${ENV_DIR:-/etc/onhost}"
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/${SITE}}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"              # who must own .git (root; tests run unprivileged)
INSTALL_REPAIR="${INSTALL_REPAIR:-0}"
START_UNITS="${START_UNITS:-}"                         # install: 1 unless 0 (containment first) · repair: 0 unless 1
QUEUES="${QUEUES:-default mails provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes}"
SAFE_PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 2; }
warn() { printf '\033[1;33mWARN %s\033[0m\n' "$*" >&2; }
need() { command -v "$1" >/dev/null 2>&1 || die "missing: $1"; }
g() { GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL="${DEPLOY_STATE_DIR}/gitconfig" git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }
art() { env -i PATH="$SAFE_PATH" HOME=/root "$PHP" "$APP_DIR/artisan" "$@"; }

# storage and bootstrap/cache belong to the PHP-FPM user; the code tree and .git stay root's. www can write $APP_DIR, so
# either directory (or bootstrap itself) could be swapped for a symlink, and `chown -R`/`chmod -R` run as root follow one
# given on the command line. Review round 3: fix_owner still ran those plain recursive commands, on the INSTALL_REPAIR
# path too. The two functions below are the deployer's, character for character (DeployGateTest compares them).
tree_is_real() { # the two directories are real directories where the checkout says they are
  local d real_app
  real_app="$(realpath "$APP_DIR")" || return 1
  for d in storage bootstrap bootstrap/cache; do
    [ ! -L "$APP_DIR/$d" ] && [ -d "$APP_DIR/$d" ] && [ "$(realpath "$APP_DIR/$d")" = "$real_app/$d" ] || return 1
  done
}
repair_ownership() {
  tree_is_real || { warn "storage, bootstrap or bootstrap/cache is a symlink or missing: ownership NOT repaired"; return 1; }
  find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -exec chown -h "$RUN_USER:$RUN_USER" {} + \
    && find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" ! -type l -exec chmod u+rwX,g+rX,o-rwx {} +
}
fix_owner() { repair_ownership || die "ownership repair refused: storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR"; }
git_is_roots() { [ -d "$APP_DIR/.git" ] && [ ! -L "$APP_DIR/.git" ] && [ -z "$(find -P "$APP_DIR/.git" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]; }

# The unit files come out of the root-owned .git at revision $1, never from the working tree: www can replace entries of
# $APP_DIR, and a unit file it wrote (User=root, its own ExecStart) would run as root at the next start (review round 3).
install_units() { # $1 = revision
  local unit q
  for unit in onhost-queue@.service onhost-scheduler.service; do
    g show "$1:infra/systemd/$unit" \
      | sed -e "s#/var/www/onhost#${APP_DIR}#g" -e "s#/usr/bin/php#${PHP}#g" -e "s#User=onhost#User=${RUN_USER}#" -e "s#Group=onhost#Group=${RUN_USER}#" -e "s#/etc/onhost/app.env#${ENV_DIR}/app.env#" \
      > "$SYSTEMD_DIR/$unit.new" && mv -f "$SYSTEMD_DIR/$unit.new" "$SYSTEMD_DIR/$unit" || die "cannot render $unit from $1"
  done
  systemctl daemon-reload
  if [ "$START_UNITS" = 1 ]; then
    systemctl enable --now onhost-scheduler.service
    for q in $QUEUES; do systemctl enable --now "onhost-queue@${q}.service"; done
  else
    echo "   units rendered, NOT enabled or started (START_UNITS=0): enable the ones docs/runbooks/staging-launch.md O12 names, after its containment steps"
  fi
}
# the units the gated deployer requires after every release (deploy.sh preflight); written once, never by the repair —
# the operator's list (staging-launch.md O12) wins
write_expected_units() {
  local q
  [ -f "$DEPLOY_STATE_DIR/expected-units" ] && return 0
  (umask 077
    { echo "# units that must run after every release (infra/aapanel/deploy.sh; docs/runbooks/staging-launch.md O12)"
      echo onhost-scheduler.service
      for q in $QUEUES; do echo "onhost-queue@${q}.service"; done; } > "$DEPLOY_STATE_DIR/expected-units")
}

say "Checking the host"
need git; need curl
[ -x "$PHP" ] || die "PHP not found at $PHP (aaPanel: App Store → PHP 8.3)"
[ -n "${BRANCH:-}" ] && die "BRANCH is no longer accepted: pass REF=<sha|tag> and EXPECTED_SHA=<sha>"
(umask 077; mkdir -p "$DEPLOY_STATE_DIR") && chmod 700 "$DEPLOY_STATE_DIR"
[ -f "$DEPLOY_STATE_DIR/gitconfig" ] || (umask 077; printf '[safe]\n\tdirectory = %s\n' "$APP_DIR" > "$DEPLOY_STATE_DIR/gitconfig")

# An installed site is never installed again (the old script re-ran `db:seed` on a live database)
if [ -f "$DEPLOY_STATE_DIR/installed" ] || { [ ! -f "$DEPLOY_STATE_DIR/installing" ] && grep -qE '^APP_KEY=base64:' "$ENV_DIR/app.env" 2>/dev/null; }; then
  if [ "$INSTALL_REPAIR" = 1 ]; then
    START_UNITS="${START_UNITS:-0}"
    say "Repair only: storage/bootstrap ownership and the systemd units (no migrations, no seed, START_UNITS=$START_UNITS)"
    tree_is_real || die "storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR (a symlink would let root re-own another tree): nothing repaired"
    git_is_roots || die "$APP_DIR/.git must be a directory owned by uid $DEPLOY_OWNER_UID and writable by nobody else (staging-launch.md S1b); the units are rendered from it: nothing repaired"
    fix_owner
    install_units HEAD
    exit 0
  fi
  die "$SITE is already installed (state marker or APP_KEY in $ENV_DIR/app.env): releases go through /usr/local/sbin/onhost-deploy; INSTALL_REPAIR=1 repairs ownership and units only"
fi
[ "$INSTALL_REPAIR" = 1 ] && die "INSTALL_REPAIR=1 repairs an installed site; $SITE is not installed"
START_UNITS="${START_UNITS:-1}"

[[ "${EXPECTED_SHA:-}" =~ ^[0-9a-f]{40}$ ]] || die "EXPECTED_SHA must be the full 40-character SHA to install"
REF="${REF:-$EXPECTED_SHA}"
[[ "$REF" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]{0,127}$ ]] && [[ "$REF" != *..* ]] || die "REF '$REF' is not a plain ref name"
has_ext() { "$PHP" -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$1"; }   # php -m output differs between builds; ask PHP itself
has_ext pdo_pgsql || die "PHP 8.3 needs pdo_pgsql (aaPanel → PHP 8.3 → Install extensions); loaded PDO drivers: $("$PHP" -r 'echo implode(",", PDO::getAvailableDrivers());')"
for ext in intl bcmath mbstring openssl redis fileinfo zip gd opcache; do has_ext "$ext" || echo "warning: PHP extension ${ext} missing — install it in aaPanel (PHP 8.3 → extensions)"; done

if [ ! -x "$COMPOSER" ]; then
  say "Installing Composer (installer checked against composer.github.io/installer.sig)"
  tmp="$(mktemp -d)"
  expected="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php"
  actual="$("$PHP" -r 'echo hash_file("sha384", $argv[1]);' "$tmp/composer-setup.php")"
  [ -n "$expected" ] && [ "$expected" = "$actual" ] || { rm -rf "$tmp"; die "the Composer installer does not match its published signature; install Composer by hand at $COMPOSER"; }
  "$PHP" "$tmp/composer-setup.php" --install-dir="$(dirname "$COMPOSER")" --filename="$(basename "$COMPOSER")"
  rm -rf "$tmp"
fi

say "Checkout in $APP_DIR ($REF = $EXPECTED_SHA)"
if [ ! -d "$APP_DIR/.git" ]; then
  mkdir -p "$(dirname "$APP_DIR")"
  [ -L "$APP_DIR/.git" ] && die "$APP_DIR/.git is a symlink"
  git clone -q --no-checkout "$REPO" "$APP_DIR"
  chmod 700 "$APP_DIR/.git"
fi
# root's clone, never a .git someone else left (a re-run of an unfinished install): review it and hand it to root by hand
git_is_roots || die "$APP_DIR/.git must be a directory owned by uid $DEPLOY_OWNER_UID and writable by nobody else (review its hooks and config, then staging-launch.md S1b)"
g fetch -q --prune --tags origin
sha="$(g rev-parse -q --verify "${REF}^{commit}" 2>/dev/null || g rev-parse -q --verify "refs/remotes/origin/${REF}^{commit}" 2>/dev/null || true)"
[ "$sha" = "$EXPECTED_SHA" ] || die "REF '$REF' resolves to '${sha:-nothing}', not EXPECTED_SHA $EXPECTED_SHA"
g checkout -q -f --detach "$EXPECTED_SHA"
cd "$APP_DIR" || die "cannot enter $APP_DIR"

say "Environment file $ENV_DIR/app.env"
mkdir -p "$ENV_DIR"
if [ ! -f "$ENV_DIR/app.env" ]; then
  cp .env.example "$ENV_DIR/app.env"
  chmod 600 "$ENV_DIR/app.env"
  echo "   → fill $ENV_DIR/app.env (see docs/runbooks/deploy-aapanel.md § 'Údaje k doplnění'), then run this script again"
  exit 0
fi
grep -qE '^DB_PASSWORD=.+' "$ENV_DIR/app.env" || die "DB_PASSWORD is empty in $ENV_DIR/app.env — fill the file first"
ln -sfn "$ENV_DIR/app.env" "$APP_DIR/.env"
# PHP-FPM runs as the web user: it must read the environment when the config cache is cleared (root owns, group reads, nobody else)
chown root:"$RUN_USER" "$ENV_DIR" "$ENV_DIR/app.env" && chmod 750 "$ENV_DIR" && chmod 640 "$ENV_DIR/app.env"
: > "$DEPLOY_STATE_DIR/installing"   # from here a re-run continues an unfinished install; `installed` ends it

say "Composer (production, no dev packages)"
env -i PATH="$SAFE_PATH" HOME=/root COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$COMPOSER" install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader

if ! grep -qE '^APP_KEY=base64:' "$ENV_DIR/app.env"; then
  say "Application key"
  art key:generate --force
fi

say "Permissions"
fix_owner

say "Database: migrations and seed (catalogue, tax rules, notification templates, legal entity from the env)"
art migrate --force
art db:seed --force
art db:seed --class=NotificationTemplateSeeder --force

say "systemd: queue workers, scheduler"
install_units "$EXPECTED_SHA"
write_expected_units

say "Caches"
art config:cache
art route:cache
art event:cache
art onhost:openapi >/dev/null || echo "warning: onhost:openapi failed; the published contract may be stale"
# VERSION sits in a directory www owns: written in the root-only state dir and renamed over whatever name is there
(umask 022; printf '%s %s\n' "$EXPECTED_SHA" "$REF" > "$DEPLOY_STATE_DIR/VERSION.new") && mv -fT "$DEPLOY_STATE_DIR/VERSION.new" "$APP_DIR/VERSION"

say "nginx site snippet"
echo "   → paste infra/aapanel/nginx-site.conf into aaPanel → Website → ${SITE} → Config (see the runbook); root = $APP_DIR/public"

fix_owner # everything above ran as root (aaPanel refuses sudo -u www); storage and bootstrap/cache belong to the PHP-FPM user
: > "$DEPLOY_STATE_DIR/installed"
rm -f "$DEPLOY_STATE_DIR/installing"

say "Doctor (informational — install.sh does not gate; every release goes through the gated deployer)"
art onhost:doctor || echo "   (the doctor reports findings above; fix them before the first gated deploy)"
echo
echo "Next: docs/runbooks/staging-launch.md — install the deployer (install-deployer.sh), staff (onhost:staff:create), first gated deploy."
