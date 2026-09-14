<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentMethod;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Contracts\PaymentProvider;
use Onhost\Providers\Contracts\StoredMethodCharging;
use Throwable;

/**
 * Automatic top-ups (audit §5e-2): the customer opts in with a threshold, an amount, a daily cap and a monthly limit;
 * when the renewal guard finds the credit short, the platform charges the stored payment method through a provider
 * that supports it (`StoredMethodCharging`) and books the credit through the ordinary payment path. Until such a
 * provider is live the attempt reports `unsupported` and the customer is asked to top up by hand — the setting,
 * the limits and the audit trail are the same either way.
 */
final class AutoTopup
{
    public function __construct(private readonly PaymentProviderRegistry $providers, private readonly PaymentService $payments, private readonly AuditRecorder $audit) {}

    /** @return array{enabled:bool, threshold:?Money, amount:?Money, max_per_day:int, monthly_limit:?Money, payment_method_id:?string, provider:?string, last_triggered_at:?string, consecutive_failures:int, supported:bool} */
    public function settings(Organization $organization): array
    {
        $setting = AutoTopupSetting::query()->where('organization_id', $organization->id)->first();
        $currency = (string) $organization->currency;

        return [
            'enabled' => (bool) ($setting?->enabled ?? false),
            'threshold' => $setting ? Money::minor((int) $setting->threshold_minor, $currency) : null,
            'amount' => $setting ? Money::minor((int) $setting->amount_minor, $currency) : null,
            'max_per_day' => (int) ($setting?->max_per_day ?? 2),
            'monthly_limit' => $setting ? Money::minor((int) $setting->monthly_limit_minor, $currency) : null,
            'payment_method_id' => $setting?->payment_method_id,
            'last_triggered_at' => $setting?->last_triggered_at?->toIso8601String(),
            'consecutive_failures' => (int) ($setting?->consecutive_failures ?? 0),
            'supported' => $this->supported(),
        ];
    }

    /** @param  array{enabled?:bool, threshold?:string|int|float, amount?:string|int|float, max_per_day?:int, monthly_limit?:string|int|float, payment_method_id?:?string, provider?:?string}  $input */
    public function configure(Organization $organization, array $input, CommandContext $context): array
    {
        $currency = (string) $organization->currency;
        $min = Money::decimal((string) config('onhost.billing.min_topup.'.$currency, '100'), $currency);
        $setting = AutoTopupSetting::query()->firstOrNew(['organization_id' => $organization->id]);
        $amount = isset($input['amount']) ? Money::decimal((string) $input['amount'], $currency) : Money::minor((int) ($setting->amount_minor ?? $min->minor * 5), $currency);
        $threshold = isset($input['threshold']) ? Money::decimal((string) $input['threshold'], $currency) : Money::minor((int) ($setting->threshold_minor ?? $min->minor * 2), $currency);
        $monthly = isset($input['monthly_limit']) ? Money::decimal((string) $input['monthly_limit'], $currency) : Money::minor((int) ($setting->monthly_limit_minor ?? $amount->minor * 4), $currency);
        $enabled = (bool) ($input['enabled'] ?? $setting->enabled ?? false);
        if ($enabled && $amount->lessThan($min)) {
            throw new DomainError('auto_topup_amount_too_small', "The automatic top-up must be at least {$min->format()}.", 422, ['field' => 'amount']);
        }
        if ($enabled && $monthly->lessThan($amount)) {
            throw new DomainError('auto_topup_limit_too_small', 'The monthly limit must cover at least one top-up.', 422, ['field' => 'monthly_limit']);
        }
        $setting->forceFill([
            'wallet_id' => $setting->wallet_id ?? 'wal_'.$organization->id, 'enabled' => $enabled, 'threshold_minor' => $threshold->minor, 'amount_minor' => $amount->minor,
            'max_per_day' => max(1, min(10, (int) ($input['max_per_day'] ?? $setting->max_per_day ?? 2))), 'monthly_limit_minor' => $monthly->minor,
            // the stored card to charge: the one given, else the one already set, else the organization's default stored method (audit §5f-1)
            'payment_method_id' => array_key_exists('payment_method_id', $input) ? ($input['payment_method_id'] ?: null) : ($setting->payment_method_id ?? PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->orderByDesc('is_default')->orderBy('created_at')->value('id')),
            'consecutive_failures' => $enabled ? (int) ($setting->consecutive_failures ?? 0) : 0,
        ])->save();
        $this->audit->record($context->withScope($organization->id), 'wallet.auto_topup', 'succeeded', ['enabled' => $enabled, 'threshold' => $threshold->minor, 'amount' => $amount->minor, 'monthly_limit' => $monthly->minor], 'organization', $organization->id);

        return $this->settings($organization);
    }

    /**
     * Charges the stored method when the setting allows it. Never throws: the renewal guard reports the outcome.
     *
     * @return array{status:string, amount?:Money, payment_intent_id?:string, reason?:string}
     */
    public function attempt(Organization $organization, Money $shortfall, CommandContext $context): array
    {
        $setting = AutoTopupSetting::query()->where('organization_id', $organization->id)->where('enabled', true)->first();
        if ($setting === null) {
            return ['status' => 'disabled'];
        }
        $currency = (string) $organization->currency;
        $amount = Money::minor(max((int) $setting->amount_minor, $shortfall->minor), $currency);
        if ($setting->last_triggered_at !== null && $setting->last_triggered_at->isToday() && $this->triggeredToday($setting) >= (int) $setting->max_per_day) {
            return ['status' => 'limited', 'reason' => 'daily cap reached'];
        }
        if ($setting->payment_method_id === null || ! $this->supported()) {
            return ['status' => 'unsupported', 'reason' => $setting->payment_method_id === null ? 'no stored payment method' : 'no provider charges stored methods yet'];
        }
        try {
            $method = PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->whereKey((string) $setting->payment_method_id)->first();
            $provider = $this->storedMethodProvider($method?->provider); // the gateway that issued the token is the only one that can charge it
            if ($provider === null || $method === null) {
                return ['status' => 'unsupported', 'reason' => $method === null ? 'the stored payment method was removed' : 'no provider charges stored methods yet'];
            }
            // the provider charges the stored method inside createPaymentIntent (method "stored"); the intent settles through the usual status/webhook path
            $intent = $this->payments->createIntent($organization, $amount, 'topup', 'wallet', $organization->id, $context, [
                'provider' => $provider::providerKey(), 'method' => 'stored', 'stored_method_id' => (string) $setting->payment_method_id,
                'description' => "Automatické dobití kreditu {$organization->name}", 'idempotency_key' => 'auto-topup:'.$organization->id.':'.now()->format('YmdH'),
            ]);
            $setting->forceFill(['last_triggered_at' => now(), 'consecutive_failures' => 0])->save();
            $this->audit->record($context->withScope($organization->id), 'wallet.auto_topup.charge', 'succeeded', ['amount' => $amount->minor, 'intent' => $intent->id], 'organization', $organization->id);

            return ['status' => 'charged', 'amount' => $amount, 'payment_intent_id' => $intent->id];
        } catch (Throwable $e) {
            $setting->forceFill(['last_triggered_at' => now(), 'consecutive_failures' => (int) $setting->consecutive_failures + 1])->save();
            $this->audit->record($context->withScope($organization->id), 'wallet.auto_topup.charge', 'failed', ['amount' => $amount->minor, 'error' => mb_substr($e->getMessage(), 0, 300)], 'organization', $organization->id);

            return ['status' => 'failed', 'reason' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /** Whether any configured payment provider can charge a stored method. */
    public function supported(): bool
    {
        return $this->storedMethodProvider() !== null;
    }

    /** The provider named (the stored method's gateway) when it charges stored methods, otherwise the first one that does. @return (PaymentProvider&StoredMethodCharging)|null */
    private function storedMethodProvider(?string $preferred = null): ?PaymentProvider
    {
        $keys = $this->providers->keys();
        if ($preferred !== null) {
            $keys = in_array($preferred, $keys, true) ? [$preferred] : [];
        }
        foreach ($keys as $key) {
            try {
                $provider = $this->providers->get($key);
            } catch (Throwable) {
                continue; // not configured in this environment
            }
            if ($provider instanceof StoredMethodCharging && $provider->storedMethodsAvailable()) {
                return $provider;
            }
        }

        return null;
    }

    private function triggeredToday(AutoTopupSetting $setting): int
    {
        return (int) ($setting->last_triggered_at?->isToday() ? 1 : 0); // one row per organization: the cap is enforced per trigger time
    }
}
