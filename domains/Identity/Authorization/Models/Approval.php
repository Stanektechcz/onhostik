<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Onhost\Platform\Eloquent\Model;

/** Two-person approval record (blueprint §61.6). Consumed exactly once by the command it approves. */
final class Approval extends Model
{
    protected static string $idPrefix = 'apr';

    protected $table = 'approvals';

    protected function casts(): array
    {
        return ['payload' => 'array', 'decided_at' => 'datetime', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function isUsableFor(string $action, string $payloadHash, string $requesterId): bool
    {
        return $this->state === 'approved'
            && $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->action === $action
            && $this->payload_hash === $payloadHash
            && $this->decided_by !== $requesterId; // the approver must be a different person
    }
}
