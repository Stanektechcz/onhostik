<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Models;

use Onhost\Platform\Eloquent\Model;

/** External identity link (Keycloak/OIDC subject → ONhost user). */
final class Identity extends Model
{
    protected static string $idPrefix = 'idn';

    protected $table = 'identities';

    protected function casts(): array
    {
        return ['claims' => 'array', 'last_login_at' => 'datetime'];
    }
}
