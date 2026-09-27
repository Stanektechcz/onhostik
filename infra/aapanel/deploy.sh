#!/usr/bin/env bash
# ONhost control plane — the gated release on the aaPanel host (docs/runbooks/release-and-rollback.md, TASK-0032).
#
# Installed root-owned as /usr/local/sbin/onhost-deploy by install-deployer.sh (never run from the site tree: the
# target revision must not bring its own judge). Run as root:
#
#   REF=<tag|sha> EXPECTED_SHA=<40-hex sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy
#
# Stages: preflight → drain → down → backup + verify → switch + build → ownership → gate → start → up → public check
# → record. Nothing changes before the drain; the site is down (503) only from `down` to `up`. The gate judges the
# target's `onhost:doctor --json` by row name (deploy-gate.php: HARD rows never pass, GATED rows only with a logged,
# SHA-bound ALLOW_DOCTOR_FAIL outside production or an `Accept-Gate:` line in the signed tag) and asks the site
# itself for /up and /v1/status through the maintenance bypass. Before 2026-09-27 the doctor ran after the restarts
# as `onhost:doctor || true` and could stop nothing (onboarding audit C12/C14).
#
# Three root-owned lists in the state dir (pre-mortem 2026-09-27, docs/runbooks/staging-launch.md O11/O12/S3):
# expected-units — the units that must run after every release (every environment; install.sh writes it on a first
# install): preflight refuses when one is not enabled or not running, or when an onhost unit runs that it does not
# name, and the live stage fails with 7 when one does not come back. Outside production also expected-nonok — the
# doctor rows staging runs non-OK on purpose; any other non-OK row stops the release — and expected-env, the spec
# `deploy-gate.php env-assert` holds the environment file to (containment: test merchant, mail to the log, customer
# destinations denied …). In production every FAIL row stops the release unless the signed tag accepts it. Outside
# production a fourth, egress-blocked: the addresses the run user must not reach (a contained staging's live panels);
# each is connected to before the release, which is refused while one answers (review round 0, security MEDIUM).
#
# Root runs git, renames VERSION into place (an entry of $APP_DIR itself) and repairs ownership — never the site's
# PHP. Every artisan and composer call runs as RUN_USER through setpriv with a clean environment (review round 0,
# security HIGH: app.env is root:www 0640 and www can write the tree, so root running its code made a www compromise
# root).
#
# Exit codes: 0 released · 2 preflight refused (nothing changed) · 3 drain, backup or verify failed (nothing switched,
# units started again, site up again when this run took it down) · 4 build failed after the switch · 5 gate failed ·
# 6 the public /up check failed after `up` · 7 a drained unit did not come back after the start (they are all stopped
# again). After 4, 5 and 7 the site stays in maintenance and the drained units stay stopped; the script prints the
# recovery command (last good release) — there is no automatic rollback.
set -euo pipefail

# Nothing this script starts may hold root's terminal (review round 1, security HIGH). as_run puts the site's PHP in a
# session of its own (setsid): no controlling terminal, so it cannot push keystrokes into root's shell (TIOCSTI, the
# su/runuser class, CVE-2016-2779). A terminal merely inherited on fd 0-2 would still let a process www leaves behind
# read what root types next (an SSH login's fds 0-2 are one read-write file), so stdin is /dev/null and a terminal
# on stdout/stderr is reached only through root's own cat, which ignores SIGINT so a Ctrl-C still shows the recovery
# lines. Runs first, before any fd is opened. Kept identical in install.sh.
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

SITE="${SITE:-staging.onhost.cz}"
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"
ENV_FILE="${ENV_FILE:-${ENV_DIR:-/etc/onhost}/app.env}"   # root-owned; $APP_DIR/.env must BE this file (install.sh symlinks it)
PHP="${PHP:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
RUN_USER="${RUN_USER:-www}"
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/${SITE}}"
DEPLOY_LIB_DIR="${DEPLOY_LIB_DIR:-/usr/local/lib/onhost-deploy}"
PHP_FPM_RELOAD="${PHP_FPM_RELOAD:-}"            # e.g. '/etc/init.d/php-fpm-83 reload' (opcache would keep serving old code)
DEPLOY_HTTP_IP="${DEPLOY_HTTP_IP:-127.0.0.1}"   # where nginx answers https://$SITE on this host (the vhost lets loopback past basic auth)
DEPLOY_HTTP_BASE="${DEPLOY_HTTP_BASE:-}"        # tests only: a plain base URL (php -S) instead of https://$SITE via --resolve
DRAIN_TIMEOUT="${DRAIN_TIMEOUT:-300}"
UNIT_SETTLE="${UNIT_SETTLE:-5}"                 # seconds a started unit gets before it must be active (a crash on boot shows by then)
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"       # who must own .git and allowed_signers (root; tests run unprivileged)
DEPLOY_WORK_DIR="${DEPLOY_WORK_DIR:-/var/cache/onhost-deploy/${SITE}}"   # the run user's HOME, composer cache and build caches
DEPLOY_SAFE_PATH="${DEPLOY_SAFE_PATH:-/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin}"
PROBE_TIMEOUT="${PROBE_TIMEOUT:-5}"             # seconds a connection to an egress-blocked address may take to fail
OPENAPI_PATH="contracts/openapi/onhost-v1.yaml"  # regenerated by every build (onhost:openapi): the one tracked file allowed to differ

STAGE=preflight
SWITCHED=0
PROD=1
SHA=""
TAG=""
TAG_OID=""
FROM=""
SET=""
OVERRIDE=""
OVERRIDE_REASON=""
SKIP_REASON=""
DRAINED_NOW=""
PREV_DRAINED=""
WAS_DOWN=0
MARKER_BY_THIS_RUN=0
WENT_DOWN=0
DRAIN_S=""
DOWN_AT=""
WINDOW_S=""
ACCEPTED=""
RUN_DIR=""
JAR=""
APP_ENV_VALUE=""
BOOT_CACHE=""
BOOT_ENV=()
EXPECTED_UNITS=""
EXPECTED_NONOK_SUM=""
DEPLOY_LOG="${DEPLOY_STATE_DIR}/deploy.log"
MARKER="${DEPLOY_STATE_DIR}/down-by-deploy"
DRAINED_FILE="${DEPLOY_STATE_DIR}/drained-units"
EXPECTED_UNITS_FILE="${DEPLOY_STATE_DIR}/expected-units"
EXPECTED_NONOK_FILE="${DEPLOY_STATE_DIR}/expected-nonok"
EXPECTED_ENV_FILE="${DEPLOY_STATE_DIR}/expected-env"
EGRESS_BLOCKED_FILE="${DEPLOY_STATE_DIR}/egress-blocked"

say() { printf '\n\033[1;32m▶ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33mWARN %s\033[0m\n' "$*" >&2; }
die() { local rc=$1; shift; printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit "$rc"; }

# git without anything the (www-writable) tree could inject: no system config, a root-owned global config that only
# names this checkout safe, no hooks, no fsmonitor
g() { GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL="${DEPLOY_STATE_DIR}/gitconfig" git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }
# The site's PHP — artisan, composer and the scripts composer runs — never runs as root (review round 0, security HIGH;
# D32.13 until then): app.env is root:www 0640 and www can write this tree (all of it on an existing staging; vendor/,
# bootstrap/cache and compiled views everywhere), so root executing that code made a www compromise root at the next
# release. setpriv switches to the run user (aaPanel kills `sudo -u www`), setsid --wait gives it a session of its own
# without a controlling terminal (review round 1, security HIGH: in root's session it could push keystrokes into
# root's shell with TIOCSTI), env -i a fixed environment, stdin is /dev/null, and fd 9 (root's deploy lock) is closed so
# nothing the site starts can keep holding it. Kept identical in install.sh.
as_run() {
  setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups -- \
    setsid --wait env -i PATH="$DEPLOY_SAFE_PATH" HOME="$DEPLOY_WORK_DIR/home" "$@" </dev/null 9>&-
}
# BOOT_ENV points Laravel's framework caches (packages, services, config, routes, events) at this run's own directory:
# the calls before the switch read a config fresh from app.env, not what the old release cached, and the build
# publishes the caches it made into bootstrap/cache for PHP-FPM.
art() { as_run ${BOOT_ENV[@]+"${BOOT_ENV[@]}"} "$PHP" "$APP_DIR/artisan" "$@"; }
art_pg() { as_run ${BOOT_ENV[@]+"${BOOT_ENV[@]}"} PGOPTIONS='-c lock_timeout=10s' "$PHP" "$APP_DIR/artisan" "$@"; }
comp() { as_run ${BOOT_ENV[@]+"${BOOT_ENV[@]}"} COMPOSER_HOME="$DEPLOY_WORK_DIR/home/.composer" "$PHP" "$COMPOSER" "$@"; }
# a path PHP reads as absolute: on a Windows test host (Git Bash) /c/x/… becomes the drive-relative /x/…; Linux: unchanged
php_path() { if command -v cygpath >/dev/null 2>&1; then local p; p="$(cygpath -m "$1")"; printf '%s' "${p#?:}"; else printf '%s' "$1"; fi; }
BOOT_FILES="packages.php services.php config.php routes-v7.php events.php"
boot_cache_init() { # $1 = directory (the run user's, inside its work directory; made by it)
  BOOT_CACHE="$1"
  (umask 077; as_run mkdir "$BOOT_CACHE") || return 1   # as_run keeps the umask
  local p; p="$(php_path "$BOOT_CACHE")"
  BOOT_ENV=(APP_PACKAGES_CACHE="$p/packages.php" APP_SERVICES_CACHE="$p/services.php" APP_CONFIG_CACHE="$p/config.php"
    APP_ROUTES_CACHE="$p/routes-v7.php" APP_EVENTS_CACHE="$p/events.php")
  # Windows test host only: stop Git Bash from rewriting /Users/… into C:/Program Files/Git/Users/… for native php.exe
  if command -v cygpath >/dev/null 2>&1; then BOOT_ENV+=(MSYS2_ENV_CONV_EXCL=APP_); fi
}
publish_boot_cache() { # the caches the build made, for PHP-FPM — copied by the run user that made them
  local f
  for f in $BOOT_FILES; do
    as_run rm -f "$APP_DIR/bootstrap/cache/$f" || return 1
    if [ -f "$BOOT_CACHE/$f" ]; then as_run cp "$BOOT_CACHE/$f" "$APP_DIR/bootstrap/cache/$f" || return 1; fi
  done
}
# The run user's own space outside the tree: HOME, the composer cache, a release's framework caches. Its parent is
# root's and writable by nobody else, so www cannot swap the directory for a link; root makes it and reads nothing
# from it. Kept identical in install.sh.
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
# root build is handed over by hand (staging-launch.md S1b), never by a recursive chown here. Kept identical in
# install.sh.
vendor_ready() {
  if [ ! -e "$APP_DIR/vendor" ] && [ ! -L "$APP_DIR/vendor" ]; then
    mkdir "$APP_DIR/vendor" && chown -h "$RUN_USER:$RUN_USER" "$APP_DIR/vendor" || return 1
  fi
  [ -d "$APP_DIR/vendor" ] && [ ! -L "$APP_DIR/vendor" ] \
    && [ -z "$(find -P "$APP_DIR/vendor" ! -user "$RUN_USER" -print -quit 2>/dev/null)" ]
}
# Outside production: every address of egress-blocked (host:port or [v6]:port, one per line) must refuse a connection
# from the run user — the verifiable half of a contained staging's isolation from the live panels (staging-launch.md
# S0 GATE; review round 0, security MEDIUM: a written revocation cannot be checked, and EgressGuard guards only the
# destinations customers name). A refused connection alone proves nothing (review round 1, security MEDIUM: NXDOMAIN, a
# DNS outage or a panel that is down refused like the host's rule): a name must resolve now, and every address it
# resolves to must also be rejected by the host's own table inet onhost_containment, in the one-address rule shape S0
# GATE step 4 writes (or TEST-NET-1 for a name pinned in /etc/hosts). An empty list passes only on a host marked Path B
# (the root-owned path-b). Prints what is wrong; a bare TCP connect per address, nothing is sent.
egress_addresses() { # $1 = host: an address literal as it is, a name as root's resolver (the application's) answers now
  if [[ "$1" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] || [[ "$1" == *:* ]]; then printf '%s\n' "$1" | tr 'A-F' 'a-f'; return 0; fi
  getent ahosts "$1" 2>/dev/null | awk '{ print tolower($1) }' | sort -u
}
egress_rule_rejects() { # $1 = the table as nft lists it, $2 = address
  printf '%s\n' "$1" | awk -v a="$2" '($1 == "ip" || $1 == "ip6") && $2 == "daddr" && $4 == "reject" \
    && ($3 == a || ($3 == "192.0.2.0/24" && index(a, "192.0.2.") == 1)) { found = 1 } END { exit found ? 0 : 1 }'
}
egress_blocked_hold() {
  local line hp host port a addrs table="" open="" unruled="" entries=()
  while IFS= read -r line || [ -n "$line" ]; do
    hp="$(printf '%s' "${line%%#*}" | tr -d ' \t\r')"
    [ -z "$hp" ] && continue
    [[ "$hp" =~ ^\[([0-9A-Fa-f:.]+)\]:([0-9]{1,5})$ ]] || [[ "$hp" =~ ^([A-Za-z0-9.-]+):([0-9]{1,5})$ ]] \
      || { echo "not an address: '$hp' (host:port or [v6]:port)"; return 1; }
    entries+=("$hp")
  done < "$EGRESS_BLOCKED_FILE"
  if [ "${#entries[@]}" = 0 ]; then
    root_only "$DEPLOY_STATE_DIR/path-b" && return 0
    echo "is empty: a contained staging (Path A) lists every live endpoint; only a Path B host with no live credential runs with none, marked by the root-owned $DEPLOY_STATE_DIR/path-b (staging-launch.md S0 GATE step 4)"
    return 1
  fi
  command -v nft >/dev/null 2>&1 || { echo "needs nft: the host's reject table is read with it (staging-launch.md S0 GATE step 4)"; return 1; }
  table="$(nft list table inet onhost_containment 2>/dev/null)" || table=""
  for hp in "${entries[@]}"; do
    [[ "$hp" =~ ^\[?([^]]*)\]?:([0-9]+)$ ]]; host="${BASH_REMATCH[1]}"; port="${BASH_REMATCH[2]}"
    addrs="$(egress_addresses "$host")"
    [ -n "$addrs" ] || { echo "'$host' does not resolve (getent ahosts): a name that does not resolve now is no proof — list its addresses, or pin it in /etc/hosts (staging-launch.md S0 GATE step 4)"; return 1; }
    for a in $addrs; do
      egress_rule_rejects "$table" "$a" || unruled="$unruled $a"
      # shellcheck disable=SC2016 # $1/$2 belong to the inner bash
      if as_run timeout "$PROBE_TIMEOUT" bash -c 'exec 3<>"/dev/tcp/$1/$2"' probe "$a" "$port" 2>/dev/null; then open="$open $hp($a)"; fi
    done
  done
  [ -z "$open" ] || echo "can be reached as $RUN_USER:$open — the host's deny rule is missing or incomplete (staging-launch.md S0 GATE)"
  if [ -z "$table" ]; then
    echo "no table inet onhost_containment (nft): nothing on this host is shown to reject the listed addresses (staging-launch.md S0 GATE step 4)"
  elif [ -n "$unruled" ]; then
    echo "not rejected by table inet onhost_containment:$unruled — a refused connection proves nothing without the host's rule (a panel that is down refuses too)"
  fi
  [ -z "$open" ] && [ -n "$table" ] && [ -z "$unruled" ]
}
gate() { "$PHP" "$DEPLOY_LIB_DIR/deploy-gate.php" "$@"; }
utc_now() { date -u +%Y%m%d-%H%M%S; }
ver_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]; }
oneline() { printf '%s' "$*" | tr '\n\r"' '   '; }

log_line() {
  local rc=$1
  [ -d "$DEPLOY_STATE_DIR" ] || return 0
  (umask 077; printf '%s site=%s operator=%s logname=%s from=%s to=%s ref=%s stage=%s rc=%s set=%s drain_s=%s window_s=%s skip_backup="%s" override="%s" accepted="%s" expected_nonok=%s run=%s\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$SITE" "$(oneline "${DEPLOY_OPERATOR:-?}")" "$(logname 2>/dev/null || echo "${SUDO_USER:-?}")" \
    "${FROM:-?}" "${SHA:-?}" "$(oneline "${REF:-?}")" "$STAGE" "$rc" "${SET:--}" "${DRAIN_S:--}" "${WINDOW_S:--}" \
    "$(oneline "$SKIP_REASON")" "$(oneline "$OVERRIDE_REASON")" "$(oneline "$ACCEPTED")" "${EXPECTED_NONOK_SUM:--}" "${RUN_DIR:--}" >> "$DEPLOY_LOG") || true
}

# ── units ───────────────────────────────────────────────────────────────────────────────────────────────────────────
active_units() { systemctl list-units --plain --no-legend --state=active,activating,reloading 'onhost-queue@*' 'onhost-scheduler.service' | awk '{print $1}' | sort -u; }
in_list() { # $1 = word, the rest = list
  local w=$1 x; shift
  for x in "$@"; do [ "$x" = "$w" ] && return 0; done
  return 1
}

start_units() { # $@ = unit names
  local u
  for u in "$@"; do systemctl start "$u" || warn "could not start $u (systemctl status $u)"; done
}
units_not_active() { # $@ = unit names; prints those that are not active
  local u
  for u in "$@"; do systemctl is-active --quiet "$u" || printf ' %s' "$u"; done
}

restore_drained_list() { # a failed run leaves the list as it found it (units a previous failed run stopped stay stopped)
  if [ -n "$PREV_DRAINED" ]; then printf '%s\n' "$PREV_DRAINED" > "$DRAINED_FILE"; else rm -f "$DRAINED_FILE"; fi
}

# ── ownership: storage and bootstrap/cache belong to the PHP-FPM user; the rest of the tree is not chowned ──────────
# www can write $APP_DIR, so either directory (or bootstrap itself) could be swapped for a symlink: `chmod -R` follows a
# symlink given on its command line and root would re-mode any tree. Refuse a link, walk with find -P (never follows),
# chown -h (the link itself), chmod only what is not a link. The per-file check-then-act race of root's chown inside a
# www-owned tree stays (review round 0: residual, recorded in the task file) — root runs no site code any more.
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
ownership_ok() { tree_is_real && [ -z "$(find -P "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" ! -user "$RUN_USER" -print -quit 2>/dev/null)" ]; }
# the file the deployer read APP_ENV from is the file Laravel loads (www can repoint the symlink $APP_DIR/.env)
env_is_ours() { [ "$APP_DIR/.env" -ef "$ENV_FILE" ]; }
# owned by DEPLOY_OWNER_UID and writable by nobody else ($@ = paths, not descended into)
root_only() {
  local p
  for p in "$@"; do [ -e "$p" ] || [ -L "$p" ] || return 1; done
  [ -z "$(find -P "$@" -maxdepth 0 \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]   # a symlink (0777) fails too
}

# An annotated tag object ($1, its object id — resolved ONCE by the caller, so the object verified is the object read)
# whose `tag` header is $2, whose LAST signature block is SSH with nothing but blank lines after its END line, which
# points at $3 and which a key in $4 signed. Git picks the verifier from the signature itself (a PGP or X.509 block
# would go to root's GnuPG keyring, not allowed_signers): gpg.program/gpg.x509.program are `false`, GNUPGHOME is empty.
# Git verifies only what precedes the last BEGIN line and OpenSSH drops everything from END on, so a line appended
# after END — an `Accept-Gate:` added by someone who can push tags but lacks the owner's key — passed verify-tag
# unsigned (VERIFIED with git 2.47 + OpenSSH, review round 3). Such a tag is refused, and only the signed message (the
# body before the last BEGIN line) is written to $5 for the gate to read. Kept identical in install-deployer.sh
# (DeployGateTest compares the two).
verify_signed_tag() { # $1 = tag object id, $2 = tag name, $3 = expected commit, $4 = allowed_signers, $5 = message file
  local oid=$1 obj name begin gh rc=0
  [ "$(g cat-file -t "$oid" 2>/dev/null)" = tag ] || { echo "tag $2 is lightweight or missing: production needs an annotated, signed tag"; return 1; }
  obj="$(g cat-file tag "$oid")"
  name="$(printf '%s\n' "$obj" | awk 'NF == 0 { exit } /^tag / { print substr($0, 5); exit }')"
  [ "$name" = "$2" ] || { echo "the tag object under refs/tags/$2 calls itself '$name', not '$2'"; return 1; }
  begin="$(printf '%s\n' "$obj" | grep -nE '^-----BEGIN [A-Z ]+-----$' | tail -n 1)"
  [ "${begin#*:}" = "-----BEGIN SSH SIGNATURE-----" ] || { echo "tag $2 is not SSH-signed (${begin:-no signature})"; return 1; }
  begin="${begin%%:*}"
  printf '%s\n' "$obj" | awk -v b="$begin" 'NR > b && ended && NF { bad = 1 } NR > b && $0 == "-----END SSH SIGNATURE-----" { ended = 1 } END { exit (ended && !bad) ? 0 : 1 }' \
    || { echo "tag $2 carries unsigned content after its signature (nothing may follow -----END SSH SIGNATURE-----)"; return 1; }
  [ "$(g rev-parse "${oid}^{commit}")" = "$3" ] || { echo "tag $2 does not point at $3"; return 1; }
  gh="$(mktemp -d)"
  GNUPGHOME="$gh" g -c gpg.format=ssh -c gpg.program=false -c gpg.x509.program=false -c gpg.ssh.program=ssh-keygen \
    -c gpg.ssh.allowedSignersFile="$4" verify-tag "$oid" >/dev/null 2>&1 || rc=$?
  rm -rf "$gh"
  [ "$rc" = 0 ] || { echo "tag $2 is not signed by a key in $4"; return 1; }
  printf '%s\n' "$obj" | awk -v b="$begin" 'NR >= b { exit } body { print } NF == 0 { body = 1 }' > "${5:-/dev/null}"
}

# ── HTTP: https://$SITE on this host (--resolve), optionally with the maintenance bypass header ─────────────────────
http_status() { # $1 = path, $2 = "bypass" to send the cookie
  local args=(-sS -o /dev/null -w '%{http_code}' --max-time 30) url
  if [ "${2:-}" = bypass ]; then args+=(-K "$JAR"); fi
  if [ -n "$DEPLOY_HTTP_BASE" ]; then url="${DEPLOY_HTTP_BASE%/}$1"; else args+=(--resolve "${SITE}:443:${DEPLOY_HTTP_IP}"); url="https://${SITE}$1"; fi
  curl "${args[@]}" "$url" 2>/dev/null || true
}

recovery_hint() {
  say "Recovery (the site stays in maintenance; the drained units stay stopped)"
  gate hint --file "$DEPLOY_STATE_DIR/last-good.json" --production "$PROD" || true
  echo "backup set of this run: ${SET:-none} (checksums: ${RUN_DIR:-?}/backup.out)"
  echo "a database restore needs the owner: docs/runbooks/release-and-rollback.md § Rollback"
}

on_exit() {
  local rc=$?
  set +e
  trap - EXIT
  if [ "$STAGE" != preflight ]; then repair_ownership 2>/dev/null; fi
  [ -n "$JAR" ] && rm -f "$JAR"
  if [ "$rc" -ne 0 ] && [ "$STAGE" != preflight ]; then
    if [ "$SWITCHED" = 0 ]; then
      if [ "$MARKER_BY_THIS_RUN" = 1 ]; then
        art up >/dev/null 2>&1 && rm -f "$MARKER"
      elif [ "$WENT_DOWN" = 1 ]; then
        art down --retry=60 >/dev/null 2>&1   # the site was down before this run: keep it down, drop this run's bypass secret
      fi
      # shellcheck disable=SC2086 # unit names are plain words
      start_units $DRAINED_NOW
      restore_drained_list
    elif [ "$rc" -ne 6 ]; then
      art down --retry=60 >/dev/null 2>&1       # new payload without a secret: the bypass dies with the failed run
      recovery_hint
    else
      recovery_hint
    fi
  fi
  [ -n "$BOOT_CACHE" ] && as_run rm -rf "$BOOT_CACHE" 2>/dev/null
  log_line "$rc"
  exit "$rc"
}
trap on_exit EXIT

# ── 0 preflight: every refusal here is exit 2 and nothing has changed ───────────────────────────────────────────────
[ -n "${BRANCH:-}" ] && die 2 "BRANCH is no longer accepted: pass REF=<tag|sha> and EXPECTED_SHA=<sha>"
[ -n "${REF:-}" ] || die 2 "REF is required (a tag, or outside production a SHA or branch)"
[[ "$REF" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]{0,127}$ ]] && [[ "$REF" != *..* ]] && [[ "$REF" != *.lock ]] || die 2 "REF '$REF' is not a plain ref name"
[[ "${EXPECTED_SHA:-}" =~ ^[0-9a-f]{40}$ ]] || die 2 "EXPECTED_SHA must be the full 40-character SHA of the release"
[[ "${DEPLOY_OPERATOR:-}" =~ ^[A-Za-z0-9._@-]{2,64}$ ]] || die 2 "DEPLOY_OPERATOR (who runs this release) is required"
self="$(realpath "$0" 2>/dev/null || echo "$0")"
case "$self" in "$(realpath "$APP_DIR" 2>/dev/null || echo "$APP_DIR")"/*) die 2 "run the installed deployer (/usr/local/sbin/onhost-deploy), not the copy in the site tree";; esac
[ -f "$DEPLOY_LIB_DIR/deploy-gate.php" ] || die 2 "the deployer is not installed ($DEPLOY_LIB_DIR/deploy-gate.php): infra/aapanel/install-deployer.sh"
# the judge must be as trustworthy as the deployer: root's, and writable by nobody else
root_only "$DEPLOY_LIB_DIR" "$DEPLOY_LIB_DIR/deploy-gate.php" "$DEPLOY_LIB_DIR/source-sha" "$(dirname "$self")" "$self" \
  || die 2 "the deployer files ($self, $DEPLOY_LIB_DIR and its deploy-gate.php, source-sha) must belong to uid $DEPLOY_OWNER_UID and be writable by nobody else: reinstall with install-deployer.sh"
for tool in git curl flock find sort sha256sum setpriv setsid timeout; do command -v "$tool" >/dev/null 2>&1 || die 2 "missing: $tool"; done
[ -x "$PHP" ] || die 2 "PHP not found at $PHP"
[ -d "$APP_DIR/.git" ] || die 2 "$APP_DIR is not a checkout"
cd "$APP_DIR" || die 2 "cannot enter $APP_DIR"

(umask 077; mkdir -p "$DEPLOY_STATE_DIR/runs") && chmod 700 "$DEPLOY_STATE_DIR" || die 2 "cannot create $DEPLOY_STATE_DIR"
exec 9>"$DEPLOY_STATE_DIR/lock"
flock -n 9 || die 2 "another deploy of $SITE is running"
[ -f "$DEPLOY_STATE_DIR/gitconfig" ] || (umask 077; printf '[safe]\n\tdirectory = %s\n' "$APP_DIR" > "$DEPLOY_STATE_DIR/gitconfig")

git_version="$(git --version | awk '{print $3}')"
ver_ge "$git_version" 2.32 || die 2 "git $git_version is too old (2.32+ for GIT_CONFIG_GLOBAL)"

# production is decided from the root-owned environment file, never from $APP_DIR/.env (a symlink in a tree www can
# write: repointed at a file saying APP_ENV=staging it would have unlocked unsigned refs and ALLOW_DOCTOR_FAIL)
root_only "$ENV_FILE" || die 2 "$ENV_FILE must exist, belong to uid $DEPLOY_OWNER_UID and be writable by nobody else (install.sh: root:www 0640)"
env_is_ours || die 2 "$APP_DIR/.env is not $ENV_FILE (install.sh links it: ln -sfn $ENV_FILE $APP_DIR/.env); refusing to judge another file"
APP_ENV_VALUE="$(gate parse-env --file "$ENV_FILE" --key APP_ENV || true)"
case "$APP_ENV_VALUE" in staging|local|testing) PROD=0 ;; *) PROD=1 ;; esac   # fail closed: anything unclear is production
[ "$PROD" = 1 ] && { ver_ge "$git_version" 2.34 || die 2 "production needs git 2.34+ (SSH-signed tags)"; }
app_url="$(gate parse-env --file "$ENV_FILE" --key APP_URL || true)"
app_host="${app_url#*://}"; app_host="${app_host%%/*}"; app_host="${app_host%%:*}"
[ "$app_host" = "$SITE" ] || die 2 "APP_URL host '$app_host' in $ENV_FILE is not SITE '$SITE'"
tree_is_real || die 2 "storage, bootstrap and bootstrap/cache must be real directories of $APP_DIR (a symlink would let root re-own another tree)"
# the site's PHP runs as the run user, never as root (as_run): a host where that cannot happen is refused here
run_uid="$(id -u "$RUN_USER" 2>/dev/null || true)"
[[ "$run_uid" =~ ^[0-9]+$ ]] && [ "$run_uid" != 0 ] || die 2 "RUN_USER '$RUN_USER' must be an existing user other than root: the site's PHP never runs as root"
[ "$(as_run id -u 2>/dev/null || true)" = "$run_uid" ] \
  || die 2 "cannot run the site's PHP as $RUN_USER (setpriv, and setsid --wait from util-linux 2.31+, run as root): the deployer never runs it as root (staging-launch.md S0)"

[ -z "$(find "$APP_DIR/.git" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ] \
  || die 2 ".git must belong to uid $DEPLOY_OWNER_UID and be writable by nobody else (chown -R root:root .git && chmod -R go-w .git)"

FROM="$(g rev-parse HEAD)" || die 2 "cannot read HEAD of $APP_DIR"
DEPLOYER_SHA="$(cat "$DEPLOY_LIB_DIR/source-sha" 2>/dev/null || true)"
[[ "$DEPLOYER_SHA" =~ ^[0-9a-f]{40}$ ]] || die 2 "the deployer has no source-sha: install it with install-deployer.sh"

say "Fetch and resolve $REF"
g fetch -q --prune --prune-tags --force --tags origin || die 2 "git fetch failed"
if [[ "$REF" =~ ^[0-9a-f]{7,40}$ ]] && SHA="$(g rev-parse -q --verify "${REF}^{commit}" 2>/dev/null)"; then
  kind=sha
elif TAG_OID="$(g rev-parse -q --verify "refs/tags/${REF}")"; then
  kind=tag; TAG="$REF"; SHA="$(g rev-parse "${TAG_OID}^{commit}")"
elif [ "$PROD" = 0 ] && g rev-parse -q --verify "refs/remotes/origin/${REF}" >/dev/null; then
  kind=branch; SHA="$(g rev-parse "refs/remotes/origin/${REF}^{commit}")"
else
  die 2 "REF '$REF' does not resolve (production: a tag only)"
fi
[ "$SHA" = "$EXPECTED_SHA" ] || die 2 "REF '$REF' is $SHA, not EXPECTED_SHA $EXPECTED_SHA"
RUN_DIR="$DEPLOY_STATE_DIR/runs/$(utc_now)-${SHA:0:12}"
(umask 077; mkdir -p "$RUN_DIR")

if [ "$PROD" = 1 ]; then
  [ "$kind" = tag ] || die 2 "production deploys only a signed, annotated tag (REF=v…)"
  [ "$(g cat-file -t "$TAG_OID")" = tag ] || die 2 "tag $REF is lightweight: production needs an annotated, signed tag"
  signers="$DEPLOY_STATE_DIR/allowed_signers"
  [ -f "$signers" ] && root_only "$signers" \
    || die 2 "no trusted $signers (root-owned, the owner's SSH public key): production deploys are refused until it exists"
  # tag.txt = the signed message only: the gate reads Accept-Gate lines from nothing the signature does not cover
  why="$(verify_signed_tag "$TAG_OID" "$REF" "$SHA" "$signers" "$RUN_DIR/tag.txt")" || die 2 "$why"
  [ -n "${ALLOW_DOCTOR_FAIL:-}" ] && die 2 "ALLOW_DOCTOR_FAIL is refused in production: accept a GATED row with an 'Accept-Gate: <area|check> — <reason>' line in the signed tag"
fi

# the root-owned lists (header). Pre-mortem 2026-09-27: a staging GO used to rest on what the operator happened to read —
# a contained staging's app.env was printed, never asserted, and every doctor row outside the gate's 11 passed
root_only "$EXPECTED_UNITS_FILE" \
  || die 2 "$EXPECTED_UNITS_FILE must exist (root's, writable by nobody else): the units that must run after a release, one per line — install.sh writes it, docs/runbooks/staging-launch.md O12 (an empty file = none)"
EXPECTED_UNITS="$(sed -e 's/#.*//' "$EXPECTED_UNITS_FILE" | tr -s ' \t' '\n\n' | sed '/^$/d' | sort -u | tr '\n' ' ')"
for u in $EXPECTED_UNITS; do
  [[ "$u" =~ ^onhost-(scheduler|queue@[A-Za-z0-9_-]+)\.service$ ]] || die 2 "$EXPECTED_UNITS_FILE names '$u': only onhost-scheduler.service and onhost-queue@<lane>.service"
done
if [ "$PROD" = 0 ]; then
  root_only "$EXPECTED_NONOK_FILE" \
    || die 2 "$EXPECTED_NONOK_FILE must exist (root's, writable by nobody else): the doctor rows this staging runs non-OK on purpose — the release record's O11 list (staging-launch.md S4b)"
  EXPECTED_NONOK_SUM="$(sha256sum "$EXPECTED_NONOK_FILE" | cut -c1-12)"
  root_only "$EXPECTED_ENV_FILE" \
    || die 2 "$EXPECTED_ENV_FILE must exist (root's, writable by nobody else): the staging environment spec of staging-launch.md S3"
  gate env-assert --file "$ENV_FILE" --spec "$EXPECTED_ENV_FILE" > "$RUN_DIR/env-assert.out" 2>&1 \
    || { grep -v '^OK ' "$RUN_DIR/env-assert.out" >&2; die 2 "$ENV_FILE does not hold what $EXPECTED_ENV_FILE expects (lines above; values are never printed)"; }
  root_only "$EGRESS_BLOCKED_FILE" \
    || die 2 "$EGRESS_BLOCKED_FILE must exist (root's, writable by nobody else): the addresses $RUN_USER must not reach, host:port per line — every live endpoint on a contained staging (staging-launch.md S0 GATE; an empty file = none)"
  why="$(egress_blocked_hold)" || die 2 "$EGRESS_BLOCKED_FILE: $why"
fi

if g merge-base --is-ancestor "$DEPLOYER_SHA" "$SHA" 2>/dev/null \
  && ! g diff --quiet "$DEPLOYER_SHA" "$SHA" -- infra/aapanel/deploy.sh infra/aapanel/deploy-gate.php; then
  die 2 "the target carries a newer deployer than the installed one ($DEPLOYER_SHA): first run
  g show $SHA:infra/aapanel/install-deployer.sh > /root/install-deployer.sh && SHA=$SHA${TAG:+ TAG=$TAG} bash /root/install-deployer.sh"
fi
g cat-file -e "${DEPLOYER_SHA}^{commit}" 2>/dev/null || die 2 "the installed deployer's source $DEPLOYER_SHA is not in this repository"

dirty="$(g status --porcelain --untracked-files=all -- . ":(exclude)${OPENAPI_PATH}" ':(exclude)VERSION')"
[ -z "$dirty" ] || die 2 "the checkout is not clean (a deploy would discard or ship this):
$dirty"

HAS_MIGRATIONS=0
[ -n "$(g diff --name-only "$FROM" "$SHA" -- database/migrations)" ] && HAS_MIGRATIONS=1
if [ -n "${SKIP_BACKUP:-}" ] && [ "${SKIP_BACKUP}" != 0 ]; then
  SKIP_REASON="$(gate override --value "$SKIP_BACKUP" --sha "$SHA")" || die 2 "SKIP_BACKUP must be \"${SHA:0:12}:<reason>\""
  [ "$HAS_MIGRATIONS" = 0 ] || die 2 "SKIP_BACKUP is refused: $FROM..$SHA changes database/migrations"
fi
if [ -n "${ALLOW_DOCTOR_FAIL:-}" ]; then
  OVERRIDE_REASON="$(gate override --value "$ALLOW_DOCTOR_FAIL" --sha "$SHA")" || die 2 "ALLOW_DOCTOR_FAIL must be \"${SHA:0:12}:<reason>\""
  OVERRIDE="$ALLOW_DOCTOR_FAIL"
fi
unset ALLOW_DOCTOR_FAIL SKIP_BACKUP

probe="$(http_status /up)"
case "$probe" in
  401|403) die 2 "https://$SITE/up answers $probe on $DEPLOY_HTTP_IP: the vhost must let loopback past basic auth (the staging block of infra/aapanel/nginx-site.conf)" ;;
  000) die 2 "nothing answers https://$SITE on $DEPLOY_HTTP_IP (DEPLOY_HTTP_IP)" ;;
esac
[ -f "$APP_DIR/storage/framework/down" ] && WAS_DOWN=1
PREV_DRAINED="$(cat "$DRAINED_FILE" 2>/dev/null || true)"

# the units: exactly the expected ones run (or were stopped by a previous failed run of this deployer, which lists them
# in drained-units), each enabled. A lane that is already dead or disabled would otherwise stay dead behind a release
# that ends rc 0, and a provider lane someone started on a contained staging would be restarted by every release.
running="$(active_units | tr '\n' ' ')" || die 2 "cannot list the onhost units (systemctl)"
for u in $running; do
  # shellcheck disable=SC2086 # unit names are plain words
  in_list "$u" $EXPECTED_UNITS || die 2 "$u runs but is not in $EXPECTED_UNITS_FILE: stop it (containment), or add it to the list when it belongs to this site"
done
for u in $EXPECTED_UNITS; do
  enabled="$(systemctl is-enabled "$u" 2>/dev/null || true)"
  [ "$enabled" = enabled ] || die 2 "$u is expected to run but is '${enabled:-unknown}', not enabled (systemctl enable $u, or take it out of $EXPECTED_UNITS_FILE)"
  # shellcheck disable=SC2086
  in_list "$u" $running || in_list "$u" $PREV_DRAINED \
    || die 2 "$u is expected to run but is not running: a release would leave it dead (systemctl start $u; journalctl -u $u), or take it out of $EXPECTED_UNITS_FILE"
done
# the run user writes storage and bootstrap/cache from the first artisan call on (`down`): files an older root run left
# there are handed back now, the guarded way
repair_ownership || die 2 "storage or bootstrap/cache could not be handed to $RUN_USER"
work_dir_ready || die 2 "$DEPLOY_WORK_DIR must be a directory of $RUN_USER's whose parent only uid $DEPLOY_OWNER_UID can write (the run user's HOME and build caches)"
vendor_ready || die 2 "$APP_DIR/vendor must be a real directory owned entirely by $RUN_USER, who runs composer (an older root build: staging-launch.md S1b)"
boot_cache_init "$DEPLOY_WORK_DIR/run-$(basename "$RUN_DIR")" || die 2 "cannot make the run's cache directory as $RUN_USER"   # from here every artisan/composer call reads it
RUN_START="$(utc_now)"
say "Release $FROM → $SHA ($kind $REF, $( [ "$PROD" = 1 ] && echo production || echo "$APP_ENV_VALUE"), operator $DEPLOY_OPERATOR)"
[ -n "$OVERRIDE_REASON" ] && warn "ALLOW_DOCTOR_FAIL accepted for GATED rows only: $OVERRIDE_REASON"

# ── 1 drain: nothing of the old code keeps running jobs while the tree changes under it ────────────────────────────
STAGE=drain
say "Drain the queue workers and the scheduler (up to ${DRAIN_TIMEOUT}s)"
DRAINED_NOW="$(active_units | tr '\n' ' ')" || die 3 "cannot list the onhost units (systemctl)"
# shellcheck disable=SC2086 # unit names are plain words
printf '%s\n%s\n' "$PREV_DRAINED" "$(printf '%s\n' $DRAINED_NOW)" | sed '/^$/d' | sort -u > "$DRAINED_FILE"
drain_started=$SECONDS
if [ -n "${DRAINED_NOW// /}" ]; then
  # shellcheck disable=SC2086
  systemctl stop --no-block $DRAINED_NOW
  while :; do
    still=""
    for u in $DRAINED_NOW; do systemctl is-active --quiet "$u" && still="$still $u"; done
    [ -z "$still" ] && break
    [ $((SECONDS - drain_started)) -ge "$DRAIN_TIMEOUT" ] && die 3 "still running after ${DRAIN_TIMEOUT}s:$still (a long job; retry later or raise DRAIN_TIMEOUT)"
    sleep 2
  done
fi
DRAIN_S=$((SECONDS - drain_started))

# ── 2 down: 503 for visitors; the gate passes with a bypass cookie built from the down file's secret ──────────────
STAGE=down
say "Maintenance mode"
art down --retry=60 --with-secret >/dev/null || die 3 "artisan down failed"   # the bypass URL it prints must never reach a terminal log
WENT_DOWN=1
DOWN_AT=$SECONDS
if [ "$WAS_DOWN" = 0 ] && [ ! -f "$MARKER" ]; then : > "$MARKER"; MARKER_BY_THIS_RUN=1; fi
[ -f "$APP_DIR/storage/framework/down" ] || die 3 "artisan down wrote no storage/framework/down (APP_MAINTENANCE_DRIVER must be file)"

# ── 3 backup + verify inside the window: the set this run wrote, read back ─────────────────────────────────────────
STAGE=backup
if [ -n "$SKIP_REASON" ]; then
  warn "backup skipped (no migrations in range): $SKIP_REASON"
else
  say "Platform backup and verification"
  (umask 077; : > "$RUN_DIR/backup.out")
  art onhost:platform:backup --no-ansi >> "$RUN_DIR/backup.out" 2>&1 || { cat "$RUN_DIR/backup.out"; die 3 "the platform backup failed"; }
  SET="$(gate backup-set --output "$RUN_DIR/backup.out" --since "$RUN_START")" || { cat "$RUN_DIR/backup.out"; die 3 "no fresh backup set from this run (is the platform.backup rule switched off?)"; }
  art onhost:platform:backup:verify "$SET" --no-ansi >> "$RUN_DIR/backup.out" 2>&1 || { cat "$RUN_DIR/backup.out"; die 3 "backup set $SET did not verify"; }
  grep -qE "^OK ${SET}( |$)" "$RUN_DIR/backup.out" || die 3 "verify did not confirm $SET"
  echo "   $SET verified (checksums kept in $RUN_DIR/backup.out)"
fi

# ── 4 switch + build ────────────────────────────────────────────────────────────────────────────────────────────────
STAGE=switch
say "Code $SHA"
SWITCHED=1
g checkout -q -f --detach "$SHA" || die 4 "checkout failed"
[ "$(g rev-parse HEAD)" = "$SHA" ] && g diff --quiet HEAD \
  && [ -z "$(g status --porcelain --untracked-files=all -- . ':(exclude)VERSION')" ] || die 4 "the tree is not clean after the checkout"

STAGE=build
# the old release's caches go: from PHP-FPM's directory (published again after the build) and from this run's copy
for f in $BOOT_FILES; do as_run rm -f "$APP_DIR/bootstrap/cache/$f" "$BOOT_CACHE/$f" || die 4 "cannot clear the framework caches"; done
say "Composer"
comp install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader || die 4 "composer install failed"
say "Migrations (additive, backward compatible for one release; lock_timeout 10s, no retry)"
art_pg migrate --force --isolated || die 4 "migrations failed"
art db:seed --class=AuthorizationSeeder --force || die 4 "AuthorizationSeeder failed"   # roles and permissions are code; the authorizer reads the database
art db:seed --class=NotificationTemplateSeeder --force || die 4 "NotificationTemplateSeeder failed"
say "Caches and contract"
art config:cache && art route:cache && art event:cache || die 4 "caching failed"
# the run user regenerates the tracked contract where it may write the file (on a root-owned checkout it may not: the
# committed contract stands, WARN). Root never writes it: a path below $APP_DIR can be a link www planted.
art onhost:openapi > "$RUN_DIR/openapi.out" 2>&1 || warn "onhost:openapi failed ($RUN_DIR/openapi.out); the published contract may be stale"
# VERSION sits in a directory www owns: written in the root-only run directory and renamed into place (mv -T renames
# over a planted link or hard link instead of writing through it; `>` would have truncated whatever it names — review
# round 3). Across file systems GNU mv removes the destination first and creates it exclusively.
(umask 022; printf '%s %s\n' "$SHA" "$REF" > "$RUN_DIR/VERSION") && mv -fT "$RUN_DIR/VERSION" "$APP_DIR/VERSION" || die 4 "cannot write VERSION"
publish_boot_cache || die 4 "cannot publish the framework caches into bootstrap/cache"
repair_ownership || die 4 "ownership repair failed"
if [ -n "$PHP_FPM_RELOAD" ]; then sh -c "$PHP_FPM_RELOAD" || warn "PHP-FPM reload failed: opcache may serve the old code"; else warn "PHP_FPM_RELOAD is not set: opcache may serve the old code"; fi

# ── 5 gate ──────────────────────────────────────────────────────────────────────────────────────────────────────────
STAGE=gate
say "Gate: ownership, doctor, HTTP"
ownership_ok || die 5 "storage or bootstrap/cache still has files not owned by $RUN_USER"
env_is_ours || die 5 "$APP_DIR/.env no longer is $ENV_FILE (repointed during the run)"
doctor_rc=0
art onhost:doctor --json > "$RUN_DIR/report.json" 2> "$RUN_DIR/doctor.err" || doctor_rc=$?
verdict_rc=0
expected_arg=""
[ "$PROD" = 0 ] && expected_arg="$EXPECTED_NONOK_FILE"
gate verdict --report "$RUN_DIR/report.json" --doctor-rc "$doctor_rc" --env "$APP_ENV_VALUE" --production "$PROD" --sha "$SHA" \
  --override "$OVERRIDE" --accept-file "$RUN_DIR/tag.txt" --expected-file "$expected_arg" > "$RUN_DIR/verdict.out" || verdict_rc=$?
cat "$RUN_DIR/verdict.out"
ACCEPTED="$(grep '^ACCEPTED ' "$RUN_DIR/verdict.out" | sed 's/^ACCEPTED //' | tr '\n' ';' || true)"
case "$verdict_rc" in
  0) ;;
  10) die 5 "a GATED doctor row is not OK (staging: ALLOW_DOCTOR_FAIL=\"${SHA:0:12}:<reason>\"; production: Accept-Gate in the signed tag)" ;;
  12) die 5 "a doctor row outside the HARD and GATED lists is not OK (ROW-FAIL above; staging: only a row the release record's O11 list names may be, in $EXPECTED_NONOK_FILE; production: an Accept-Gate line in the signed tag)" ;;
  *) die 5 "a HARD doctor row is not OK, or the doctor report cannot be trusted (never overridable)" ;;
esac
JAR="$RUN_DIR/bypass.curlrc"
gate cookie --down-file "$APP_DIR/storage/framework/down" --out "$JAR" --ttl 900 || die 5 "cannot build the maintenance bypass"
for path in /up /v1/status; do
  code="$(http_status "$path" bypass)"
  [ "$code" = 200 ] || die 5 "GET $path answered $code through the maintenance bypass (not overridable)"
  echo "   GET $path → 200"
done
rm -f "$JAR"; JAR=""

# ── 6 live ──────────────────────────────────────────────────────────────────────────────────────────────────────────
STAGE=live
repair_ownership || die 5 "ownership repair failed"
ownership_ok || die 5 "storage or bootstrap/cache has files not owned by $RUN_USER"
if [ -f "$DEPLOY_STATE_DIR/expect-freeze" ] && [ "$PROD" = 0 ]; then
  art onhost:provisioning:freeze "staging: expected freeze" >/dev/null || die 5 "could not re-assert the expected provisioning freeze"
  echo "   provisioning freeze re-asserted (expect-freeze)"
fi
say "Start the drained units"
to_start="$(cat "$DRAINED_FILE" 2>/dev/null || true)"
start_now=""
for u in $to_start; do # only what is expected now: a unit taken off the list since a failed run stays stopped (containment wins)
  # shellcheck disable=SC2086 # unit names are plain words
  if in_list "$u" $EXPECTED_UNITS; then start_now="$start_now $u"; else warn "$u was drained but is not in $EXPECTED_UNITS_FILE: left stopped"; fi
done
# shellcheck disable=SC2086
start_units $start_now
if [ -n "${EXPECTED_UNITS// /}" ]; then
  sleep "$UNIT_SETTLE"
  # every EXPECTED unit, not only the drained ones (pre-mortem: a lane that was not running stayed dead behind rc 0)
  # shellcheck disable=SC2086
  dead="$(units_not_active $EXPECTED_UNITS)"
  if [ -n "$dead" ]; then # a worker that cannot run the new code is a failed release, not a warning: all of them stop again
    # shellcheck disable=SC2086
    printf '%s\n' $to_start $EXPECTED_UNITS | sed '/^$/d' | sort -u > "$DRAINED_FILE"
    # shellcheck disable=SC2086
    systemctl stop --no-block $EXPECTED_UNITS || true
    die 7 "drained units did not come back:$dead (journalctl -u <unit>); every expected unit is stopped again, the site stays in maintenance"
  fi
fi
rm -f "$DRAINED_FILE"
if [ -f "$MARKER" ]; then
  art up >/dev/null || die 6 "artisan up failed"
  rm -f "$MARKER"
  WINDOW_S=$((SECONDS - DOWN_AT))
  say "Live (maintenance window ${WINDOW_S}s)"
  code="$(http_status /up)"
  [ "$code" = 200 ] || die 6 "public GET /up answers $code after up"
else
  art down --retry=60 >/dev/null   # drop this run's bypass secret
  say "The site was down before this deploy; left down (artisan up when you are ready)"
fi

# ── 7 record ────────────────────────────────────────────────────────────────────────────────────────────────────────
STAGE=done
(umask 077; printf '{"sha":"%s","ref":"%s","tag":"%s","at":"%s","operator":"%s"}\n' "$SHA" "$REF" "$TAG" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$(oneline "$DEPLOY_OPERATOR")" > "$DEPLOY_STATE_DIR/last-good.json")
say "Released $SHA. Run onhost:doctor again after 6 minutes or more for the liveness rows (scheduler, worker)."
