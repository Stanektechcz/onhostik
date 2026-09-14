<?php

declare(strict_types=1);

namespace Onhost\Platform\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Str;

/**
 * Public identifiers are ULIDs with a stable type prefix (blueprint §5.1):
 * `srv_01J…`, `vm_01J…`, `op_01J…`. Provider identifiers (PVE vmid, Pterodactyl
 * uuid, WAPI ids) are never exposed as business identity.
 */
trait HasPrefixedUlid
{
    use HasUlids;

    public function newUniqueId(): string
    {
        return static::idPrefix().'_'.strtolower((string) Str::ulid());
    }

    public static function idPrefix(): string
    {
        return static::$idPrefix ?? 'id';
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    public static function isValidPublicId(?string $value): bool
    {
        return is_string($value) && preg_match('/^'.preg_quote(static::idPrefix(), '/').'_[0-9a-hjkmnp-tv-z]{26}$/', $value) === 1;
    }
}
