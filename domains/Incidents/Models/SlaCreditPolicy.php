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

    /**
     * The policies of `onhost.sla.credit_policies` as the active versions: a class whose bands or cap changed gets a new version
     * (the old one superseded — credits keep referencing it), an unchanged one keeps its version. Run by StatusSeeder and, for L-05,
     * by a migration, so a deploy alone puts the SLA in force into the computation. Returns how many versions it wrote.
     */
    public static function syncFromConfig(): int
    {
        $written = 0;
        foreach ((array) config('onhost.sla.credit_policies', []) as $class => $policy) {
            $current = self::query()->where('key', "sla-{$class}")->orderByDesc('version')->first();
            if ($current !== null && $current->bands === $policy['bands'] && $current->cap_percent === (int) $policy['cap_percent']) {
                continue; // unchanged: keep the version (credits reference it)
            }
            if ($current !== null) {
                $current->forceFill(['state' => 'superseded'])->save();
            }
            self::query()->create([
                'key' => "sla-{$class}", 'version' => ($current === null ? 0 : (int) $current->version) + 1, 'sla_class' => $class, 'bands' => $policy['bands'], 'cap_percent' => (int) $policy['cap_percent'],
                'claim' => 'auto', 'eligibility' => ['paid_up' => true, 'not_suspended_for_dunning' => true], 'exclusions' => ['maintenance_excluded', 'force_majeure', 'customer_caused', 'upstream_registry'], 'state' => 'active',
            ]);
            $written++;
        }

        return $written;
    }

    public static function current(string $slaClass): ?self
    {
        return self::query()->where('sla_class', $slaClass)->where('state', 'active')->orderByDesc('version')->first();
    }

    /**
     * L-05: the credit for a month. A policy with a band `per_started_hour_percent` (the SLA 2026-09: Standard 5 %, Business 10 %)
     * pays that percent for every started hour of downtime over what the contractual availability allows in the month, up to the
     * cap; a policy of availability bands (`below` → `credit_percent`) pays the band.
     */
    public function creditPercent(float $availabilityPct, int $downtimeSeconds, int $monthSeconds, float $contractualPct): int
    {
        foreach ((array) $this->bands as $band) {
            if (isset($band['per_started_hour_percent'])) {
                $allowed = (int) round((100 - $contractualPct) / 100 * $monthSeconds);
                $hours = (int) ceil(max(0, $downtimeSeconds - $allowed) / 3600);

                return min($hours * (int) $band['per_started_hour_percent'], (int) $this->cap_percent);
            }
        }

        return $this->creditPercentFor($availabilityPct);
    }

    /** Credit percent for an achieved availability: the largest band whose threshold the availability falls below. */
    public function creditPercentFor(float $availabilityPct): int
    {
        $percent = 0;
        foreach ($this->bands as $band) {
            if (isset($band['below']) && $availabilityPct < (float) $band['below']) {
                $percent = max($percent, (int) $band['credit_percent']);
            }
        }

        return min($percent, $this->cap_percent);
    }
}
