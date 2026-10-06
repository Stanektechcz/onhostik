#!/usr/bin/env bash
# ONhost control plane — FIRST installation on an aaPanel host (docs/runbooks/deploy-aapanel.md). Run as root after
# aaPanel has PHP 8.3, PostgreSQL, Redis and the site (staging.onhost.cz by default) created:
#
#   REF=<sha|tag> EXPECTED_SHA=<40-hex sha> [START_UNITS=0] bash onhost-install.sh
#
# Run 1 fetches the repository into root's $DEPLOY_GIT_DIR (outside the site tree: review round 2, security HIGH),
# checks EXPECTED_SHA out into $APP_DIR, writes /etc/onhost/app.env from .env.example and stops; run 2 installs
# (composer, key, migrations, seed, systemd units, caches). Every later release goes through the gated deployer
# (install-deployer.sh → /usr/local/sbin/onhost-deploy). An installed site — marked `installed` in the state dir, or
# a pre-marker install recognised by its APP_KEY — is refused: re-seeding a live database is not a repair.
#
#   INSTALL_REPAIR=1 bash onhost-install.sh      (an installed site only; no REF/EXPECTED_SHA)
#
# hands over what in storage/bootstrap/cache is not the run user's and re-renders the systemd units from the checked-out
# revision in root's repository. It never migrates or seeds, and it does NOT change whether a unit runs or starts at boot: with START_UNITS=0
# (the repair's default) units are rendered only — never enabled, started or restarted. Review round 3 stopped the
# repair from starting units; the pre-mortem of 2026-09-27 found that `enable` alone put every provider lane of a
# contained staging back into the boot sequence (the next reboot restarted the workers that talk to live panels).
# START_UNITS=1 enables and starts them. A first install writes $DEPLOY_STATE_DIR/expected-units (the scheduler and
# every lane of QUEUES) for the gated deployer; staging phase 1 installs with QUEUES='default mails'.
set -euo pipefail

# Nothing this script starts may hold root's terminal (review round 1, security HIGH): as_run puts the site's PHP in a
# session of its own (setsid, no TIOCSTI into root's shell — CVE-2016-2779's class), stdin is /dev/null and a terminal
# on stdout/stderr is reached only through root's own cat (a process www leaves behind cannot read what root types
# next). Runs first. The deployer's function, character for character.
keep_terminal_from_children() {
  exec </dev/null
  if [ -t 1 ] && [ -t 2 ]; then
    exec > >(trap '' INT; exec cat) 2>&1
  elif [ -t 1 ]; then
    exec > >(trap '' INT; exec cat)
  elif [ -t 2 ]; then
    exec 2> >(trap '' INT; exec cat >&2)
  fi
}
keep_terminal_from_children

SITE="${SITE:-staging.onhost.cz}"                     # the aaPanel site (staging.onhost.cz for testing, onhost.cz for production)
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"           # aaPanel site root (the repository checkout; nginx serves $APP_DIR/public)
REPO="${REPO:-https://github.com/Stanektechcz/onhostik.git}"
PHP="${PHP:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
RUN_USER="${RUN_USER:-www}"                            # aaPanel's PHP-FPM user
ENV_DIR="${ENV_DIR:-/etc/onhost}"
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/${SITE}}"
DEPLOY_GIT_DIR="${DEPLOY_GIT_DIR:-${DEPLOY_STATE_DIR}/repo.git}"   # root's repository (the deployer's); $APP_DIR is only its work tree
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"              # who must own the repository (root; tests run unprivileged)
INSTALL_REPAIR="${INSTALL_REPAIR:-0}"
START_UNITS="${START_UNITS:-}"                         # install: 1 unless 0 (containment first) · repair: 0 unless 1
QUEUES="${QUEUES:-default mails provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes provider-penpot}"
DEPLOY_SAFE_PATH="${DEPLOY_SAFE_PATH:-/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin}"
DEPLOY_WORK_DIR="${DEPLOY_WORK_DIR:-/var/cache/onhost-deploy/${SITE}}"   # the run user's HOME and composer cache (the deployer's too)
USRANALYSE_PRELOAD_FILE="${USRANALYSE_PRELOAD_FILE:-/etc/ld.so.preload}"   # where aaPanel preloads its security module
USRANALYSE_DROPIN=10-aapanel-usranalyse.conf

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 2; }
warn() { printf '\033[1;33mWARN %s\033[0m\n' "$*" >&2; }
need() { command -v "$1" >/dev/null 2>&1 || die "missing: $1"; }
art() { as_run "$PHP" "$APP_DIR/artisan" "$@"; }

# The site's PHP — artisan, composer and the scripts composer runs — never runs as root (review round 0, security HIGH;
# D32.13 until then): app.env is root:www 0640 and www can write this tree (all of it on an existing staging; vendor/,
# bootstrap/cache and compiled views everywhere), so root executing that code made a www compromise root at the next
# release. setpriv switches to the run user (aaPanel kills `sudo -u www`), setsid --wait gives it a session of its own
# without a controlling terminal (review round 1, security HIGH), env -i a fixed environment, stdin is /dev/null, and
# fd 9 (the deployer's lock; unused here) is closed. The three functions below are the deployer's, character for
# character.
as_run() {
  setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups -- \
    setsid --wait env -i PATH="$DEPLOY_SAFE_PATH" HOME="$DEPLOY_WORK_DIR/home" "$@" </dev/null 9>&-
}
# The run user's own space outside the tree: HOME, the composer cache, a release's framework caches. Its parent is
# root's and writable by nobody else, so www cannot swap the directory for a link; root makes it and reads nothing
# from it.
work_dir_ready() {
  local parent
  parent="$(dirname "$DEPLOY_WORK_DIR")"
  (umask 022; mkdir -p "$parent") \
    && [ -z "$(find -P "$parent" -maxdepth 0 \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ] || return 1
  if [ ! -e "$DEPLOY_WORK_DIR" ] && [ ! -L "$DEPLOY_WORK_DIR" ]; then
    (umask 077; mkdir "$DEPLOY_WORK_DIR") && chown -h "$RUN_USER:$RUN_USER" "$DEPLOY_WORK_DIR" || return 1
  fi
  [ -d "$DEPLOY_WORK_DIR" ] && [ ! -L "$DEPLOY_WORK_DIR" ] \
    && [ -n "$(find -P "$DEPLOY_WORK_DIR" -maxdepth 0 -user "$RUN_USER" -print)" ] \
    && as_run mkdir -p "$DEPLOY_WORK_DIR/home"
}
# vendor/ is the run user's, because composer runs as it. Absent (a first build), root makes the empty directory and
# hands it over; present, it must be a real directory the run user owns entirely — a root-owned vendor/ of an older
# root build is handed over by hand (staging-launch.md S1b), never by a recursive chown here.
vendor_ready() {
  if [ ! -e "$APP_DIR/vendor" ] && [ ! -L "$APP_DIR/vendor" ]; then
    mkdir "$APP_DIR/vendor" && chown -h "$RUN_USER:$RUN_USER" "$APP_DIR/vendor" || return 1
  fi
  [ -d "$APP_DIR/vendor" ] && [ ! -L "$APP_DIR/vendor" ] \
    && [ -z "$(find -P "$APP_DIR/vendor" ! -user "$RUN_USER" -print -quit 2>/dev/null)" ]
}

# storage and bootstrap/cache belong to the PHP-FPM user; the code tree stays root's. www can write $APP_DIR, so either
# directory (or bootstrap itself) could be swapped for a symlink: refuse a link, walk with find -P. Review round 2
# (security HIGH): root no longer chowns every entry — only those not owned by the run user, each from inside its
# directory (-execdir, one path component, -h), and `o-rwx` is set by the run user itself. A first install hands over
# what root's checkout wrote; INSTALL_REPAIR hands over what an older root run left (the one-time S1b step). The two
# functions below are the deployer's, character for character (DeployGateTest compares them).
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
fix_owner() { repair_ownership || die "ownership hand-over refused or failed: storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR"; }
# git on root's repository, never on a .git in the site tree (review round 2, security HIGH): $APP_DIR stays writable by
# www, so www could rename a root-owned $APP_DIR/.git after the preflight looked at it and put its own there — config
# (filters, textconv, sshCommand, url rewrites), info/attributes, alternates and hooks that root's fetch, status, diff
# and checkout would then obey, and the old global config named $APP_DIR safe.directory, which switched git's own
# ownership check off. GIT_DIR names the root-only repository under the root-only state dir, so git never looks for a
# .git in the tree; the tree is only the work tree. No system or global config is read (no safe.directory for a
# www-owned path), no hooks, no fsmonitor. What the work tree itself can still carry (a .gitattributes, a nested
# .gitignore) runs nothing without a config that names a driver, and changes only files www can write anyway.
# Identical in install.sh and install-deployer.sh.
g() { GIT_DIR="$DEPLOY_GIT_DIR" GIT_WORK_TREE="$APP_DIR" GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }
# the repository is a real directory, owned by DEPLOY_OWNER_UID throughout and writable by nobody else. Identical in
# install.sh and install-deployer.sh.
repo_is_roots() { [ -d "$DEPLOY_GIT_DIR" ] && [ ! -L "$DEPLOY_GIT_DIR" ] && [ -z "$(find -P "$DEPLOY_GIT_DIR" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]; }

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

# The unit files come out of root's repository at revision $1, never from the working tree: www can replace entries of
# $APP_DIR, and a unit file it wrote (User=root, its own ExecStart) would run as root at the next start (review round 3).
install_units() { # $1 = revision
  local unit q
  for unit in onhost-queue@.service onhost-scheduler.service; do
    g show "$1:infra/systemd/$unit" \
      | sed -e "s#/var/www/onhost#${APP_DIR}#g" -e "s#/usr/bin/php#${PHP}#g" -e "s#User=onhost#User=${RUN_USER}#" -e "s#Group=onhost#Group=${RUN_USER}#" -e "s#/etc/onhost/app.env#${ENV_DIR}/app.env#" \
      > "$SYSTEMD_DIR/$unit.new" && mv -f "$SYSTEMD_DIR/$unit.new" "$SYSTEMD_DIR/$unit" || die "cannot render $unit from $1"
  done
  usranalyse_dropins onhost-queue@.service onhost-scheduler.service || die "cannot write the usranalyse drop-ins under $SYSTEMD_DIR"
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
# the site's PHP runs as the run user, never as root (as_run; review round 0, security HIGH) — the repair needs
# it too: the run user itself sets o-rwx on what is handed over
need setpriv
need setsid
run_uid="$(id -u "$RUN_USER" 2>/dev/null || true)"
[[ "$run_uid" =~ ^[0-9]+$ ]] && [ "$run_uid" != 0 ] || die "RUN_USER '$RUN_USER' must be an existing user other than root: the site's PHP never runs as root"
[ "$(as_run id -u 2>/dev/null || true)" = "$run_uid" ] || die "cannot run the site's PHP as $RUN_USER (setpriv, and setsid --wait from util-linux 2.31+, run as root)"
if usranalyse_loaded; then
  warn "aaPanel's usranalyse module is preloaded ($USRANALYSE_PRELOAD_FILE): it crashes every process of $RUN_USER that systemd starts under ProtectSystem= (the workers die with SIGSEGV, a deploy ends with rc 7)."
  warn "  this script writes $SYSTEMD_DIR/onhost-queue@.service.d/$USRANALYSE_DROPIN and $SYSTEMD_DIR/onhost-scheduler.service.d/$USRANALYSE_DROPIN (ProtectSystem=no plus hardening that keeps / writable: kernel tunables, modules, cgroups, no set-uid, no capabilities; NoNewPrivileges and PrivateTmp stay) when it renders the units, and removes them once the module is gone."
  warn "  verify each directive on this host; one that brings the crash back goes into $DEPLOY_STATE_DIR/usranalyse-omit (docs/runbooks/staging-aapanel.md)"
fi

# An installed site is never installed again (the old script re-ran `db:seed` on a live database)
if [ -f "$DEPLOY_STATE_DIR/installed" ] || { [ ! -f "$DEPLOY_STATE_DIR/installing" ] && grep -qE '^APP_KEY=base64:' "$ENV_DIR/app.env" 2>/dev/null; }; then
  if [ "$INSTALL_REPAIR" = 1 ]; then
    START_UNITS="${START_UNITS:-0}"
    say "Repair only: storage/bootstrap ownership and the systemd units (no migrations, no seed, START_UNITS=$START_UNITS)"
    tree_is_real || die "storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR (a symlink would let root re-own another tree): nothing repaired"
    repo_is_roots || die "$DEPLOY_GIT_DIR must be root's repository, owned by uid $DEPLOY_OWNER_UID throughout and writable by nobody else (staging-launch.md S0/S1b move an older install to it); the units are rendered from it: nothing repaired"
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
# the repository is root's, outside the site tree (review round 2, security HIGH): made here on a first run — never a
# .git in $APP_DIR (www can replace entries there), never a repository someone else left
[ -e "$APP_DIR" ] || mkdir -p "$APP_DIR"
[ -d "$APP_DIR" ] && [ ! -L "$APP_DIR" ] || die "$APP_DIR is not a directory"
if [ ! -e "$DEPLOY_GIT_DIR" ] && [ ! -L "$DEPLOY_GIT_DIR" ]; then
  (umask 077; GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git init -q --bare "$DEPLOY_GIT_DIR") \
    && g config core.bare false && g remote add origin "$REPO" || die "cannot create the repository $DEPLOY_GIT_DIR"
fi
repo_is_roots || die "$DEPLOY_GIT_DIR must be root's repository, owned by uid $DEPLOY_OWNER_UID throughout and writable by nobody else"
{ [ -e "$APP_DIR/.git" ] || [ -L "$APP_DIR/.git" ]; } && warn "$APP_DIR/.git exists and is ignored (git runs on $DEPLOY_GIT_DIR): take it out of the tree (staging-launch.md S1b)"
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

if ! grep -qE '^APP_KEY=base64:' "$ENV_DIR/app.env"; then
  say "Application key"
  # generated and written by root into the root-owned file www only reads: `artisan key:generate` would have been the
  # site's PHP as root, and as www it cannot write app.env (review round 0, security HIGH)
  key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  (umask 077; awk -v k="$key" '/^APP_KEY=/ { if (!done) print "APP_KEY=" k; done = 1; next } { print } END { if (!done) print "APP_KEY=" k }' \
      "$ENV_DIR/app.env" > "$ENV_DIR/app.env.new") \
    && chown root:"$RUN_USER" "$ENV_DIR/app.env.new" && chmod 640 "$ENV_DIR/app.env.new" && mv -f "$ENV_DIR/app.env.new" "$ENV_DIR/app.env" \
    || die "cannot write APP_KEY into $ENV_DIR/app.env"
fi

say "Permissions: storage, bootstrap/cache and vendor belong to $RUN_USER, who runs the site's PHP from here on"
fix_owner
work_dir_ready || die "$DEPLOY_WORK_DIR must be a directory of $RUN_USER's whose parent only uid $DEPLOY_OWNER_UID can write"
vendor_ready || die "$APP_DIR/vendor must be a real directory owned entirely by $RUN_USER, who runs composer (hand it over: staging-launch.md S1b)"

say "Composer (production, no dev packages; as $RUN_USER)"
as_run COMPOSER_HOME="$DEPLOY_WORK_DIR/home/.composer" "$PHP" "$COMPOSER" install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader

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
# the committed contract is never rewritten here: generated into storage and compared (the same as deploy.sh)
if art onhost:openapi --out=storage/framework/openapi-generated.yaml >/dev/null; then
  cmp -s "$APP_DIR/contracts/openapi/onhost-v1.yaml" "$APP_DIR/storage/framework/openapi-generated.yaml" || warn "the committed contracts/openapi/onhost-v1.yaml differs from what the routes generate: run 'php artisan onhost:openapi' in development and commit it"
else
  warn "onhost:openapi failed: the committed contract could not be compared with the routes"
fi
as_run rm -f "$APP_DIR/storage/framework/openapi-generated.yaml"
# VERSION sits in a directory www owns: written in the root-only state dir and renamed over whatever name is there
(umask 022; printf '%s %s\n' "$EXPECTED_SHA" "$REF" > "$DEPLOY_STATE_DIR/VERSION.new") && mv -fT "$DEPLOY_STATE_DIR/VERSION.new" "$APP_DIR/VERSION"

say "nginx site snippet"
echo "   → paste infra/aapanel/nginx-site.conf into aaPanel → Website → ${SITE} → Config (see the runbook); root = $APP_DIR/public"

fix_owner # root's checkout may have written tracked files under storage; storage and bootstrap/cache belong to the PHP-FPM user
: > "$DEPLOY_STATE_DIR/installed"
rm -f "$DEPLOY_STATE_DIR/installing"

say "Doctor (informational — install.sh does not gate; every release goes through the gated deployer)"
art onhost:doctor || echo "   (the doctor reports findings above; fix them before the first gated deploy)"
echo
echo "Next: docs/runbooks/staging-launch.md — install the deployer (install-deployer.sh), staff (onhost:staff:create), first gated deploy."
