<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One blocklist listing of a sending address or a customer mail domain, open until the list stops answering (SP1a).
 *
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property ?Carbon $cleared_at
 * @property list<string>|null $service_ids
 */
final class ReputationListing extends Model
{
    protected static string $idPrefix = 'mrl';

    protected $table = 'mail_reputation_listings';

    public const OPEN = 'open';

    public const CLEARED = 'cleared';

    protected function casts(): array
    {
        return ['service_ids' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'cleared_at' => 'datetime'];
    }
}
