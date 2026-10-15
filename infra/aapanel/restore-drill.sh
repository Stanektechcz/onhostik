#!/usr/bin/env bash
# ONhost control plane — the restore drill (P13, phase R; docs/runbooks/backups.md "Restore drill"). Monthly, as root:
#
#   PGHOST=127.0.0.1 PGUSER=<the application's role: CREATEDB, no superuser> [PGPASSWORD in the shell; unattended runs (systemd timer) use PGPASSFILE from a root-only credential] bash restore-drill.sh
#
# The newest platform backup set is exported through the application (`onhost:platform:backup:export-db`, so it works on any
# backup disk, an S3 bucket included, and checks size and SHA-256 against the manifest), restored with pg_restore into a
# throw-away database `onhost_drill_<UTC time>` on the same PostgreSQL, measured (migrations, users, documents, ledger postings,
# the double-entry balance) and dropped again — also when anything fails. The application then judges the measurement against
# the counts the backup took before its dump and keeps the verdict on the backup disk (`onhost:platform:backup:drill-record`,
# `<set>/restore-drill.json`); the doctor row "platform restore drill within 35 days" and the metric
# onhost_platform_restore_drill_timestamp read it. Nothing of the live database is touched: the drill only creates and drops
# its own database.
#
# Like the deployer, the site's PHP never runs as root: artisan runs as RUN_USER through setpriv, in a session of its own, with a
# fixed environment and stdin from /dev/null. Unprivileged (CI) it runs as the current user, which must then be RUN_USER.
#
# Exit codes: 0 the drill passed · 1 the drill ran and the restored copy failed a check · 2 refused before anything was created
# (tools, configuration, another drill running, not a PostgreSQL dump) · 3 the export or the restore failed.
set -euo pipefail

# Nothing this script starts may hold root's terminal (the deployer's rule, deploy.sh keep_terminal_from_children).
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
PHP="${PHP:-/www/server/php/85/bin/php}"
RUN_USER="${RUN_USER:-www}"
DRILL_TMP="${DRILL_TMP:-/var/tmp}"
DRILL_LOCK="${DRILL_LOCK:-/run/lock/onhost-restore-drill.lock}"
DEPLOY_SAFE_PATH="${DEPLOY_SAFE_PATH:-/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin}"
PG_BIN="${PG_BIN:-}"   # directory of pg_restore/psql/createdb/dropdb matching the server; empty = like PlatformBackup::pgBin()

say() { printf '▶ %s\n' "$*"; }
die() { local rc=$1; shift; printf '✖ %s\n' "$*" >&2; exit "$rc"; }

if [ -z "$PG_BIN" ]; then
  mapfile -t PG_CANDIDATES < <(printf '%s\n' /www/server/pgsql/bin; compgen -G '/usr/lib/postgresql/*/bin' | sort -rV; printf '%s\n' /usr/local/pgsql/bin)
  for candidate in "${PG_CANDIDATES[@]}"; do
    if [ -x "$candidate/pg_restore" ]; then PG_BIN="$candidate"; break; fi
  done
fi
pg() { local tool=$1; shift; if [ -n "$PG_BIN" ]; then "$PG_BIN/$tool" "$@"; else "$tool" "$@"; fi; }

as_run() {
  if [ "$(id -u)" = 0 ]; then
    setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups -- \
      setsid --wait env -i PATH="$DEPLOY_SAFE_PATH" HOME=/tmp "$@" </dev/null
  else
    setsid --wait env -i PATH="$DEPLOY_SAFE_PATH" HOME=/tmp "$@" </dev/null
  fi
}
art() { as_run "$PHP" "$APP_DIR/artisan" "$@"; }

# ── preflight: nothing is created before every check passed ──────────────────────────────────────────────────────────────
STAGE="preflight"
for tool in pg_restore psql createdb dropdb; do
  if [ -n "$PG_BIN" ]; then [ -x "$PG_BIN/$tool" ] || die 2 "$PG_BIN/$tool missing (PG_BIN)"; else command -v "$tool" >/dev/null || die 2 "$tool not on PATH (set PG_BIN)"; fi
done
[ -x "$PHP" ] || die 2 "PHP not executable: $PHP"
[ -f "$APP_DIR/artisan" ] || die 2 "no artisan in $APP_DIR"
if [ "$(id -u)" != 0 ] && [ "$(id -un)" != "$RUN_USER" ]; then die 2 "unprivileged, the drill runs as RUN_USER ($RUN_USER) itself"; fi
mkdir -p "$(dirname "$DRILL_LOCK")" 2>/dev/null || true
exec 8>"$DRILL_LOCK"
flock -n 8 || die 2 "another restore drill is running ($DRILL_LOCK)"
ROLE_POWER="$(pg psql -X -A -t -v ON_ERROR_STOP=1 --dbname=postgres -c "select (rolsuper or pg_has_role(current_user, 'pg_execute_server_program', 'member') or pg_has_role(current_user, 'pg_write_server_files', 'member') or pg_has_role(current_user, 'pg_read_server_files', 'member'))::int || ',' || rolcreatedb::int from pg_roles where rolname = current_user")" \
  || die 2 "cannot reach PostgreSQL as ${PGUSER:-$(id -un)} (PGHOST/PGPORT/PGUSER; password from PGPASSWORD in the shell or PGPASSFILE under the timer; host:port in the pgpass line must match PGHOST/PGPORT)"
# The dump is written by the application (www): a compromised site could plant one. Restored by a superuser (or a role that may run
# programs or touch server files) it could reach the database server's shell, so the drill restores only as a plain role with
# CREATEDB — the application's own role is the natural choice: it gains nothing it did not have.
[ "${ROLE_POWER%%,*}" = 0 ] || die 2 "refused: ${PGUSER:-$(id -un)} is a superuser or may run programs / touch server files — use a plain role with CREATEDB (the application's own)"
[ "${ROLE_POWER##*,}" = 1 ] || die 2 "${PGUSER:-$(id -un)} lacks CREATEDB"

WORK=""
DB=""
CREATED=0
cleanup() {
  local rc=$?
  if [ "$CREATED" = 1 ]; then
    pg dropdb --if-exists "$DB" || printf '✖ could not drop %s — drop it by hand: dropdb %s\n' "$DB" "$DB" >&2
  fi
  [ -n "$WORK" ] && rm -rf "$WORK"
  [ "$rc" = 0 ] || printf '✖ restore drill stopped in stage %s (exit %s)\n' "$STAGE" "$rc" >&2
  exit "$rc"
}
trap cleanup EXIT

WORK="$(mktemp -d "$DRILL_TMP/onhost-drill.XXXXXX")"
chmod 0700 "$WORK"
if [ "$(id -u)" = 0 ]; then chown "$RUN_USER:" "$WORK"; fi

# ── export: the application copies the dump of the newest set and checks it against the manifest ─────────────────────────
STAGE="export"
say "export the newest platform backup set"
EXPORT="$(art onhost:platform:backup:export-db --to="$WORK/database.dump" --json)" || die 3 "export failed"
field() { printf '%s' "$EXPORT" | "$PHP" -n -r '$j = json_decode(stream_get_contents(STDIN), true); echo is_array($j) ? (string) ($j[$argv[1]] ?? "") : "";' "$1"; }
SET="$(field set)"
DRIVER="$(field driver)"
[ -n "$SET" ] || die 3 "the export named no set"
[ "$DRIVER" = pgsql ] || die 2 "set $SET is a $DRIVER dump: the drill restores PostgreSQL dumps only"
say "set $SET ($(stat -c %s "$WORK/database.dump") bytes)"

# ── restore into a database of its own ──────────────────────────────────────────────────────────────────────────────────
STAGE="restore"
DB="onhost_drill_$(date -u +%Y%m%d%H%M%S)"
pg createdb "$DB" || die 3 "createdb $DB failed (the role needs CREATEDB)"
CREATED=1
START="$(date +%s)"
say "pg_restore into $DB"
pg pg_restore --no-owner --no-privileges --exit-on-error --dbname="$DB" "$WORK/database.dump" || die 3 "pg_restore failed"

# ── measure the restored copy ───────────────────────────────────────────────────────────────────────────────────────────
STAGE="measure"
q() { pg psql -X -A -t -v ON_ERROR_STOP=1 --dbname="$DB" -c "$1"; }
MIGRATIONS="$(q 'select count(*) from migrations')"
USERS="$(q 'select count(*) from users')"
INVOICES="$(q 'select count(*) from invoices')"
POSTINGS="$(q 'select count(*) from ledger_postings')"
IMBALANCE="$(q "select coalesce(sum(case when direction = 'debit' then amount_minor else -amount_minor end), 0) from ledger_postings")"
UNBALANCED="$(q "select count(*) from (select transaction_id from ledger_postings group by transaction_id having sum(case when direction = 'debit' then amount_minor else 0 end) <> sum(case when direction = 'credit' then amount_minor else 0 end)) u")"
SECONDS_TAKEN=$(( $(date +%s) - START ))
say "migrations $MIGRATIONS · users $USERS · documents $INVOICES · postings $POSTINGS · imbalance $IMBALANCE · unbalanced $UNBALANCED · ${SECONDS_TAKEN} s"

STAGE="drop"
pg dropdb "$DB"
CREATED=0

# ── the application judges and records ────────────────────────────────────────────────────────────────────────────────
STAGE="record"
set +e
art onhost:platform:backup:drill-record "$SET" --database="$DB" --seconds="$SECONDS_TAKEN" \
  --measured="migrations=$MIGRATIONS,users=$USERS,invoices=$INVOICES,ledger_postings=$POSTINGS,ledger_imbalance=$IMBALANCE,unbalanced_transactions=$UNBALANCED"
RC=$?
set -e
case "$RC" in
  0) say "restore drill passed" ;;
  1) exit 1 ;;
  *) die 3 "the result could not be recorded (exit $RC)" ;;
esac
