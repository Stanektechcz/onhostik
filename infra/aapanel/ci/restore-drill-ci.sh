#!/usr/bin/env bash
# P13 (phase R): the restore drill for real on a throw-away CI runner (lesson provision-twice: a script that restores the
# platform is proven by restoring, not by reading). Against a disposable PostgreSQL (the job's service container):
#   1. migrate and seed this tree, post one balanced ledger transaction, back up (pg_dump) and verify the set;
#   2. restore-drill.sh must refuse a superuser (exit 2) and pass as a plain role with CREATEDB (exit 0), drop its database and leave the doctor row OK — read back from the backup disk;
#   3. an unbalanced ledger in a new set must fail the drill (exit 1), still drop the database, and turn the row into a warning;
#   4. a tampered dump must be refused before anything is created (exit 3);
#   5. a good set again passes.
#   DB_HOST=… DB_PORT=… DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=… bash infra/aapanel/ci/restore-drill-ci.sh <empty work dir>
set -euo pipefail

work="${1:?usage: restore-drill-ci.sh <empty work dir>}"
repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
mkdir -p "$work"
cd "$repo"
php="$(command -v php)"
export PGHOST="${DB_HOST:?}" PGPORT="${DB_PORT:-5432}" PGUSER="${DB_USERNAME:?}" PGPASSWORD="${DB_PASSWORD:?}"
fail() { printf '✖ %s\n' "$*" >&2; exit 1; }

# the drill runs artisan with a clean environment (env -i), like the host: the application reads its configuration from .env
cat > .env <<ENV
APP_ENV=local
APP_DEBUG=false
APP_KEY=
APP_URL=http://localhost
DB_CONNECTION=pgsql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:?}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
MAIL_MAILER=array
ONHOST_SECRETS_DRIVER=env
ONHOST_PLATFORM_BACKUP_DISK=local
ONHOST_PLATFORM_BACKUP_FILES_ROOT=${work}/private-files
ENV
mkdir -p "$work/private-files/invoices"
echo '%PDF-1.4 drill' > "$work/private-files/invoices/FV-1.pdf"
"$php" artisan key:generate --force --no-interaction >/dev/null
"$php" artisan migrate --force --no-interaction >/dev/null
"$php" artisan db:seed --force --no-interaction >/dev/null

sql() { psql -X -A -t -v ON_ERROR_STOP=1 --dbname="$DB_DATABASE" -c "$1"; }
sql "insert into ledger_transactions (id, kind, currency, idempotency_key, posted_at, created_at) values ('ltx_drill_1', 'drill', 'CZK', 'restore-drill-ci-1', now(), now())" >/dev/null
sql "insert into ledger_postings (id, transaction_id, account_id, direction, amount_minor, currency, created_at) values ('lp_drill_1', 'ltx_drill_1', 'acc_a', 'debit', 12100, 'CZK', now()), ('lp_drill_2', 'ltx_drill_1', 'acc_b', 'credit', 12100, 'CZK', now())" >/dev/null

backup() { sleep 1; "$php" artisan onhost:platform:backup >/dev/null && "$php" artisan onhost:platform:backup:verify; } # a set is named to the second
# the drill restores as a plain role with CREATEDB (the service container's own user is a superuser, which the script refuses)
sql "create role onhost_drill_role login createdb password 'drill'" >/dev/null
drill_as() { set +e; PGUSER="$1" PGPASSWORD="$2" RUN_USER="$(id -un)" APP_DIR="$repo" PHP="$php" DRILL_TMP="$work" DRILL_LOCK="$work/drill.lock" bash infra/aapanel/restore-drill.sh; local rc=$?; set -e; return "$rc"; }
drill() { drill_as onhost_drill_role drill; }
leftovers() { psql -X -A -t --dbname="$DB_DATABASE" -c "select count(*) from pg_database where datname like 'onhost_drill_%'"; }
row() { # the doctor row, read after the cache is cleared: the verdict must come back from the backup disk
  "$php" artisan cache:clear >/dev/null
  "$php" artisan onhost:doctor --json 2>/dev/null | "$php" -r '$j = json_decode(stream_get_contents(STDIN), true); foreach ($j["checks"] ?? [] as $c) { if ($c["check"] === "platform restore drill within 35 days") { echo $c["status"], " ", $c["detail"], "\n"; } }' || true
}

echo "── 1. no drill yet"
row | grep -q '^WARN no restore drill recorded yet' || fail "the row must warn before the first drill: $(row)"

echo "── 2. a superuser is refused before anything is created; a good set passes as a plain role"
backup
rc=0; drill_as "$DB_USERNAME" "$DB_PASSWORD" || rc=$?
[ "$rc" = 2 ] || fail "a superuser drill exited $rc, expected 2"
[ "$(leftovers)" = 0 ] || fail "a refused drill created a database"
rc=0; drill || rc=$?
[ "$rc" = 0 ] || fail "the drill of a good set exited $rc"
[ "$(leftovers)" = 0 ] || fail "the drill left its database behind"
row | tee /dev/stderr | grep -q '^OK set platform-backups/.* ledger balanced' || fail "the row is not OK after a passed drill"
[ -f storage/app/private/platform-backups-restore-drill.json ] || fail "no verdict pointer on the backup disk"

echo "── 3. an unbalanced ledger fails the drill"
sql "insert into ledger_postings (id, transaction_id, account_id, direction, amount_minor, currency, created_at) values ('lp_drill_3', 'ltx_drill_1', 'acc_a', 'debit', 5, 'CZK', now())" >/dev/null
backup
rc=0; drill || rc=$?
[ "$rc" = 1 ] || fail "the drill of an unbalanced ledger exited $rc, expected 1"
[ "$(leftovers)" = 0 ] || fail "the failed drill left its database behind"
row | tee /dev/stderr | grep -q '^WARN .*FAILED: ledger debits and credits differ by 5' || fail "the row does not say the drill failed"

echo "── 4. a tampered dump is refused before anything is created"
newest="$(ls -d storage/app/private/platform-backups/*/ | sort | tail -n 1)"
printf 'x' >> "${newest}database.pgdump"
rc=0; drill || rc=$?
[ "$rc" = 3 ] || fail "a tampered dump exited $rc, expected 3"
[ "$(leftovers)" = 0 ] || fail "a refused drill created a database"
row | grep -q 'FAILED' || fail "a refused drill must not overwrite the last verdict"

echo "── 5. a good set passes again"
sql "delete from ledger_postings where id = 'lp_drill_3'" >/dev/null
backup
rc=0; drill || rc=$?
[ "$rc" = 0 ] || fail "the drill of a good set exited $rc"
row | grep -q '^OK ' || fail "the row is not OK again"
echo "restore drill: OK"
