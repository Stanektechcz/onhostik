<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services\Gateways;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout Session gateway client (redirect flow).
 *
 * Docs: https://stripe.com/docs/api/checkout/sessions
 *
 * Security model:
 *  - api_key never logged (only 'api_key_set: true' is safe to log)
 *  - webhook_secret never logged or serialized
 *  - card data NEVER touches our servers (Stripe-hosted Checkout) → PCI DSS SAQ-A
 *  - webhook authenticated by HMAC-SHA256 signature verification
 *
 * Environment:
 *  STRIPE_SECRET_KEY    — sk_test_... or sk_live_...
 *  STRIPE_PUBLIC_KEY    — pk_test_... or pk_live_... (frontend only)
 *  STRIPE_WEBHOOK_SECRET — whsec_... (signing secret from Stripe dashboard)
 */
final class StripeGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $webhookSecret,
        private readonly string $baseUrl,
    ) {}

    /**
     * Admin-managed credentials (set at /admin/integrace) take precedence;
     * .env is the fallback so existing deployments keep working.
     */
    public static function fromConfig(): self
    {
        $creds = \App\Domains\Integrations\Models\IntegrationSetting::credentialsFor('stripe');

        return new self(
            apiKey:        ($creds['secret_key'] ?? '') ?: (string) config('stripe.api_key'),
            webhookSecret: ($creds['webhook_secret'] ?? '') ?: (string) config('stripe.webhook_secret'),
            baseUrl:       (string) config('stripe.base_url'),
        );
    }

    /**
     * Create a Stripe Checkout Session and return the hosted payment URL.
     *
     * @return array{sessionId: string, url: string}
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException(
                'Stripe not configured — set STRIPE_SECRET_KEY in .env (api_key_set: false).'
            );
        }

        $total    = $invoice->total;
        $currency = mb_strtolower($total->getCurrency()->getCurrencyCode());
        $amount   = $total->getMinorAmount()->toInt();

        $response = $this->client()->post('/checkout/sessions', [
            'mode'                      => 'payment',
            'payment_method_types[]'    => 'card',
            'line_items[0][price_data][currency]'                  => $currency,
            'line_items[0][price_data][unit_amount]'               => $amount,
            'line_items[0][price_data][product_data][name]'        => "Faktura {$invoice->number}",
            'line_items[0][price_data][product_data][description]' => "OnHost {$invoice->number}",
            'line_items[0][quantity]'   => 1,
            'client_reference_id'       => $invoice->uuid,
            'customer_email'            => $invoice->customer?->email,
            'success_url'               => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'                => $cancelUrl . '?status=cancelled',
            'metadata[invoice_uuid]'    => $invoice->uuid,
            'metadata[invoice_number]'  => $invoice->number,
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'unknown error');

            throw new RuntimeException("Stripe createCheckoutSession failed: {$error}");
        }

        $data = $response->json();

        return [
            'sessionId' => $data['id'],
            'url'       => $data['url'],
        ];
    }

    /**
     * Retrieve a Checkout Session by ID to confirm its payment_status.
     *
     * @return array<string, mixed>
     */
    public function retrieveSession(string $sessionId): array
    {
        $response = $this->client()->get("/checkout/sessions/{$sessionId}");

        if (! $response->successful()) {
            throw new RuntimeException("Stripe retrieveSession failed for [{$sessionId}].");
        }

        return $response->json();
    }

    /**
     * Verify and parse a Stripe webhook event.
     *
     * Stripe uses a timestamp + HMAC-SHA256 signature scheme documented at:
     * https://stripe.com/docs/webhooks/signatures
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the signature is invalid or replayed
     */
    public function constructEvent(string $rawPayload, string $sigHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new RuntimeException(
                'Stripe webhook_secret is not configured (webhook_secret: not set).'
            );
        }

        // Parse `t=<timestamp>,v1=<sig1>,v1=<sig2>,...`
        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            [$k, $v] = explode('=', $item, 2) + ['', ''];
            $parts[$k][] = $v;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if (abs(time() - $timestamp) > 300) {
            throw new RuntimeException('Stripe webhook timestamp too old — possible replay attack.');
        }

        $signedPayload = "{$timestamp}.{$rawPayload}";
        $expected      = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $valid = false;
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }

        $event = json_decode($rawPayload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Stripe webhook payload is not valid JSON.');
        }

        return $event;
    }

    /** Whether real API calls are allowed (keys configured + test_mode off). */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    // ---------------------------------------------------------------- internals

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout((int) config('stripe.timeout', 30))
            ->retry((int) config('stripe.retry_attempts', 3), 1000)
            ->withToken($this->apiKey)
            ->asForm();
    }
}
