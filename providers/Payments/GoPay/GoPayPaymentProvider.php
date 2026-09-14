<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments\GoPay;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
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
 * GoPay REST API (OAuth2 client credentials). Contingency adapter (§63.2).
 *   POST /oauth2/token                 access token (scope payment-all)
 *   POST /payments/payment             create -> id + gw_url
 *   GET  /payments/payment/{id}        status
 *   POST /payments/payment/{id}/refund
 *   POST /payments/payment/{id}/create-recurrence   charge a card kept by an ON_DEMAND recurrent payment (audit §5g-5)
 * Notification: GET notification_url?id=… — verified by fetching the status.
 * Stored cards: a payment created with `recurrence` ON_DEMAND is the token (its id); later charges are its recurrences.
 */
final class GoPayPaymentProvider implements PaymentProvider, StoredMethodCharging
{
    public function __construct(
        private readonly ProviderHttpClient $http,
        private readonly SecretStore $secrets,
        private readonly CacheRepository $cache,
    ) {}

    public static function providerKey(): string
    {
        return 'gopay';
    }

    public function supportedMethods(): array
    {
        return ['card', 'apple_pay', 'google_pay', 'bank_transfer'];
    }

    public function createPaymentIntent(Money $amount, array $input): array
    {
        if (! empty($input['stored_method_token'])) { // an automatic top-up: the customer is not present
            return $this->chargeStoredMethod((string) $input['stored_method_token'], $amount, ['description' => (string) ($input['description'] ?? ''), 'reference' => (string) ($input['reference'] ?? ''), 'idempotency_key' => $input['idempotency_key'] ?? null, 'email' => (string) ($input['email'] ?? '')]);
        }
        $save = ! empty($input['save_method']);
        $instrument = $save ? 'PAYMENT_CARD' : match ($input['method'] ?? null) {
            'bank_transfer' => 'BANK_ACCOUNT', 'apple_pay' => 'APPLE_PAY', 'google_pay' => 'GPAY', default => 'PAYMENT_CARD',
        };
        $body = [
            'payer' => ['default_payment_instrument' => $instrument, 'allowed_payment_instruments' => $save ? ['PAYMENT_CARD'] : ['PAYMENT_CARD', 'BANK_ACCOUNT', 'APPLE_PAY', 'GPAY'], 'contact' => ['email' => (string) $input['email']]],
            'target' => ['type' => 'ACCOUNT', 'goid' => (int) config('onhost.payments.gopay.goid')],
            'amount' => $amount->minor,
            'currency' => $amount->currency->value,
            'order_number' => (string) $input['reference'],
            'order_description' => (string) $input['description'],
            'items' => [['type' => 'ITEM', 'name' => (string) $input['description'], 'amount' => $amount->minor, 'count' => 1]],
            'callback' => ['return_url' => $input['return_url'], 'notification_url' => rtrim((string) config('onhost.portal_url'), '/').'/v1/webhooks/payments/gopay'],
            'lang' => strtoupper((string) ($input['locale'] ?? 'cs')),
        ];
        if ($save) { // a card the customer wants to keep: an on-demand recurrent payment; its id becomes the token
            $body['recurrence'] = ['recurrence_cycle' => 'ON_DEMAND', 'recurrence_date_to' => now()->addYears(3)->format('Y-m-d')];
        }
        $response = $this->send('POST', '/payments/payment', 'payment.create', $body);
        if (! isset($response['id'])) {
            throw new DomainError('payment_provider_error', 'GoPay did not return a payment id.', 502);
        }

        return ['provider_id' => (string) $response['id'], 'redirect_url' => (string) ($response['gw_url'] ?? ''), 'state' => (string) ($response['state'] ?? 'CREATED'), 'raw' => $response + ['save_method' => $save]];
    }

    public function storedMethodsAvailable(): bool
    {
        try {
            $creds = $this->secrets->read(SecretRef::parse((string) config('onhost.payments.gopay.secret_ref')));
        } catch (\Throwable) {
            return false;
        }

        return (string) ($creds['client_id'] ?? '') !== '' && (string) ($creds['client_secret'] ?? '') !== '' && (int) config('onhost.payments.gopay.goid') > 0 && (bool) config('onhost.payments.gopay.recurring', true);
    }

    /** The token is the parent (ON_DEMAND) payment; the charge is one of its recurrences and settles like any payment. */
    public function chargeStoredMethod(string $methodId, Money $amount, array $options = []): array
    {
        $description = (string) ($options['description'] ?? '') ?: 'ONhost';
        $response = $this->send('POST', '/payments/payment/'.rawurlencode($methodId).'/create-recurrence', 'payment.recurrence', [
            'amount' => $amount->minor,
            'currency' => $amount->currency->value,
            'order_number' => (string) ($options['reference'] ?? ''),
            'order_description' => $description,
            'items' => [['type' => 'ITEM', 'name' => $description, 'amount' => $amount->minor, 'count' => 1]],
        ]);
        if (! isset($response['id'])) {
            throw new DomainError('payment_provider_error', 'GoPay did not return a payment id for the recurrence.', 502);
        }

        return ['provider_id' => (string) $response['id'], 'redirect_url' => null, 'state' => strtoupper((string) ($response['state'] ?? 'CREATED')), 'raw' => $response + ['recurring' => true]];
    }

    /** Only a payment created with a recurrence can be charged again; the masked card comes with its status (`payer.payment_card`). */
    public function storedMethodFrom(string $providerId, array $raw): ?array
    {
        if (empty($raw['recurrence']) && empty($raw['save_method'])) {
            return null;
        }
        $card = is_array($raw['payer']['payment_card'] ?? null) ? $raw['payer']['payment_card'] : [];
        $expiration = (string) ($card['card_expiration'] ?? ''); // YYMM

        return [
            'token' => $providerId,
            'brand' => isset($card['card_brand']) ? (string) $card['card_brand'] : null,
            'last4' => preg_match('/(\d{4})\D*$/', (string) ($card['card_number'] ?? ''), $m) === 1 ? $m[1] : null,
            'expires' => preg_match('/^(\d{2})(\d{2})$/', $expiration, $e) === 1 ? '20'.$e[1].'-'.$e[2] : null,
        ];
    }

    public function getPaymentStatus(string $providerId): array
    {
        $response = $this->send('GET', '/payments/payment/'.rawurlencode($providerId), 'payment.status');
        $state = strtoupper((string) ($response['state'] ?? 'CREATED'));

        return ['state' => $state, 'amount' => isset($response['amount'], $response['currency']) ? Money::minor((int) $response['amount'], (string) $response['currency']) : null, 'paid_at' => $state === 'PAID' ? now()->toISOString() : null, 'method' => isset($response['payment_instrument']) ? strtolower((string) $response['payment_instrument']) : null, 'raw' => $response];
    }

    public function capture(string $providerId, ?Money $amount = null): array
    {
        return $this->send('POST', '/payments/payment/'.rawurlencode($providerId).'/capture', 'payment.capture', $amount ? ['amount' => $amount->minor] : []);
    }

    public function cancel(string $providerId): array
    {
        return $this->send('POST', '/payments/payment/'.rawurlencode($providerId).'/void-authorization', 'payment.cancel', []);
    }

    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array
    {
        $response = $this->send('POST', '/payments/payment/'.rawurlencode($providerId).'/refund', 'payment.refund', ['amount' => $amount->minor], form: true);

        return ['provider_refund_id' => (string) ($response['id'] ?? $providerId), 'state' => strtoupper((string) ($response['result'] ?? 'ACCEPTED')) === 'FINISHED' ? 'succeeded' : 'pending', 'raw' => $response];
    }

    public function verifyWebhook(Request $request): array
    {
        $id = (string) $request->query('id', $request->input('id', ''));
        if ($id === '' || ! preg_match('/^\d{5,20}$/', $id)) {
            throw new DomainError('webhook_invalid', 'GoPay notification is missing a payment id.', 400);
        }
        // GoPay notifications are unsigned; the status endpoint (authenticated) is the truth.
        $status = $this->getPaymentStatus($id);

        return ['event_id' => $id.':'.$status['state'], 'provider_id' => $id, 'state' => $status['state'], 'amount' => $status['amount'], 'raw' => $status['raw']];
    }

    public function reconcile(string $periodStart, string $periodEnd): array
    {
        $response = $this->send('GET', '/accounts/'.(int) config('onhost.payments.gopay.goid').'/statements', 'statements', null, query: ['date_from' => $periodStart, 'date_to' => $periodEnd, 'currency' => 'CZK', 'format' => 'JSON']);
        $items = [];
        foreach ((array) ($response['items'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['payment_id'])) {
                continue;
            }
            $currency = (string) ($row['currency'] ?? 'CZK');
            $items[] = ['provider_id' => (string) $row['payment_id'], 'amount' => Money::minor((int) ($row['amount'] ?? 0), $currency), 'fee' => isset($row['fee']) ? Money::minor((int) $row['fee'], $currency) : null, 'settled_at' => (string) ($row['date'] ?? $periodEnd), 'state' => 'settled', 'reference' => $row['order_number'] ?? null];
        }

        return $items;
    }

    private function send(string $method, string $path, string $action, ?array $body = null, bool $form = false, array $query = []): array
    {
        $response = $this->http->send(new ProviderRequest(
            provider: 'gopay', instanceKey: 'gopay', method: $method, url: rtrim((string) config('onhost.payments.gopay.base_url'), '/').$path,
            action: $action, headers: ['Authorization' => 'Bearer '.$this->token(), 'Accept' => 'application/json'], body: $body, bodyType: $form ? 'form' : 'json', query: $query, timeoutSeconds: 15, critical: true,
        ));
        $json = $response->json();
        if (! is_array($json) || $response->status >= 400) {
            throw new DomainError('payment_provider_error', "GoPay {$action} failed (HTTP {$response->status})", 502, ['errors' => is_array($json) ? ($json['errors'] ?? null) : null]);
        }

        return $json;
    }

    private function token(): string
    {
        $cached = $this->cache->get('onhost:gopay:token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $creds = $this->secrets->read(SecretRef::parse((string) config('onhost.payments.gopay.secret_ref')));
        $response = $this->http->send(new ProviderRequest(
            provider: 'gopay', instanceKey: 'gopay', method: 'POST', url: rtrim((string) config('onhost.payments.gopay.base_url'), '/').'/oauth2/token',
            action: 'oauth.token', headers: ['Authorization' => 'Basic '.base64_encode(($creds['client_id'] ?? '').':'.($creds['client_secret'] ?? '')), 'Accept' => 'application/json'],
            body: ['grant_type' => 'client_credentials', 'scope' => 'payment-all'], bodyType: 'form', timeoutSeconds: 10, critical: true,
        ));
        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new DomainError('payment_provider_error', 'GoPay OAuth token could not be obtained.', 502);
        }
        $this->cache->put('onhost:gopay:token', $token, max(60, (int) $response->json('expires_in', 1800) - 60));

        return $token;
    }
}
