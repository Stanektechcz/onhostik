<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A monthly spending limit of an organization (or of one project of it).
 *
 * @property string $id
 * @property string $organization_id
 * @property ?string $project_id
 * @property string $currency
 * @property int $limit_minor
 * @property bool $hard
 * @property ?list<int> $alert_thresholds
 * @property ?int $max_single_service_minor
 * @property ?int $approval_above_minor
 * @property int $spent_minor
 * @property ?Carbon $period_start
 * @property ?list<int> $notified
 */
final class Budget extends Model
{
    protected static string $idPrefix = 'bud';

    protected $table = 'budgets';

    protected function casts(): array
    {
        return [
            'limit_minor' => 'integer', 'hard' => 'boolean', 'alert_thresholds' => 'array', 'max_single_service_minor' => 'integer',
            'approval_above_minor' => 'integer', 'spent_minor' => 'integer', 'period_start' => 'date', 'notified' => 'array',
        ];
    }
}
