<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Tax\Jobs\CheckVatNumber;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Partner / reseller programme (prototype Onhost-partner.dc.html, handoff §7):
 *  - attribution is bound to the client account (`organizations.partner_organization_id`), never to a product;
 *  - commission accrues from PAID tax documents only (invoice FV, statement VY), credit notes reverse it;
 *  - revenue-share tier from the trailing three months of paid volume, rate only moves up immediately and
 *    a dropped tier keeps the old rate for three more months; one-off model = 3 monthly payments on the first invoice, then 5 %;
 *  - payouts ≥ 1 000 CZK by self-billing, ledger-posted as partner-commission expense when paid.
 */
final class PartnerService
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly PayoutAccounts $accounts,
        private readonly PartnerPayouts $payouts,
    ) {}

    // ── lifecycle ────────────────────────────────────────────────────────────

    /** @param array{model?:string,company?:string,clients?:string,site?:string,note?:string} $application */
    public function apply(Organization $organization, array $application, CommandContext $context): Partner
    {
        $existing = Partner::query()->where('organization_id', $organization->id)->first();
        if ($existing !== null) {
            if ($existing->state === 'closed') {
                $existing->forceFill(['state' => 'applied', 'application' => $application])->save();

                return $existing;
            }

            return $existing;
        }
        $model = in_array($application['model'] ?? 'share', Partner::MODELS, true) ? $application['model'] ?? 'share' : 'share';
        $partner = Partner::query()->create([
            'organization_id' => $organization->id, 'code' => $this->code($organization), 'model' => $model, 'tier' => 'bronze', 'rate_pct' => $this->tierRate('bronze'),
            'currency' => $organization->currency ?? 'CZK', 'state' => 'applied', 'application' => array_intersect_key($application, array_flip(['company', 'clients', 'site', 'note', 'model'])),
            'whitelabel' => ['domain' => null, 'hide_brand' => false, 'own_mail' => false, 'own_prices' => false, 'own_support' => false, 'verified_at' => null],
        ]);
        $this->audit->record($context->withScope($organization->id), 'partner.apply', 'succeeded', ['model' => $model, 'code' => $partner->code], 'partner', $partner->id);
        $this->queueVatCheck($organization);
        $this->outbox->publish(GenericEvent::of('partner.application.received', 'partner', $partner->id, ['code' => $partner->code, 'model' => $model, 'company' => $application['company'] ?? $organization->name], $organization->id));

        return $partner;
    }

    public function approve(Partner $partner, CommandContext $context): Partner
    {
        if ($partner->state === 'active') {
            return $partner;
        }
        $partner->forceFill(['state' => 'active', 'approved_by' => $context->actorId, 'approved_at' => now()])->save();
        $organization = Organization::query()->find($partner->organization_id);
        if ($organization !== null) {
            $this->queueVatCheck($organization);
        }
        $this->audit->record($context->withScope($partner->organization_id), 'partner.approve', 'succeeded', ['code' => $partner->code], 'partner', $partner->id);
        $this->outbox->publish(GenericEvent::of('partner.approved', 'partner', $partner->id, ['code' => $partner->code, 'tier' => $partner->tier, 'rate' => $partner->rate_pct], $partner->organization_id));

        return $partner;
    }

    public function setState(Partner $partner, string $state, string $reason, CommandContext $context): Partner
    {
        if (! in_array($state, ['suspended', 'closed', 'active'], true)) {
            throw new DomainError('partner_state_invalid', 'State must be active, suspended or closed.', 422, ['field' => 'state']);
        }
        $partner->forceFill(['state' => $state])->save();
        $this->audit->record($context->withScope($partner->organization_id), 'partner.state', 'succeeded', ['state' => $state, 'reason' => $reason], 'partner', $partner->id);

        return $partner;
    }

    /** Bind a client organization to a partner by referral code; first attribution wins and is permanent. */
    public function attribute(Organization $client, string $code, CommandContext $context): ?Partner
    {
        $partner = Partner::query()->where('code', strtoupper(trim($code)))->where('state', 'active')->first();
        if ($partner === null) {
            return null;
        }
        if ($partner->organization_id === $client->id || $client->partner_organization_id !== null) {
            return null;
        }
        $client->forceFill(['partner_organization_id' => $partner->organization_id])->save();
        $this->audit->record($context->withScope($client->id), 'partner.attribute', 'succeeded', ['partner' => $partner->id, 'code' => $partner->code], 'organization', $client->id);

        return $partner;
    }

    public function partnerFor(Organization|string $organization): ?Partner
    {
        return Partner::query()->where('organization_id', $organization instanceof Organization ? $organization->id : $organization)->first();
    }

    public function partnerOfClient(Organization $client): ?Partner
    {
        return $client->partner_organization_id === null ? null : Partner::query()->where('organization_id', $client->partner_organization_id)->where('state', 'active')->first();
    }

    // ── commissions ──────────────────────────────────────────────────────────

    /** Called from the `invoice.paid` outbox event: one commission per paid FV/VY document. */
    public function accrueForInvoice(Invoice $invoice): ?PartnerCommission
    {
        if (! in_array($invoice->type, ['invoice', 'statement'], true) || $invoice->state !== Invoice::PAID) {
            return null;
        }
        $client = Organization::query()->find($invoice->organization_id);
        $partner = $client === null ? null : $this->partnerOfClient($client);
        if ($partner === null) {
            return null;
        }
        if (PartnerCommission::query()->where('invoice_id', $invoice->id)->where('kind', '!=', 'reversal')->exists()) {
            return null;
        }
        $base = Money::minor((int) $invoice->subtotal_minor - (int) $invoice->discount_minor, $invoice->currency);
        if (! $base->isPositive()) {
            return null;
        }
        if ($partner->model === 'oneoff') {
            $first = ! PartnerCommission::query()->where('partner_id', $partner->id)->where('organization_id', $client->id)->exists();
            $kind = $first ? 'oneoff' : 'tail';
            $rate = $first ? 100 * (int) config('onhost.partners.oneoff_bonus_months', 3) : (int) config('onhost.partners.oneoff_tail_pct', 5);
        } else {
            $kind = 'share';
            $rate = (int) $partner->rate_pct;
        }
        $amount = $base->percent($rate);
        $paidAt = $invoice->paid_at ?? now();
        $commission = PartnerCommission::query()->create([
            'partner_id' => $partner->id, 'organization_id' => $client->id, 'invoice_id' => $invoice->id, 'period' => $paidAt->format('Y-m'), 'kind' => $kind,
            'base_minor' => $base->minor, 'rate_pct' => $rate, 'amount_minor' => $amount->minor, 'currency' => $invoice->currency, 'state' => 'payable', 'invoice_paid_at' => $paidAt,
        ]);
        $this->audit->record(CommandContext::system('partner.commission')->withScope($partner->organization_id), 'partner.commission.accrue', 'succeeded', ['invoice' => $invoice->number, 'client' => $client->id, 'kind' => $kind, 'rate' => $rate, 'amount' => $amount], 'partner_commission', $commission->id);

        return $commission;
    }

    /** A credit note reverses the commission of the document it corrects (negative payable line). */
    public function reverseForCreditNote(Invoice $creditNote): ?PartnerCommission
    {
        if ($creditNote->type !== 'credit_note' || $creditNote->corrects_invoice_id === null) {
            return null;
        }
        $original = PartnerCommission::query()->where('invoice_id', $creditNote->corrects_invoice_id)->where('kind', '!=', 'reversal')->first();
        if ($original === null || PartnerCommission::query()->where('invoice_id', $creditNote->id)->where('kind', 'reversal')->exists()) {
            return null;
        }
        $creditedNet = abs((int) $creditNote->subtotal_minor - (int) $creditNote->discount_minor);
        $share = min($creditedNet, $original->base_minor);
        $amount = Money::minor($share, $creditNote->currency)->percent($original->rate_pct)->negate();

        return PartnerCommission::query()->create([
            'partner_id' => $original->partner_id, 'organization_id' => $original->organization_id, 'invoice_id' => $creditNote->id, 'period' => now()->format('Y-m'), 'kind' => 'reversal',
            'base_minor' => -$share, 'rate_pct' => $original->rate_pct, 'amount_minor' => $amount->minor, 'currency' => $creditNote->currency, 'state' => 'payable', 'invoice_paid_at' => now(),
        ]);
    }

    /** Trailing 3-month tier with the "rate only moves up immediately, down after 3 months" rule. */
    public function recomputeTier(Partner $partner, ?Carbon $now = null): Partner
    {
        $now ??= now();
        $from = $now->copy()->subMonths(3)->startOfMonth();
        $volume = (int) PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', '!=', 'reversal')->where('invoice_paid_at', '>=', $from)->where('invoice_paid_at', '<', $now->copy()->startOfMonth())->sum('base_minor');
        $monthly = intdiv($volume, 3);
        $tier = 'bronze';
        foreach ((array) config('onhost.partners.tiers', []) as [$name, $threshold]) {
            if ($monthly >= (int) $threshold) {
                $tier = $name;
            }
        }
        $newRate = $this->tierRate($tier);
        $previousTier = $partner->tier;
        $update = ['volume_3m_minor' => $monthly, 'tier_recomputed_at' => $now];
        if ($partner->model !== 'share') {
            $update['tier'] = $tier;
        } elseif ($newRate >= $partner->rate_pct) {
            $update += ['tier' => $tier, 'rate_pct' => $newRate, 'rate_locked_until' => null];
        } elseif ($partner->rate_locked_until === null) {
            $update['rate_locked_until'] = $now->copy()->addMonths((int) config('onhost.partners.rate_lock_months', 3));
        } elseif ($now >= $partner->rate_locked_until) {
            $update += ['tier' => $tier, 'rate_pct' => $newRate, 'rate_locked_until' => null];
        }
        $partner->forceFill($update)->save();
        if ($partner->tier !== $previousTier) {
            $this->outbox->publish(GenericEvent::of('partner.tier.changed', 'partner', $partner->id, ['from' => $previousTier, 'to' => $partner->tier, 'rate' => $partner->rate_pct, 'volume' => Money::minor($monthly, $partner->currency)], $partner->organization_id));
        }

        return $partner;
    }

    public function recomputeAllTiers(?Carbon $now = null): int
    {
        $count = 0;
        foreach (Partner::query()->where('state', 'active')->get() as $partner) {
            $this->recomputeTier($partner, $now);
            $count++;
        }

        return $count;
    }

    public function tierRate(string $tier): int
    {
        foreach ((array) config('onhost.partners.tiers', []) as [$name, , $rate]) {
            if ($name === $tier) {
                return (int) $rate;
            }
        }

        return 15;
    }

    /** @return array{payable: Money, held: Money, paid: Money, allocated: Money} */
    public function balance(Partner $partner): array
    {
        $currency = $partner->currency;
        $sum = fn (string $state) => Money::minor((int) PartnerCommission::query()->where('partner_id', $partner->id)->where('state', $state)->sum('amount_minor'), $currency);
        $clientIds = $this->clientQuery($partner)->pluck('id');
        $openNet = (int) Invoice::query()->whereIn('organization_id', $clientIds)->where('type', 'invoice')->where('state', Invoice::ISSUED)->where('currency', $currency)->selectRaw('coalesce(sum(subtotal_minor - discount_minor), 0) as n')->value('n');

        return ['payable' => $sum('payable'), 'allocated' => $sum('allocated'), 'paid' => $sum('paid'), 'held' => Money::minor($openNet, $currency)->percent($partner->rate_pct)];
    }

    // ── payouts ──────────────────────────────────────────────────────────────
    // TASK-0040: the lifecycle (locked request, approval, the four-eyes payment, freeze) lives in PartnerPayouts; these are the
    // doors its callers — the command handler, the automatic payouts, tests — already use.

    public function requestPayout(Partner $partner, Money $amount, ?string $iban, CommandContext $context, string $method = 'bank_transfer'): PartnerPayout
    {
        return $this->payouts->requestPayout($partner, $amount, $iban, $context, $method);
    }

    public function approvePayout(PartnerPayout $payout, CommandContext $context): PartnerPayout
    {
        return $this->payouts->approvePayout($payout, $context);
    }

    public function rejectPayout(PartnerPayout $payout, string $reason, CommandContext $context): PartnerPayout
    {
        return $this->payouts->rejectPayout($payout, $reason, $context);
    }

    public function markPayoutPaid(PartnerPayout $payout, string $paymentReference, CommandContext $context): PartnerPayout
    {
        return $this->payouts->markPayoutPaid($payout, $paymentReference, $context);
    }

    public function freezePayout(PartnerPayout $payout, string $reason, CommandContext $context): PartnerPayout
    {
        return $this->payouts->freezePayout($payout, $reason, $context);
    }

    public function unfreezePayout(PartnerPayout $payout, string $reason, CommandContext $context, bool $confirmsAccount = false): PartnerPayout
    {
        return $this->payouts->unfreezePayout($payout, $reason, $context, $confirmsAccount);
    }

    public function payoutAccounts(): PayoutAccounts
    {
        return $this->accounts;
    }

    // ── portal projections ───────────────────────────────────────────────────

    /**
     * Client rows in the prototype shape: id, name, contact, st (ok|due|churn), mrr, since, svc, services[[name, monthly]].
     *
     * TASK-0040 (program D13, §10 O9, audit P5): the partner's view (`$internal` false) carries no contact and no dunning —
     * `contact` is null with `contact_masked`, and an overdue client reads `ok` (active) as any other. Attribution gives the
     * partner commission, not the client's e-mail or the fact that it is behind with its invoices (no DPA covers that
     * disclosure). Contact-level detail comes back only with the client's recorded consent (S3-01). Staff see everything.
     */
    public function clients(Partner $partner, bool $internal = false): Collection
    {
        $clients = $this->clientQuery($partner)->orderBy('created_at')->get();
        $out = collect();
        foreach ($clients as $client) {
            $services = Service::query()->where('organization_id', $client->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get();
            $subs = Subscription::query()->where('organization_id', $client->id)->whereIn('state', ['active', 'past_due'])->get();
            $mrr = $subs->sum(fn (Subscription $s) => $s->period === 'year' ? intdiv((int) $s->amount_minor, 12) : (int) $s->amount_minor);
            $overdue = Invoice::query()->where('organization_id', $client->id)->where('type', 'invoice')->where('state', Invoice::ISSUED)->where('due_at', '<', now())->exists()
                || DunningCase::query()->where('organization_id', $client->id)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->exists();
            $churn = $subs->isNotEmpty() && $subs->every(fn (Subscription $s) => $s->cancel_at_period_end) || $client->state === 'closed';
            $owner = User::query()->find($client->owner_user_id);
            $families = $services->groupBy('family')->map(fn ($g, $family) => count($g) > 1 ? count($g).'× '.$family : $family)->values()->implode(' · ');
            $out->push([
                'id' => $client->id, 'name' => $client->name, 'contact' => $internal ? ($owner?->email ?? $client->billing_email) : null, 'contact_masked' => ! $internal,
                'st' => $churn ? 'churn' : ($overdue && $internal ? 'due' : 'ok'),
                'mrr' => Money::minor($mrr, $partner->currency), 'since' => $client->created_at->format('n / Y'), 'svc' => $families,
                'services' => $services->map(fn (Service $s) => [$s->name.($s->hostname ? " — {$s->hostname}" : ''), Money::minor((int) ($subs->firstWhere('service_id', $s->id)?->amount_minor ?? 0), $partner->currency)])->values()->all(),
                'commission' => Money::minor($mrr, $partner->currency)->percent($partner->rate_pct),
            ]);
        }

        return $out;
    }

    /** Overview KPIs, tier progress and activity feed for `#/prehled`. */
    public function overview(Partner $partner): array
    {
        $clients = $this->clients($partner);
        $mrr = Money::minor((int) $clients->sum(fn ($c) => $c['mrr']->minor), $partner->currency);
        $balance = $this->balance($partner);
        $tiers = (array) config('onhost.partners.tiers', []);
        $index = 0;
        foreach ($tiers as $i => [$name]) {
            if ($name === $partner->tier) {
                $index = $i;
            }
        }
        $next = $tiers[$index + 1] ?? null;
        $feed = collect();
        foreach (PartnerCommission::query()->where('partner_id', $partner->id)->orderByDesc('created_at')->limit(8)->get() as $c) {
            $client = $clients->firstWhere('id', $c->organization_id);
            $feed->push(['when' => $c->created_at->toIso8601String(), 'tag' => $c->kind === 'reversal' ? 'dobropis' : 'provize', 'text' => ($client['name'] ?? $c->organization_id).' · '.Money::minor($c->amount_minor, $c->currency)->format()]);
        }
        foreach (PartnerPayout::query()->where('partner_id', $partner->id)->orderByDesc('created_at')->limit(3)->get() as $p) {
            $feed->push(['when' => ($p->paid_at ?? $p->requested_at)->toIso8601String(), 'tag' => 'výplata', 'text' => "{$p->number} · ".Money::minor($p->amount_minor, $p->currency)->format().' · '.$p->state]);
        }

        return [
            'partner' => ['code' => $partner->code, 'model' => $partner->model, 'tier' => $partner->tier, 'rate' => $partner->rate_pct, 'state' => $partner->state, 'rate_locked_until' => $partner->rate_locked_until?->toIso8601String()],
            'kpis' => ['volume' => $mrr, 'commission_monthly' => $mrr->percent($partner->rate_pct), 'active_clients' => $clients->where('st', '!=', 'churn')->count(), 'clients' => $clients->count(), 'churn' => $clients->where('st', 'churn')->count()],
            'balance' => $balance,
            'tier' => ['name' => $partner->tier, 'rate' => $partner->rate_pct, 'table' => array_map(fn ($t) => ['name' => $t[0], 'threshold_minor' => (int) $t[1], 'rate' => (int) $t[2]], $tiers), 'volume_3m' => Money::minor($partner->volume_3m_minor, $partner->currency), 'next' => $next ? ['name' => $next[0], 'threshold' => Money::minor((int) $next[1], $partner->currency), 'missing' => Money::minor(max(0, (int) $next[1] - $mrr->minor), $partner->currency)] : null, 'marks' => array_map(fn ($t) => ['name' => $t[0], 'threshold' => Money::minor((int) $t[1], $partner->currency), 'rate' => $t[2]], $tiers)],
            'top' => $clients->sortByDesc(fn ($c) => $c['mrr']->minor)->take(5)->values()->all(),
            'feed' => $feed->sortByDesc('when')->values()->take(8)->all(),
        ];
    }

    /** Monthly commission table (`#/provize`). */
    public function commissionMonths(Partner $partner): array
    {
        $rows = PartnerCommission::query()->where('partner_id', $partner->id)->get()->groupBy('period')->sortKeysDesc();
        $out = [];
        foreach ($rows as $period => $items) {
            $out[] = [
                'period' => $period, 'clients' => $items->pluck('organization_id')->unique()->count(), 'base' => Money::minor((int) $items->sum('base_minor'), $partner->currency),
                'rate' => (int) round($items->avg('rate_pct')), 'amount' => Money::minor((int) $items->sum('amount_minor'), $partner->currency),
                'state' => $items->every(fn ($c) => $c->state === 'paid') ? 'paid' : ($items->contains(fn ($c) => $c->state === 'allocated') ? 'requested' : 'accruing'),
                'lines' => $items->map(fn (PartnerCommission $c) => ['invoice_id' => $c->invoice_id, 'organization_id' => $c->organization_id, 'kind' => $c->kind, 'base' => Money::minor($c->base_minor, $c->currency), 'rate' => $c->rate_pct, 'amount' => Money::minor($c->amount_minor, $c->currency), 'state' => $c->state, 'paid_at' => $c->invoice_paid_at?->toIso8601String()])->values()->all(),
            ];
        }

        return $out;
    }

    /** @param array{domain?:string|null,hide_brand?:bool,own_mail?:bool,own_prices?:bool,own_support?:bool} $input */
    public function updateWhitelabel(Partner $partner, array $input, CommandContext $context): Partner
    {
        $current = $partner->whitelabel ?? [];
        $domain = array_key_exists('domain', $input) ? strtolower(trim((string) ($input['domain'] ?? ''))) : ($current['domain'] ?? null);
        if ($domain !== null && $domain !== '' && ! preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
            throw new DomainError('whitelabel_domain_invalid', 'Enter a valid panel domain (e.g. panel.agentura.cz).', 422, ['field' => 'domain']);
        }
        $domain = $domain === '' ? null : $domain;
        $next = [
            'domain' => $domain, 'verified_at' => $domain === ($current['domain'] ?? null) ? ($current['verified_at'] ?? null) : null,
            'hide_brand' => (bool) ($input['hide_brand'] ?? $current['hide_brand'] ?? false), 'own_mail' => (bool) ($input['own_mail'] ?? $current['own_mail'] ?? false),
            'own_prices' => (bool) ($input['own_prices'] ?? $current['own_prices'] ?? false), 'own_support' => (bool) ($input['own_support'] ?? $current['own_support'] ?? false),
        ];
        $limited = [];
        if (($partner->whitelabel_scope ?: 'basic') !== 'full') { // §5n-1: own mail, prices and support are the full scope — a contract term finance grants
            foreach (['own_mail', 'own_prices', 'own_support'] as $flag) {
                if ($next[$flag]) {
                    $next[$flag] = false;
                    $limited[] = $flag;
                }
            }
        }
        $next['limited'] = $limited;
        $next['cname_target'] = config('onhost.partners.whitelabel_cname', 'panel.onhost.cz');
        $partner->forceFill(['whitelabel' => $next])->save();
        $this->audit->record($context->withScope($partner->organization_id), 'partner.whitelabel.update', 'succeeded', array_diff_key($next, ['verified_at' => 1]), 'partner', $partner->id);

        return $partner;
    }

    /** CNAME verification for white-label domains (skipped when DNS is unavailable, e.g. in tests). */
    public function verifyWhitelabelDomains(): int
    {
        $verified = 0;
        $target = (string) config('onhost.partners.whitelabel_cname', 'panel.onhost.cz');
        foreach (Partner::query()->where('state', 'active')->whereNotNull('whitelabel->domain')->whereNull('whitelabel->verified_at')->get() as $partner) {
            $domain = $partner->whitelabel['domain'];
            $records = function_exists('dns_get_record') ? @dns_get_record($domain, DNS_CNAME) : [];
            foreach ((array) $records as $record) {
                if (strcasecmp(rtrim((string) ($record['target'] ?? ''), '.'), $target) === 0) {
                    $partner->forceFill(['whitelabel' => ['verified_at' => now()->toIso8601String()] + $partner->whitelabel])->save();
                    $verified++;
                    break;
                }
            }
        }

        return $verified;
    }

    // ── contract changes (§5m-1, §5n-1): a term is a contract clause — the partner asks, finance decides, the change takes effect next month ──

    public const CHANGE_KINDS = ['model', 'rate_lock', 'payout_terms', 'whitelabel_scope'];

    /** kind => the values a partner may ask for (rate_lock: months) */
    public const CHANGE_VALUES = ['model' => ['share', 'oneoff'], 'rate_lock' => ['3', '6', '12'], 'payout_terms' => ['on_request', 'monthly', 'quarterly'], 'whitelabel_scope' => ['basic', 'full']];

    public function requestModelChange(Partner $partner, string $model, ?string $note, CommandContext $context): PartnerChangeRequest
    {
        return $this->requestChange($partner, 'model', $model, $note, $context);
    }

    public function requestChange(Partner $partner, string $kind, string $value, ?string $note, CommandContext $context): PartnerChangeRequest
    {
        if (! in_array($kind, self::CHANGE_KINDS, true)) {
            throw new DomainError('partner_change_kind_invalid', 'The term is one of: '.implode(', ', self::CHANGE_KINDS).'.', 422, ['field' => 'kind']);
        }
        if (! in_array($value, self::CHANGE_VALUES[$kind], true)) {
            throw new DomainError($kind === 'model' ? 'partner_model_invalid' : 'partner_change_value_invalid', 'Allowed values: '.implode(', ', self::CHANGE_VALUES[$kind]).'.', 422, ['field' => $kind === 'model' ? 'model' : 'value']);
        }
        $current = $this->termValue($partner, $kind);
        if ($kind !== 'rate_lock' && $value === $current && ! $this->pendingOf($partner, $kind)) {
            throw new DomainError($kind === 'model' ? 'partner_model_same' : 'partner_change_same', 'That is the term in force already.', 409);
        }
        if (PartnerChangeRequest::query()->where('partner_id', $partner->id)->where('kind', $kind)->where('state', PartnerChangeRequest::REQUESTED)->exists()) {
            throw new DomainError('partner_request_pending', 'A change request for this term is already waiting for finance.', 409);
        }
        $request = PartnerChangeRequest::query()->create(['partner_id' => $partner->id, 'kind' => $kind, 'from_value' => $current, 'to_value' => $value, 'state' => PartnerChangeRequest::REQUESTED, 'note' => $note !== null ? mb_substr($note, 0, 500) : null, 'requested_by' => $context->actorId]);
        $this->audit->record($context->withScope($partner->organization_id), 'partner.change.request', 'succeeded', ['request' => $request->id, 'kind' => $kind, 'from' => $current, 'to' => $value], 'partner', $partner->id);
        $payload = ['request_id' => $request->id, 'kind' => $kind, 'from' => $current, 'to' => $value, 'note' => $request->note, 'partner_code' => $partner->code];
        $this->outbox->publish(GenericEvent::of($kind === 'model' ? 'partner.model.requested' : 'partner.change.requested', 'partner', $partner->id, $payload, $partner->organization_id));
        $reason = $this->autoApproveReason($partner, $kind, $value); // §5o-1: simple terms need no finance
        if ($reason !== null) {
            $this->decideChange($request, 'approve', 'automaticky: '.$reason, CommandContext::system('partner.auto_approve'));
            $this->outbox->publish(GenericEvent::of('partner.change.auto_approved', 'partner', $partner->id, $payload + ['reason' => $reason, 'effective_from' => $request->effective_from?->toDateString()], $partner->organization_id));
        }

        return $request->refresh();
    }

    /**
     * Term-specific finance rules (§5o-1): a rate lock up to `auto_approve.rate_lock_max_months`, and a payout-terms change
     * for a partner active for `auto_approve.clean_months` without a rejected payout, are approved by the rule
     * `partners.auto_approve`; the model and the white-label scope always wait for finance.
     */
    public function autoApproveReason(Partner $partner, string $kind, string $value): ?string
    {
        if (! app(AutomationLedger::class)->enabled('partners.auto_approve')) {
            return null;
        }
        $rules = (array) config('onhost.partners.auto_approve', []);
        if ($kind === 'rate_lock') {
            $max = max(0, (int) ($rules['rate_lock_max_months'] ?? 6));

            return (int) $value <= $max ? "zámek sazby do {$max} měsíců" : null;
        }
        if ($kind === 'model' && $value === (string) config('onhost.partners.default_model', 'share')) { // §5p-5: back to the default model after a clean year
            $months = max(1, (int) ($rules['clean_months'] ?? 12));
            $cleanSince = now()->subMonths($months);
            if ($partner->approved_at === null || $partner->approved_at > $cleanSince || PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'rejected')->where('updated_at', '>=', $cleanSince)->exists()) {
                return null;
            }

            return "návrat na výchozí model po čistém roce ({$months} měsíců)";
        }
        if ($kind === 'payout_terms') {
            $months = max(1, (int) ($rules['clean_months'] ?? 12));
            $cleanSince = now()->subMonths($months);
            if ($partner->approved_at === null || $partner->approved_at > $cleanSince) {
                return null;
            }
            if (PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'rejected')->where('updated_at', '>=', $cleanSince)->exists()) {
                return null;
            }

            return "čistý rok partnera ({$months} měsíců bez zamítnuté výplaty)";
        }

        return null;
    }

    public function decideModelChange(PartnerChangeRequest $request, string $decision, ?string $note, CommandContext $context): PartnerChangeRequest
    {
        return $this->decideChange($request, $decision, $note, $context);
    }

    public function decideChange(PartnerChangeRequest $request, string $decision, ?string $note, CommandContext $context): PartnerChangeRequest
    {
        if ($request->state !== PartnerChangeRequest::REQUESTED) {
            throw new DomainError('partner_request_decided', 'This request was decided already.', 409, ['state' => $request->state]);
        }
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new DomainError('partner_decision_invalid', 'Decision must be approve or reject.', 422, ['field' => 'decision']);
        }
        $partner = Partner::query()->findOrFail($request->partner_id);
        // money terms take effect on the first of next month; the white-label scope has no money in it and applies at once
        $effective = $decision === 'approve' ? ($request->kind === 'whitelabel_scope' ? now()->toDateString() : now()->startOfMonth()->addMonth()->toDateString()) : null;
        $request->forceFill(['state' => $decision === 'approve' ? PartnerChangeRequest::APPROVED : PartnerChangeRequest::REJECTED, 'decision_note' => $note !== null ? mb_substr($note, 0, 500) : null, 'decided_by' => $context->actorId, 'decided_at' => now(), 'effective_from' => $effective])->save();
        if ($decision === 'approve' && $request->kind === 'model') {
            $partner->forceFill(['pending_model' => $request->to_value, 'model_effective_from' => $effective])->save();
        }
        $this->audit->record($context->withScope($partner->organization_id), 'partner.change.decide', 'succeeded', ['request' => $request->id, 'kind' => $request->kind, 'decision' => $decision, 'effective_from' => $effective, 'note' => $note], 'partner', $partner->id);
        $payload = ['request_id' => $request->id, 'kind' => $request->kind, 'to' => $request->to_value, 'effective_from' => $effective, 'note' => $note];
        $this->outbox->publish(GenericEvent::of(($request->kind === 'model' ? 'partner.model.' : 'partner.change.').($decision === 'approve' ? 'approved' : 'rejected'), 'partner', $partner->id, $payload, $partner->organization_id));
        if ($decision === 'approve' && $effective !== null && $effective <= now()->toDateString()) {
            $this->applyChange($request, $partner);
        }

        return $request;
    }

    public function applyPendingModels(?Carbon $now = null): int
    {
        return $this->applyPendingChanges($now);
    }

    /** Applies approved changes whose day came (scheduled daily); returns how many. */
    public function applyPendingChanges(?Carbon $now = null): int
    {
        $now ??= now();
        $count = 0;
        foreach (PartnerChangeRequest::query()->where('state', PartnerChangeRequest::APPROVED)->whereNull('applied_at')->whereDate('effective_from', '<=', $now->toDateString())->orderBy('created_at')->get() as $request) {
            $partner = Partner::query()->find($request->partner_id);
            if ($partner === null) {
                continue;
            }
            $this->applyChange($request, $partner, $now);
            $count++;
        }
        foreach (Partner::query()->whereNotNull('pending_model')->whereDate('model_effective_from', '<=', $now->toDateString())->get() as $partner) { // approvals recorded before the request table carried `applied_at`
            $from = $partner->model;
            $partner->forceFill(['model' => $partner->pending_model, 'pending_model' => null, 'model_effective_from' => null])->save();
            $this->audit->record(CommandContext::system('partner.model')->withScope($partner->organization_id), 'partner.model.apply', 'succeeded', ['from' => $from, 'to' => $partner->model], 'partner', $partner->id);
            $this->outbox->publish(GenericEvent::of('partner.model.changed', 'partner', $partner->id, ['from' => $from, 'to' => $partner->model], $partner->organization_id));
            $count++;
        }

        return $count;
    }

    private function applyChange(PartnerChangeRequest $request, Partner $partner, ?Carbon $now = null): void
    {
        $now ??= now();
        $from = $this->termValue($partner, $request->kind);
        match ($request->kind) {
            'model' => $partner->forceFill(['model' => $request->to_value, 'pending_model' => null, 'model_effective_from' => null]),
            'rate_lock' => $partner->forceFill(['rate_locked_until' => $now->copy()->startOfDay()->addMonths((int) $request->to_value)]),
            'payout_terms' => $partner->forceFill(['payout_terms' => $request->to_value]),
            'whitelabel_scope' => $partner->forceFill(['whitelabel_scope' => $request->to_value]),
            default => throw new DomainError('partner_change_kind_invalid', 'Unknown term.', 422),
        };
        $partner->save();
        $request->forceFill(['applied_at' => $now])->save();
        $to = $this->termValue($partner, $request->kind);
        $this->audit->record(CommandContext::system('partner.change')->withScope($partner->organization_id), 'partner.change.apply', 'succeeded', ['request' => $request->id, 'kind' => $request->kind, 'from' => $from, 'to' => $to], 'partner', $partner->id);
        $this->outbox->publish(GenericEvent::of($request->kind === 'model' ? 'partner.model.changed' : 'partner.change.applied', 'partner', $partner->id, ['request_id' => $request->id, 'kind' => $request->kind, 'from' => $from, 'to' => $to], $partner->organization_id));
    }

    /** The value of a term as the partner sees it (rate_lock: the date the rate is locked until). */
    public function termValue(Partner $partner, string $kind): ?string
    {
        return match ($kind) {
            'model' => $partner->model,
            'rate_lock' => $partner->rate_locked_until !== null && $partner->rate_locked_until > now() ? $partner->rate_locked_until->toDateString() : null,
            'payout_terms' => (string) ($partner->payout_terms ?: 'on_request'),
            'whitelabel_scope' => (string) ($partner->whitelabel_scope ?: 'basic'),
            default => null,
        };
    }

    /** @return array<string,mixed> the terms in force, what is pending or open per term, and the request history — the portal's contract block */
    public function terms(Partner $partner): array
    {
        $requests = PartnerChangeRequest::query()->where('partner_id', $partner->id)->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get();
        $pending = [];
        $open = [];
        foreach ($requests as $r) {
            if ($r->state === PartnerChangeRequest::APPROVED && $r->applied_at === null && ! isset($pending[$r->kind])) {
                $pending[$r->kind] = ['to' => $r->to_value, 'effective_from' => $r->effective_from?->toDateString()];
            }
            if ($r->state === PartnerChangeRequest::REQUESTED && ! isset($open[$r->kind])) {
                $open[$r->kind] = self::presentRequest($r);
            }
        }
        if ($partner->pending_model !== null && ! isset($pending['model'])) {
            $pending['model'] = ['to' => $partner->pending_model, 'effective_from' => $partner->model_effective_from?->toDateString()];
        }

        return [
            'model' => $partner->model, 'rate' => (int) $partner->rate_pct, 'rate_locked_until' => $this->termValue($partner, 'rate_lock'), 'payout_terms' => $this->termValue($partner, 'payout_terms'), 'whitelabel_scope' => $this->termValue($partner, 'whitelabel_scope'),
            'pending' => $pending, 'open' => $open, 'kinds' => self::CHANGE_VALUES, 'min_payout' => Money::minor((int) config('onhost.partners.min_payout_minor', 100000), $partner->currency),
            'requests' => $requests->map(fn (PartnerChangeRequest $r) => self::presentRequest($r))->values()->all(),
        ];
    }

    private function pendingOf(Partner $partner, string $kind): bool
    {
        return PartnerChangeRequest::query()->where('partner_id', $partner->id)->where('kind', $kind)->where('state', PartnerChangeRequest::APPROVED)->whereNull('applied_at')->exists();
    }

    /** @return array<string,mixed>|null the open or last decided model request of a partner */
    public function modelRequest(Partner $partner): ?array
    {
        $request = PartnerChangeRequest::query()->where('partner_id', $partner->id)->where('kind', 'model')->orderByDesc('created_at')->orderByDesc('id')->first();

        return $request === null ? null : self::presentRequest($request);
    }

    /** @return array<string,mixed> */
    public static function presentRequest(PartnerChangeRequest $r): array
    {
        return ['id' => $r->id, 'partner_id' => $r->partner_id, 'kind' => $r->kind, 'from' => $r->from_value, 'to' => $r->to_value, 'state' => $r->state, 'note' => $r->note, 'decision_note' => $r->decision_note, 'effective_from' => $r->effective_from?->toDateString(), 'applied_at' => $r->applied_at?->toIso8601String(), 'requested_at' => $r->created_at?->toIso8601String(), 'decided_at' => $r->decided_at?->toIso8601String()];
    }

    // ── automatic payouts (§5n-1): partners on monthly or quarterly terms get their payable balance requested for them ──

    /** @return array{requested:int, skipped:int} */
    public function autoPayouts(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['requested' => 0, 'skipped' => 0];
        foreach (Partner::query()->where('state', 'active')->whereIn('payout_terms', ['monthly', 'quarterly'])->get() as $partner) {
            if ($partner->payout_terms === 'quarterly' && ! in_array((int) $now->month, [1, 4, 7, 10], true)) {
                continue;
            }
            $balance = $this->balance($partner);
            $min = Money::minor((int) config('onhost.partners.min_payout_minor', 100000), $partner->currency);
            // TASK-0040: to the confirmed payout account only — `partners.iban` was whatever the last request typed, paid or not
            if ($this->accounts->of($partner)['active'] === null || $balance['payable']->lessThan($min) || PartnerPayout::query()->where('partner_id', $partner->id)->whereIn('state', ['requested', 'approved'])->exists()) {
                $stats['skipped']++;

                continue;
            }
            try {
                $payout = $this->requestPayout($partner, $balance['payable'], null, CommandContext::system('partner.auto_payout')->withScope($partner->organization_id));
            } catch (DomainError $refused) {
                $stats['skipped']++;
                if ($refused->error === 'email_unverified') { // R5: the partner is told why the payout did not come, and what to do
                    $this->outbox->publish(GenericEvent::of('partner.payout.auto_skipped', 'partner', $partner->id, ['reason' => 'email_unverified', 'amount' => $balance['payable'], 'terms' => $partner->payout_terms], $partner->organization_id));
                }

                continue;
            }
            $this->outbox->publish(GenericEvent::of('partner.payout.auto', 'partner_payout', $payout->id, ['number' => $payout->number ?? $payout->id, 'amount' => $balance['payable'], 'terms' => $partner->payout_terms], $partner->organization_id));
            $stats['requested']++;
        }

        return $stats;
    }

    // ── TASK-0040 (program §10 O9): partners are told the same day that client contacts and dunning are masked ──

    public const MASKING_NOTICE = 'partner.client_data.masked_notice';

    /** Whether this partner was told already (the audit row is the record; a second run tells nobody twice). */
    public function maskingNoticed(Partner $partner): bool
    {
        return DB::table('audit_events')->where('action', self::MASKING_NOTICE)->where('resource_id', $partner->id)->exists();
    }

    /** Tells one active partner, once: in the portal and by the mandatory notice mail. Returns false when it was told already. */
    public function noticeMasking(Partner $partner, CommandContext $context): bool
    {
        if (! $partner->isActive() || $this->maskingNoticed($partner)) {
            return false;
        }
        $this->audit->record($context->withScope($partner->organization_id), self::MASKING_NOTICE, 'succeeded', ['code' => $partner->code], 'partner', $partner->id);
        $this->outbox->publish(GenericEvent::of('partner.client_data.masked', 'partner', $partner->id, ['partner_code' => $partner->code], $partner->organization_id));

        return true;
    }
    // ── end TASK-0040 ──

    // ── helpers ──────────────────────────────────────────────────────────────

    private function clientQuery(Partner $partner)
    {
        return Organization::query()->where('partner_organization_id', $partner->organization_id);
    }

    private function code(Organization $organization): string
    {
        $base = strtoupper(Str::slug(Str::limit($organization->slug ?? $organization->name, 6, ''), ''));
        $base = $base !== '' ? $base : 'ONH';
        for ($i = 0; $i < 20; $i++) {
            $code = $base.'-'.strtoupper(Str::random(4));
            if (! Partner::query()->where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new DomainError('partner_code_conflict', 'Could not allocate a partner code.', 500);
    }

    /**
     * A partner's number decides the VAT of its self-billing documents (review round 2, D31.6): when an organization applies
     * or is approved, a number no check has spoken about is asked about on the queue — as the system, like a number the
     * customer has just given. Existing partners are reached by the operator's command only (owner rule).
     */
    private function queueVatCheck(Organization $organization): void
    {
        if (! (bool) config('onhost.vies.enabled', false) || ! VatStanding::payerUnverified($organization) || ! VatNumberChecks::withinBudget($organization)) {
            return;
        }
        CheckVatNumber::dispatch($organization->id)->afterCommit();
    }
}
