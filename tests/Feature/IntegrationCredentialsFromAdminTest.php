<?php

declare(strict_types=1);

use App\Domains\Billing\Services\Gateways\ComgateGateway;
use App\Domains\Billing\Services\Gateways\GopayGateway;
use App\Domains\Billing\Services\Gateways\StripeGateway;
use App\Domains\Integrations\Models\IntegrationSetting;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Credentials entered at /admin/integrace must actually drive the payment
 * gateways (with .env as the fallback), so an operator can go live without
 * editing server env files.
 */

function gatewayProp(object $gateway, string $prop): mixed
{
    return (new ReflectionProperty($gateway, $prop))->getValue($gateway);
}

function seedIntegration(string $provider, array $credentials): void
{
    IntegrationSetting::create([
        'provider'    => $provider,
        'label'       => ucfirst($provider),
        'credentials' => $credentials,
        'is_active'   => true,
        'mock_mode'   => false,
        'dry_run'     => false,
    ]);
}

it('returns an empty credential array for a provider with no row', function (): void {
    expect(IntegrationSetting::credentialsFor('does-not-exist'))->toBe([]);
});

it('prefers admin-configured Comgate credentials over env', function (): void {
    config(['comgate.merchant_id' => 'ENV_MERCHANT', 'comgate.secret' => 'env_secret']);
    seedIntegration('comgate', ['merchant_id' => 'ADMIN_MERCHANT', 'secret' => 'admin_secret']);

    $gw = ComgateGateway::fromConfig();

    expect(gatewayProp($gw, 'merchantId'))->toBe('ADMIN_MERCHANT')
        ->and(gatewayProp($gw, 'secret'))->toBe('admin_secret');
});

it('falls back to env Comgate credentials when the admin has not set them', function (): void {
    config(['comgate.merchant_id' => 'ENV_MERCHANT', 'comgate.secret' => 'env_secret']);
    // Row exists but with no credentials → still falls back.
    seedIntegration('comgate', []);

    $gw = ComgateGateway::fromConfig();

    expect(gatewayProp($gw, 'merchantId'))->toBe('ENV_MERCHANT')
        ->and(gatewayProp($gw, 'secret'))->toBe('env_secret');
});

it('prefers admin-configured Stripe credentials over env', function (): void {
    config(['stripe.api_key' => 'env_sk', 'stripe.webhook_secret' => 'env_whsec']);
    seedIntegration('stripe', ['secret_key' => 'admin_sk_live', 'webhook_secret' => 'admin_whsec']);

    $gw = StripeGateway::fromConfig();

    expect(gatewayProp($gw, 'apiKey'))->toBe('admin_sk_live')
        ->and(gatewayProp($gw, 'webhookSecret'))->toBe('admin_whsec');
});

it('prefers admin-configured GoPay credentials over env', function (): void {
    config(['gopay.client_id' => 'env_cid', 'gopay.client_secret' => 'env_cs', 'gopay.go_id' => 'env_goid']);
    seedIntegration('gopay', ['client_id' => 'admin_cid', 'client_secret' => 'admin_cs', 'goid' => 'admin_goid']);

    $gw = GopayGateway::fromConfig();

    expect(gatewayProp($gw, 'clientId'))->toBe('admin_cid')
        ->and(gatewayProp($gw, 'clientSecret'))->toBe('admin_cs')
        ->and(gatewayProp($gw, 'goId'))->toBe('admin_goid');
});

it('keeps admin secrets out of the masked display', function (): void {
    seedIntegration('comgate', ['merchant_id' => 'M1', 'secret' => 'topsecretvalue1234']);

    $masked = IntegrationSetting::where('provider', 'comgate')->first()->maskedCredentials();

    expect($masked['secret'])->not->toContain('topsecretvalue')
        ->and($masked['secret'])->toContain('•');
});
