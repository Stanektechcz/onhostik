<?php

declare(strict_types=1);

namespace Onhost\Platform\Ids;

use Illuminate\Support\Str;

final class PublicId
{
    public static function make(string $prefix): string
    {
        return $prefix.'_'.strtolower((string) Str::ulid());
    }

    public static function prefixOf(string $id): ?string
    {
        $pos = strpos($id, '_');

        return $pos === false ? null : substr($id, 0, $pos);
    }

    /** Human readable sequential numbers for documents (OH-2026-1042, FV-2026-0061). */
    public static function documentNumber(string $prefix, int $year, int $sequence, int $pad = 4): string
    {
        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT));
    }
}
