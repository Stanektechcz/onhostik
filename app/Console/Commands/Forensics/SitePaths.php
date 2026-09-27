<?php

declare(strict_types=1);

namespace App\Console\Commands\Forensics;

/**
 * Lexical judgement of stored paths and shell commands against an aaPanel site root, for the PA-02 source of
 * `onhost:forensics:lookback` (TASK-0038). Lexical only: a symlink inside the root lands elsewhere and is invisible here —
 * the node is the only place that shows it (the TASK-0034 dry-run).
 */
final class SitePaths
{
    /** Absolute paths a site's command may name without reaching another tenant: binaries, PHP builds, null device. */
    private const SYSTEM_PREFIXES = ['/usr/', '/bin/', '/sbin/', '/opt/', '/www/server/php/', '/www/server/onhost/', '/tmp/', '/dev/null', '/dev/stdout', '/dev/stderr'];

    /** @param array<string,mixed> $desired @return list<string> the paths a file operation names */
    public static function filePaths(array $desired): array
    {
        $paths = array_merge(array_map(fn ($k) => $desired[$k] ?? null, ['path', 'from', 'to', 'target']), (array) ($desired['paths'] ?? []));

        return array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== ''));
    }

    /**
     * What a terminal or scheduled command shows of a way out of the root: absolute paths outside it (not system paths),
     * a `..` in the command or its working directory, an `ln -s`.
     *
     * @return array{outside: list<string>, climbs: bool, symlink: bool}
     */
    public static function commandTrace(string $command, string $cwd, ?string $root): array
    {
        return [
            'outside' => array_values(array_filter(self::absolutePaths($command), fn (string $p) => $root !== null && ! self::systemPath($p) && self::outsideRoot($p, $root))),
            'climbs' => preg_match('~(^|[\s/=\'"])\.\.(/|\s|$)~', $command) === 1 || ($cwd !== '' && self::outsideRoot($cwd, null)),
            'symlink' => preg_match('~(^|[\s;&|(])ln\s+(-\w*s\w*|--symbolic)\b~', $command) === 1,
        ];
    }

    /** Lexically: a NUL, a `..` that climbs above the root, or an absolute path not under the root (stored paths are relative). */
    public static function outsideRoot(string $path, ?string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, "\0")) {
            return true;
        }
        if (str_starts_with($path, '/')) {
            $normal = self::normalize($path);

            return $root === null || $normal === null || ($normal !== rtrim($root, '/') && ! str_starts_with($normal, rtrim($root, '/').'/'));
        }

        return self::normalize('/'.$path) === null;
    }

    /** `/a/b/../c` → `/a/c`; null when `..` climbs above `/`. */
    private static function normalize(string $absolute): ?string
    {
        $stack = [];
        foreach (explode('/', $absolute) as $segment) {
            if ($segment === '..') {
                if ($stack === []) {
                    return null;
                }
                array_pop($stack);
            } elseif ($segment !== '' && $segment !== '.') {
                $stack[] = $segment;
            }
        }

        return '/'.implode('/', $stack);
    }

    /** @return list<string> absolute paths named in a shell command (not the `//` of a URL) */
    private static function absolutePaths(string $command): array
    {
        preg_match_all('~(?<![^\s\'"=<>])/[^\s\'"`;|&<>()]*~', $command, $matches);

        return array_values(array_unique($matches[0]));
    }

    private static function systemPath(string $path): bool
    {
        foreach (self::SYSTEM_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return ! str_contains($path, '..');
            }
        }

        return false;
    }
}
