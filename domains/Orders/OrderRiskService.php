<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Risk\RiskWeights;
use Onhost\Domain\Risk\Turnstile;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Contracts\IpGeoProvider;

/**
 * Order intake pre-check (audit §5f-8): a handful of signals the platform already has — a brand-new account, a
 * disposable or free mailbox behind a company, several orders in minutes, a large first order, failed payments
 * behind it, a VAT id from another country, an address from another country than the customer claims (§5g-4) —
 * add up to a score. Below the hold threshold nothing changes; above it the order is still placed and paid but its
 * fulfilment waits for a staff decision (release or reject), so a fraudulent card or a stolen account never gets a
 * server provisioned in the ninety seconds before anyone looks. Automatic orders (plan upgrades the platform itself
 * places) are never scored. Staff decisions feed back (§5g-4): every release lowers the weight of the signals that
 * held the order, every reject raises them, within bounds — the check learns what this customer base looks like.
 */
final class OrderRiskService
{
    public const HOLD_SCORE = 60;

    public const WEIGHTS_SETTING = RiskWeights::SETTING; // §5n-4: one table for both loops

    public const FEEDBACK_SETTING = RiskWeights::FEEDBACK_SETTING;

    public const HOLD_SETTING = 'orders.risk.hold_score';

    /** Default weight of every signal; staff feedback moves them between MIN_WEIGHT and MAX_WEIGHT. */
    public const WEIGHTS = ['referral_flagged' => 40, 'new_account' => 25, 'disposable_email' => 50, 'free_mail_company' => 10, 'rapid_orders' => 30, 'large_first_order' => 25, 'failed_payments' => 30, 'vat_country_mismatch' => 15, 'ip_country_mismatch' => 20, 'turnstile_failed' => 35];

    public const MIN_WEIGHT = 5;

    public const MAX_WEIGHT = 100;

    public function __construct(private readonly AutomationLedger $ledger, private readonly SettingsStore $settings, private readonly IpGeoProvider $geo, private readonly RiskWeights $risk, private readonly Turnstile $turnstile) {}

    /** The weights in force: the defaults moved by staff feedback. @return array<string,int> */
    public function weights(): array
    {
        return $this->risk->weights(self::WEIGHTS); // §5n-4: the shared table, this loop's signals
    }

    /** @return array{score:int, reasons:list<string>, hold:bool} */
    public function assess(Quote $quote, Organization $organization, ?User $user, CommandContext $context, string $source = 'web'): array
    {
        if ($source === 'auto' || ! (bool) config('onhost.orders.risk.enabled', true) || ! $this->ledger->enabled('order.risk')) {
            return ['score' => 0, 'reasons' => [], 'hold' => false];
        }
        $w = $this->weights();
        $now = CarbonImmutable::now();
        $score = 0;
        $reasons = [];
        $email = strtolower((string) ($user?->email ?: $organization->billing_email ?: ''));
        $domain = str_contains($email, '@') ? substr($email, strrpos($email, '@') + 1) : '';
        $paidBefore = Order::query()->where('organization_id', $organization->id)->whereNotNull('paid_at')->exists();
        $orgAgeHours = $organization->created_at ? $organization->created_at->diffInHours($now) : 0;
        $country = strtoupper((string) $organization->country);

        if (! $paidBefore && $orgAgeHours < 24) {
            $score += $w['new_account'];
            $reasons[] = 'new_account';
        }
        if ($domain !== '' && in_array($domain, (array) config('onhost.orders.risk.disposable_domains', []), true)) {
            $score += $w['disposable_email'];
            $reasons[] = 'disposable_email';
        }
        if ($domain !== '' && $organization->type === 'company' && in_array($domain, (array) config('onhost.orders.risk.free_mail_domains', []), true)) {
            $score += $w['free_mail_company'];
            $reasons[] = 'free_mail_company';
        }
        $recent = Order::query()->where('organization_id', $organization->id)->where('placed_at', '>=', $now->subMinutes((int) config('onhost.orders.risk.rapid_window_minutes', 15)))->count();
        if ($recent >= (int) config('onhost.orders.risk.rapid_orders', 3)) {
            $score += $w['rapid_orders'];
            $reasons[] = 'rapid_orders';
        }
        $limit = (int) config('onhost.orders.risk.first_order_limit_minor.'.$quote->currency, 0);
        if (! $paidBefore && $limit > 0 && (int) $quote->total_minor >= $limit) {
            $score += $w['large_first_order'];
            $reasons[] = 'large_first_order';
        }
        $failedPayments = PaymentIntent::query()->where('organization_id', $organization->id)->whereIn('state', ['FAILED', 'CANCELED'])->where('created_at', '>=', $now->subDay())->count();
        if ($failedPayments >= 2) {
            $score += $w['failed_payments'];
            $reasons[] = 'failed_payments';
        }
        $vat = strtoupper(trim((string) $organization->vat_id));
        if ($vat !== '' && preg_match('/^[A-Z]{2}/', $vat) === 1 && substr($vat, 0, 2) !== $country && ! (substr($vat, 0, 2) === 'EL' && $country === 'GR')) {
            $score += $w['vat_country_mismatch'];
            $reasons[] = 'vat_country_mismatch';
        }
        $ipCountry = $country !== '' && $context->ip ? $this->geo->country((string) $context->ip) : null;
        if ($ipCountry !== null && $ipCountry !== $country) {
            $score += $w['ip_country_mismatch'];
            $reasons[] = 'ip_country_mismatch';
        }

        // §5q-6: a checkout without a Turnstile token, or with one the verifier refused, is a signal — never a refusal
        if ($this->turnstile->enabled() && in_array(Turnstile::result(), [Turnstile::FAIL, Turnstile::MISSING], true)) {
            $score += $w['turnstile_failed'];
            $reasons[] = 'turnstile_failed';
        }

        // §5m-4: the referral loop's verdict on this organization is a signal here — a held, refused or clawed-back referral means somebody already doubted it
        if (Referral::query()->where('referred_organization_id', $organization->id)->whereIn('state', ['held', 'refused', 'clawback'])->exists()) {
            $score += $w['referral_flagged'];
            $reasons[] = 'referral_flagged';
        }

        return ['score' => $score, 'reasons' => $reasons, 'hold' => $score >= $this->holdScore()];
    }

    /** The hold threshold in force: what staff set in the console, else the configured default. */
    public function holdScore(): int
    {
        $set = $this->settings->get(self::HOLD_SETTING);

        return max(10, min(300, (int) ($set !== null ? $set : config('onhost.orders.risk.hold_score', self::HOLD_SCORE))));
    }

    /**
     * Staff tune the check from the console (audit §5h-4): weights per signal within bounds, the hold threshold, or a
     * reset to the defaults (which also forgets the feedback counters).
     *
     * @param  array<string,int|string>  $weights
     * @return array{weights:array<string,int>, hold_score:int, defaults:array<string,int>, feedback:array<string,array{released:int,rejected:int}>}
     */
    public function tune(array $weights, ?int $holdScore, bool $reset, ?string $by = null): array
    {
        if ($reset) {
            $this->risk->reset();
            $this->settings->forget(self::HOLD_SETTING);

            return $this->tuning();
        }
        $this->risk->set($weights, $by); // any signal of either loop (§5n-4)
        if ($holdScore !== null) {
            $this->settings->set(self::HOLD_SETTING, max(10, min(300, $holdScore)), $by);
        }

        return $this->tuning();
    }

    /** @return array{weights:array<string,int>, hold_score:int, defaults:array<string,int>, feedback:array<string,array{released:int,rejected:int}>} */
    public function tuning(): array
    {
        return ['weights' => $this->weights(), 'hold_score' => $this->holdScore(), 'defaults' => self::WEIGHTS, 'feedback' => $this->feedback(), 'shared' => $this->risk->weights(), 'referral' => ['hold_score' => ReferralService::HOLD_SCORE, 'refuse_score' => ReferralService::REFUSE_SCORE]]; // §5n-4: the whole table next to this loop's slice
    }

    /**
     * Staff decided about a held order (§5g-4): the signals that held it get lighter after a release and heavier after
     * a reject, one step per decision, within bounds. Returns the weights now in force.
     *
     * @return array<string,int>
     */
    public function learn(Order $order, string $decision, ?string $by = null): array
    {
        $reasons = array_values(array_filter(array_map('strval', (array) data_get($order->meta, 'risk.reasons', [])), fn ($r) => array_key_exists($r, self::WEIGHTS)));
        if ($reasons === [] || ! in_array($decision, ['release', 'reject'], true)) {
            return $this->weights();
        }
        $signals = $reasons;
        if ($decision === 'reject' && Referral::query()->where('referrer_organization_id', $order->organization_id)->exists()) { // §5m-4: a referrer whose own order was rejected makes `referrer_risk` heavier in the referral loop
            $signals[] = 'referrer_risk';
        }
        $this->risk->learn($signals, $decision, max(0, (int) config('onhost.orders.risk.feedback_step', 5)), $by); // §5n-4: one table learns from both loops

        return $this->weights();
    }

    /** What staff taught the check so far. @return array<string,array{released:int,rejected:int}> */
    public function feedback(): array
    {
        return array_intersect_key($this->risk->feedback(), self::WEIGHTS);
    }
}
