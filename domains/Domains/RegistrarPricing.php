<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\RegistrarPricingProvider;

/**
 * Wholesale price book: what every registrar charges ONhost per TLD and operation.
 * Registrars with a price API (`RegistrarPricingProvider`) are refreshed on schedule
 * and on demand; the others are maintained by staff (Nastavení systému → Registrátoři
 * domén). ONhost sets its own selling prices in `domain_prices`; the selector compares
 * the cost side and registers each domain with the cheapest registrar.
 */
final class RegistrarPricing
{
    public const OPERATIONS = ['register', 'renew', 'transfer', 'restore'];

    public function __construct(
        private readonly RegistrarClient $registrar,
        private readonly CatalogService $catalog,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * Refresh cost prices from every registrar that publishes them (or one instance).
     *
     * @param  list<string>|null  $tlds
     * @return array{refreshed:int, registrars:array<string, array{tlds:int, error:?string}>}
     */
    public function refresh(?ProviderInstance $only = null, ?array $tlds = null, ?CommandContext $context = null): array
    {
        $tlds ??= $this->catalog->tlds()->pluck('tld')->all();
        $report = ['refreshed' => 0, 'registrars' => []];
        foreach ($only !== null ? [$only] : $this->registrar->instances() as $instance) {
            try {
                $adapter = $this->registrar->adapterFor($instance);
            } catch (\Throwable $e) {
                $report['registrars'][$instance->provider] = ['tlds' => 0, 'error' => 'adapter unavailable: '.mb_substr($e->getMessage(), 0, 120)];

                continue;
            }
            if (! $adapter instanceof RegistrarPricingProvider) {
                $report['registrars'][$instance->provider] = ['tlds' => 0, 'error' => 'no price API; maintain the cost list manually'];

                continue;
            }
            try {
                $prices = $adapter->costPrices($tlds);
            } catch (ProviderException $e) {
                $report['registrars'][$instance->provider] = ['tlds' => 0, 'error' => $e->getMessage()];

                continue;
            }
            $count = 0;
            foreach ($prices as $tld => $row) {
                RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => $instance->provider, 'tld' => strtolower((string) $tld)], [
                    'provider_instance_id' => $instance->id, 'currency' => strtoupper((string) $row['currency']),
                    'register_minor' => self::minor($row['register'] ?? null), 'renew_minor' => self::minor($row['renew'] ?? null), 'transfer_minor' => self::minor($row['transfer'] ?? null), 'restore_minor' => self::minor($row['restore'] ?? null),
                    'source' => 'api', 'fetched_at' => now(), 'meta' => array_diff_key($row, array_flip(['currency', 'register', 'renew', 'transfer', 'restore'])),
                ]);
                $count++;
            }
            $report['registrars'][$instance->provider] = ['tlds' => $count, 'error' => null];
            $report['refreshed'] += $count;
        }
        if ($context !== null) {
            $this->audit->record($context, 'registrar.costs.refresh', 'succeeded', $report, 'registrar', 'costs');
        }
        $this->outbox->publish(GenericEvent::of('registrar.costs.refreshed', 'registrar', 'costs', $report));

        return $report;
    }

    /** Staff-maintained cost row (registrars without a price API, or an override). @param array<string,mixed> $input */
    public function upsertManual(array $input, CommandContext $context): RegistrarTldCost
    {
        $provider = strtolower(trim((string) ($input['registrar_provider'] ?? '')));
        if ($provider === '' || ! in_array($provider, $this->knownProviders(), true)) {
            throw new DomainError('registrar_unknown', 'Unknown registrar: '.implode(', ', $this->knownProviders()).' are configured.', 422, ['field' => 'registrar_provider']);
        }
        $tld = strtolower(ltrim(trim((string) ($input['tld'] ?? '')), '.'));
        if ($tld === '' || ! preg_match('/^[a-z0-9-]{2,32}$/', $tld)) {
            throw new DomainError('tld_invalid', 'TLD must be a bare label such as cz or com.', 422, ['field' => 'tld']);
        }
        $currency = strtoupper((string) ($input['currency'] ?? 'CZK'));
        if (! isset(self::fx()[$currency])) {
            throw new DomainError('currency_unsupported', 'Currency must be one of '.implode(', ', array_keys(self::fx())).'.', 422, ['field' => 'currency']);
        }
        $values = [];
        foreach (self::OPERATIONS as $op) {
            $raw = $input[$op] ?? null;
            if ($raw === null || $raw === '') {
                $values[$op.'_minor'] = null;

                continue;
            }
            if (! is_numeric($raw) || (float) $raw < 0) {
                throw new DomainError('price_invalid', "Price for {$op} must be a non-negative number.", 422, ['field' => $op]);
            }
            $values[$op.'_minor'] = self::minor((string) $raw);
        }
        if ($values['register_minor'] === null && $values['renew_minor'] === null && $values['transfer_minor'] === null) {
            throw new DomainError('price_missing', 'Enter at least the registration, renewal or transfer price.', 422, ['field' => 'register']);
        }
        $instance = ProviderInstance::query()->platform()->where('provider', $provider)->where('state', 'active')->orderBy('key')->first();
        $row = RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => $provider, 'tld' => $tld], $values + [
            'provider_instance_id' => $instance?->id, 'currency' => $currency, 'source' => 'manual', 'fetched_at' => now(), 'meta' => ['by' => $context->actorId, 'note' => mb_substr((string) ($input['note'] ?? ''), 0, 250) ?: null],
        ]);
        $this->audit->record($context, 'registrar.costs.upsert', 'succeeded', ['registrar' => $provider, 'tld' => $tld, 'currency' => $currency] + $values, 'registrar_tld_cost', $row->id);

        return $row;
    }

    /** Pin a TLD to one registrar or let the price decide (`auto`). */
    public function setPolicy(string $tld, string $provider, CommandContext $context): TldPolicy
    {
        $policy = $this->catalog->tld($tld);
        $provider = strtolower(trim($provider)) ?: 'auto';
        if ($provider !== 'auto' && ! in_array($provider, $this->knownProviders(), true)) {
            throw new DomainError('registrar_unknown', 'Unknown registrar: '.implode(', ', $this->knownProviders()).' or auto.', 422, ['field' => 'registrar_provider']);
        }
        $policy->forceFill(['registrar_provider' => $provider])->save();
        $this->audit->record($context, 'registrar.policy.set', 'succeeded', ['tld' => $policy->tld, 'registrar' => $provider], 'tld_policy', $policy->tld);

        return $policy;
    }

    /** Cost of one operation at one registrar, or null when unknown. @return array{provider:string, currency:string, minor:int, czk_minor:int, source:string, fetched_at:?string, stale:bool}|null */
    public function costFor(string $provider, string $tld, string $operation = 'register'): ?array
    {
        $row = RegistrarTldCost::query()->where('registrar_provider', $provider)->where('tld', strtolower(ltrim($tld, '.')))->first();
        if ($row === null) {
            return null;
        }
        $minor = $row->{$operation.'_minor'} ?? null;
        if ($minor === null) {
            return null;
        }

        return [
            'provider' => $provider, 'currency' => $row->currency, 'minor' => (int) $minor, 'czk_minor' => self::toCzkMinor((int) $minor, $row->currency), 'source' => $row->source,
            'fetched_at' => $row->fetched_at?->toIso8601String(), 'stale' => $row->isStale((int) config('onhost.domains.registrar.cost_ttl_hours', 24)),
        ];
    }

    /** Refresh one TLD at one registrar when the cost is unknown or stale (best effort, never throws). */
    public function ensureFresh(ProviderInstance $instance, string $tld): void
    {
        $row = RegistrarTldCost::query()->where('registrar_provider', $instance->provider)->where('tld', $tld)->first();
        if ($row !== null && ! $row->isStale((int) config('onhost.domains.registrar.cost_ttl_hours', 24))) {
            return;
        }
        if ($row !== null && $row->source === 'manual') {
            return;
        }
        try {
            $adapter = $this->registrar->adapterFor($instance);
            if ($adapter instanceof RegistrarPricingProvider) {
                $this->refresh($instance, [$tld]);
            }
        } catch (\Throwable) {
            // a price refresh must never block a registration; the stale/absent row is reported in the matrix
        }
    }

    /**
     * Matrix for the settings page: ONhost selling prices vs. every registrar's cost per TLD, winner and margin.
     *
     * @return array{registrars:list<array<string,mixed>>, tlds:list<array<string,mixed>>, fx:array<string,float>, cost_ttl_hours:int}
     */
    public function matrix(): array
    {
        $instances = $this->registrar->instances();
        $providers = $this->knownProviders();
        $tlds = [];
        foreach ($this->catalog->tlds() as $policy) {
            $choice = null;
            try {
                $choice = app(RegistrarSelector::class)->choose($policy->tld, 'register'); // may fetch a missing/stale cost from a price API first
            } catch (DomainError) {
                // no registrar available
            }
            $costs = RegistrarTldCost::query()->where('tld', $policy->tld)->get()->groupBy('tld');
            $selling = [];
            foreach (['CZK', 'EUR'] as $currency) {
                try {
                    $price = $this->catalog->domainPrice($policy->tld, $currency);
                    $selling[$currency] = ['register' => $price->register()->minor, 'renew' => $price->renew()->minor, 'transfer' => $price->transfer()->minor];
                } catch (DomainError) {
                    // no selling price in that currency
                }
            }
            $rows = [];
            foreach ($providers as $provider) {
                $row = ($costs->get($policy->tld) ?? collect())->firstWhere('registrar_provider', $provider);
                $rows[$provider] = $row === null ? null : [
                    'currency' => $row->currency, 'register' => $row->register_minor, 'renew' => $row->renew_minor, 'transfer' => $row->transfer_minor, 'restore' => $row->restore_minor,
                    'register_czk' => $row->register_minor === null ? null : self::toCzkMinor((int) $row->register_minor, $row->currency),
                    'renew_czk' => $row->renew_minor === null ? null : self::toCzkMinor((int) $row->renew_minor, $row->currency),
                    'source' => $row->source, 'fetched_at' => $row->fetched_at?->toIso8601String(), 'stale' => $row->isStale((int) config('onhost.domains.registrar.cost_ttl_hours', 24)),
                ];
            }
            $winner = $choice['provider'] ?? null;
            $known = array_values(array_filter(array_map(fn ($r) => $r['register_czk'] ?? null, $rows), fn ($v) => $v !== null));
            $winnerCost = $winner !== null ? ($rows[$winner]['register_czk'] ?? null) : ($known === [] ? null : min($known)); // no usable registrar: still show the margin against the cheapest known cost
            $sellingCzk = $selling['CZK']['register'] ?? null;
            $tlds[] = [
                'tld' => $policy->tld, 'pinned' => $policy->registrar_provider ?: 'auto', 'selling' => $selling, 'costs' => $rows, 'winner' => $winner, 'reason' => $choice['reason'] ?? 'unavailable',
                'margin_czk' => $sellingCzk !== null && $winnerCost !== null ? $sellingCzk - $winnerCost : null,
            ];
        }

        return [
            'registrars' => array_map(fn (ProviderInstance $i) => ['provider' => $i->provider, 'key' => $i->key, 'name' => $i->name, 'has_price_api' => $this->hasPriceApi($i), 'has_scraper' => isset(RegistrarPriceScraper::scrapers()[$i->provider]), 'up' => (bool) (($i->health ?? [])['up'] ?? false), 'credit' => ($i->health ?? [])['credit'] ?? null, 'currency' => ($i->health ?? [])['currency'] ?? null], $instances),
            'providers' => $providers, 'tlds' => $tlds, 'fx' => self::fx(), 'cost_ttl_hours' => (int) config('onhost.domains.registrar.cost_ttl_hours', 24), 'preference' => (array) config('onhost.domains.registrar.preference', []),
        ];
    }

    private function hasPriceApi(ProviderInstance $instance): bool
    {
        try {
            return $this->registrar->adapterFor($instance) instanceof RegistrarPricingProvider;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Registrar keys that have an instance (any state) or a cost row — what staff may reference. @return list<string> */
    public function knownProviders(): array
    {
        $keys = array_merge(
            $this->registrar->providerKeys(),
            ProviderInstance::query()->platform()->whereJsonContains('capabilities->registrar', true)->pluck('provider')->all(),
            RegistrarTldCost::query()->distinct()->pluck('registrar_provider')->all(),
        );

        return array_values(array_unique(array_filter(array_map('strval', $keys))));
    }

    /** @return array<string,float> */
    public static function fx(): array
    {
        $fx = (array) config('onhost.domains.registrar.fx_czk', ['CZK' => 1.0]);
        $fx['CZK'] = 1.0;

        return array_map('floatval', $fx);
    }

    public static function toCzkMinor(int $minor, string $currency): int
    {
        $rate = self::fx()[strtoupper($currency)] ?? null;
        if ($rate === null) {
            throw new DomainError('currency_unsupported', "No CZK exchange rate for {$currency} (onhost.domains.registrar.fx_czk).", 500);
        }

        return (int) round($minor * $rate);
    }

    public static function minor(string|int|float|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return Money::decimal(str_replace(',', '.', (string) $amount), 'CZK')->minor; // currency only matters for scale (2 decimals)
    }
}
