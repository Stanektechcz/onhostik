<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Models\SlaCreditPolicy;
use Onhost\Domain\Incidents\Models\SlaMeasurement;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Incidents\Models\SloWindow;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * SLA/SLO engine (blueprint §66.4–66.9).
 *
 *  - External probes (≥3 locations) report measurements; a component is "down" when a
 *    2-of-3 quorum of locations fails (`evaluateQuorum`), which auto-opens a probe-sourced incident.
 *  - SLI = good/total measurements outside SLA-excluded maintenance; SLO objective per SLA class;
 *    error budget policy states and multi-window burn-rate alerts (`computeWindows`).
 *  - SLA credits: candidates computed per affected contractual service from a versioned policy,
 *    approved by staff, issued as a DK credit note plus a non-refundable wallet credit (`issue`).
 */
final class SlaService
{
    public const WINDOWS = ['5m' => 300, '30m' => 1800, '1h' => 3600, '6h' => 21600, '3d' => 259200, '30d' => 2592000];

    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly IncidentService $incidents,
        private readonly InvoiceService $invoices,
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
    ) {}

    // ── probes ───────────────────────────────────────────────────────────────

    /**
     * Register an external probe; the bearer token is returned once (hash stored).
     *
     * @param  array{key:string,component_key:string,kind:string,target:string,location:string,expected?:array,interval_seconds?:int}  $input
     * @return array{probe: SlaProbe, token: string}
     */
    public function registerProbe(array $input, CommandContext $context): array
    {
        if (! StatusComponent::query()->whereKey($input['component_key'] ?? '')->exists()) {
            throw new DomainError('probe_component_unknown', 'Unknown status component.', 422, ['field' => 'component_key']);
        }
        if (! in_array($input['kind'] ?? '', SlaProbe::KINDS, true)) {
            throw new DomainError('probe_kind_invalid', 'Probe kind must be http, dns, tcp or icmp.', 422, ['field' => 'kind']);
        }
        $token = 'prb_'.Str::random(40);
        $probe = SlaProbe::query()->updateOrCreate(['key' => (string) $input['key']], [
            'component_key' => $input['component_key'], 'kind' => $input['kind'], 'target' => $input['target'], 'location' => $input['location'],
            'expected' => $input['expected'] ?? null, 'interval_seconds' => (int) ($input['interval_seconds'] ?? 60), 'token_hash' => hash('sha256', $token), 'state' => 'active',
        ]);
        $this->audit->record($context, 'sla.probe.register', 'succeeded', ['key' => $probe->key, 'component' => $probe->component_key, 'location' => $probe->location], 'sla_probe', $probe->id);

        return ['probe' => $probe, 'token' => $token];
    }

    public function authenticateProbe(?string $token): ?SlaProbe
    {
        if ($token === null || $token === '') {
            return null;
        }

        return SlaProbe::query()->where('token_hash', hash('sha256', $token))->where('state', 'active')->first();
    }

    /**
     * Ingest a batch of results from a probe agent (idempotent per probe+timestamp), then re-evaluate the quorum.
     *
     * @param  list<array{at:string,ok:bool,latency_ms?:int,detail?:string}>  $results
     * @return array{accepted:int,duplicates:int,component:string,down:bool}
     */
    public function ingest(SlaProbe $probe, array $results): array
    {
        $accepted = 0;
        $duplicates = 0;
        foreach ($results as $result) {
            $at = Carbon::parse((string) ($result['at'] ?? now()->toIso8601String()))->utc()->startOfSecond();
            try {
                SlaMeasurement::query()->create([
                    'probe_id' => $probe->id, 'component_key' => $probe->component_key, 'location' => $probe->location, 'measured_at' => $at,
                    'ok' => (bool) ($result['ok'] ?? false), 'latency_ms' => isset($result['latency_ms']) ? (int) $result['latency_ms'] : null,
                    'detail' => isset($result['detail']) ? mb_substr((string) $result['detail'], 0, 250) : null,
                ]);
                $accepted++;
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
                $duplicates++;
            }
        }
        $probe->forceFill(['last_seen_at' => now()])->save();
        $down = $this->evaluateQuorum($probe->component_key);

        return ['accepted' => $accepted, 'duplicates' => $duplicates, 'component' => $probe->component_key, 'down' => $down];
    }

    /**
     * Quorum rule (§66.6): the component is down when ≥ `probe_quorum` distinct locations report a
     * failure in their latest measurement and at least `probe_locations_min` locations are reporting
     * at all (a single silent probe is a monitoring problem, not an outage).
     */
    public function evaluateQuorum(string $componentKey, ?Carbon $now = null): bool
    {
        $now ??= now();
        $probes = SlaProbe::query()->where('component_key', $componentKey)->where('state', 'active')->get();
        $latestByLocation = [];
        foreach ($probes as $probe) {
            $m = SlaMeasurement::query()->where('probe_id', $probe->id)->where('measured_at', '>=', $now->copy()->subSeconds($probe->interval_seconds * 3))->orderByDesc('measured_at')->first();
            if ($m === null) {
                continue;
            }
            $loc = $probe->location;
            if (! isset($latestByLocation[$loc]) || $latestByLocation[$loc]->measured_at < $m->measured_at) {
                $latestByLocation[$loc] = $m;
            }
        }
        $reporting = count($latestByLocation);
        $failing = count(array_filter($latestByLocation, fn (SlaMeasurement $m) => ! $m->ok));
        $quorum = (int) config('onhost.sla.probe_quorum', 2);
        $minLocations = (int) config('onhost.sla.probe_locations_min', 3);
        $down = $reporting >= min($minLocations, max(1, $probes->pluck('location')->unique()->count())) && $failing >= $quorum;

        $component = StatusComponent::query()->find($componentKey);
        $open = Incident::query()->where('source', 'probes')->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM])->get()->first(fn (Incident $i) => in_array($componentKey, $i->components, true));
        if ($down && $open === null && $component !== null) {
            $severity = in_array($component->sla_class, ['ha', 'critical'], true) ? 'p1' : 'p2';
            $this->incidents->open([
                'title' => "Výpadek detekován sondami: {$component->name}", 'severity' => $severity, 'components' => [$componentKey],
                'impact' => sprintf('%d z %d externích sond hlásí nedostupnost.', $failing, $reporting), 'source' => 'probes',
                'affected_services' => $this->servicesForComponent($component)->pluck('id')->all(),
                'note' => 'Automaticky otevřeno: kvorum externích sond hlásí nedostupnost.', 'meta' => ['auto' => true, 'failing' => $failing, 'reporting' => $reporting],
            ], CommandContext::system('sla.probe_quorum'));
        } elseif (! $down && $open !== null && ($open->meta['auto'] ?? false) && in_array($open->state, [IncidentStateMachine::DETECTED, IncidentStateMachine::INVESTIGATING], true)) {
            $this->incidents->update($open, 'Sondy hlásí obnovení dostupnosti; sledujeme stabilitu.', CommandContext::system('sla.probe_recovery'), IncidentStateMachine::MONITORING);
        }

        return $down;
    }

    /** Auto-resolve probe-sourced incidents that stayed in MONITORING for the configured recovery period. */
    public function settleAutoIncidents(?Carbon $now = null): int
    {
        $now ??= now();
        $minutes = (int) config('onhost.sla.auto_resolve_minutes', 15);
        $count = 0;
        foreach (Incident::query()->where('source', 'probes')->where('state', IncidentStateMachine::MONITORING)->get() as $incident) {
            if (! ($incident->meta['auto'] ?? false)) {
                continue;
            }
            $last = $incident->updates()->latest('created_at')->first();
            if ($last !== null && $last->created_at <= $now->copy()->subMinutes($minutes) && ! $this->evaluateQuorum($incident->components[0], $now)) {
                $this->incidents->resolve($incident, 'Dostupnost obnovena a stabilní; incident uzavřen automaticky.', CommandContext::system('sla.auto_resolve'));
                $count++;
            }
        }

        return $count;
    }

    // ── SLI / SLO ────────────────────────────────────────────────────────────

    /**
     * Compute SLO windows for a component: availability, error budget consumption (30d) and burn rates,
     * excluding measurements inside SLA-excluded maintenance windows. Persists one row per window.
     *
     * @return array<string, SloWindow>
     */
    public function computeWindows(StatusComponent $component, ?Carbon $now = null): array
    {
        $now ??= now();
        $objective = (float) config("onhost.sla.classes.{$component->sla_class}.objective", 99.9);
        $budget = max(0.0001, (100 - $objective) / 100);
        $excluded = Maintenance::query()->where('sla_treatment', 'excluded')->whereIn('state', ['approved', 'in_progress', 'completed'])->where('ends_at', '>=', $now->copy()->subSeconds(self::WINDOWS['30d']))->get()->filter(fn (Maintenance $m) => in_array($component->key, $m->components, true));
        $out = [];
        foreach (self::WINDOWS as $name => $seconds) {
            $start = $now->copy()->subSeconds($seconds);
            $rows = SlaMeasurement::query()->where('component_key', $component->key)->where('measured_at', '>', $start)->where('measured_at', '<=', $now)->get(['measured_at', 'ok', 'location']);
            $rows = $rows->reject(fn (SlaMeasurement $m) => $excluded->contains(fn (Maintenance $w) => $w->isActiveAt($m->measured_at)));
            // SLI per minute: a minute is good when the quorum of reporting locations is healthy.
            $minutes = $rows->groupBy(fn (SlaMeasurement $m) => $m->measured_at->format('YmdHi'));
            $total = $minutes->count();
            $good = $minutes->filter(function (Collection $bucket) {
                $failing = $bucket->groupBy('location')->filter(fn (Collection $per) => $per->last()->ok === false)->count();

                return $failing < (int) config('onhost.sla.probe_quorum', 2);
            })->count();
            $availability = $total === 0 ? null : round($good / $total * 100, 4);
            $errorRatio = $total === 0 ? 0.0 : 1 - $good / $total;
            $burn = round($errorRatio / $budget, 2);
            $consumed = $name === '30d' ? round($errorRatio / $budget * 100, 2) : null;
            $policy = $consumed === null ? null : $this->policyState($consumed);
            $out[$name] = SloWindow::query()->updateOrCreate(
                ['component_key' => $component->key, 'window' => $name, 'window_end' => $now->copy()->startOfMinute()],
                ['window_start' => $start, 'good' => $good, 'total' => $total, 'objective' => $objective, 'availability_pct' => $availability, 'budget_consumed_pct' => $consumed, 'burn_rate' => $burn, 'policy_state' => $policy, 'computed_at' => $now],
            );
        }
        $this->alertBurnRate($component, $out);

        return $out;
    }

    /** Error-budget policy (§66.5): 25 % → review, 50 % → high-risk changes need review, 75 % → reliability work first, 100 % → change freeze. */
    public function policyState(float $consumedPct): string
    {
        $state = 'normal';
        foreach ((array) config('onhost.sla.error_budget_policy', []) as $threshold => $name) {
            if ($consumedPct >= (float) $threshold) {
                $state = (string) $name;
            }
        }

        return $state;
    }

    /** Multi-window, multi-burn-rate alerting (§66.5): both the short and the long window must exceed the threshold. */
    private function alertBurnRate(StatusComponent $component, array $windows): void
    {
        foreach ((array) config('onhost.sla.burn_rate_windows', []) as [$short, $long, $threshold]) {
            $s = $windows[$short] ?? null;
            $l = $windows[$long] ?? null;
            if ($s === null || $l === null || $s->total === 0 || $l->total === 0) {
                continue;
            }
            if ((float) $s->burn_rate >= (float) $threshold && (float) $l->burn_rate >= (float) $threshold) {
                $this->outbox->publish(GenericEvent::of('sla.burn_rate', 'status_component', $component->key, [
                    'component' => $component->key, 'name' => $component->name, 'short' => $short, 'long' => $long, 'threshold' => $threshold,
                    'burn_short' => (float) $s->burn_rate, 'burn_long' => (float) $l->burn_rate, 'policy_state' => $windows['30d']->policy_state ?? null,
                ]));
                break; // the fastest-burning pair is enough; the alert is deduplicated by the notification layer
            }
        }
        $month = $windows['30d'] ?? null;
        if ($month !== null && $month->policy_state === 'freeze' && $month->total > 0) {
            $this->outbox->publish(GenericEvent::of('sla.budget.exhausted', 'status_component', $component->key, ['component' => $component->key, 'name' => $component->name, 'consumed' => (float) $month->budget_consumed_pct]));
        }
    }

    /** Scheduler entry: quorum + windows for every component, then auto-settle probe incidents. */
    public function evaluate(?Carbon $now = null): array
    {
        $now ??= now();
        $components = StatusComponent::query()->get();
        $down = [];
        foreach ($components as $component) {
            if ($this->evaluateQuorum($component->key, $now)) {
                $down[] = $component->key;
            }
            $this->computeWindows($component, $now);
        }
        $resolved = $this->settleAutoIncidents($now);
        $stale = SlaProbe::query()->where('state', 'active')->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $now->copy()->subMinutes(10)))->count();

        return ['components' => $components->count(), 'down' => $down, 'auto_resolved' => $resolved, 'stale_probes' => $stale];
    }

    /** Staff SLO dashboard: latest windows per component, policy state, probe coverage, pending credits. */
    public function report(): array
    {
        $components = StatusComponent::query()->orderBy('sort')->get();
        $rows = [];
        foreach ($components as $component) {
            $latest = [];
            foreach (array_keys(self::WINDOWS) as $window) {
                $w = SloWindow::query()->where('component_key', $component->key)->where('window', $window)->orderByDesc('window_end')->first();
                $latest[$window] = $w === null ? null : ['availability' => $w->availability_pct, 'burn_rate' => $w->burn_rate, 'good' => $w->good, 'total' => $w->total, 'budget_consumed' => $w->budget_consumed_pct, 'policy_state' => $w->policy_state, 'computed_at' => $w->computed_at->toIso8601String()];
            }
            $probes = SlaProbe::query()->where('component_key', $component->key)->where('state', 'active')->get();
            $rows[] = [
                'component' => $component->key, 'name' => $component->name, 'state' => $component->state, 'sla_class' => $component->sla_class,
                'objective' => (float) config("onhost.sla.classes.{$component->sla_class}.objective", 99.9), 'contractual' => config("onhost.sla.classes.{$component->sla_class}.contractual"),
                'windows' => $latest, 'probe_locations' => $probes->pluck('location')->unique()->values()->all(),
                'probes_stale' => $probes->filter(fn (SlaProbe $p) => $p->last_seen_at === null || $p->last_seen_at < now()->subMinutes(10))->pluck('key')->values()->all(),
            ];
        }

        return ['components' => $rows, 'credits_pending' => SlaCredit::query()->where('state', 'candidate')->count(), 'generated_at' => now()->toIso8601String()];
    }

    // ── SLA credits ──────────────────────────────────────────────────────────

    /**
     * Compute credit candidates for an incident (§66.9). Per affected service with a contractual SLA class:
     * monthly availability = 1 − (incident downtime outside excluded maintenance ÷ seconds in the month),
     * band from the current policy version, amount = credit % × monthly net price (annual plans ÷ 12).
     *
     * @return Collection<int, SlaCredit>
     */
    public function creditCandidates(Incident $incident, CommandContext $context): Collection
    {
        if ($incident->resolved_at === null) {
            throw new DomainError('incident_not_resolved', 'SLA credits are computed after the incident is resolved.', 409);
        }
        if (! $incident->sla_relevant) {
            return collect();
        }
        $downtime = $this->downtimeSeconds($incident);
        $monthSeconds = $incident->started_at->copy()->startOfMonth()->diffInSeconds($incident->started_at->copy()->endOfMonth()) + 1;
        $created = collect();
        $services = Service::query()->whereIn('id', (array) ($incident->affected_services ?? []))->get();
        foreach ($services as $service) {
            $policy = SlaCreditPolicy::current($service->sla_class);
            if ($policy === null || config("onhost.sla.classes.{$service->sla_class}.contractual") === null) {
                continue;
            }
            $availability = round(max(0.0, 100 - ($downtime / $monthSeconds) * 100), 4);
            $percent = $policy->creditPercentFor($availability);
            if ($percent === 0) {
                continue;
            }
            $subscription = Subscription::query()->where('service_id', $service->id)->whereIn('state', ['active', 'past_due'])->first();
            if ($subscription === null) {
                continue;
            }
            $monthly = Money::minor((int) $subscription->amount_minor, $subscription->currency);
            if ($subscription->period === 'year') {
                $monthly = $monthly->share(1, 12);
            }
            $amount = $monthly->percent($percent);
            if (! $amount->isPositive()) {
                continue;
            }
            $existing = SlaCredit::query()->where('organization_id', $service->organization_id)->where('incident_id', $incident->id)->where('service_id', $service->id)->first();
            if ($existing !== null) {
                $created->push($existing);

                continue;
            }
            $credit = SlaCredit::query()->create([
                'organization_id' => $service->organization_id, 'incident_id' => $incident->id, 'service_id' => $service->id, 'policy_id' => $policy->id,
                'availability_pct' => $availability, 'credit_percent' => $percent, 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value, 'state' => 'candidate',
                'calculation' => [
                    'incident' => $incident->number, 'policy' => "{$policy->key}@v{$policy->version}", 'sla_class' => $service->sla_class, 'window' => [$incident->started_at->toIso8601String(), $incident->resolved_at->toIso8601String()],
                    'downtime_seconds' => $downtime, 'month_seconds' => $monthSeconds, 'availability_pct' => $availability, 'band_percent' => $percent, 'cap_percent' => $policy->cap_percent,
                    'monthly_net' => $monthly, 'subscription_id' => $subscription->id,
                ],
            ]);
            $this->audit->record($context->withScope($service->organization_id), 'sla.credit.candidate', 'succeeded', ['incident' => $incident->number, 'service' => $service->id, 'percent' => $percent, 'amount' => $amount], 'sla_credit', $credit->id);
            $created->push($credit);
        }

        return $created;
    }

    public function approve(SlaCredit $credit, CommandContext $context): SlaCredit
    {
        if ($credit->state !== 'candidate') {
            throw new DomainError('sla_credit_not_candidate', 'Only candidates can be approved.', 409);
        }
        $credit->forceFill(['state' => 'approved', 'approved_by' => $context->actorId])->save();
        $this->audit->record($context->withScope($credit->organization_id), 'sla.credit.approve', 'succeeded', ['amount' => Money::minor($credit->amount_minor, $credit->currency)], 'sla_credit', $credit->id);

        return $credit;
    }

    public function reject(SlaCredit $credit, string $reason, CommandContext $context): SlaCredit
    {
        if ($credit->state === 'issued') {
            throw new DomainError('sla_credit_issued', 'Issued credits cannot be rejected; issue a correction instead.', 409);
        }
        $credit->forceFill(['state' => 'rejected', 'calculation' => $credit->calculation + ['rejected_reason' => $reason]])->save();
        $this->audit->record($context->withScope($credit->organization_id), 'sla.credit.reject', 'succeeded', ['reason' => $reason], 'sla_credit', $credit->id);

        return $credit;
    }

    /** Issue: DK credit note (negative line, tax per engine) + non-refundable promo-bucket wallet credit + `sla.credit.issued`. */
    public function issue(SlaCredit $credit, CommandContext $context): SlaCredit
    {
        if ($credit->state !== 'approved') {
            throw new DomainError('sla_credit_not_approved', 'Approve the credit before issuing it.', 409);
        }
        $organization = Organization::query()->findOrFail($credit->organization_id);
        $incident = Incident::query()->findOrFail($credit->incident_id);
        $service = Service::query()->withTrashed()->find($credit->service_id);
        $amount = Money::minor($credit->amount_minor, $credit->currency);
        $calc = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'sla', 'net' => $amount, 'product_class' => 'esd']], $credit->currency, $organization->id);
        $line = $calc['lines'][0];
        $scoped = $context->withScope($organization->id);

        $credit = DB::transaction(function () use ($credit, $organization, $incident, $service, $amount, $line, $scoped) {
            $draft = $this->invoices->draft($organization, 'credit_note', $credit->currency, [[
                'sku' => 'sla-credit', 'description' => "SLA kredit {$credit->credit_percent} % za incident {$incident->number}".($service ? " — {$service->name}" : ''), 'qty' => 1, 'unit' => 'ks',
                'unit_net' => -$amount->minor, 'discount' => 0, 'net' => -$amount->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'],
                'tax' => -$line['tax']->minor, 'total' => -$line['total']->minor, 'service_id' => $service?->id,
                'period_from' => $incident->started_at->toDateString(), 'period_to' => $incident->resolved_at?->toDateString(),
            ]], $scoped, null, ['reason' => 'sla_credit', 'incident' => $incident->number, 'sla_credit_id' => $credit->id, 'calculation' => $credit->calculation]);
            $note = $this->invoices->issue($draft, $scoped, dueDays: 0);
            $this->wallets->topup($organization, $amount, 'sla_credit', "sla_credit:{$credit->id}", $scoped, null, "SLA kredit {$incident->number}", promo: true);
            $credit->forceFill(['state' => 'issued', 'invoice_id' => $note->id, 'issued_at' => now()])->save();
            $this->audit->record($scoped, 'sla.credit.issue', 'succeeded', ['incident' => $incident->number, 'credit_note' => $note->number, 'amount' => $amount, 'percent' => $credit->credit_percent], 'sla_credit', $credit->id);

            return $credit;
        });
        $this->outbox->publish(GenericEvent::of('sla.credit.issued', 'sla_credit', $credit->id, ['amount' => $amount, 'incident' => $incident->number, 'percent' => $credit->credit_percent, 'service_id' => $credit->service_id, 'credit_note_id' => $credit->invoice_id], $organization->id));

        return $credit;
    }

    /** Incident downtime minus overlap with SLA-excluded maintenance windows. */
    public function downtimeSeconds(Incident $incident): int
    {
        $start = $incident->started_at;
        $end = $incident->resolved_at ?? now();
        $seconds = max(0, (int) $start->diffInSeconds($end));
        $windows = Maintenance::query()->where('sla_treatment', 'excluded')->whereIn('state', ['approved', 'in_progress', 'completed'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get()
            ->filter(fn (Maintenance $m) => array_intersect($m->components, $incident->components) !== []);
        foreach ($windows as $w) {
            $overlap = max(0, (int) $w->starts_at->max($start)->diffInSeconds($w->ends_at->min($end)));
            $seconds -= $overlap;
        }

        return max(0, $seconds);
    }

    /** Services mapped to a component by family group and region (web-cz1 ← family web in cz1). */
    public function servicesForComponent(StatusComponent $component): Collection
    {
        if ($component->group === null) {
            return collect();
        }

        return Service::query()->where('family', $component->group)->whereIn('state', ['ACTIVE', 'DEGRADED', 'SUSPENDED'])
            ->when($component->region_code !== null, fn ($q) => $q->where('region_code', $component->region_code))->get();
    }
}
