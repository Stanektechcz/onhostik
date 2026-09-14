<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Game versions in the order wizard (audit §5p, customer convenience): a template with versions is offered per version
 * (`key@version`, newest first); the chosen version travels as `config.version` into the service's MINECRAFT_VERSION.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('offers the Spigot versions in the wizard and carries the chosen one into the service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail(); // §5s: only templates a panel maps are offered
    $instance->forceFill(['options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 5], 'minecraft-spigot' => ['nest' => 1, 'egg' => 5, 'via_fallback' => true]]]])->save();
    $script = $this->actingAs($owner)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($script)->toContain('"key":"minecraft-spigot"')->toContain('"versions":["1.21.8","1.21.7","1.21.4","1.20.6"]')->toContain('"key":"minecraft-paper"');
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-order.api.js'));
    expect($js)->toContain("e.key + '@' + v")->toContain('config.version = os.slice(at + 1)');

    // an order with the version: the paid service carries it into the environment
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('9000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-spigot', 'version' => '1.21.8', 'label' => 'Spigot liga']]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'wv-1', $ctx)['order'];
    app(OutboxPublisher::class)->relayPending(); // the paid order is fulfilled by the outbox listener
    app(OutboxPublisher::class)->relayPending();
    expect($order->refresh()->paid_at)->not->toBeNull();
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'game')->where('label', 'Spigot liga')->first();
    expect($service)->not->toBeNull()->and($service->desired_spec['egg'])->toBe('minecraft-spigot')->and($service->desired_spec['environment']['MINECRAFT_VERSION'] ?? null)->toBe('1.21.8');
});
