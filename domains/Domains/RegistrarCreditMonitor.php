<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Illuminate\Support\Facades\Log;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarCreditSnapshot;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Registrar credit runway (blueprint §46.6): samples `credit-info`, estimates the
 * cost of renewals due in the next 30 days from the catalogue renew prices (an
 * upper bound of the registrar cost) and raises `registrar.credit.low` when the
 * balance is under the configured minimum or the runway is shorter than 30 days.
 */
final class RegistrarCreditMonitor
{
    public function __construct(private readonly RegistrarClient $registrar, private readonly CatalogService $catalog, private readonly OutboxPublisher $outbox) {}

    /** @return list<RegistrarCreditSnapshot> one snapshot per usable registrar (a registrar outage does not stop the others) */
    public function sampleAll(): array
    {
        $out = [];
        foreach ($this->registrar->instances() as $instance) {
            try {
                $out[] = $this->sample($instance);
            } catch (ProviderException $e) {
                Log::warning('registrar credit sample failed', ['instance' => $instance->key, 'error' => $e->errorCode->value]);
            }
        }

        return $out;
    }

    public function sample(?ProviderInstance $instance = null): RegistrarCreditSnapshot
    {
        $instance ??= $this->registrar->instance();
        $provider = $instance->provider;
        $credit = $this->registrar->adapterFor($instance)->creditInfo();
        $currency = strtoupper($credit['currency']);
        $balance = Money::decimal((string) $credit['balance'], $currency);
        $due = Domain::query()->where('state', DomainStateMachine::ACTIVE)->where('registrar_provider', $provider)->where('auto_renew', true)->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(30)])->get();
        $renewals = Money::zero($currency);
        foreach ($due as $domain) {
            try {
                $renewals = $renewals->add($this->catalog->domainPrice($domain->tld, $currency)->renew()->multiply($domain->renewal_period ?: 1));
            } catch (\Throwable) {
                // unknown TLD price: skip from the estimate but still count the domain
            }
        }
        $dailyBurn = $renewals->minor > 0 ? $renewals->minor / 30 : 0;
        $runway = $dailyBurn > 0 ? (int) floor($balance->minor / $dailyBurn) : 3650;
        $minimum = (int) (((array) config($provider === 'wedos' ? 'onhost.wapi.credit_min' : "onhost.{$provider}.credit_min", []))[$currency] ?? 0);
        $below = $balance->minor < $minimum || $runway < 30;
        $snapshot = RegistrarCreditSnapshot::query()->create([
            'registrar_provider' => $provider, 'balance_minor' => $balance->minor, 'currency' => $currency, 'renewals_30d_minor' => $renewals->minor, 'renewals_30d_count' => $due->count(),
            'runway_days' => min($runway, 3650), 'below_minimum' => $below, 'taken_at' => now(),
        ]);
        if ($below) {
            $this->outbox->publish(GenericEvent::of('registrar.credit.low', 'registrar', $provider, ['registrar' => $provider, 'instance' => $instance->key, 'balance' => $balance, 'minimum_minor' => $minimum, 'renewals_30d' => $renewals, 'runway_days' => $snapshot->runway_days, 'domains_due' => $due->count()]));
        }

        return $snapshot;
    }
}
