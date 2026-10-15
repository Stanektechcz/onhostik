#!/usr/bin/env bash
# Thin Linux counterpart of brain.ps1 for cloud sandboxes (W4-2). Only `gate` exists here.
#   ./brain.sh gate [--quick] [tests...]
# Baseline logic lives in scripts/ci/gate-classify.php, never in bash.
# TODO(W4-2 D): gate.ps1 převezme gate-classify.php
set -uo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$root"

if [ "${1:-}" != "gate" ]; then
    echo "usage: ./brain.sh gate [--quick] [tests...]" >&2
    exit 2
fi
shift

quick=0
tests=()
for arg in "$@"; do
    if [ "$arg" = "--quick" ]; then quick=1; else tests+=("$arg"); fi
done

baseline=".ai/baseline/baseline.json"
junit="$(mktemp "${TMPDIR:-/tmp}/onhost-gate-junit.XXXXXX")"
trap 'rm -f "$junit"' EXIT
: >"$junit"

steps=()
tool_fail=0
step() {
    local name="$1" code start
    shift
    echo
    echo "== $name =="
    start=$SECONDS
    "$@"
    code=$?
    if [ "$code" -eq 0 ]; then
        steps+=("PASS  $name (exit 0; $((SECONDS - start))s)")
    else
        steps+=("FAIL  $name (exit $code; $((SECONDS - start))s)")
    fi
    return "$code"
}

step 'Pint' php vendor/bin/pint --test || tool_fail=1
if [ "$quick" -eq 0 ]; then
    step 'Larastan' php vendor/bin/phpstan analyse --no-progress --memory-limit=2G || tool_fail=1
    step 'PHPStan baseline ratchet' php scripts/ci/phpstan-baseline-ratchet.php || tool_fail=1
fi

pest_args=(artisan test --compact --log-junit "$junit")
if [ "$quick" -eq 1 ]; then
    mapfile -t critical < <(php -r '$b = json_decode((string) file_get_contents($argv[1]), true); foreach ($b["critical_flows"] ?? [] as $f) { foreach ($f["tests"] ?? [] as $t) { echo $t, PHP_EOL; } }' "$baseline")
    pest_args+=("${critical[@]}")
    name='Pest (critical flows)'
else
    name='Pest'
fi
pest_args+=("${tests[@]}")
step "$name" php "${pest_args[@]}"
pest_code=$?

step 'Classify failures' php scripts/ci/gate-classify.php --junit="$junit" --baseline="$baseline" --pest-exit="$pest_code"
classify_code=$?

verdict=PASS
if [ "$classify_code" -ne 0 ] || [ "$tool_fail" -ne 0 ]; then verdict=FAIL; fi

echo
echo "VERDICT $verdict"
printf '  %s\n' "${steps[@]}"
[ "$verdict" = PASS ]
