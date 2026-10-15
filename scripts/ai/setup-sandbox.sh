#!/usr/bin/env bash
# SessionStart hook for Claude Code cloud sessions (Linux sandbox), W4-2.
# Makes vendor/ and the PHP extensions required by composer.json available so Pest, Pint and Larastan run.
# Never creates or reads .env, never runs key:generate or migrate, never talks to panels:
# the test environment (APP_KEY included) comes from phpunit.xml.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

root="$(git rev-parse --show-toplevel)"
cd "$root"

warn() { echo "setup-sandbox: $*" >&2; }

# 1) PHP extensions required by composer.json (require.ext-*).
required="$(php -r '$c = json_decode((string) file_get_contents("composer.json"), true); foreach (array_keys($c["require"] ?? []) as $k) { if (str_starts_with($k, "ext-")) { echo strtolower(substr($k, 4)), PHP_EOL; } }')"

missing_exts() {
    local ext loaded out=""
    loaded="$(php -m | tr '[:upper:]' '[:lower:]')"
    for ext in $required; do
        if ! grep -qx "$ext" <<<"$loaded"; then
            out="$out $ext"
        fi
    done
    echo "${out# }"
}

missing="$(missing_exts)"
if grep -qw bcmath <<<"$missing"; then
    if [ "$(id -u)" = "0" ] && command -v apt-get >/dev/null 2>&1; then
        export DEBIAN_FRONTEND=noninteractive
        if ! apt-get install -y php8.3-bcmath >/dev/null 2>&1; then
            # The package index of a fresh image is often empty: update only when the install failed.
            apt-get update >/dev/null 2>&1 || true
            apt-get install -y php8.3-bcmath >/dev/null 2>&1 || true
        fi
        missing="$(missing_exts)"
    fi
fi
if [ -n "$missing" ]; then
    warn "missing PHP extension(s): $missing (install them; --ignore-platform-req is not an option)"
    exit 1
fi

# 2) Composer dependencies, skipped when vendor/ is current with composer.lock.
if [ -f vendor/autoload.php ] && [ -f vendor/composer/installed.json ] \
    && ! [ composer.lock -nt vendor/composer/installed.json ]; then
    exit 0
fi

if ! command -v composer >/dev/null 2>&1; then
    warn "composer is not installed"
    exit 1
fi

if ! composer install --no-interaction --prefer-dist --no-progress; then
    # In a fresh sandbox usually only the scripts step (package:discover) fails.
    warn "composer install failed; retrying with --no-scripts"
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
    # --no-scripts here too: post-autoload-dump runs the same package:discover that just failed.
    composer dump-autoload --no-interaction --no-scripts
    warn "WARNING: composer scripts (package:discover) were skipped; Laravel package discovery may be stale"
fi
