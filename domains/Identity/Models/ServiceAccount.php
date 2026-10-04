<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Onhost\Platform\Eloquent\Model;

/** Non-human principal owned by an organization (CI deploy tokens, Terraform, partner API). */
final class ServiceAccount extends Model
{
    use HasApiTokens;
    use SoftDeletes;

    protected static string $idPrefix = 'sa';

    protected $table = 'service_accounts';

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    /**
     * TASK-0044: the Authorizer asks every principal for its identifier the way it asks a user (bindings, cache keys). A service
     * account had none, so deciding anything for one — a pipeline inviting a member, a grantor's backing — failed with an error.
     */
    public function getAuthIdentifier(): string
    {
        return (string) $this->getKey();
    }
}
