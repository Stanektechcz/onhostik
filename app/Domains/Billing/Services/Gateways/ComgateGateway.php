<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services\Gateways;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Comgate payment gateway client.
 * Docs: https://apidoc.comgate.cz/
 *
 * Security model:
 *  - secret never logged, never serialized
 *  - card data NEVER touches our servers (redirect flow) → PCI DSS SAQ-A
 *  - webhook verified by IP whitelist + transId lookup against our DB
 */
final class ComgateGateway
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $secret,
        private readonly bool $testMode,
        private readonly string $baseUrl,
    ) {}

    /**
     * Admin-managed credentials (set at /admin/integrace) take precedence;
     * .env is the fallback so existing deployments keep working.
     */
    public static function fromConfig(): self
    {
        $creds = \App\Domains\Integrations\Models\IntegrationSetting::credentialsFor('comgate');

        return new self(
            merchantId: ($creds['merchant_id'] ?? '') ?: (string) config('comgate.merchant_id'),
            secret: ($creds['secret'] ?? '') ?: (string) config('comgate.secret'),
            testMode: (bool) config('comgate.test_mode'),
            baseUrl: (string) config('comgate.base_url'),
        );
    }

    /**
     * Create a payment and return the redirect URL.
     *
     * @return array{transId: string, redirect: string}
     */
    public function createPayment(Invoice $invoice, string $returnUrl): array
    {
        if ($this->merchantId === '' || $this->secret === '') {
            throw new RuntimeException('Comgate credentials not configured (merchant_id or secret is empty).');
        }

        $total = $invoice->total;

        $response = $this->client()->post('/create', [
            'merchant'   => $this->merchantId,
            'test'       => $this->testMode ? 'true' : 'false',
            'price'      => $total->getMinorAmount()->toInt(),     // Comgate expects minor units
            'curr'       => $total->getCurrency()->getCurrencyCode(),
            'label'      => "Faktura {$invoice->number}",
            'refId'      => $invoice->uuid,
            'method'     => 'ALL',
            'email'      => $invoice->customer->email,
            'prepareOnly' => 'true',
            'url_paid'      => $returnUrl . '?status=paid',
            'url_cancelled' => $returnUrl . '?status=cancelled',
            'url_pending'   => $returnUrl . '?status=pending',
        ]);

        $data = $this->parseResponse($response->body());

        if (($data['code'] ?? null) !== '0') {
            throw new RuntimeException(
                'Comgate create failed: ' . ($data['message'] ?? 'unknown error')
            );
        }

        return [
            'transId'  => $data['transId'],
            'redirect' => $data['redirect'],
        ];
    }

    /**
     * Query payment status by transaction id (used for webhook verification).
     *
     * @return array<string, mixed>
     */
    public function getStatus(string $transId): array
    {
        $response = $this->client()->post('/status', [
            'merchant' => $this->merchantId,
            'transId'  => $transId,
        ]);

        return $this->parseResponse($response->body());
    }

    /**
     * Verify a webhook request:
     *  1. source IP must be in Comgate whitelist (if configured)
     *  2. status MUST be re-fetched server-to-server — never trust the
     *     inbound payload alone.
     */
    public function verifyWebhookSource(string $ip): bool
    {
        $whitelist = config('comgate.webhook_ip_whitelist', []);

        // Empty whitelist => verification deferred to status re-fetch only.
        return $whitelist === [] || in_array($ip, $whitelist, true);
    }

    // ---------------------------------------------------------------- internals

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->asForm()
            ->timeout((int) config('comgate.timeout', 30))
            ->retry((int) config('comgate.retry_attempts', 3), 1000)
            ->withOptions(['verify' => true])
            ->withBasicAuth($this->merchantId, $this->secret);
    }

    /**
     * Comgate answers in application/x-www-form-urlencoded format.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(string $body): array
    {
        parse_str($body, $data);

        return $data;
    }
}
