<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services\Gateways;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * GoPay payment gateway client.
 * Docs: https://doc.gopay.com/
 *
 * Security model:
 *  - client_secret never logged or serialized
 *  - card data NEVER touches our servers (redirect flow) → PCI DSS SAQ-A
 *  - OAuth2 Bearer token, rotated per request
 *
 * Environment:
 *  GOPAY_CLIENT_ID     — numeric client id from GoPay merchant settings
 *  GOPAY_CLIENT_SECRET — secret from GoPay merchant settings
 *  GOPAY_GO_ID         — numeric Go Id (merchant identifier)
 *  GOPAY_BASE_URL      — https://gw.sandbox.gopay.com/api or https://gate.gopay.cz/api
 */
final class GopayGateway
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $goId,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            clientId:     (string) config('gopay.client_id'),
            clientSecret: (string) config('gopay.client_secret'),
            goId:         (string) config('gopay.go_id'),
            baseUrl:      (string) config('gopay.base_url'),
        );
    }

    /**
     * Create a GoPay payment and return the redirect (gw_url) and payment id.
     *
     * @return array{paymentId: string, gwUrl: string}
     */
    public function createPayment(Invoice $invoice, string $returnUrl, string $notifyUrl): array
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('GoPay credentials not configured.');
        }

        $total    = $invoice->total;
        $amount   = $total->getMinorAmount()->toInt();
        $currency = $total->getCurrency()->getCurrencyCode();

        $response = $this->client()->post('/payments/payment', [
            'payer' => [
                'contact' => [
                    'email' => $invoice->customer->email ?? '',
                ],
            ],
            'target' => [
                'type' => 'ACCOUNT',
                'go_id' => $this->goId,
            ],
            'amount'           => $amount,
            'currency'         => $currency,
            'order_number'     => $invoice->number,
            'order_description' => "Faktura {$invoice->number}",
            'items' => [
                [
                    'type'        => 'ITEM',
                    'name'        => "Faktura {$invoice->number}",
                    'amount'      => $amount,
                    'count'       => 1,
                    'vat_rate'    => 0,
                ],
            ],
            'callback' => [
                'return_url'       => $returnUrl,
                'notification_url' => $notifyUrl,
            ],
            'lang' => 'CS',
        ]);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'unknown error');
            throw new RuntimeException("GoPay createPayment failed: {$error}");
        }

        $data = $response->json();

        return [
            'paymentId' => (string) ($data['id'] ?? ''),
            'gwUrl'     => (string) ($data['gw_url'] ?? ''),
        ];
    }

    /**
     * Fetch payment status by GoPay payment id.
     *
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $response = $this->client()->get("/payments/payment/{$paymentId}");

        if (! $response->successful()) {
            throw new RuntimeException("GoPay getPaymentStatus failed for [{$paymentId}].");
        }

        return $response->json();
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->goId !== '';
    }

    // ---------------------------------------------------------------- internals

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout((int) config('gopay.timeout', 30))
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson();
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post('/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope'      => 'payment-all',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GoPay OAuth2 token request failed.');
        }

        $this->accessToken = (string) ($response->json('access_token') ?? '');

        if ($this->accessToken === '') {
            throw new RuntimeException('GoPay OAuth2 returned empty access_token.');
        }

        return $this->accessToken;
    }
}
