<?php

declare(strict_types=1);

use App\Domains\Integrations\Clients\CloudflareClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Cloudflare client — mock-gated like the other providers. HTTP is faked, so we
 * verify request construction, the refusal gates, and that the api_token travels
 * only as a Bearer header (never in a URL / never logged).
 */

function cfSetting(array $overrides = []): IntegrationSetting
{
    return IntegrationSetting::create(array_merge([
        'provider'    => 'cloudflare',
        'label'       => 'Cloudflare',
        'credentials' => ['api_token' => 'cf-secret-token', 'zone_id' => 'zone123'],
        'is_active'   => true,
        'mock_mode'   => false,
        'dry_run'     => false,
    ], $overrides));
}

it('does no HTTP in mock mode and reports a dry-run connection', function (): void {
    Http::fake();
    $client = new CloudflareClient(cfSetting(['mock_mode' => true]));

    $result = $client->connectionTest();

    expect($result['ok'])->toBeTrue()->and($result['dry_run'])->toBeTrue();
    Http::assertNothingSent();
});

it('verifies the token against the real API when active', function (): void {
    config(['integrations.real_write_gates.cloudflare' => true]);
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['status' => 'active']], 200)]);

    $result = (new CloudflareClient(cfSetting()))->connectionTest();

    expect($result['ok'])->toBeTrue()->and($result['dry_run'])->toBeFalse();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/user/tokens/verify')
            && $request->hasHeader('Authorization', 'Bearer cf-secret-token')
            && ! str_contains($request->url(), 'cf-secret-token'); // token never in URL
    });
});

it('creates a DNS record when the write gate is open', function (): void {
    config(['integrations.real_write_gates.cloudflare' => true]);
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['id' => 'rec1', 'name' => 'a.example.com']], 200)]);

    $record = (new CloudflareClient(cfSetting()))->createDnsRecord('zone123', 'A', 'a.example.com', '1.2.3.4');

    expect($record['id'])->toBe('rec1');
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_contains($r->url(), '/zones/zone123/dns_records')
        && $r['type'] === 'A' && $r['content'] === '1.2.3.4');
});

it('refuses a real write when the env gate is closed', function (): void {
    config(['integrations.real_write_gates.cloudflare' => false]);
    Http::fake();

    expect(fn () => (new CloudflareClient(cfSetting()))->createDnsRecord('zone123', 'A', 'a.example.com', '1.2.3.4'))
        ->toThrow(ProvisioningException::class);

    Http::assertNothingSent();
});

it('returns a simulated result for a write in dry-run without any HTTP', function (): void {
    Http::fake();

    $record = (new CloudflareClient(cfSetting(['dry_run' => true])))
        ->createDnsRecord('zone123', 'A', 'a.example.com', '1.2.3.4');

    expect($record['dry_run'])->toBeTrue();
    Http::assertNothingSent();
});

it('is wired into the admin connection tester', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['status' => 'active']], 200)]);
    config(['integrations.real_write_gates.cloudflare' => true]);

    $result = app(\App\Domains\Integrations\Services\ConnectionTester::class)->test(cfSetting());

    expect($result['ok'])->toBeTrue();
});
