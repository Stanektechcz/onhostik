<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Events\PaymentFailed;
use Onhost\Domain\Payments\Events\PaymentSucceeded;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentEvent;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentMethod;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Domain\Payments\Models\ReconciliationItem;
use Onhost\Domain\Payments\Models\ReconciliationRun;
use Onhost\Domain\Payments\Models\Settlement;
use Onhost\Domain\Payments\Models\SettlementItem;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Domain\WalletLedger\Models\WalletRefund;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Providers\Contracts\StoredMethodCharging;
use Throwable;

/**
 * Payment orchestration (§63): intents, verified callbacks, dedupe, settlement to
 * the wallet, refunds, daily reconciliation against provider settlements and bank
 * statements. Every successful payment becomes a wallet top-up + receipt; orders
 * and invoices are settled by listeners of PaymentSucceeded.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly WalletService $wallets,
        private readonly InvoiceService $invoices,
        private readonly Dispatcher $events,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly Redactor $redactor,
    ) {}

    /** @param array{provider?:?string,method?:?string,return_urls?:array<string,string>,description?:string,email?:?string,reference?:?string,idempotency_key?:string} $options */
    public function createIntent(Organization $organization, Money $amount, string $purpose, ?string $referenceType, ?string $referenceId, CommandContext $context, array $options = []): PaymentIntent
    {
        if (! $amount->isPositive()) {
            throw new DomainError('invalid_amount', 'Payment amount must be positive.');
        }
        $providerKey = $options['provider'] ?? null;
        $provider = $this->providers->get($providerKey);
        // One live intent per purpose/reference/amount (orders, invoices). Purposes without a unique reference — wallet
        // top-ups reference the organization — pass their own per-attempt key, otherwise a second top-up of the same
        // amount would silently return the already-paid intent.
        $idempotencyKey = $options['idempotency_key'] ?? "{$purpose}:{$referenceType}:{$referenceId}:{$amount->minor}:{$amount->currency->value}";
        $existing = PaymentIntent::query()->where('idempotency_key', $idempotencyKey)->whereNotIn('state', [S::FAILED, S::CANCELED])->first();
        if ($existing !== null) {
            return $existing;
        }
        // stored methods (audit §5f-1): the customer's saved card is resolved here and its token handed to the provider; the flag to keep a new card rides on the intent
        $stored = null;
        if (! empty($options['stored_method_id'])) {
            $stored = PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->whereKey((string) $options['stored_method_id'])->first();
            if ($stored === null || $stored->provider !== $provider::providerKey() || ! $provider instanceof StoredMethodCharging) {
                throw new DomainError('payment_method_unknown', 'The stored payment method is not available for this provider.', 422, ['field' => 'stored_method_id']);
            }
        }
        $saveMethod = ! empty($options['save_method']) && $provider instanceof StoredMethodCharging;
        $intent = PaymentIntent::query()->create([
            'organization_id' => $organization->id,
            'provider' => $provider::providerKey(),
            'purpose' => $purpose,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'amount_minor' => $amount->minor,
            'currency' => $amount->currency->value,
            'state' => S::CREATED,
            'method' => $options['method'] ?? null,
            'return_urls' => ($options['return_urls'] ?? []) + ($saveMethod ? ['save_method' => true] : []),
            'idempotency_key' => $idempotencyKey,
            'created_by' => $context->actorType.':'.($context->actorId ?? 'system'),
        ]);
        $base = rtrim((string) config('onhost.portal_url'), '/');
        try {
            $result = $provider->createPaymentIntent($amount, [
                'reference' => $options['reference'] ?? $intent->id,
                'description' => $options['description'] ?? "ONhost {$purpose} {$intent->id}",
                'email' => (string) ($options['email'] ?? $organization->billing_email ?? ''),
                'stored_method_id' => $stored?->id, // automatic top-ups: a provider that keeps payment methods charges this one (StoredMethodCharging)
                'stored_method_token' => $stored?->provider_token,
                'save_method' => $saveMethod,
                'return_url' => $options['return_urls']['success'] ?? "{$base}/panel/fakturace?payment={$intent->id}&result=success",
                'cancel_url' => $options['return_urls']['cancel'] ?? "{$base}/panel/fakturace?payment={$intent->id}&result=cancel",
                'pending_url' => $options['return_urls']['pending'] ?? "{$base}/panel/fakturace?payment={$intent->id}&result=pending",
                'method' => $options['method'] ?? null,
                'locale' => $organization->locale,
                'country' => $organization->country,
                'idempotency_key' => $intent->id,
                'metadata' => ['intent' => $intent->id, 'organization' => $organization->id, 'purpose' => $purpose],
            ]);
        } catch (Throwable $e) {
            $intent->forceFill(['state' => S::FAILED, 'failure_reason' => mb_substr($this->redactor->redactString($e->getMessage()), 0, 250)])->save();
            throw new DomainError('payment_provider_error', 'The payment provider could not create the payment. Try again or choose another method.', 502);
        }
        $state = $this->normalizeState($result['state'], S::PENDING_CUSTOMER);
        $intent->forceFill([
            'provider_id' => $result['provider_id'],
            'redirect_url' => $result['redirect_url'],
            'state' => $state === S::SUCCEEDED ? S::PROCESSING : $state, // a charge the provider completed on the spot is booked below, never recorded as paid without the credit
            'raw' => $this->redactor->redact($result['raw']),
        ])->save();
        $this->audit->record($context->withScope($organization->id), 'payment.intent.create', 'succeeded', ['provider' => $intent->provider, 'purpose' => $purpose, 'amount' => $amount], 'payment_intent', $intent->id);
        if ($state === S::SUCCEEDED) { // stored cards charged off-session (Stripe, GoPay recurrence): the gateway answers "paid" at once — the status path would only confirm it
            $this->settle($intent, $context, ($options['method'] ?? null) === 'stored' ? 'card' : ($options['method'] ?? null));
            $intent->refresh();
        }

        return $intent;
    }

    /**
     * Provider callback. Verification -> dedupe -> status re-check -> transition.
     *
     * @return array{result:string, intent:?PaymentIntent}
     */
    public function handleWebhook(string $providerKey, Request $request): array
    {
        $provider = $this->providers->get($providerKey);
        $verified = $provider->verifyWebhook($request); // throws on invalid signature/source
        try {
            $event = DB::transaction(fn () => PaymentEvent::query()->create([ // savepoint: a duplicate delivery does not abort the surrounding transaction (PostgreSQL)
                'provider' => $providerKey,
                'event_id' => $verified['event_id'],
                'provider_id' => $verified['provider_id'],
                'type' => $verified['state'],
                'signature_ok' => true,
                'payload' => $this->redactor->redact($verified['raw']),
            ]));
        } catch (QueryException $e) {
            // unique (provider, event_id) — duplicate delivery: exactly one business effect (S51)
            return ['result' => 'duplicate', 'intent' => PaymentIntent::query()->where('provider', $providerKey)->where('provider_id', $verified['provider_id'])->first()];
        }
        $intent = PaymentIntent::query()->where('provider', $providerKey)->where('provider_id', $verified['provider_id'])->first();
        if ($intent === null) {
            $event->forceFill(['processed_at' => now(), 'result' => 'orphan'])->save();
            $this->outbox->publish(GenericEvent::of('payment.orphan_callback', 'payment', $verified['provider_id'], ['provider' => $providerKey]));

            return ['result' => 'orphan', 'intent' => null];
        }
        $event->forceFill(['payment_intent_id' => $intent->id])->save();
        $result = $this->syncFromProvider($intent, CommandContext::system("webhook:{$providerKey}"));
        $event->forceFill(['processed_at' => now(), 'result' => $result])->save();

        return ['result' => $result, 'intent' => $intent->refresh()];
    }

    /** Re-read provider status (source of truth) and apply it. Used by webhooks, return pages and reconciliation. */
    public function syncFromProvider(PaymentIntent $intent, CommandContext $context): string
    {
        $provider = $this->providers->get($intent->provider);
        $status = $provider->getPaymentStatus((string) $intent->provider_id);
        $target = $this->normalizeState($status['state'], $intent->state);
        if ($target === S::SUCCEEDED) {
            $paid = $status['amount'];
            if ($paid !== null && ! $paid->equals($intent->amount())) {
                $intent->forceFill(['raw' => array_merge($intent->raw ?? [], ['amount_mismatch' => $paid])])->save();
                $this->openReconciliationItem('payment_amount_mismatch', $intent->id, $intent->amount_minor, $paid->minor, 'Provider reported a different amount than the intent');

                return 'amount_mismatch';
            }
            if (! empty($intent->return_urls['save_method']) && ! $intent->isSucceeded()) {
                $intent->forceFill(['raw' => array_merge($intent->raw ?? [], ['status' => $this->redactor->redact((array) ($status['raw'] ?? []))])])->save(); // the token facts of a card to keep live in the status payload
            }

            return $this->settle($intent, $context, $status['method'] ?? $intent->method) ? 'settled' : 'already_settled';
        }
        if ($target === S::FAILED || $target === S::CANCELED) {
            if (! in_array($intent->state, [S::SUCCEEDED, S::REFUNDED, S::PARTIALLY_REFUNDED, S::FAILED, S::CANCELED], true)) {
                $intent->forceFill(['state' => $target, 'failure_reason' => $status['raw']['reason'] ?? $target])->save();
                $this->events->dispatch(new PaymentFailed($intent, (string) ($status['raw']['reason'] ?? $target)));
            }

            return strtolower($target);
        }
        if ($intent->state !== $target && S::machine()->canTransition($intent->state, $target)) {
            $intent->forceFill(['state' => $target])->save();
        }

        return strtolower($target);
    }

    /** Idempotent settlement: wallet top-up + receipt + PaymentSucceeded. Returns false if already settled. */
    public function settle(PaymentIntent $intent, CommandContext $context, ?string $method = null): bool
    {
        return DB::transaction(function () use ($intent, $context, $method) {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->isSucceeded()) {
                return false;
            }
            S::machine()->assertTransition($intent->state, S::SUCCEEDED);
            $intent->forceFill(['state' => S::SUCCEEDED, 'paid_at' => now(), 'method' => $method ?? $intent->method])->save();
            $organization = Organization::query()->findOrFail($intent->organization_id);
            $ctx = $context->withScope($organization->id);
            $this->wallets->topup($organization, $intent->amount(), $intent->method ?? 'card', "pi:{$intent->id}", $ctx, $intent->id, "Payment {$intent->provider} {$intent->provider_id}", bankProvider: $intent->provider, purpose: (string) ($intent->purpose ?: 'topup'));
            $this->invoices->issueReceipt($organization, $intent->amount(), $intent->method ?? $intent->provider, $ctx, $intent->id);
            $this->audit->record($ctx, 'payment.succeeded', 'succeeded', ['provider' => $intent->provider, 'provider_id' => $intent->provider_id, 'amount' => $intent->amount(), 'purpose' => $intent->purpose], 'payment_intent', $intent->id);
            $this->outbox->publish(GenericEvent::of('payment.succeeded', 'payment', $intent->id, ['purpose' => $intent->purpose, 'reference' => [$intent->reference_type, $intent->reference_id], 'amount' => $intent->amount()], $organization->id));
            if (! empty($intent->return_urls['save_method']) && $intent->provider_id) {
                $this->rememberMethod($organization, $intent, $ctx);
            }
            $this->events->dispatch(new PaymentSucceeded($intent, $ctx));

            return true;
        }, 3);
    }

    /**
     * Keeps the card behind a settled payment as the organization's stored method (audit §5f-1): the provider's id of
     * the initial payment is the token, the first stored card becomes the default and the automatic top-up's method.
     */
    private function rememberMethod(Organization $organization, PaymentIntent $intent, CommandContext $ctx): void
    {
        $raw = (array) ($intent->raw ?? []);
        $raw = array_merge($raw, is_array($raw['status'] ?? null) ? $raw['status'] : []); // the creation response plus the last status the gateway reported
        $provider = $this->providers->get($intent->provider);
        $facts = $provider instanceof StoredMethodCharging ? $provider->storedMethodFrom((string) $intent->provider_id, $raw) : null;
        if ($facts === null || ($facts['token'] ?? '') === '') {
            $this->audit->record($ctx, 'payment.method.saved', 'failed', ['provider' => $intent->provider, 'reason' => 'the gateway returned no reusable token'], 'payment_intent', $intent->id);

            return;
        }
        $last4 = isset($facts['last4']) ? mb_substr((string) $facts['last4'], 0, 4) : null;
        $first = ! PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->exists();
        $method = PaymentMethod::query()->create([
            'organization_id' => $organization->id, 'provider' => $intent->provider, 'provider_token' => (string) $facts['token'], 'kind' => 'card',
            'brand' => isset($facts['brand']) ? mb_substr((string) $facts['brand'], 0, 24) : null, 'last4' => $last4, 'expires' => isset($facts['expires']) ? mb_substr((string) $facts['expires'], 0, 7) : null, 'is_default' => $first, 'state' => 'active',
        ]);
        $setting = AutoTopupSetting::query()->where('organization_id', $organization->id)->first();
        if ($setting !== null && $setting->payment_method_id === null) {
            $setting->forceFill(['payment_method_id' => $method->id])->save();
        }
        $this->audit->record($ctx, 'payment.method.saved', 'succeeded', ['provider' => $intent->provider, 'method' => $method->id, 'last4' => $last4], 'payment_method', $method->id);
        $this->outbox->publish(GenericEvent::of('payment.method.saved', 'payment_method', $method->id, ['provider' => $intent->provider, 'kind' => 'card', 'last4' => $last4, 'is_default' => $first, 'auto_topup' => $setting !== null && (bool) $setting->enabled], $organization->id));
    }

    /** The organization's stored payment methods (never their tokens). @return list<array<string,mixed>> */
    public function methods(Organization $organization): array
    {
        return PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->orderByDesc('is_default')->orderBy('created_at')->get()
            ->map(fn (PaymentMethod $m) => ['id' => $m->id, 'provider' => $m->provider, 'kind' => $m->kind, 'brand' => $m->brand, 'last4' => $m->last4, 'expires' => $m->expires, 'is_default' => (bool) $m->is_default, 'created_at' => $m->created_at?->toIso8601String()])->all();
    }

    /** Removes a stored method; an automatic top-up that used it falls back to "notice only". */
    public function removeMethod(Organization $organization, string $methodId, CommandContext $context): void
    {
        $method = PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->whereKey($methodId)->first();
        if ($method === null) {
            throw DomainError::notFound('payment_method');
        }
        $method->forceFill(['state' => 'removed', 'is_default' => false])->save();
        AutoTopupSetting::query()->where('organization_id', $organization->id)->where('payment_method_id', $method->id)->update(['payment_method_id' => null]);
        $next = PaymentMethod::query()->where('organization_id', $organization->id)->where('state', 'active')->orderBy('created_at')->first();
        $next?->forceFill(['is_default' => true])->save();
        $this->audit->record($context->withScope($organization->id), 'payment.method.removed', 'succeeded', ['method' => $method->id, 'last4' => $method->last4], 'payment_method', $method->id);
    }

    /**
     * Abandons a payment that never completed (the order was cancelled before the customer paid): the intent leaves the
     * matchable states, so a late bank transfer with its symbol lands in reconciliation instead of paying a dead order.
     * Settled payments are refunded, never cancelled.
     */
    public function cancel(PaymentIntent $intent, CommandContext $context, string $reason): bool
    {
        return DB::transaction(function () use ($intent, $context, $reason) {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if (! in_array($intent->state, [S::CREATED, S::PENDING_CUSTOMER, S::AUTHENTICATION_REQUIRED], true)) {
                return false;
            }
            S::machine()->assertTransition($intent->state, S::CANCELED);
            $intent->forceFill(['state' => S::CANCELED, 'failure_reason' => mb_substr($reason, 0, 250), 'raw' => array_merge($intent->raw ?? [], ['cancelled_at' => now()->toIso8601String()])])->save();
            $this->audit->record($context->withScope($intent->organization_id), 'payment.intent.cancel', 'succeeded', ['provider' => $intent->provider, 'purpose' => $intent->purpose, 'amount' => $intent->amount(), 'reason' => $reason], 'payment_intent', $intent->id);

            return true;
        }, 3);
    }

    /** Refund to the original payment source; wallet refund record is completed when the provider confirms. */
    public function refund(PaymentIntent $intent, Money $amount, string $reason, string $idempotencyKey, CommandContext $context, ?string $walletRefundId = null): PaymentRefund
    {
        if (! $intent->isSucceeded()) {
            throw new DomainError('payment_not_refundable', 'Only successful payments can be refunded.', 409);
        }
        if ($intent->refunded_minor + $amount->minor > $intent->amount_minor) {
            throw new DomainError('refund_exceeds_payment', 'Refund exceeds the captured amount.', 409);
        }
        $existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $provider = $this->providers->get($intent->provider);
        $result = $provider->refund((string) $intent->provider_id, $amount, $idempotencyKey, $reason);
        $refund = PaymentRefund::query()->create([
            'payment_intent_id' => $intent->id, 'provider_refund_id' => $result['provider_refund_id'], 'amount_minor' => $amount->minor, 'currency' => $amount->currency->value,
            'state' => in_array($result['state'], ['succeeded', 'FINISHED', 'PAID', 'REFUNDED', 'succeeded_pending'], true) ? 'succeeded' : 'pending', 'reason' => $reason,
            'idempotency_key' => $idempotencyKey, 'wallet_refund_id' => $walletRefundId, 'created_by' => $context->actorType.':'.($context->actorId ?? 'system'),
        ]);
        $refunded = $intent->refunded_minor + $amount->minor;
        $intent->forceFill(['refunded_minor' => $refunded, 'state' => $refunded >= $intent->amount_minor ? S::REFUNDED : S::PARTIALLY_REFUNDED])->save();
        if ($walletRefundId !== null) {
            WalletRefund::query()->where('id', $walletRefundId)->update(['state' => $refund->state === 'succeeded' ? 'completed' : 'pending', 'payment_refund_id' => $refund->id]);
        }
        $this->audit->record($context->withScope($intent->organization_id), 'payment.refund', 'succeeded', ['amount' => $amount, 'reason' => $reason, 'provider_refund' => $result['provider_refund_id']], 'payment_intent', $intent->id);

        return $refund;
    }

    /** Daily settlement reconciliation (§64.6): provider settlement vs intents vs ledger. */
    public function reconcile(string $providerKey, \DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, CommandContext $context): ReconciliationRun
    {
        $provider = $this->providers->get($providerKey);
        $items = $provider->reconcile($periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d'));
        $run = ReconciliationRun::query()->create(['kind' => 'payments', 'period_start' => $periodStart, 'period_end' => $periodEnd, 'state' => 'completed', 'created_by' => $context->actorType.':'.($context->actorId ?? 'system'), 'summary' => ['provider' => $providerKey, 'items' => count($items)]]);
        $settlement = Settlement::query()->create(['provider' => $providerKey, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'currency' => $items[0]['amount']->currency->value ?? 'CZK', 'items_count' => count($items), 'state' => 'imported']);
        $mismatches = 0;
        $total = 0;
        $fees = 0;
        foreach ($items as $item) {
            $intent = PaymentIntent::query()->where('provider', $providerKey)->where('provider_id', $item['provider_id'])->first();
            $state = 'matched';
            if ($intent === null) {
                $state = 'unmatched';
                $mismatches++;
                ReconciliationItem::query()->create(['run_id' => $run->id, 'kind' => 'settlement_without_intent', 'reference' => $item['provider_id'], 'actual_minor' => $item['amount']->minor, 'note' => 'Settlement item has no payment intent']);
            } elseif (! $intent->isSucceeded()) {
                // Payment settled at the provider but never marked here (S53): sync now.
                $this->syncFromProvider($intent, $context);
                $intent->refresh();
                if (! $intent->isSucceeded()) {
                    $state = 'mismatch';
                    $mismatches++;
                    ReconciliationItem::query()->create(['run_id' => $run->id, 'kind' => 'settled_but_not_succeeded', 'reference' => $intent->id, 'expected_minor' => $intent->amount_minor, 'actual_minor' => $item['amount']->minor]);
                }
            } elseif ($intent->amount_minor !== $item['amount']->minor) {
                $state = 'mismatch';
                $mismatches++;
                ReconciliationItem::query()->create(['run_id' => $run->id, 'kind' => 'amount_mismatch', 'reference' => $intent->id, 'expected_minor' => $intent->amount_minor, 'actual_minor' => $item['amount']->minor]);
            }
            $total += $item['amount']->minor;
            $fees += $item['fee']?->minor ?? 0;
            SettlementItem::query()->create(['settlement_id' => $settlement->id, 'provider_id' => $item['provider_id'], 'amount_minor' => $item['amount']->minor, 'fee_minor' => $item['fee']?->minor ?? 0, 'currency' => $item['amount']->currency->value, 'settled_at' => $item['settled_at'], 'matched_payment_intent_id' => $intent?->id, 'state' => $state]);
        }
        // Intents that succeeded in the period but do not appear in the settlement.
        $settledIds = array_column($items, 'provider_id');
        $missing = PaymentIntent::query()->where('provider', $providerKey)->where('state', S::SUCCEEDED)->whereBetween('paid_at', [$periodStart, $periodEnd])->whereNotIn('provider_id', $settledIds)->get();
        foreach ($missing as $intent) {
            $mismatches++;
            ReconciliationItem::query()->create(['run_id' => $run->id, 'kind' => 'succeeded_without_settlement', 'reference' => $intent->id, 'expected_minor' => $intent->amount_minor, 'note' => 'Succeeded intent missing from provider settlement']);
        }
        $settlement->forceFill(['total_minor' => $total, 'fee_minor' => $fees, 'state' => $mismatches ? 'mismatch' : 'reconciled'])->save();
        $run->forceFill(['mismatches' => $mismatches, 'state' => $mismatches ? 'mismatch' : 'completed'])->save();
        if ($mismatches) {
            $this->outbox->publish(GenericEvent::of('finance.reconciliation.mismatch', 'reconciliation', $run->id, ['provider' => $providerKey, 'mismatches' => $mismatches]));
        }

        return $run;
    }

    /** Match an imported bank statement line to a pending bank-transfer intent by variable symbol + amount. */
    public function matchBankLine(BankStatementLine $line, CommandContext $context): ?PaymentIntent
    {
        if ($line->state === 'matched' || $line->amount_minor <= 0 || $line->variable_symbol === null) {
            return null;
        }
        $intent = PaymentIntent::query()->where('provider', 'bank')->whereIn('state', [S::CREATED, S::PENDING_CUSTOMER])
            ->where('raw->instructions->variable_symbol', $line->variable_symbol)->where('currency', $line->currency)->first();
        if ($intent === null) {
            return null;
        }
        if ($intent->amount_minor !== $line->amount_minor) {
            $this->openReconciliationItem('bank_amount_mismatch', $intent->id, $intent->amount_minor, $line->amount_minor, "Bank line {$line->external_id} amount differs");

            return null;
        }
        $intent->forceFill(['raw' => array_merge($intent->raw ?? [], ['bank_line' => $line->external_id, 'status' => 'PAID'])])->save();
        $line->forceFill(['matched_payment_intent_id' => $intent->id, 'state' => 'matched'])->save();
        $this->settle($intent, $context, 'bank');

        return $intent->refresh();
    }

    private function openReconciliationItem(string $kind, string $reference, ?int $expected, ?int $actual, ?string $note): void
    {
        $run = ReconciliationRun::query()->create(['kind' => 'payments', 'period_start' => now()->startOfDay(), 'period_end' => now(), 'state' => 'mismatch', 'mismatches' => 1, 'created_by' => 'system', 'summary' => ['ad_hoc' => $kind]]);
        ReconciliationItem::query()->create(['run_id' => $run->id, 'kind' => $kind, 'reference' => $reference, 'expected_minor' => $expected, 'actual_minor' => $actual, 'note' => $note]);
        $this->outbox->publish(GenericEvent::of('finance.reconciliation.mismatch', 'reconciliation', $run->id, ['kind' => $kind, 'reference' => $reference]));
    }

    private function normalizeState(string $providerState, string $fallback): string
    {
        return match (strtoupper($providerState)) {
            'PAID', 'SUCCEEDED', 'COMPLETED', 'CAPTURED' => S::SUCCEEDED,
            'PENDING', 'CREATED', 'PAYMENT_METHOD_CHOSEN', 'UNPAID', 'OPEN', 'REQUIRES_PAYMENT_METHOD' => S::PENDING_CUSTOMER,
            'AUTHORIZED', 'REQUIRES_ACTION', 'REQUIRES_CONFIRMATION' => S::AUTHENTICATION_REQUIRED,
            'PROCESSING', 'REQUIRES_CAPTURE' => S::PROCESSING,
            'CANCELLED', 'CANCELED', 'TIMEOUTED', 'EXPIRED' => S::CANCELED,
            'FAILED', 'DECLINED' => S::FAILED,
            'REFUNDED' => S::REFUNDED,
            'PARTIALLY_REFUNDED' => S::PARTIALLY_REFUNDED,
            default => $fallback,
        };
    }
}
