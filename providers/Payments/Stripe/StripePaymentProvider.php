<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments\Stripe;

use Illuminate\Http\Request;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\PaymentProvider;
use Onhost\Providers\Contracts\StoredMethodCharging;

/**
 * Stripe (international contingency): hosted Checkout Session -> PaymentIntent.
 * Webhooks are verified with the signing secret (Stripe-Signature v1 HMAC, 5 min tolerance).
 *
 * Stored cards (audit §5g-5): a Checkout Session created with `setup_future_usage=off_session` and a Customer keeps
 * the card on Stripe's side; the token the platform stores is `customer:payment_method`, and later charges are
 * off-session PaymentIntents confirmed with it — identified by `pi_` ids in status, refund and webhook handling.
 */
final class StripePaymentProvider implements PaymentProvider, StoredMethodCharging
{
    private ?array $credentials = null;

    public function __construct(
        private readonly ProviderHttpClient $http,
        private readonly SecretStore $secrets,
    ) {}

    public static function providerKey(): string
    {
        return 'stripe';
    }

    public function supportedMethods(): array
    {
        return ['card', 'apple_pay', 'google_pay'];
    }

    public function createPaymentIntent(Money $amount, array $input): array
    {
        if (! empty($input['stored_method_token'])) { // an automatic top-up: the customer is not present
            return $this->chargeStoredMethod((string) $input['stored_method_token'], $amount, ['description' => (string) ($input['description'] ?? ''), 'reference' => (string) ($input['reference'] ?? ''), 'idempotency_key' => $input['idempotency_key'] ?? null, 'email' => (string) ($input['email'] ?? '')]);
        }
        $save = ! empty($input['save_method']);
        $body = [
            'mode' => 'payment',
            'line_items[0][price_data][currency]' => strtolower($amount->currency->value),
            'line_items[0][price_data][unit_amount]' => $amount->minor,
            'line_items[0][price_data][product_data][name]' => (string) $input['description'],
            'line_items[0][quantity]' => 1,
            'success_url' => $input['return_url'],
            'cancel_url' => $input['cancel_url'],
            'client_reference_id' => (string) $input['reference'],
            'customer_email' => (string) $input['email'],
            'metadata[reference]' => (string) $input['reference'],
            'payment_intent_data[metadata][reference]' => (string) $input['reference'],
            'expires_at' => time() + 2 * 3600,
        ];
        if ($save) {
            $body['payment_method_types[0]'] = 'card'; // only a card can be kept
            $body['customer_creation'] = 'always';
            $body['payment_intent_data[setup_future_usage]'] = 'off_session';
        }
        $response = $this->send('POST', '/checkout/sessions', 'checkout.create', $body, $input['idempotency_key'] ?? null);

        return ['provider_id' => (string) $response['id'], 'redirect_url' => (string) ($response['url'] ?? ''), 'state' => 'PENDING', 'raw' => $response + ['save_method' => $save]];
    }

    public function storedMethodsAvailable(): bool
    {
        try {
            $creds = $this->credentials();
        } catch (\Throwable) {
            return false;
        }

        return (string) ($creds['secret_key'] ?? '') !== '' && (bool) config('onhost.payments.stripe.recurring', true);
    }

    /** `customer:payment_method` → an off-session PaymentIntent confirmed with the stored card; a decline is an HTTP 402 and surfaces as payment_provider_error. */
    public function chargeStoredMethod(string $methodId, Money $amount, array $options = []): array
    {
        [$customer, $paymentMethod] = array_pad(explode(':', $methodId, 2), 2, '');
        if ($customer === '' || $paymentMethod === '') {
            throw new DomainError('payment_method_unknown', 'The stored payment method token is not usable.', 422);
        }
        $response = $this->send('POST', '/payment_intents', 'intent.offsession', array_filter([
            'amount' => $amount->minor,
            'currency' => strtolower($amount->currency->value),
            'customer' => $customer,
            'payment_method' => $paymentMethod,
            'off_session' => 'true',
            'confirm' => 'true',
            'description' => (string) ($options['description'] ?? 'ONhost'),
            'receipt_email' => (string) ($options['email'] ?? '') ?: null,
            'metadata[reference]' => (string) ($options['reference'] ?? ''),
            'metadata[stored]' => '1',
        ], fn ($v) => $v !== null), $options['idempotency_key'] ?? null);

        return ['provider_id' => (string) $response['id'], 'redirect_url' => null, 'state' => $this->intentState((string) ($response['status'] ?? '')), 'raw' => $response + ['recurring' => true]];
    }

    /** The settled session carries the Customer and (expanded) the PaymentIntent's payment method with the card facts. */
    public function storedMethodFrom(string $providerId, array $raw): ?array
    {
        $customer = is_array($raw['customer'] ?? null) ? (string) ($raw['customer']['id'] ?? '') : (string) ($raw['customer'] ?? '');
        $intent = is_array($raw['payment_intent'] ?? null) ? $raw['payment_intent'] : [];
        $method = is_array($intent['payment_method'] ?? null) ? $intent['payment_method'] : (is_array($raw['payment_method'] ?? null) ? $raw['payment_method'] : []);
        $methodId = (string) ($method['id'] ?? (is_string($intent['payment_method'] ?? null) ? $intent['payment_method'] : ''));
        if ($customer === '' || $methodId === '') {
            return null;
        }
        $card = is_array($method['card'] ?? null) ? $method['card'] : [];

        return [
            'token' => $customer.':'.$methodId,
            'brand' => isset($card['brand']) ? (string) $card['brand'] : null,
            'last4' => isset($card['last4']) ? (string) $card['last4'] : null,
            'expires' => isset($card['exp_year'], $card['exp_month']) ? sprintf('%04d-%02d', (int) $card['exp_year'], (int) $card['exp_month']) : null,
        ];
    }

    public function getPaymentStatus(string $providerId): array
    {
        if (str_starts_with($providerId, 'pi_')) { // an off-session charge of a stored card
            $intent = $this->send('GET', '/payment_intents/'.rawurlencode($providerId), 'intent.status', null, null, ['expand[]' => 'payment_method']);
            $state = $this->intentState((string) ($intent['status'] ?? ''));

            return ['state' => $state, 'amount' => isset($intent['amount'], $intent['currency']) ? Money::minor((int) ($intent['amount_received'] ?: $intent['amount']), strtoupper((string) $intent['currency'])) : null, 'paid_at' => $state === 'PAID' ? now()->toISOString() : null, 'method' => 'card', 'raw' => $intent];
        }
        $session = $this->send('GET', '/checkout/sessions/'.rawurlencode($providerId), 'checkout.status', null, null, ['expand[]' => 'payment_intent.payment_method']);
        $paid = ($session['payment_status'] ?? '') === 'paid';
        $state = $paid ? 'PAID' : (($session['status'] ?? '') === 'expired' ? 'EXPIRED' : 'PENDING');

        return ['state' => $state, 'amount' => isset($session['amount_total'], $session['currency']) ? Money::minor((int) $session['amount_total'], strtoupper((string) $session['currency'])) : null, 'paid_at' => $paid ? now()->toISOString() : null, 'method' => 'card', 'raw' => $session];
    }

    public function capture(string $providerId, ?Money $amount = null): array
    {
        $intentId = str_starts_with($providerId, 'pi_') ? $providerId : (string) $this->send('GET', '/checkout/sessions/'.rawurlencode($providerId), 'checkout.status')['payment_intent'];

        return $this->send('POST', '/payment_intents/'.rawurlencode($intentId).'/capture', 'intent.capture', $amount ? ['amount_to_capture' => $amount->minor] : []);
    }

    public function cancel(string $providerId): array
    {
        if (str_starts_with($providerId, 'pi_')) {
            return $this->send('POST', '/payment_intents/'.rawurlencode($providerId).'/cancel', 'intent.cancel', []);
        }

        return $this->send('POST', '/checkout/sessions/'.rawurlencode($providerId).'/expire', 'checkout.expire', []);
    }

    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array
    {
        $intentId = str_starts_with($providerId, 'pi_') ? $providerId : (string) $this->send('GET', '/checkout/sessions/'.rawurlencode($providerId), 'checkout.status')['payment_intent'];
        $response = $this->send('POST', '/refunds', 'refund.create', ['payment_intent' => $intentId, 'amount' => $amount->minor, 'reason' => 'requested_by_customer'], $idempotencyKey);

        return ['provider_refund_id' => (string) $response['id'], 'state' => ($response['status'] ?? '') === 'succeeded' ? 'succeeded' : 'pending', 'raw' => $response];
    }

    public function verifyWebhook(Request $request): array
    {
        $header = (string) $request->header('Stripe-Signature', '');
        $payload = (string) $request->getContent();
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        if ($timestamp === 0 || abs(time() - $timestamp) > 300) {
            throw new DomainError('webhook_signature_invalid', 'Stripe signature timestamp outside tolerance.', 403);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) ($this->credentials()['webhook_secret'] ?? ''));
        $valid = false;
        foreach ($parts['v1'] ?? [] as $signature) {
            $valid = $valid || hash_equals($expected, $signature);
        }
        if (! $valid) {
            throw new DomainError('webhook_signature_invalid', 'Stripe signature mismatch.', 403);
        }
        $event = json_decode($payload, true);
        if (! is_array($event) || empty($event['id'])) {
            throw new DomainError('webhook_invalid', 'Stripe event payload is invalid.', 400);
        }
        $object = $event['data']['object'] ?? [];
        $stored = ! empty($object['metadata']['stored']); // off-session charges are their own intent; checkout payments are known by their session
        $providerId = match ((string) ($object['object'] ?? '')) {
            'checkout.session' => (string) $object['id'],
            'payment_intent' => (string) ($object['metadata']['session'] ?? ($stored ? $object['id'] : '')),
            default => (string) ($object['metadata']['session'] ?? ''),
        };
        $state = match ($event['type'] ?? '') {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'PAID',
            'payment_intent.succeeded' => $stored ? 'PAID' : 'PENDING',
            'checkout.session.async_payment_failed', 'payment_intent.payment_failed' => 'FAILED',
            'checkout.session.expired' => 'EXPIRED',
            'charge.refunded' => 'REFUNDED',
            default => 'PENDING',
        };
        $amount = isset($object['amount_total'], $object['currency']) ? Money::minor((int) $object['amount_total'], strtoupper((string) $object['currency'])) : (isset($object['amount_received'], $object['currency']) && $stored ? Money::minor((int) $object['amount_received'], strtoupper((string) $object['currency'])) : null);

        return ['event_id' => (string) $event['id'], 'provider_id' => $providerId, 'state' => $state, 'amount' => $amount, 'raw' => $event];
    }

    public function reconcile(string $periodStart, string $periodEnd): array
    {
        $response = $this->send('GET', '/balance_transactions', 'balance.list', null, null, ['created[gte]' => strtotime($periodStart), 'created[lte]' => strtotime($periodEnd.' 23:59:59'), 'type' => 'charge', 'limit' => 100, 'expand[]' => 'data.source']);
        $items = [];
        foreach ((array) ($response['data'] ?? []) as $row) {
            $currency = strtoupper((string) ($row['currency'] ?? 'eur'));
            $source = is_array($row['source'] ?? null) ? $row['source'] : [];
            $items[] = ['provider_id' => (string) ($source['metadata']['session'] ?? $source['payment_intent'] ?? $row['source'] ?? $row['id']), 'amount' => Money::minor((int) ($row['amount'] ?? 0), $currency), 'fee' => Money::minor((int) ($row['fee'] ?? 0), $currency), 'settled_at' => date('Y-m-d', (int) ($row['available_on'] ?? time())), 'state' => 'settled', 'reference' => $source['metadata']['reference'] ?? null];
        }

        return $items;
    }

    private function intentState(string $status): string
    {
        return match ($status) {
            'succeeded' => 'PAID',
            'canceled' => 'CANCELLED',
            'requires_payment_method' => 'FAILED', // the off-session confirmation was declined
            'requires_action', 'requires_confirmation' => 'REQUIRES_ACTION',
            'processing', 'requires_capture' => 'PROCESSING',
            default => 'PENDING',
        };
    }

    private function send(string $method, string $path, string $action, ?array $body = null, ?string $idempotencyKey = null, array $query = []): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->credentials()['secret_key'], 'Stripe-Version' => '2025-08-27.basil'];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'stripe', instanceKey: 'stripe', method: $method, url: rtrim((string) config('onhost.payments.stripe.base_url'), '/').$path,
            action: $action, headers: $headers, body: $body, bodyType: 'form', query: $query, timeoutSeconds: 20, critical: true,
        ));
        $json = $response->json();
        if (! is_array($json) || $response->status >= 400) {
            throw new DomainError('payment_provider_error', "Stripe {$action} failed (HTTP {$response->status}): ".(is_array($json) ? ($json['error']['message'] ?? '') : ''), 502);
        }

        return $json;
    }

    private function credentials(): array
    {
        return $this->credentials ??= $this->secrets->read(SecretRef::parse((string) config('onhost.payments.stripe.secret_ref')));
    }
}
