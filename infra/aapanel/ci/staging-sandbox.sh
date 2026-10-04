#!/usr/bin/env bash
# Test fixture for tests/Feature/Platform/DeployGateTest.php (TASK-0082, E0): a throw-away "aaPanel host" in $1 for
# infra/aapanel/staging.sh — the site tree app/ (checked out from root's repository state/repo.git, no .git in the
# tree), a bare origin with a development branch (the tip `setup` must deploy), the root-owned environment file
# etc/app.env that the site's .env is, the deployer's last-good.json naming release A, an empty systemd directory, a
# preload file (etc/ld.so.preload) and stubs: id (uid 0), systemctl, setpriv, setsid, chown, chmod, su, node (v24),
# npm and the gated deployer sbin/onhost-deploy (STUB_DEPLOY_RC; on 0, 4, 5 and 7 it writes VERSION the way the real
# one switches the tree in place). Every stub call is appended to stub.log. Nothing outside $1 is touched. Prints
# KEY=VALUE lines for the test.
#
#   REPO_ROOT=<this repository> bash staging-sandbox.sh <empty dir>
set -euo pipefail
umask 022   # a root checkout's modes: the code check refuses group/other-writable code, so the fixture must not be

box="$1"
: "${REPO_ROOT:?}"
mkdir -p "$box"/{bin,sbin,state,systemd,etc,seed,work}
gitc() { git -c user.name=staging-test -c user.email=staging-test@example.invalid -c core.autocrlf=false -c init.defaultBranch=development -c commit.gpgsign=false "$@"; }

# ── the repository: one commit with the top-level directories the code check walks ───────────────────────────────────
seed="$box/seed"
gitc init -q "$seed"
mkdir -p "$seed/app" "$seed/config" "$seed/public" "$seed/bootstrap" "$seed/storage"
printf '<?php // app\n' > "$seed/app/Kernel.php"
printf '<?php return [];\n' > "$seed/config/app.php"
printf '<?php // front controller\n' > "$seed/public/index.php"
printf '<?php // bootstrap\n' > "$seed/bootstrap/app.php"
printf '*\n!.gitignore\n' > "$seed/storage/.gitignore"
printf '%s\n' '.env' '/VERSION' '/bootstrap/cache/' '/public/build/' '/node_modules/' > "$seed/.gitignore"
gitc -C "$seed" add -A && gitc -C "$seed" commit -q -m "the site"
tip="$(git -C "$seed" rev-parse HEAD)"
git clone -q --bare "$seed" "$box/origin.git"

app="$box/app"
repo="$box/state/repo.git"
mkdir -p "$app"
git init -q --bare "$repo"
git --git-dir="$repo" config core.bare false
git --git-dir="$repo" config core.autocrlf false
git --git-dir="$repo" remote add origin "$box/origin.git"
git --git-dir="$repo" fetch -q origin
GIT_DIR="$repo" GIT_WORK_TREE="$app" git -C "$app" checkout -q -f --detach "$tip"
mkdir -p "$app/storage/framework" "$app/bootstrap/cache"

# release A is the last good one (the deployer's record); B is the release under test (the deployer is a stub)
sha_a=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
sha_b=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
printf '%s %s\n' "$sha_a" "$sha_a" > "$app/VERSION"
printf '{"sha":"%s","ref":"%s","tag":"","at":"2026-10-01T00:00:00Z","operator":"op"}\n' "$sha_a" "$sha_a" > "$box/state/last-good.json"

# the root-owned environment file and the site's .env that IS it (a hard link: Git Bash makes no symlinks unprivileged)
printf 'APP_ENV=staging\n' > "$box/etc/app.env"
ln -f "$box/etc/app.env" "$app/.env"
printf '/usr/local/usranalyse/lib/libusranalyse.so\n' > "$box/etc/ld.so.preload"

# ── stubs: every call is appended to $STUB_LOG; knobs come from bin/stub.env (as_run runs under env -i) ────────────────
cat > "$box/bin/stub.env" <<EOF
STUB_LOG='$box/stub.log'
EOF
log_stub() { # $1 = name: a stub that logs its argv and exits STUB_<NAME>_EXIT
  cat > "$box/bin/$1" <<EOF
#!/usr/bin/env bash
. "$box/bin/stub.env"
printf '$1 %s\n' "\$*" >> "\$STUB_LOG"
exit "\${STUB_$(printf '%s' "$1" | tr 'a-z-' 'A-Z_')_EXIT:-0}"
EOF
}
for tool in chown chmod systemctl fpm-reload; do log_stub "$tool"; done
cat > "$box/bin/id" <<'EOF'
#!/usr/bin/env bash
# root for staging.sh's own check; anything else is the real id
if [ "$*" = "-u" ]; then echo 0; exit 0; fi
IFS=: read -r -a dirs <<< "$PATH"
for d in "${dirs[@]}"; do if [ -x "$d/id" ] && ! [ "$d/id" -ef "$0" ]; then exec "$d/id" "$@"; fi; done
exit 1
EOF
cat > "$box/bin/setpriv" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
printf 'setpriv %s\n' "$*" >> "$STUB_LOG"
while [ $# -gt 0 ] && [ "$1" != -- ]; do shift; done
shift
exec "$@"
EOF
cat > "$box/bin/setsid" <<'EOF'
#!/usr/bin/env bash
while [ $# -gt 0 ] && [ "${1#-}" != "$1" ]; do shift; done
exec "$@"
EOF
cat > "$box/bin/su" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
printf 'su %s\n' "$*" >> "$STUB_LOG"
echo 1   # the role exists
EOF
cat > "$box/bin/node" <<'EOF'
#!/usr/bin/env bash
echo v24.9.0
EOF
cat > "$box/bin/npm" <<'EOF'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
. "$here/stub.env"
printf 'npm %s\n' "$*" >> "$STUB_LOG"
case "$*" in "run build"*) exit "${STUB_NPM_BUILD_EXIT:-0}" ;; esac
exit "${STUB_NPM_EXIT:-0}"
EOF
# the gated deployer: logs what it was asked for and answers STUB_DEPLOY_RC; from the switch on (0, 4, 5, 7) the tree
# holds the new code and VERSION names it, exactly as the real deployer leaves it
cat > "$box/sbin/onhost-deploy" <<EOF
#!/usr/bin/env bash
. "$box/bin/stub.env"
printf 'onhost-deploy REF=%s EXPECTED_SHA=%s OPERATOR=%s\n' "\${REF:-}" "\${EXPECTED_SHA:-}" "\${DEPLOY_OPERATOR:-}" >> "\$STUB_LOG"
rc="\${STUB_DEPLOY_RC:-0}"
case "\$rc" in 0|4|5|7) printf '%s %s\n' "\${STUB_DEPLOY_VERSION:-\$EXPECTED_SHA}" "\$REF" > "$app/VERSION" ;; esac
[ "\$rc" = 0 ] && printf '{"sha":"%s","ref":"%s","tag":"","at":"x","operator":"op"}\n' "\$EXPECTED_SHA" "\$REF" > "$box/state/last-good.json"
exit "\$rc"
EOF
chmod +x "$box/bin/"* "$box/sbin/onhost-deploy"

printf 'SHA_A=%s\nSHA_B=%s\nTIP=%s\nAPP=%s\nENV=%s\nUID=%s\nUSER=%s\n' "$sha_a" "$sha_b" "$tip" "$app" "$box/etc/app.env" "$(id -u)" "$(id -un)"
