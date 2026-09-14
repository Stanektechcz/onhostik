<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Models;

use Onhost\Platform\Eloquent\Model;

/** Staged change (add/update/delete) waiting for a commit. */
final class DnsChange extends Model
{
    protected static string $idPrefix = 'dch';

    protected $table = 'dns_changes';

    protected function casts(): array
    {
        return ['record' => 'array', 'previous' => 'array', 'committed_at' => 'datetime'];
    }
}
