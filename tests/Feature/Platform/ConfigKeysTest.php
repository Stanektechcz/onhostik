<?php

declare(strict_types=1);

/*
 * A setting the code reads has to exist. `config('onhost.security.staff_mfa_required')` was read by the step-up and
 * by the doctor while the setting lives under `onhost.identity.*`: the read silently gave null, so the doctor reported
 * staff MFA as off whatever the environment said, and the rule for staff followed a value nobody could set. A missing
 * key never fails at runtime — it only behaves as "off" — so it is caught here.
 */

/** @return list<string> every literal `config('onhost.…')` key the code reads */
function configKeysReadByCode(): array
{
    $keys = [];
    foreach (['domains', 'app', 'platform', 'providers', 'routes'] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/config\('(onhost\.[A-Za-z0-9_.]+)'/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (! str_ends_with($key, '.')) { // a key built by concatenation is checked where it is built
                    $keys[$key] = true;
                }
            }
        }
    }
    ksort($keys);

    return array_keys($keys);
}

it('reads no setting that the configuration does not define', function () {
    $keys = configKeysReadByCode();
    expect(count($keys))->toBeGreaterThan(200);
    expect(array_values(array_filter($keys, fn (string $key) => ! config()->has($key))))->toBe([]);
});
