<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Onhost\Platform\Eloquent\Model;

/** A partner's request to change a contract term (the commission model, §5m-1); finance decides, the change takes effect next month. */
final class PartnerChangeRequest extends Model
{
    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected static string $idPrefix = 'pcr';

    protected $table = 'partner_change_requests';

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'effective_from' => 'date', 'applied_at' => 'datetime'];
    }
}
