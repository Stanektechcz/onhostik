<?php

declare(strict_types=1);

namespace Onhost\Platform\Audit;

use Onhost\Platform\Eloquent\Model;

/**
 * Append-only, hash-chained audit record (blueprint §84). The application DB role
 * has no UPDATE/DELETE grant on this table in production (see infra/ansible).
 */
final class AuditEvent extends Model
{
    protected static string $idPrefix = 'aud';

    protected $table = 'audit_events';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'approval_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
