<?php

declare(strict_types=1);

namespace OnHost\Sdk;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * Official OnHost REST API client (PHP).
 *
 * A thin, dependency-light wrapper over the v1/v2 REST API. Authenticate with a
 * personal access token created in the panel (Účet → API tokeny). Idempotency
 * keys are attached automatically to write calls so a retried request never
 * double-charges or double-creates.
 *
 * Usage:
 *   $onhost = new \OnHost\Sdk\Client('pat_xxx');
 *   $services = $onhost->services();
 *   $onhost->topUpCredit(50000, 'CZK');
 */
final class Client
{
    private ClientInterface $http;

    public function __construct(
        string $token,
        string $baseUri = 'https://onhost.cz/api/',
        string $version = 'v1',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new HttpClient([
            'base_uri' => rtrim($baseUri, '/') . '/' . trim($version, '/') . '/',
            'headers'  => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    // ── reads ──────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function profile(): array
    {
        return $this->get('profile');
    }

    /** @return array<string, mixed> */
    public function services(): array
    {
        return $this->get('services');
    }

    /** @return array<string, mixed> */
    public function service(string $id): array
    {
        return $this->get('services/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function invoices(): array
    {
        return $this->get('invoices');
    }

    /** @return array<string, mixed> */
    public function domains(): array
    {
        return $this->get('domains');
    }

    /** @return array<string, mixed> */
    public function creditBalance(): array
    {
        return $this->get('billing/credit');
    }

    /** @return array<string, mixed> */
    public function tickets(): array
    {
        return $this->get('support/tickets');
    }

    // ── writes (idempotent) ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function topUpCredit(int $amountMinor, string $currency = 'CZK'): array
    {
        return $this->post('billing/credit/topup', [
            'amount'   => $amountMinor,
            'currency' => $currency,
        ]);
    }

    /** @return array<string, mixed> */
    public function createTicket(string $subject, string $body): array
    {
        return $this->post('support/tickets', [
            'subject' => $subject,
            'body'    => $body,
        ]);
    }

    /** @return array<string, mixed> */
    public function replyTicket(string $ticketId, string $body): array
    {
        return $this->post('support/tickets/' . rawurlencode($ticketId) . '/reply', ['body' => $body]);
    }

    // ── transport ────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->decode($this->http->request('GET', $path));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->decode($this->http->request('POST', $path, [
            RequestOptions::JSON    => $payload,
            // A retried write must not repeat its effect — the server replays
            // the stored response for a repeated key.
            RequestOptions::HEADERS => ['Idempotency-Key' => $this->idempotencyKey()],
        ]));
    }

    /** @return array<string, mixed> */
    private function decode(\Psr\Http\Message\ResponseInterface $response): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?: [];

        return $data;
    }

    private function idempotencyKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
