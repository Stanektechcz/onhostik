<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

/**
 * Whether a version falls into an advisory's range (`introduced` ≤ v, v < `fixed`, v ≤ `last_affected`, or one of `versions`).
 * Versions are compared the way PHP compares release numbers (`version_compare`), after dropping a leading `v`, a Debian epoch
 * (`1:`) and a build suffix (`+rke2r1`); WordPress, Composer, npm and the panels all number their releases that way. A range with
 * no bound at all says nothing about versions: `null` (the match is only probable, never fixed automatically).
 */
final class AdvisoryVersion
{
    /** @param array<string,mixed> $range */
    public static function affects(string $version, array $range): ?bool
    {
        $v = self::normalize($version);
        if ($v === '') {
            return null;
        }
        $versions = array_map(fn ($x) => self::normalize((string) $x), (array) ($range['versions'] ?? []));
        $introduced = isset($range['introduced']) ? self::normalize((string) $range['introduced']) : '';
        $fixed = isset($range['fixed']) ? self::normalize((string) $range['fixed']) : '';
        $last = isset($range['last_affected']) ? self::normalize((string) $range['last_affected']) : '';
        if ($versions !== [] && $introduced === '' && $fixed === '' && $last === '') {
            return in_array($v, $versions, true);
        }
        if ($introduced === '' && $fixed === '' && $last === '') {
            return null;
        }
        if ($introduced !== '' && version_compare($v, $introduced, '<')) {
            return false;
        }
        if ($fixed !== '' && version_compare($v, $fixed, '>=')) {
            return false;
        }
        if ($last !== '' && version_compare($v, $last, '>')) {
            return false;
        }

        return true;
    }

    public static function normalize(string $version): string
    {
        $v = strtolower(trim($version));
        $v = (string) preg_replace('/^\d+:/', '', $v); // Debian epoch
        $v = (string) preg_replace('/\+.*$/', '', $v); // build metadata
        $v = ltrim($v, 'v');

        return preg_match('/^[0-9][0-9a-z.~_-]*$/', $v) === 1 ? $v : '';
    }

    /** The leading `n` parts of a version (`6.4.3`, 2 → `6.4`). */
    public static function line(string $version, int $parts): string
    {
        return implode('.', array_slice(explode('.', self::normalize($version)), 0, $parts));
    }
}
