<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('gives an account without a customer profile the catalogue and lets it create the profile before ordering (audit §5z)', function () {
    $user = User::query()->create(['name' => 'Bez Profilu', 'email' => 'bez-profilu@onhost.test', 'password' => 'Velmi-dlouhe-heslo-2026', 'locale' => 'cs', 'timezone' => 'Europe/Prague', 'is_staff' => false, 'state' => 'active', 'email_verified_at' => now()]);
    $this->actingAs($user);
    $seam = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    expect($payload['needs_organization'])->toBeTrue()->and($payload['catalog'])->not->toBeEmpty()->and($payload['consents'])->toHaveKey('terms')->and($payload)->toHaveKey('game_config');

    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/organizations', ['name' => 'Firma', 'type' => 'company', 'street' => 'Vodičkova 12', 'city' => 'Praha', 'postal_code' => '11000'])->assertUnprocessable()->assertJsonValidationErrors(['ico']);
    $created = $this->withHeader('Idempotency-Key', 'org-1')->postJson('/v1/organizations', ['name' => 'Bez Profilu', 'type' => 'person', 'street' => 'Vodičkova 12', 'city' => 'Praha', 'postal_code' => '11000'])->assertCreated();
    $orgId = $created->json('organization_id');
    expect(OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $orgId)->value('role_key'))->toBe('owner');

    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start']], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->postJson('/v1/cart/quote')->assertOk();
    expect($quote->json('required_documents') ?? $quote->json('data.required_documents'))->toContain('terms');
});

it('pins a service to a named panel only for staff and operator orders, and sends the Vanilla version to its egg variable (audit §5z)', function () {
    [, $org] = $this->customerWithOrganization();
    $aapanel = ProviderInstance::query()->create(['key' => 'aapanel-test', 'provider' => 'aapanel', 'name' => 'aaPanel test', 'base_url' => 'https://aapanel.test:7800', 'region_code' => null, 'state' => 'active', 'options' => [], 'secret_ref' => 'db://test/aapanel']);
    $version = Product::query()->where('key', 'web-hosting')->firstOrFail()->plans()->where('key', 'start')->firstOrFail()->versions()->firstOrFail();
    $product = Product::query()->where('key', 'web-hosting')->firstOrFail();
    $services = app(ServiceService::class);
    $ctx = CommandContext::system('test');

    $order = Order::query()->create(['number' => 'OH-T-1', 'organization_id' => $org->id, 'state' => 'PAID', 'currency' => 'CZK', 'subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'payment_mode' => 'wallet', 'source' => 'cli', 'placed_at' => now(), 'idempotency_key' => 't-1']);
    $item = OrderItem::query()->create(['order_id' => $order->id, 'sku' => 'start', 'product_key' => 'web-hosting', 'name' => 'Webhosting', 'qty' => 1, 'unit_net_minor' => 0, 'discount_minor' => 0, 'tax_rate' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'period' => 'month', 'config' => [], 'state' => 'pending']);
    $pinned = $services->create($org, $product, $version, ['placement_instance' => 'aapanel-test'], $ctx, $item);
    expect($pinned->desired_spec['placement']['instance_key'])->toBe('aapanel-test')->and($pinned->desired_spec['executor'])->toBe('aapanel');

    $webOrder = Order::query()->create(['number' => 'OH-T-2', 'organization_id' => $org->id, 'state' => 'PAID', 'currency' => 'CZK', 'subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'payment_mode' => 'wallet', 'source' => 'panel', 'placed_at' => now(), 'idempotency_key' => 't-2']);
    $webItem = OrderItem::query()->create(['order_id' => $webOrder->id, 'sku' => 'start', 'product_key' => 'web-hosting', 'name' => 'Webhosting', 'qty' => 1, 'unit_net_minor' => 0, 'discount_minor' => 0, 'tax_rate' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'period' => 'month', 'config' => [], 'state' => 'pending']);
    $customer = $services->create($org, $product, $version, ['placement_instance' => 'aapanel-test'], $ctx, $webItem);
    expect($customer->desired_spec['placement']['instance_key'] ?? null)->not->toBe('aapanel-test'); // a customer's cart cannot choose the panel

    $game = Product::query()->where('key', 'game')->firstOrFail();
    $gameVersion = $game->plans()->where('key', 'game-custom')->firstOrFail()->versions()->firstOrFail();
    $server = $services->create($org, $game, $gameVersion, ['egg' => 'minecraft-vanilla', 'version' => '1.21.8', 'options' => ['ram_gb' => 2]], $ctx);
    expect($server->desired_spec['environment'])->toMatchArray(['VANILLA_VERSION' => '1.21.8'])->not->toHaveKey('MINECRAFT_VERSION');
    expect(config('onhost.game.eggs.minecraft-vanilla.versions'))->toContain('1.21.8');
});
