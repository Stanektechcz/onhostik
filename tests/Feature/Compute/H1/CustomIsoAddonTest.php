<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Platform\GoLiveChecks;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\CustomIso\CustomIsoLibrary;
use Onhost\Domain\Services\CustomIso\CustomIsoPolicy;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * H1 (phase H, TASK-0121): a custom ISO bought as an add-on of a VPS whose plan does not sell it (owner decision G-R5 says "only
 * when the ordered VPS plan has it" — the add-on is a paid part of that plan). It goes through the `Addons` registry like every
 * other add-on: buying it patches the parent's `custom_iso` and `custom_iso_max_mb` and writes down what they were; cancelling
 * it gives back exactly that, so the server stops offering uploads and attaches (the ways out — detach and delete — stay open).
 * The organization's quota is the same with or without it. It is seeded as a draft: the owner prices and publishes it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']); // provisioning of the parent is not the subject here
});

/** @return array{0:Service, 1:Service} the VPS and the custom ISO add-on bought for it in one order */
function h1IsoAddonPair(object $owner, object $org, CommandContext $context, string $parentProduct = 'vps', string $parentPlan = 'compute-2', array $parentConfig = []): array
{
    app(WalletService::class)->topup($org, Money::decimal('900000', 'CZK'), 'bank', 'h1-iso-seed-'.Str::random(6), $context);
    $quote = app(QuoteService::class)->quote([
        ['line_id' => 'p', 'product_key' => $parentProduct, 'plan_key' => $parentPlan, 'config' => $parentConfig],
        ['line_id' => 'a', 'product_key' => 'custom-iso', 'plan_key' => 'iso-4g', 'config' => ['parent_line_id' => 'p']],
    ], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'h1-iso-'.Str::random(8), $context)['order'];
    $items = OrderItem::query()->where('order_id', $order->id)->get()->keyBy(fn (OrderItem $i) => (string) $i->config['line_id']);
    $services = app(ServiceService::class);
    $parent = $services->createFromOrderItem($items['p'], $order, $context);
    $addon = $services->createFromOrderItem($items['a'], $order, $context);

    return [$parent->fresh(), $addon->fresh()];
}

function h1PublishIsoAddon(): void
{
    Product::query()->where('key', 'custom-iso')->update(['state' => 'active']); // the owner's decision: price and publish
}

it('is seeded as a draft the platform knows how to deliver, offered only to a VPS once it is published', function () {
    expect(Product::query()->where('key', 'custom-iso')->value('state'))->toBe('draft')
        ->and(Addons::sellable('custom-iso'))->toBeTrue()
        ->and(Addons::unsellable())->toBe([])
        ->and(app(PricingRules::class)->addonProducts('vps'))->not->toContain('custom-iso'); // a draft is offered nowhere

    h1PublishIsoAddon();
    expect(app(PricingRules::class)->addonProducts('vps'))->toContain('custom-iso')
        ->and(app(PricingRules::class)->addonProducts('vds'))->toContain('custom-iso')
        ->and(app(PricingRules::class)->addonProducts('web-hosting'))->not->toContain('custom-iso');
});

it('gives a VPS whose plan has no custom ISO the feature when the add-on is bought, and takes it back when it is cancelled', function () {
    h1PublishIsoAddon();
    [$owner, $org] = $this->customerWithOrganization();
    [$parent, $addon] = h1IsoAddonPair($owner, $org, $this->contextFor($owner, $org));

    expect($addon->family)->toBe('addon')->and(data_get($addon->tags, 'parent_service_id'))->toBe($parent->id)
        ->and($parent->entitlements['custom_iso'] ?? null)->toBeTrue()
        ->and($parent->entitlements['custom_iso_max_mb'] ?? null)->toBe(4096)
        ->and(CustomIsoPolicy::inPlan($parent))->toBeTrue()
        ->and(data_get($addon->tags, 'addon.before'))->toBe(['custom_iso' => null, 'custom_iso_max_mb' => null]); // the plan had neither

    // the quota is the organization's and the add-on does not raise it
    $listing = CustomIsoLibrary::listing($parent);
    expect($listing['offered'])->toBeTrue()
        ->and($listing['quota']['quota_bytes'])->toBe(CustomIsoPolicy::quotaBytes())
        ->and($listing['quota']['max_images'])->toBe(CustomIsoPolicy::maxImages())
        ->and($listing['max_bytes'])->toBe(min(4096, (int) config('onhost.custom_iso.scan_max_mb')) * 1048576);

    app(ServiceService::class)->requestAction($addon, 'terminate', CommandContext::system('test')->withScope($org->id), 'h1-iso-off', ['reason' => 'už nepotřebuji']);
    $parent = $parent->fresh();
    expect($parent->entitlements)->not->toHaveKey('custom_iso')->not->toHaveKey('custom_iso_max_mb')
        ->and(CustomIsoPolicy::inPlan($parent))->toBeFalse()
        ->and($addon->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(fn () => CustomIsoPolicy::assertInPlan($parent))->toThrow(DomainError::class);
});

it('leaves the feature alone on cancellation when somebody changed it after the add-on was bought', function () {
    h1PublishIsoAddon();
    [$owner, $org] = $this->customerWithOrganization();
    $context = $this->contextFor($owner, $org);
    [$parent, $addon] = h1IsoAddonPair($owner, $org, $context);
    // a plan change meanwhile sold the feature with a bigger image: the cancellation must not take it away
    $parent->forceFill(['entitlements' => array_replace((array) $parent->entitlements, ['custom_iso_max_mb' => 8192])])->save();

    app(ServiceService::class)->requestAction($addon, 'terminate', CommandContext::system('test')->withScope($org->id), 'h1-iso-off-2', ['reason' => 'konec']);
    // the switch and its size are one decision: somebody changed it since, so neither half is taken back
    expect($parent->fresh()->entitlements['custom_iso_max_mb'])->toBe(8192)
        ->and($parent->fresh()->entitlements['custom_iso'])->toBeTrue()
        ->and(CustomIsoPolicy::inPlan($parent->fresh()))->toBeTrue()
        ->and(data_get($addon->fresh()->tags, 'addon.kept'))->toEqualCanonicalizing(['custom_iso', 'custom_iso_max_mb']);
});

it('refuses to apply the add-on to anything but a cloud server', function () {
    $web = new Service(['family' => 'web', 'product_key' => 'web-hosting', 'entitlements' => ['sites' => 1]]);
    $addon = new Service(['family' => 'addon', 'product_key' => 'custom-iso', 'entitlements' => ['custom_iso' => true, 'custom_iso_max_mb' => 4096], 'tags' => []]);

    expect(fn () => app(Addons::class)->apply($web, $addon))->toThrow(DomainError::class, 'cloud');
});

it('asks the doctor nothing about the virus scan while the add-on is a draft, and everything once it is published', function () {
    app()->instance(IsoScanner::class, new class implements IsoScanner
    {
        public function scan($stream): array
        {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }

        public function selfTest(): array
        {
            return ['ok' => false, 'detail' => 'no clamd is configured'];
        }
    });
    $row = fn () => (array) collect((new GoLiveChecks)->rows())->firstWhere('check', 'custom ISO virus scan passes its self-test');

    expect($row()['ok'])->toBeTrue()->and($row()['detail'])->toContain('no plan sells'); // a draft sells nothing: no new FAIL

    h1PublishIsoAddon();
    expect($row()['ok'])->toBeFalse(); // on sale: the scan it needs is asked for
});
