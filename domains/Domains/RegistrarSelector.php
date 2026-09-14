<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;

/**
 * Picks the registrar for a new registration or transfer: the TLD policy may pin one
 * registrar, otherwise the registrar with the lowest cost price wins (all costs are
 * compared in CZK), ties and missing price data fall back to the configured
 * preference order. Renewals never move: they always go to the holding registrar.
 */
final class RegistrarSelector
{
    public function __construct(private readonly RegistrarClient $registrar, private readonly RegistrarPricing $pricing, private readonly CatalogService $catalog) {}

    /**
     * @return array{provider:string, instance:ProviderInstance, cost:?array{provider:string,currency:string,minor:int,czk_minor:int,source:string,fetched_at:?string,stale:bool}, reason:string, candidates:list<array<string,mixed>>}
     */
    public function choose(string $tld, string $operation = 'register', bool $testMode = false): array
    {
        $tld = strtolower(ltrim($tld, '.'));
        $policy = $this->catalog->tld($tld);
        $instances = $this->registrar->instances();
        if ($instances === []) {
            throw new DomainError('registrar_unavailable', 'No enabled registrar instance is configured.', 503);
        }
        $pinned = strtolower((string) ($policy->registrar_provider ?: 'auto'));
        $candidates = [];
        $eligible = [];
        foreach ($instances as $instance) {
            $skip = null;
            try {
                $capabilities = $this->registrar->adapterFor($instance)->capabilities();
            } catch (\Throwable) { // credentials not stored yet, adapter misconfigured: the instance is not a candidate, the others still are
                $capabilities = [];
                $skip = 'adapter_unavailable';
            }
            if ($skip === null && $testMode && ! ($capabilities['test_mode'] ?? false)) {
                $skip = 'no_test_mode';
            }
            if ($skip === null) {
                $this->pricing->ensureFresh($instance, $tld);
            }
            $cost = $skip === null ? $this->pricing->costFor($instance->provider, $tld, $operation) : null;
            $candidates[] = ['provider' => $instance->provider, 'instance_key' => $instance->key, 'cost' => $cost, 'skipped' => $skip];
            if ($skip === null) {
                $eligible[] = [$instance, $cost];
            }
        }
        if ($eligible === []) {
            throw new DomainError('registrar_unavailable', 'No registrar can handle this request'.($testMode ? ' in test mode (Subreg needs a demoreg.net instance)' : '').'.', 503, ['candidates' => $candidates]);
        }
        if ($pinned !== 'auto' && $pinned !== '') {
            foreach ($eligible as [$instance, $cost]) {
                if ($instance->provider === $pinned) {
                    return ['provider' => $instance->provider, 'instance' => $instance, 'cost' => $cost, 'reason' => 'pinned', 'candidates' => $candidates];
                }
            }
        }
        $priced = array_values(array_filter($eligible, fn (array $c) => $c[1] !== null));
        if ($priced === []) {
            [$instance, $cost] = $eligible[0];

            return ['provider' => $instance->provider, 'instance' => $instance, 'cost' => $cost, 'reason' => $pinned !== 'auto' ? 'pinned_unavailable' : 'no_cost_data', 'candidates' => $candidates];
        }
        usort($priced, fn (array $a, array $b) => $a[1]['czk_minor'] <=> $b[1]['czk_minor']); // stable sort keeps the preference order for equal prices
        [$instance, $cost] = $priced[0];
        $reason = count($priced) > 1 && $priced[1][1]['czk_minor'] === $cost['czk_minor'] ? 'tie_preference' : 'cheapest';

        return ['provider' => $instance->provider, 'instance' => $instance, 'cost' => $cost, 'reason' => $pinned !== 'auto' ? 'pinned_unavailable' : $reason, 'candidates' => $candidates];
    }

    /** Availability checks go to the registrar that would register the name (its answer includes the live price). */
    public function instanceForTld(string $tld): ProviderInstance
    {
        return $this->choose($tld, 'register')['instance'];
    }

    /** Audit-friendly summary stored on the domain. @param array<string,mixed> $choice @return array<string,mixed> */
    public static function summary(array $choice): array
    {
        return [
            'provider' => $choice['provider'], 'instance_key' => $choice['instance']->key, 'reason' => $choice['reason'], 'chosen_at' => now()->toIso8601String(),
            'cost' => $choice['cost'] === null ? null : ['currency' => $choice['cost']['currency'], 'minor' => $choice['cost']['minor'], 'czk_minor' => $choice['cost']['czk_minor'], 'source' => $choice['cost']['source']],
            'candidates' => array_map(fn (array $c) => ['provider' => $c['provider'], 'czk_minor' => $c['cost']['czk_minor'] ?? null, 'skipped' => $c['skipped']], $choice['candidates']),
        ];
    }
}
