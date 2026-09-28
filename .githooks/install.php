<?php

/**
 * Sets core.hooksPath to .githooks so the pre-commit hook (which blocks a staged
 * .env or other private runtime file) is active for every developer without a
 * manual step (audit C11 / TASK-0048).
 *
 * Invoked from composer.json's "post-install-cmd" and "setup" scripts. It must
 * never fail the install: a CI checkout from a tarball, or the aaPanel deployer's
 * detached release directory (whose .git is not inside the site tree), simply is
 * not a git checkout here, so this is a harmless no-op in both cases.
 */

$devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

exec("git rev-parse --is-inside-work-tree > {$devNull} 2>&1", $output, $exitCode);

if ($exitCode !== 0) {
    // Not inside a git work tree (or no git binary on PATH) — nothing to do.
    exit(0);
}

exec("git config core.hooksPath .githooks > {$devNull} 2>&1", $output, $exitCode);

// Never fail composer install/setup over this, even if the git config write itself
// somehow failed (e.g. a read-only .git in a shared cache).
exit(0);
