<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

/**
 * Whether the version a panel reports is one its adapter declares (`supportedVendorVersions()`). The adapters write the
 * list in the vendors' own shapes: a release line (`8.2` covers `8.2.4`, not `8.20.1`), a wildcard (`7.0.x`), an
 * ISPConfig release with its patch level (`3.2.11` covers `3.2.11p2`), a distribution after `+` (`v1.33+rke2` covers
 * `v1.33.5+rke2r1`, not `+k3s1`).
 */
final class VendorVersion
{
    /** @param list<string> $declared */
    public static function declared(string $reported, array $declared): bool
    {
        [$version, $build] = array_pad(explode('+', strtolower(trim($reported)), 2), 2, null);
        if ($version === '') {
            return false;
        }
        foreach ($declared as $pattern) {
            [$line, $flavour] = array_pad(explode('+', strtolower(trim((string) $pattern)), 2), 2, null);
            if ($line === '' || ($flavour !== null && ($build === null || ! str_starts_with($build, $flavour)))) {
                continue;
            }
            if (str_ends_with($line, '.x')) {
                if (str_starts_with($version, substr($line, 0, -1))) {
                    return true;
                }

                continue;
            }
            if ($version === $line || str_starts_with($version, $line.'.') || preg_match('/^'.preg_quote($line, '/').'p\d+$/', $version) === 1) {
                return true;
            }
        }

        return false;
    }
}
