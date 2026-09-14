<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace\Models;

use Onhost\Platform\Eloquent\Model;

/** A partner's service on the marketplace (audit §5j-1): published by staff, ordered by customers, fulfilled by the partner. */
final class MarketplaceListing extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const PAUSED = 'paused';

    public const RETIRED = 'retired';

    public const STATES = [self::DRAFT, self::PUBLISHED, self::PAUSED, self::RETIRED];

    public const BILLING = ['oneoff', 'monthly'];

    protected static string $idPrefix = 'mkl';

    protected $table = 'marketplace_listings';

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'commission_pct' => 'integer', 'delivery_days' => 'integer', 'meta' => 'array'];
    }
}
