<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Incidents\Models\SlaCreditPolicy;
use Onhost\Domain\Incidents\Models\StatusComponent;

/** Status components (config `onhost.status.components`) and versioned SLA credit policies (`onhost.sla.credit_policies`). */
final class StatusSeeder extends Seeder
{
    /** component key → [group (service family), region, sla class] */
    private const META = [
        'web-cz1' => ['web', 'cz1', 'standard'], 'managed' => ['managed', null, 'business'], 'apps-cz1' => ['apps', 'cz1', 'business'], 'cloud-cz1' => ['cloud', 'cz1', 'business'],
        'games-cz1' => ['game', 'cz1', 'standard'], 'dns' => [null, null, 'critical'], 'domains' => [null, null, 'business'], 'mail' => ['mail', null, 'business'],
        'payments' => [null, null, 'business'], 'portal' => [null, null, 'business'], 'ai' => ['ai', null, 'standard'],
    ];

    public function run(): void
    {
        $sort = 0;
        foreach ((array) config('onhost.status.components', []) as $key => $name) {
            [$group, $region, $class] = self::META[$key] ?? [null, null, 'standard'];
            StatusComponent::query()->updateOrCreate(['key' => $key], ['name' => $name, 'group' => $group, 'region_code' => $region, 'sla_class' => $class, 'sort' => ++$sort, 'public' => true]);
        }
        SlaCreditPolicy::syncFromConfig();
    }
}
