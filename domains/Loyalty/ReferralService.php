<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Risk\RiskWeights;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/**
 * Referral programme on the loyalty rails (audit §5j-2): every organization has a personal invite code; a new
 * organization registered with it is bound to the referrer for good, and when it pays its first tax document both
 * sides earn points and promo credit — once. Fraud scoring (§5l-4): every settlement is scored from signals (the same
 * e-mail domain, the same registration address, held or rejected orders, rapid or piled-up invites, a chargeback, a
 * referrer with refused history); a score at or above the refuse mark is refused with the strongest signal as the
 * reason, at or above the hold mark it waits for finance, and staff decisions teach the weights — a release lightens
 * the signals that held it, a reject makes them heavier; a chargeback of a rewarded referral claws it back and does
 * the same. Weights live in system settings; nothing here can reduce a customer's credit.
 */
final class ReferralService
{
    public const FREE_MAIL = ['gmail.com', 'seznam.cz', 'email.cz', 'centrum.cz', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com', 'proton.me', 'protonmail.com', 'volny.cz', 'post.cz', 'atlas.cz', 'tiscali.cz', 'azet.sk', 'zoznam.sk'];

    public const WEIGHTS_SETTING = RiskWeights::SETTING; // §5n-4: one table for both loops

    /** signal => default weight (points); the sum is capped at 100 */
    public const WEIGHTS = ['same_email_domain' => 100, 'risk_hold' => 100, 'chargeback' => 100, 'same_address' => 60, 'disposable_email' => 50, 'referrer_risk' => 40, 'refused_history' => 30, 'rapid_signup' => 25, 'many_pending' => 20];

    public const HOLD_SCORE = 60;

    public const REFUSE_SCORE = 100;

    public function __construct(private readonly LoyaltyService $loyalty, private readonly WalletService $wallets, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox, private readonly SettingsStore $settings, private readonly RiskWeights $risk) {}

    /** The organization's invite code; created on first use and never changed afterwards. */
    public function code(Organization $organization): string
    {
        if ($organization->referral_code) {
            return $organization->referral_code;
        }
        $base = preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii(mb_substr((string) $organization->slug, 0, 6)))) ?: 'ONH';
        for ($i = 0; $i < 20; $i++) {
            $code = $base.'-'.strtoupper(Str::random(4));
            if (! Organization::query()->where('referral_code', $code)->exists()) {
                $organization->forceFill(['referral_code' => $code])->save();

                return $code;
            }
        }
        throw new DomainError('referral_code_unavailable', 'Could not allocate an invite code; try again.', 503);
    }

    /** @return array<string,mixed> the account page: link, rules, invited organizations and what they earned */
    public function summary(Organization $organization): array
    {
        $rules = $this->rules();
        $rows = Referral::query()->where('referrer_organization_id', $organization->id)->orderByDesc('created_at')->limit(50)->get();
        $names = Organization::query()->whereIn('id', $rows->pluck('referred_organization_id'))->pluck('name', 'id');
        $currency = (string) ($organization->currency ?: config('onhost.loyalty.reward_currency', 'CZK'));

        return [
            'code' => $this->code($organization),
            'link' => rtrim((string) config('onhost.portal_url'), '/').'/registrace?ref='.rawurlencode($this->code($organization)),
            'reward' => ['referrer_points' => $rules['referrer_points'], 'referred_points' => $rules['referred_points'], 'referrer_credit' => Money::minor($rules['referrer_credit_minor'], $currency), 'referred_credit' => Money::minor($rules['referred_credit_minor'], $currency)],
            'counts' => ['pending' => $rows->whereIn('state', [Referral::PENDING, Referral::HELD])->count(), 'rewarded' => $rows->where('state', Referral::REWARDED)->count(), 'refused' => $rows->whereIn('state', [Referral::REFUSED, Referral::CLAWBACK])->count(), 'monthly_cap' => $rules['max_per_30d']],
            'referrals' => $rows->map(fn (Referral $r) => ['id' => $r->id, 'organization' => $this->mask((string) ($names[$r->referred_organization_id] ?? '')), 'state' => $r->state === Referral::HELD ? Referral::PENDING : $r->state, 'reason' => $r->state === Referral::HELD ? null : $r->reason, 'registered_at' => $r->created_at?->toIso8601String(), 'rewarded_at' => $r->rewarded_at?->toIso8601String()])->values()->all(),
        ];
    }

    /** Binds a freshly registered organization to the code's owner; an unknown or own code is ignored (registration never fails on it). */
    public function attach(Organization $referred, ?string $code, ?string $ip, CommandContext $context): ?Referral
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $referrer = Organization::query()->where('referral_code', $code)->first();
        if ($referrer === null || $referrer->id === $referred->id || Referral::query()->where('referred_organization_id', $referred->id)->exists()) {
            return null;
        }
        $referral = Referral::query()->create(['referrer_organization_id' => $referrer->id, 'referred_organization_id' => $referred->id, 'code' => $code, 'state' => Referral::PENDING, 'registration_ip' => $ip !== null ? mb_substr($ip, 0, 45) : null]);
        $this->audit->record($context->withScope($referred->id), 'referral.attach', 'succeeded', ['referrer' => $referrer->id, 'code' => $code], 'referral', $referral->id);
        $this->outbox->publish(GenericEvent::of('referral.registered', 'referral', $referral->id, ['referred' => $referred->name, 'code' => $code], $referrer->id));

        return $referral;
    }

    /** `invoice.paid` of the referred organization: the first paid tax document settles the referral — reward, hold for finance, or refuse. */
    public function onInvoicePaid(Invoice $invoice): ?Referral
    {
        if (! in_array($invoice->type, ['invoice', 'receipt'], true) || (int) $invoice->total_minor <= 0) {
            return null;
        }
        $referral = Referral::query()->where('referred_organization_id', $invoice->organization_id)->where('state', Referral::PENDING)->first();
        if ($referral === null) {
            return null;
        }
        $referred = Organization::query()->find($referral->referred_organization_id);
        $referrer = Organization::query()->find($referral->referrer_organization_id);
        if ($referred === null || $referrer === null) {
            return $this->refuse($referral, 'organization_missing');
        }
        if (data_get($referrer->feature_flags, 'sandbox') || data_get($referred->feature_flags, 'sandbox')) {
            return $this->refuse($referral, 'sandbox');
        }
        $recent = Referral::query()->where('referrer_organization_id', $referrer->id)->where('state', Referral::REWARDED)->where('rewarded_at', '>=', now()->subDays(30))->count();
        if ($recent >= $this->rules()['max_per_30d']) {
            return $this->refuse($referral, 'monthly_cap');
        }
        $assessment = $this->assess($referral, $referrer, $referred);
        $referral->forceFill(['score' => $assessment['score'], 'signals' => $assessment['signals']])->save();
        if ($assessment['score'] >= self::REFUSE_SCORE) {
            return $this->refuse($referral, (string) ($assessment['signals'][0] ?? 'score'));
        }
        if ($assessment['score'] >= self::HOLD_SCORE) {
            $referral->forceFill(['state' => Referral::HELD])->save();
            $this->audit->record(CommandContext::system('referral')->withScope($referrer->id), 'referral.hold', 'succeeded', ['referral' => $referral->id, 'score' => $assessment['score'], 'signals' => $assessment['signals']], 'referral', $referral->id);
            $this->outbox->publish(GenericEvent::of('referral.held', 'referral', $referral->id, ['score' => $assessment['score'], 'signals' => $assessment['signals'], 'referred' => $this->mask($referred->name), 'referred_organization_id' => $referred->id], $referrer->id));

            return $referral;
        }

        return $this->reward($referral, $referrer, $referred, $invoice->number);
    }

    /**
     * The fraud signals of a referral and their weighted score (0–100).
     *
     * @return array{score:int, signals:list<string>}
     */
    public function assess(Referral $referral, Organization $referrer, Organization $referred): array
    {
        $signals = [];
        if (Order::query()->where('organization_id', $referred->id)->where(fn ($q) => $q->where('meta->review->state', 'rejected')->orWhere('meta->review->state', 'pending'))->exists()) {
            $signals[] = 'risk_hold';
        }
        $referrerOwner = User::query()->find($referrer->owner_user_id);
        $referredOwner = User::query()->find($referred->owner_user_id);
        $domainOf = fn (?User $u) => strtolower((string) substr(strrchr((string) $u?->email, '@') ?: '', 1));
        $d1 = $domainOf($referrerOwner);
        if ($d1 !== '' && $d1 === $domainOf($referredOwner) && ! in_array($d1, self::FREE_MAIL, true)) {
            $signals[] = 'same_email_domain';
        }
        if ($domainOf($referredOwner) !== '' && in_array($domainOf($referredOwner), (array) config('onhost.orders.risk.disposable_domains', []), true)) { // §5n-4: the order loop's signal, shared weight
            $signals[] = 'disposable_email';
        }
        if ($referral->registration_ip !== null && Referral::query()->where('referrer_organization_id', $referrer->id)->whereKeyNot($referral->id)->where('registration_ip', $referral->registration_ip)->exists()) {
            $signals[] = 'same_address';
        }
        if (ChargebackRequest::query()->where('organization_id', $referred->id)->whereIn('state', [ChargebackRequest::APPROVED, ChargebackRequest::CANCELLING, ChargebackRequest::REFUNDED])->exists()) {
            $signals[] = 'chargeback';
        }
        if (Referral::query()->where('referrer_organization_id', $referrer->id)->whereIn('state', [Referral::REFUSED, Referral::CLAWBACK])->count() >= 2) {
            $signals[] = 'refused_history';
        }
        $previous = Referral::query()->where('referrer_organization_id', $referrer->id)->where('id', '<', $referral->id)->where('created_at', '>=', $referral->created_at->copy()->subHour())->exists(); // an earlier invite (ULIDs order in time) inside the hour
        if ($previous) {
            $signals[] = 'rapid_signup';
        }
        if (Referral::query()->where('referrer_organization_id', $referrer->id)->whereIn('state', [Referral::PENDING, Referral::HELD])->count() >= 5) {
            $signals[] = 'many_pending';
        }
        if (Order::query()->where('organization_id', $referrer->id)->where('meta->review->state', 'rejected')->where('placed_at', '>=', now()->subDays(180))->exists()) { // §5m-4: the order loop's verdict on the referrer
            $signals[] = 'referrer_risk';
        }
        $weights = $this->weights();
        usort($signals, fn ($a, $b) => ($weights[$b] ?? 0) <=> ($weights[$a] ?? 0));
        $score = min(100, array_sum(array_map(fn ($s) => (int) ($weights[$s] ?? 0), $signals)));

        return ['score' => $score, 'signals' => $signals];
    }

    /** Finance decides a held referral: `release` rewards it, `reject` refuses it; both teach the weights. */
    public function review(Referral $referral, string $decision, CommandContext $context, ?string $note = null): Referral
    {
        if ($referral->state !== Referral::HELD) {
            throw new DomainError('referral_not_held', 'Only a held referral can be reviewed.', 409, ['state' => $referral->state]);
        }
        if (! in_array($decision, ['release', 'reject'], true)) {
            throw new DomainError('referral_decision_invalid', 'Decision must be release or reject.', 422, ['field' => 'decision']);
        }
        $weights = $this->learn((array) ($referral->signals ?? []), $decision);
        $referral->forceFill(['decided_by' => $context->actorId, 'decided_at' => now()])->save();
        $this->audit->record($context->withScope($referral->referrer_organization_id), 'referral.review', 'succeeded', ['referral' => $referral->id, 'decision' => $decision, 'note' => $note, 'weights' => array_intersect_key($weights, array_flip((array) ($referral->signals ?? [])))], 'referral', $referral->id);
        if ($decision === 'reject') {
            return $this->refuse($referral, 'staff'.($note !== null && $note !== '' ? ': '.mb_substr($note, 0, 100) : ''));
        }
        $referrer = Organization::query()->find($referral->referrer_organization_id);
        $referred = Organization::query()->find($referral->referred_organization_id);
        if ($referrer === null || $referred === null) {
            return $this->refuse($referral, 'organization_missing');
        }

        return $this->reward($referral, $referrer, $referred, 'review');
    }

    /** A chargeback of a rewarded referral within the clawback window marks it and makes its signals heavier. */
    public function onChargeback(string $organizationId): ?Referral
    {
        $referral = Referral::query()->where('referred_organization_id', $organizationId)->where('state', Referral::REWARDED)->where('rewarded_at', '>=', now()->subDays(max(1, (int) config('onhost.loyalty.referral.clawback_days', 90))))->first();
        if ($referral === null) {
            return null;
        }
        $this->learn(array_values(array_unique(array_merge((array) ($referral->signals ?? []), ['chargeback']))), 'reject', 10);
        $referral->forceFill(['state' => Referral::CLAWBACK, 'reason' => 'chargeback'])->save();
        $ctx = CommandContext::system('referral');
        $this->audit->record($ctx->withScope($referral->referrer_organization_id), 'referral.clawback', 'succeeded', ['referral' => $referral->id, 'referred' => $organizationId], 'referral', $referral->id);
        $this->outbox->publish(GenericEvent::of('referral.clawback', 'referral', $referral->id, ['referred_organization_id' => $organizationId, 'signals' => $referral->signals], $referral->referrer_organization_id));

        return $referral;
    }

    /** @return array<string,int> */
    public function weights(): array
    {
        return $this->risk->weights(self::WEIGHTS); // §5n-4: the shared table, this loop's signals
    }

    /** @param  list<string>  $signals @return array<string,int> */
    public function learn(array $signals, string $decision, int $step = 5, bool $cross = true): array
    {
        $signals = array_values(array_filter(array_map('strval', $signals), fn ($s) => array_key_exists($s, self::WEIGHTS)));
        if ($cross && $decision === 'reject' && array_intersect($signals, ['risk_hold', 'referrer_risk']) !== []) { // §5m-4: the order loop's `referral_flagged` learns from a rejected referral that carried an order signal
            $signals[] = 'referral_flagged';
        }
        $this->risk->learn($signals, $decision, $step, 'referral.learn'); // §5n-4: one table learns from both loops

        return $this->weights();
    }

    /** @return array{referrer_points:int, referred_points:int, referrer_credit_minor:int, referred_credit_minor:int, max_per_30d:int} */
    public function rules(): array
    {
        $cfg = (array) config('onhost.loyalty.referral', []);

        return [
            'referrer_points' => max(0, (int) ($cfg['referrer_points'] ?? 200)), 'referred_points' => max(0, (int) ($cfg['referred_points'] ?? 100)),
            'referrer_credit_minor' => max(0, (int) ($cfg['referrer_credit_minor'] ?? 20000)), 'referred_credit_minor' => max(0, (int) ($cfg['referred_credit_minor'] ?? 10000)),
            'max_per_30d' => max(1, (int) ($cfg['max_per_30d'] ?? 20)),
        ];
    }

    /** @return array<string,mixed> the staff row */
    public function present(Referral $referral): array
    {
        $names = Organization::query()->whereIn('id', [$referral->referrer_organization_id, $referral->referred_organization_id])->pluck('name', 'id');

        return [
            'id' => $referral->id, 'state' => $referral->state, 'score' => (int) $referral->score, 'signals' => (array) ($referral->signals ?? []), 'reason' => $referral->reason, 'code' => $referral->code,
            'referrer' => ['id' => $referral->referrer_organization_id, 'name' => $names[$referral->referrer_organization_id] ?? null], 'referred' => ['id' => $referral->referred_organization_id, 'name' => $names[$referral->referred_organization_id] ?? null],
            'registered_at' => $referral->created_at?->toIso8601String(), 'rewarded_at' => $referral->rewarded_at?->toIso8601String(), 'decided_at' => $referral->decided_at?->toIso8601String(), 'decided_by' => $referral->decided_by,
        ];
    }

    private function reward(Referral $referral, Organization $referrer, Organization $referred, string $reference): Referral
    {
        $rules = $this->rules();
        $ctx = CommandContext::system('referral');
        $currencyOf = fn (Organization $o) => (string) ($o->currency ?: config('onhost.loyalty.reward_currency', 'CZK'));
        $this->loyalty->award($referrer->id, 'referral', $referral->id, $rules['referrer_points'], 'Doporučení: '.$this->mask($referred->name), $ctx);
        $this->loyalty->badge($referrer->id, 'ambassador', $ctx);
        $this->loyalty->award($referred->id, 'referral.welcome', $referral->id, $rules['referred_points'], 'Přišli jste na doporučení', $ctx);
        if ($rules['referrer_credit_minor'] > 0) {
            $this->wallets->topup($referrer->id, Money::minor($rules['referrer_credit_minor'], $currencyOf($referrer)), 'promo', "referral:{$referral->id}:referrer", $ctx->withScope($referrer->id), null, 'Odměna za doporučení', true);
        }
        if ($rules['referred_credit_minor'] > 0) {
            $this->wallets->topup($referred->id, Money::minor($rules['referred_credit_minor'], $currencyOf($referred)), 'promo', "referral:{$referral->id}:referred", $ctx->withScope($referred->id), null, 'Uvítací kredit za doporučení', true);
        }
        $referral->forceFill(['state' => Referral::REWARDED, 'rewarded_at' => now()])->save();
        $this->audit->record($ctx->withScope($referrer->id), 'referral.reward', 'succeeded', ['referral' => $referral->id, 'referred' => $referred->id, 'reference' => $reference, 'score' => $referral->score], 'referral', $referral->id);
        $this->outbox->publish(GenericEvent::of('referral.rewarded', 'referral', $referral->id, ['referred' => $this->mask($referred->name), 'points' => $rules['referrer_points'], 'credit' => Money::minor($rules['referrer_credit_minor'], $currencyOf($referrer))], $referrer->id));
        $this->outbox->publish(GenericEvent::of('referral.welcomed', 'referral', $referral->id, ['points' => $rules['referred_points'], 'credit' => Money::minor($rules['referred_credit_minor'], $currencyOf($referred))], $referred->id));

        return $referral;
    }

    private function refuse(Referral $referral, string $reason): Referral
    {
        $referral->forceFill(['state' => Referral::REFUSED, 'reason' => mb_substr($reason, 0, 120)])->save();
        $this->audit->record(CommandContext::system('referral')->withScope($referral->referrer_organization_id), 'referral.refuse', 'succeeded', ['referral' => $referral->id, 'reason' => $reason], 'referral', $referral->id);
        $this->outbox->publish(GenericEvent::of('referral.refused', 'referral', $referral->id, ['reason' => $reason, 'referred_organization_id' => $referral->referred_organization_id], $referral->referrer_organization_id));

        return $referral;
    }

    /** Referrers see who came, but not the whole name of another customer. */
    private function mask(string $name): string
    {
        $name = trim($name);

        return $name === '' ? '—' : mb_substr($name, 0, 2).str_repeat('·', max(1, min(6, mb_strlen($name) - 2)));
    }
}
