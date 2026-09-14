<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Onhost\Platform\Eloquent\Model;

/** Immutable consent evidence (§23.7 B2C, §46.3 registrar terms, §49.4 pricing). */
final class Consent extends Model
{
    protected static string $idPrefix = 'cns';

    protected $table = 'consents';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['evidence' => 'array', 'accepted_at' => 'datetime'];
    }
}
