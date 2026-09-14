<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

/**
 * POSIX shell quoting for commands that run on the nodes. PHP's escapeshellarg() quotes for the *local* platform
 * (double quotes on Windows, which the panels strip), so every remote command is quoted here instead.
 */
final class Q
{
    /** One argument, single-quoted for sh/bash on the node. */
    public static function arg(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    /** A list of arguments joined with spaces. */
    public static function args(string ...$values): string
    {
        return implode(' ', array_map([self::class, 'arg'], $values));
    }
}
