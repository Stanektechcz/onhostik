<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/** E2: one "tell me when {product} is available" signup (double opt-in). The token and the IP are stored as hashes only. */
final class WaitlistSignup extends Model
{
    protected static string $idPrefix = 'wl';

    protected $table = 'waitlist_signups';

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    protected $hidden = ['token_hash', 'ip_hash'];

    protected function casts(): array
    {
        return ['token_sent_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
