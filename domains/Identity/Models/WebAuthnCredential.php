<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

final class WebAuthnCredential extends Model
{
    protected static string $idPrefix = 'wac';

    protected $table = 'webauthn_credentials';

    protected function casts(): array
    {
        return ['transports' => 'array', 'last_used_at' => 'datetime', 'sign_count' => 'integer'];
    }
}
