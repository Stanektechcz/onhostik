<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * An offer of paid work on a ticket and the customer's decision about its price (Brain card H29).
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $organization_id
 * @property ?string $service_id
 * @property string $scope
 * @property string $description
 * @property ?int $minutes
 * @property int $price_net_minor
 * @property string $currency
 * @property string $state
 * @property ?string $proposed_by
 * @property ?string $decided_by
 * @property ?string $decision_note
 * @property ?string $invoice_id
 * @property ?string $payment
 * @property ?Carbon $valid_until
 * @property ?Carbon $decided_at
 * @property ?Carbon $completed_at
 */
final class WorkOffer extends Model
{
    public const PROPOSED = 'proposed';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    public const EXPIRED = 'expired';

    public const COMPLETED = 'completed';

    protected static string $idPrefix = 'two';

    protected $table = 'ticket_work_offers';

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'price_net_minor' => 'integer', 'valid_until' => 'datetime', 'decided_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** An offer nobody decided in time is no longer an offer. */
    public function isOpen(): bool
    {
        return $this->state === self::PROPOSED && ($this->valid_until === null || $this->valid_until->isFuture());
    }
}
