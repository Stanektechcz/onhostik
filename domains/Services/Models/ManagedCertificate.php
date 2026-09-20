<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A certificate the platform issues and renews for a site (ACME).
 *
 * @property ?Carbon $issued_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $renew_after
 */
final class ManagedCertificate extends Model
{
    protected static string $idPrefix = 'crt';

    protected $table = 'managed_certificates';

    protected function casts(): array
    {
        return ['domains' => 'array', 'wildcard' => 'boolean', 'issued_at' => 'datetime', 'expires_at' => 'datetime', 'renew_after' => 'datetime'];
    }
}
