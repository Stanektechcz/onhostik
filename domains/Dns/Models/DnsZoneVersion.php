<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * Immutable snapshot of a committed zone (S39 rollback).
 *
 * @property list<array<string,mixed>> $records
 */
final class DnsZoneVersion extends Model
{
    protected static string $idPrefix = 'zv';

    protected $table = 'dns_zone_versions';

    protected function casts(): array
    {
        return ['version' => 'integer', 'serial' => 'integer', 'records' => 'array', 'committed_at' => 'datetime'];
    }
}
