#!/usr/bin/env bash
# Phase Q, Q4 (decision P-R78, TASK-0190): the spec staging.sh writes for the deployer (`write_expected_env` → expected-env, held
# by `deploy-gate.php env-assert` before every deploy) must accept the operator switches that the production plan turns on after
# launch. It pinned ONHOST_TOKEN_ORGANIZATION_REQUIRED=false, so rehearsing step 8 of the plan on staging (ST-6, `true`) would
# have stopped every later deploy with rc 2. This runs the real function of the real script against a staging-like app.env in a
# throw-away directory: the switch on, off and unset passes; anything else is refused; a pinned line is still held.
#
#   PHP_BIN=<php> bash infra/aapanel/ci/expected-env-switches.sh <empty dir>     (nothing outside the directory is touched)
set -euo pipefail

box="${1:?usage: expected-env-switches.sh <empty dir>}"
repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
mkdir -p "$box/state" "$box/etc"
PHP="${PHP_BIN:-$(command -v php || true)}"
[ -n "$PHP" ] || { echo "no php (set PHP_BIN)" >&2; exit 2; }
export DEPLOY_STATE_DIR="$box/state" ENV_DIR="$box/etc" PHP
# shellcheck source=../staging.sh
source "$repo/infra/aapanel/staging.sh"   # sourced: only the functions and variables, main does not run
set +e                                  # this script decides what a failure is
DG="$repo/infra/aapanel/deploy-gate.php" # the repository's gate, not the host's installed copy

# app.env as staging.sh leaves it: every pinned line of the spec as written, the pattern lines with the staging value
spec_lines() { sed -n '/^write_expected_env() {/,/^EOF$/p' "$repo/infra/aapanel/staging.sh" | sed '1,2d;$d'; }
base_env() {
  spec_lines | while IFS= read -r line; do
    case $line in
      *'*'*) ;;                                                  # a family that must stay empty: nothing to write
      ONHOST_TOKEN_ORGANIZATION_REQUIRED*) ;;                    # each case below writes its own
      ONHOST_ACME_DIRECTORY~=*) echo 'ONHOST_ACME_DIRECTORY=https://acme-staging-v02.api.letsencrypt.org/directory';;
      ONHOST_VIES_ENABLED~=*) echo 'ONHOST_VIES_ENABLED=false';;
      DB_DATABASE!=*) echo 'DB_DATABASE=onhost_staging_b';;
      *'~='*|*'!='*) echo "unhandled pattern line (add its staging value here): ${line%%[~!]=*}" >&2; return 1;;
      *=*) echo "$line";;
    esac
  done
}

fail=0
check() { # <label> <expected rc: 0 passes, 13 refused> <app.env lines that replace the base line of the same key>
  local label=$1 want=$2 extra=$3 rc
  printf '%b' "$extra" > "$box/extra.env"
  base_env > "$box/base.env" || exit 1
  sed -n 's/^\([A-Za-z0-9_]*=\).*/\1/p' "$box/extra.env" > "$box/extra.keys"
  { if [ -s "$box/extra.keys" ]; then grep -v -F -f "$box/extra.keys" "$box/base.env"; else cat "$box/base.env"; fi; cat "$box/extra.env"; } > "$ENV_DIR/app.env"
  write_expected_env > "$box/write.out" 2>&1 || { echo "FAIL $label: write_expected_env"; cat "$box/write.out"; fail=1; return; }
  "$PHP" "$DG" env-assert --file "$ENV_DIR/app.env" --spec "$DEPLOY_STATE_DIR/expected-env" > "$box/assert.out" 2>&1; rc=$?
  if [ "$rc" = "$want" ]; then echo "ok   $label (rc $rc)"; else echo "FAIL $label: rc $rc, expected $want"; grep -v '^OK ' "$box/assert.out"; fail=1; fi
  if grep -q 'lIvE-0123' "$box/assert.out"; then echo "FAIL $label: a value was printed"; fail=1; fi
}

check 'token switch off (false)'                                    0  'ONHOST_TOKEN_ORGANIZATION_REQUIRED=false\n'
check 'token switch on (true, ST-6 / plan step 8)'                  0  'ONHOST_TOKEN_ORGANIZATION_REQUIRED=true\n'
check 'token switch unset (code default off)'                       0  ''
check 'token switch misspelt (yes) is refused'                      13 'ONHOST_TOKEN_ORGANIZATION_REQUIRED=yes\n'
check 'token switch on, a pinned switch is still held (staff reach)' 13 'ONHOST_TOKEN_ORGANIZATION_REQUIRED=true\nONHOST_STAFF_REACH_ENFORCED=true\n'
check 'token switch on, an outward key is still refused'            13 'ONHOST_TOKEN_ORGANIZATION_REQUIRED=true\nAWS_SECRET_ACCESS_KEY=lIvE-0123\n'

exit "$fail"
