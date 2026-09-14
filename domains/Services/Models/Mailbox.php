<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class Mailbox extends Model
{
    protected static string $idPrefix = 'mbx';

    protected $table = 'mailboxes';

    protected function casts(): array
    {
        return ['quota_mb' => 'integer', 'remote_id' => 'integer'];
    }
}
