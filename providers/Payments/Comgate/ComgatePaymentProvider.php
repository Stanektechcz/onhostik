<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments\Comgate;

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
 * Comgate Payments API v2.0 (REST/JSON, HTTP Basic merchant:secret).
 *   POST /payment                       create (prepareOnly) -> transId + redirect
 *   GET  /payment/transId/{transId}     status (source of truth for callbacks)
 *   POST /payment/transId/{transId}/refund
 *   GET  /transfer?date=YYYY-MM-DD, GET /transfer/{transferId}   settlement items
 * Push notifications are verified by re-reading the status; the optional secret and
 * IP allow-list are additional checks, never the only trust mechanism (§63.4).
 *
 * Recurring card payments (audit §5f-1): a payment created with `initRecurring` becomes the stored method — its
 * `transId` is the token — and later charges are created with `initRecurringId` and no customer present.
 */
final class ComgatePaymentProvider implements PaymentProvider, StoredMethodCharging
{
    private ?array $credentials = null;

    public function __construct(
        private readonly ProviderHttpClient $http,
        private readonly SecretStore $secrets,
    ) {}

    public static function providerKey(): string
    {
        return 'comgate';
    }

    public function supportedMethods(): array
    {
        return ['card', 'apple_pay', 'google_pay', 'bank_transfer', 'qr'];
    }

    public function createPaymentIntent(Money $amount, array $input): array
    {
        if (($input['method'] ?? null) === 'stored') { // automatic top-ups: the stored card is charged without the customer
            $token = (string) ($input['stored_method_token'] ?? '');
            if ($token === '') {
                throw new DomainError('payment_method_unknown', 'No stored payment method to charge.', 422);
            }

            return $this->chargeStoredMethod($token, $amount, ['description' => (string) ($input['description'] ?? ''), 'reference' => (string) ($input['reference'] ?? ''), 'idempotency_key' => $input['idempotency_key'] ?? null, 'email' => (string) ($input['email'] ?? '')]);
        }
        $method = match ($input['method'] ?? null) {
            'card' => 'CARD_CZ_CSOB_2',
            'apple_pay' => 'APPLEPAY_REDIRECT',
            'google_pay' => 'GOOGLEPAY_REDIRECT',
            'bank_transfer' => 'BANK_ALL',
            default => 'ALL',
        };
        $save = ! empty($input['save_method']);
        $response = $this->send('POST', '/payment', 'payment.create', array_filter([
            'price' => $amount->minor,
            'curr' => $amount->currency->value,
            'label' => mb_substr((string) $input['description'], 0, 16),
            'refId' => (string) $input['reference'],
            'method' => $save ? 'CARD_CZ_CSOB_2' : $method, // a method the customer wants to keep must be a card
            'email' => (string) $input['email'],
            'prepareOnly' => true,
            'initRecurring' => $save ?: null,
            'test' => (bool) config('onhost.payments.comgate.test', true),
            'lang' => in_array($input['locale'] ?? 'cs', ['cs', 'sk', 'en', 'pl'], true) ? $input['locale'] : 'cs',
            'country' => strtoupper((string) ($input['country'] ?? 'CZ')),
            'expirationTime' => '2h',
            'url_paid' => $input['return_url'],
            'url_cancelled' => $input['cancel_url'],
            'url_pending' => $input['pending_url'],
        ], fn ($v) => $v !== null), $input['idempotency_key'] ?? null);
        $this->assertOk($response, 'payment.create');

        return ['provider_id' => (string) $response['transId'], 'redirect_url' => (string) ($response['redirect'] ?? ''), 'state' => 'PENDING', 'raw' => $response + ['initRecurring' => $save]];
    }

    public function storedMethodsAvailable(): bool
    {
        try {
            $creds = $this->credentials();
        } catch (\Throwable) {
            return false;
        }

        return $creds['merchant'] !== '' && $creds['secret'] !== '' && (bool) config('onhost.payments.comgate.recurring', true);
    }

    /** The token is the `transId` of the payment that was created with `initRecurring`; Comgate charges the same card in the background. */
    public function chargeStoredMethod(string $methodId, Money $amount, array $options = []): array
    {
        $response = $this->send('POST', '/payment', 'payment.recurring', [
            'price' => $amount->minor,
            'curr' => $amount->currency->value,
            'label' => mb_substr((string) ($options['description'] ?? 'ONhost'), 0, 16) ?: 'ONhost',
            'refId' => (string) ($options['reference'] ?? ''),
            'method' => 'CARD_CZ_CSOB_2',
            'email' => (string) ($options['email'] ?? ''),
            'prepareOnly' => true,
            'initRecurringId' => $methodId,
            'test' => (bool) config('onhost.payments.comgate.test', true),
        ], $options['idempotency_key'] ?? null);
        $this->assertOk($response, 'payment.recurring');

        return ['provider_id' => (string) $response['transId'], 'redirect_url' => null, 'state' => strtoupper((string) ($response['status'] ?? 'PENDING')), 'raw' => $response + ['recurring' => true]];
    }

    /** The token is the initial payment itself; the masked card number and brand come with its status. */
    public function storedMethodFrom(string $providerId, array $raw): ?array
    {
        if (empty($raw['initRecurring']) && ! isset($raw['cardNumber']) && ! isset($raw['card_number'])) {
            return null;
        }
        $number = (string) ($raw['cardNumber'] ?? $raw['card_number'] ?? '');

        return [
            'token' => $providerId,
            'brand' => isset($raw['cardBrand']) ? mb_substr((string) $raw['cardBrand'], 0, 24) : null,
            'last4' => preg_match('/(\d{4})\D*$/', $number, $m) === 1 ? $m[1] : null,
            'expires' => isset($raw['cardExpiration']) ? mb_substr((string) $raw['cardExpiration'], 0, 7) : null,
        ];
    }

    public function getPaymentStatus(string $providerId): array
    {
        $response = $this->send('GET', '/payment/transId/'.rawurlencode($providerId), 'payment.status');
        $this->assertOk($response, 'payment.status');
        $state = strtoupper((string) ($response['status'] ?? 'PENDING'));
        $amount = isset($response['price'], $response['curr']) ? Money::minor((int) $response['price'], (string) $response['curr']) : null;

        return ['state' => $state, 'amount' => $amount, 'paid_at' => $state === 'PAID' ? now()->toISOString() : null, 'method' => isset($response['method']) ? $this->mapMethod((string) $response['method']) : null, 'raw' => $response];
    }

    public function capture(string $providerId, ?Money $amount = null): array
    {
        $response = $this->send('POST', '/payment/transId/'.rawurlencode($providerId).'/capturePreauth', 'payment.capture', $amount ? ['amount' => $amount->minor] : []);
        $this->assertOk($response, 'payment.capture');

        return $response;
    }

    public function cancel(string $providerId): array
    {
        $response = $this->send('DELETE', '/payment/transId/'.rawurlencode($providerId).'/cancelPreauth', 'payment.cancel');
        $this->assertOk($response, 'payment.cancel');

        return $response;
    }

    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array
    {
        $response = $this->send('POST', '/payment/transId/'.rawurlencode($providerId).'/refund', 'payment.refund', [
            'amount' => $amount->minor, 'curr' => $amount->currency->value, 'refId' => mb_substr($idempotencyKey, 0, 40), 'test' => (bool) config('onhost.payments.comgate.test', true),
        ], $idempotencyKey);
        $this->assertOk($response, 'payment.refund');

        return ['provider_refund_id' => (string) ($response['refundId'] ?? $providerId.':'.$idempotencyKey), 'state' => 'succeeded_pending', 'raw' => $response];
    }

    public function verifyWebhook(Request $request): array
    {
        $payload = $request->isJson() ? (array) $request->json()->all() : $request->all();
        $transId = (string) ($payload['transId'] ?? '');
        if ($transId === '' || ! preg_match('/^[A-Z0-9\-]{4,40}$/i', $transId)) {
            throw new DomainError('webhook_invalid', 'Comgate notification is missing transId.', 400);
        }
        $allow = (array) config('onhost.payments.comgate.callback_allowlist', []);
        if ($allow !== [] && ! in_array((string) $request->ip(), $allow, true)) {
            throw new DomainError('webhook_source_rejected', 'Notification source is not allow-listed.', 403);
        }
        if (isset($payload['secret'])) {
            $creds = $this->credentials();
            if (! hash_equals((string) $creds['secret'], (string) $payload['secret'])) {
                throw new DomainError('webhook_signature_invalid', 'Comgate notification secret mismatch.', 403);
            }
        }
        $status = strtoupper((string) ($payload['status'] ?? 'PENDING'));

        return ['event_id' => $transId.':'.$status, 'provider_id' => $transId, 'state' => $status, 'amount' => isset($payload['price'], $payload['curr']) ? Money::minor((int) $payload['price'], (string) $payload['curr']) : null, 'raw' => $payload];
    }

    public function reconcile(string $periodStart, string $periodEnd): array
    {
        $items = [];
        $cursor = new \DateTimeImmutable($periodStart);
        $end = new \DateTimeImmutable($periodEnd);
        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $transfers = $this->send('GET', '/transfer', 'transfer.list', null, null, ['date' => $day]);
            foreach ((array) ($transfers['transfers'] ?? $transfers ?? []) as $transfer) {
                if (! is_array($transfer) || ! isset($transfer['transferId'])) {
                    continue;
                }
                $detail = $this->send('GET', '/transfer/'.rawurlencode((string) $transfer['transferId']), 'transfer.detail');
                foreach ((array) ($detail['items'] ?? $detail ?? []) as $row) {
                    if (! is_array($row) || ! isset($row['transId'])) {
                        continue;
                    }
                    $currency = (string) ($row['curr'] ?? $transfer['curr'] ?? 'CZK');
                    $items[] = [
                        'provider_id' => (string) $row['transId'],
                        'amount' => Money::minor((int) ($row['price'] ?? $row['amount'] ?? 0), $currency),
                        'fee' => isset($row['fee']) ? Money::minor((int) $row['fee'], $currency) : null,
                        'settled_at' => (string) ($transfer['transferDate'] ?? $day),
                        'state' => 'settled',
                        'reference' => $row['refId'] ?? null,
                    ];
                }
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $items;
    }

    /** @return array<string,mixed> */
    private function send(string $method, string $path, string $action, ?array $body = null, ?string $idempotencyKey = null, array $query = []): array
    {
        $creds = $this->credentials();
        $headers = ['Authorization' => 'Basic '.base64_encode($creds['merchant'].':'.$creds['secret'])];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'comgate', instanceKey: 'comgate', method: $method, url: rtrim((string) config('onhost.payments.comgate.base_url'), '/').$path,
            action: $action, headers: $headers, body: $body, bodyType: 'json', query: $query, timeoutSeconds: 15, critical: true, idempotent: $method === 'GET',
        ));
        $json = $response->json();
        if (! is_array($json)) {
            throw new DomainError('payment_provider_error', "Comgate returned a non-JSON response (HTTP {$response->status})", 502);
        }

        return $json;
    }

    private function assertOk(array $response, string $action): void
    {
        $code = (int) ($response['code'] ?? 0);
        if ($code !== 0) {
            throw new DomainError('payment_provider_error', "Comgate {$action} failed: [{$code}] ".($response['message'] ?? 'unknown'), 502, ['vendor_code' => $code]);
        }
    }

    /** @return array{merchant:string,secret:string} */
    private function credentials(): array
    {
        if ($this->credentials === null) {
            $values = $this->secrets->read(SecretRef::parse((string) config('onhost.payments.comgate.secret_ref')));
            $this->credentials = ['merchant' => (string) ($values['merchant'] ?? config('onhost.payments.comgate.merchant')), 'secret' => (string) ($values['secret'] ?? '')];
        }

        return $this->credentials;
    }

    private function mapMethod(string $method): string
    {
        return match (true) {
            str_starts_with($method, 'CARD') => 'card',
            str_starts_with($method, 'APPLEPAY') => 'apple_pay',
            str_starts_with($method, 'GOOGLEPAY') => 'google_pay',
            str_starts_with($method, 'BANK') => 'bank_transfer',
            default => strtolower($method),
        };
    }
}
