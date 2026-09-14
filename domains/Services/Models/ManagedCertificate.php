<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class ManagedCertificate extends Model
{
    protected static string $idPrefix = 'crt';

    protected $table = 'managed_certificates';

    protected function casts(): array
    {
        return ['domains' => 'array', 'wildcard' => 'boolean', 'issued_at' => 'datetime', 'expires_at' => 'datetime', 'renew_after' => 'datetime'];
    }
}
