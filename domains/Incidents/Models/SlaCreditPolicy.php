<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** Versioned SLA credit policy (blueprint §66.9): bands by achieved availability, capped. */
final class SlaCreditPolicy extends Model
{
    protected static string $idPrefix = 'slp';

    protected $table = 'sla_credit_policies';

    protected function casts(): array
    {
        return ['bands' => 'array', 'eligibility' => 'array', 'exclusions' => 'array', 'version' => 'integer', 'cap_percent' => 'integer'];
    }

    public static function current(string $slaClass): ?self
    {
        return self::query()->where('sla_class', $slaClass)->where('state', 'active')->orderByDesc('version')->first();
    }

    /** Credit percent for an achieved availability: the largest band whose threshold the availability falls below. */
    public function creditPercentFor(float $availabilityPct): int
    {
        $percent = 0;
        foreach ($this->bands as $band) {
            if ($availabilityPct < (float) $band['below']) {
                $percent = max($percent, (int) $band['credit_percent']);
            }
        }

        return min($percent, $this->cap_percent);
    }
}
