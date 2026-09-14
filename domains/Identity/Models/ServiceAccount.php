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
}
