#!/usr/bin/env bash
# Test fixture for tests/Feature/Platform/DeployGateTest.php (TASK-0032): builds a throw-away "host" in $1 — a bare
# origin, a site checkout at commit A, commit B (adds a migration) with an annotated tag vtest, a lightweight tag
# vlight, an SSH-signed tag vsigned (+ the same object as vrenamed, a PGP-block tag vpgp; the key's allowed_signers
# line in allowed_signers.src), the root-owned environment file etc/app.env that the site's .env is, stub binaries
# (php, systemctl with unit state, curl, flock, chown, fpm-reload) and the deployer installed from A with the real
# install-deployer.sh. Nothing outside $1 is touched. Prints KEY=VALUE lines for the test.
#
#   REPO_ROOT=<this repository> REAL_PHP=<php binary> bash deploy-sandbox.sh <empty dir>
set -euo pipefail

box="$1"
: "${REPO_ROOT:?}" "${REAL_PHP:?}"
mkdir -p "$box"/{bin,sbin,lib,state,origin.git,seed}
gitc() { git -c user.name=deploy-test -c user.email=deploy-test@example.invalid -c core.autocrlf=false -c init.defaultBranch=development -c commit.gpgsign=false -c tag.gpgsign=false "$@"; }

# ── the repository: A = the deployer + a minimal site, B = A + one migration ─────────────────────────────────────────
seed="$box/seed"
gitc init -q "$seed"
mkdir -p "$seed/infra/aapanel" "$seed/database/migrations" "$seed/contracts/openapi" "$seed/storage/framework" "$seed/bootstrap/cache"
cp "$REPO_ROOT/infra/aapanel/deploy.sh" "$REPO_ROOT/infra/aapanel/deploy-gate.php" "$REPO_ROOT/infra/aapanel/install-deployer.sh" "$seed/infra/aapanel/"
printf '<?php // stub: the sandbox php answers artisan calls\n' > "$seed/artisan"
printf 'openapi: 3.1.0\n' > "$seed/contracts/openapi/onhost-v1.yaml"
printf '%s\n' '.env' '/VERSION' '/storage/' '/bootstrap/cache/' > "$seed/.gitignore"
printf 'keep\n' > "$seed/database/migrations/.gitkeep"
gitc -C "$seed" add -A && gitc -C "$seed" commit -q -m "A: last good"
sha_a="$(git -C "$seed" rev-parse HEAD)"
printf '<?php // new table\n' > "$seed/database/migrations/2026_09_27_000001_x.php"
gitc -C "$seed" add -A && gitc -C "$seed" commit -q -m "B: adds a migration"
sha_b="$(git -C "$seed" rev-parse HEAD)"
gitc -C "$seed" tag -a vtest -m "Release-Record: .ai/releases/x.md" -m "Accept-Gate: storage|queue driver — accepted for the sandbox test"
gitc -C "$seed" tag vlight
# the owner's SSH key: vsigned is a real SSH-signed tag on B; vrenamed is the same tag object filed under another name;
# vpgp carries a (fake) PGP block — git would hand it to root's GnuPG keyring, never to allowed_signers
ssh-keygen -q -t ed25519 -N '' -C owner -f "$box/owner_key"
printf 'owner namespaces="git" %s\n' "$(cut -d' ' -f1,2 "$box/owner_key.pub")" > "$box/allowed_signers.src"
gitc -C "$seed" -c gpg.format=ssh -c user.signingkey="$box/owner_key" tag -s vsigned -m "Release-Record: .ai/releases/x.md" -m "Verdict: READY"
gitc -C "$seed" update-ref refs/tags/vrenamed "$(git -C "$seed" rev-parse refs/tags/vsigned)"
pgp="$(printf 'object %s\ntype commit\ntag vpgp\ntagger deploy-test <deploy-test@example.invalid> 1790000000 +0000\n\nrelease\n-----BEGIN PGP SIGNATURE-----\n\nZmFrZQ==\n-----END PGP SIGNATURE-----\n' "$sha_b" | git -C "$seed" mktag)"
gitc -C "$seed" update-ref refs/tags/vpgp "$pgp"
git clone -q --bare "$seed" "$box/origin.git"

app="$box/app"
git clone -q -c core.autocrlf=false "$box/origin.git" "$app"
git -C "$app" checkout -q --detach "$sha_a"
mkdir -p "$app/storage/framework" "$app/storage/app" "$app/bootstrap/cache"
# the root-owned environment file and the site's .env that IS it (a symlink on the host; a hard link here, because Git
# Bash cannot make symlinks without privileges — the deployer's rule is "the same file", which both satisfy)
mkdir -p "$box/etc"
printf 'APP_ENV=staging   # staging | production\nAPP_URL=https://staging.test\n' > "$box/etc/app.env"
ln -f "$box/etc/app.env" "$app/.env"

# ── stubs: every call is appended to $STUB_LOG; knobs come from bin/stub.env (artisan runs under env -i) ─────────────
cat > "$box/bin/stub.env" <<EOF
STUB_LOG='$box/stub.log'
REAL_PHP='$REAL_PHP'
EOF
cat > "$box/bin/php" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
case "${1:-}" in *deploy-gate.php) exec "$REAL_PHP" "$@" ;; esac
printf 'php %s [cache=%s]\n' "$*" "${APP_CONFIG_CACHE:-}" >> "$STUB_LOG"
case "${1:-}" in *composer*) exit "${STUB_COMPOSER_EXIT:-0}" ;; esac
app="$(dirname "${1:-.}")"
case "${2:-}" in
  down)
    secret=""
    for a in "$@"; do case "$a" in --with-secret) secret="s${RANDOM}x${RANDOM}x${RANDOM}" ;; --secret=*) secret="${a#--secret=}" ;; esac; done
    mkdir -p "$app/storage/framework"
    if [ -n "$secret" ]; then
      printf '{"secret":"%s","retry":60}' "$secret" > "$app/storage/framework/down"
      printf '%s\n' "$secret" >> "$here/secrets.seen"
    else
      printf '{"secret":null,"retry":60}' > "$app/storage/framework/down"
    fi ;;
  up) rm -f "$app/storage/framework/down" ;;
  onhost:platform:backup)
    if [ -n "${STUB_BACKUP_OUT:-}" ]; then printf '%s\n' "$STUB_BACKUP_OUT"; else echo "Set platform-backups/$(date -u +%Y%m%d-%H%M%S) on disk local (pgsql); pruned 0"; fi
    exit "${STUB_BACKUP_EXIT:-0}" ;;
  onhost:platform:backup:verify) echo "OK ${3:-} created x"; exit "${STUB_VERIFY_EXIT:-0}" ;;
  migrate) exit "${STUB_MIGRATE_EXIT:-0}" ;;
  config:cache)
    target="${APP_CONFIG_CACHE:-}"
    # on Windows the deployer hands PHP a drive-relative path (/Users/…); this stub is bash and needs /c/Users/…
    if [ -n "$target" ] && command -v cygpath >/dev/null 2>&1; then target="$(cygpath -u "$(cygpath -m "$here" | cut -c1-2)$target")"; fi
    [ -z "$target" ] || printf '<?php return []; // built by root\n' > "$target" ;;
  onhost:doctor) cat "$here/doctor.json"; exit "${STUB_DOCTOR_EXIT:-0}" ;;
esac
exit 0
EOF
cat > "$box/bin/systemctl" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
printf 'systemctl %s\n' "$*" >> "$STUB_LOG"
# unit state: stop → inactive, start → active (STUB_START_FAIL names units that fail to start), is-active reads it
mkdir -p "$here/units"
last=""; for a in "$@"; do last="$a"; done
case "${1:-}" in
  list-units) for u in ${STUB_UNITS:-}; do echo "$u loaded active running stub"; done ;;
  stop) for u in "$@"; do case "$u" in stop|--*) ;; *) echo inactive > "$here/units/$u" ;; esac; done ;;
  start)
    case " ${STUB_START_FAIL:-} " in *" $last "*) echo failed > "$here/units/$last"; exit 1 ;; esac
    echo active > "$here/units/$last" ;;
  is-active) [ "$(cat "$here/units/$last" 2>/dev/null)" = active ] || exit 3 ;;
esac
exit 0
EOF
cat > "$box/bin/curl" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
printf 'curl %s\n' "$*" >> "$STUB_LOG"
code="${STUB_CURL_CODE:-200}"
prev=""
for a in "$@"; do
  if [ "$prev" = -K ]; then
    if grep -q '^header = "Cookie: laravel_maintenance=' "$a" 2>/dev/null; then code="${STUB_CURL_BYPASS_CODE:-200}"; else code=000; fi
  fi
  prev="$a"
done
printf '%s' "$code"
EOF
for tool in flock chown fpm-reload; do
  cat > "$box/bin/$tool" <<EOF
#!/usr/bin/env bash
. "$box/bin/stub.env"
printf '$tool %s\n' "\$*" >> "\$STUB_LOG"
exit "\${STUB_$(printf '%s' "$tool" | tr 'a-z-' 'A-Z_')_EXIT:-0}"
EOF
done
chmod +x "$box/bin/"*

# ── the deployer, installed from A the way the runbook does it ──────────────────────────────────────────────────────
SHA="$sha_a" FIRST=1 APP_DIR="$app" ENV_FILE="$box/etc/app.env" DEPLOY_STATE_DIR="$box/state" DEPLOYER_BIN="$box/sbin/onhost-deploy" \
  DEPLOYER_LIB_DIR="$box/lib" DEPLOY_OWNER_UID="$(id -u)" bash "$REPO_ROOT/infra/aapanel/install-deployer.sh" >/dev/null

printf 'SHA_A=%s\nSHA_B=%s\nAPP=%s\nSEED=%s\nENV=%s\nUID=%s\nUSER=%s\n' "$sha_a" "$sha_b" "$app" "$seed" "$box/etc/app.env" "$(id -u)" "$(id -un)"
