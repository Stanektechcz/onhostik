<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * A paid add-on changes the service it was bought for (audit §5ac). An add-on has no resource of its own — it is a
 * billing row whose whole job is to change its parent. Five of the seven add-ons on sale changed nothing at all:
 * hourly backups, mailboxes, the CDN, the OV certificate and the anti-DDoS profile were charged every month and never
 * delivered. The two that did something wrote a backup policy in a shape `BackupScheduler` cannot read, so even those
 * customers kept the schedule of their own plan. And none of them could be cancelled: an add-on has no provider
 * binding, so the identity check that guards every deletion refused it — the subscription went on billing.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']); // provisioning of the parent is not the subject here
});

/**
 * One order: the service and the add-on bought for it, both delivered.
 *
 * @return array{0:Service, 1:Service, 2:Organization, 3:CommandContext}
 */
function addonPair(object $owner, object $org, CommandContext $context, string $parentProduct, string $parentPlan, string $addonProduct, string $addonPlan, array $parentConfig = []): array
{
    app(WalletService::class)->topup($org, Money::decimal('900000', 'CZK'), 'bank', 'addon-seed-'.Str::random(6), $context);
    $quote = app(QuoteService::class)->quote([
        ['line_id' => 'p', 'product_key' => $parentProduct, 'plan_key' => $parentPlan, 'config' => $parentConfig],
        ['line_id' => 'a', 'product_key' => $addonProduct, 'plan_key' => $addonPlan, 'config' => ['parent_line_id' => 'p']],
    ], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'addon-'.Str::random(8), $context)['order'];
    $items = OrderItem::query()->where('order_id', $order->id)->get()->keyBy(fn (OrderItem $i) => (string) $i->config['line_id']);
    $services = app(ServiceService::class);
    $parent = $services->createFromOrderItem($items['p'], $order, $context);
    $addon = $services->createFromOrderItem($items['a'], $order, $context);

    return [$parent->fresh(), $addon->fresh()];
}

it('gives a VPS the hourly schedule its backup add-on sells, in the words the scheduler reads', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$parent, $addon] = addonPair($owner, $org, $this->contextFor($owner, $org), 'vps', 'compute-4', 'backup-hourly', 'hourly-30');

    expect($addon->family)->toBe('addon')->and(data_get($addon->tags, 'parent_service_id'))->toBe($parent->id);
    $policy = BackupPolicy::query()->where('service_id', $parent->id)->sole(); // against the old code there is no row at all
    expect($policy->schedule['frequency'] ?? null)->toBe('hourly')
        ->and(BackupScheduler::FREQUENCIES[$policy->schedule['frequency'] ?? ''] ?? null)->toBe(60) // the scheduler knows this word
        ->and($policy->retention['days'] ?? null)->toBe(30)
        ->and($policy->retention['generations'] ?? null)->toBe(720) // an hour apart for thirty days
        ->and($policy->offsite)->toBeTrue();
});

it('turns the generations of the daily backup add-on into the days and copies the scheduler asks for', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$parent] = addonPair($owner, $org, $this->contextFor($owner, $org), 'vps', 'compute-4', 'backup-plus', 'backup-30');

    $policy = BackupPolicy::query()->where('service_id', $parent->id)->sole();
    // the old row said schedule: {daily: '02:30'} and retention: {daily: 30, weekly: 4, monthly: 6} — the scheduler reads
    // schedule.frequency and retention.days/generations, so it found neither and fell back to the plan's own daily/7/7
    expect($policy->schedule['frequency'] ?? null)->toBe('daily')
        ->and($policy->retention['days'] ?? null)->toBe(30)
        ->and($policy->retention['generations'] ?? null)->toBe(40)
        ->and($policy->restore_test['cadence'] ?? null)->toBe('monthly');
});

it('puts the mailboxes and the CDN of an add-on on the service they were bought for, and takes them back when it is cancelled', function () {
    $start = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    [$owner, $org] = $this->customerWithOrganization();
    [$parent, $addon] = addonPair($owner, $org, $this->contextFor($owner, $org), 'web-hosting', 'start', 'mail-hosting', 'basic', ['fqdn' => 'doplnky.cz']);

    expect($parent->entitlements['mailboxes'])->toBe((int) $start->entitlements['mailboxes'] + 5) // five on top of what the plan sells
        ->and($parent->entitlements['dkim'] ?? null)->toBeTrue()
        ->and(data_get($addon->tags, 'addon.before.mailboxes'))->toBe((int) $start->entitlements['mailboxes']); // what it replaced, for the cancellation

    // cancelling the add-on: against the old code the identity check refused it (409) and the subscription billed on
    app(ServiceService::class)->requestAction($addon, 'terminate', CommandContext::system('test')->withScope($org->id), 'addon-off-1', ['reason' => 'nechci schránky']);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe((int) $start->entitlements['mailboxes'])
        ->and($parent->fresh()->entitlements)->not->toHaveKey('dkim')
        ->and($addon->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED);

    [$site, $cdn] = addonPair($owner, $org, $this->contextFor($owner, $org), 'web-hosting', 'start', 'cdn', 'shield', ['fqdn' => 'zrychleny.cz']);
    expect($site->entitlements['cdn'])->toBeTrue()->and($site->entitlements['waf'])->toBe('custom rules')
        ->and(data_get($cdn->tags, 'addon.before.waf'))->toBe($start->entitlements['waf']); // the plan's own WAF comes back on cancellation
});

it('leaves alone what somebody changed after the add-on was bought', function () {
    $start = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    [$owner, $org] = $this->customerWithOrganization();
    [$parent, $addon] = addonPair($owner, $org, $this->contextFor($owner, $org), 'web-hosting', 'start', 'mail-hosting', 'basic', ['fqdn' => 'zmeneno.cz']);
    $parent->forceFill(['entitlements' => array_replace((array) $parent->entitlements, ['mailboxes' => 50])])->save(); // a plan change, a staff correction

    app(ServiceService::class)->requestAction($addon->fresh(), 'terminate', CommandContext::system('test')->withScope($org->id), 'addon-off-2', ['reason' => 'konec']);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe(50) // not (int) $start + 5 - 5, and not the value from before the add-on
        ->and(data_get($addon->fresh()->tags, 'addon.kept'))->toBe(['mailboxes']);
});

it('sells no add-on the platform cannot deliver', function () {
    // the list is code: whoever puts an add-on on sale has to pass this
    expect(Addons::unsellable())->toBe([]);
    foreach (Product::query()->where('family', 'addon')->where('state', 'active')->pluck('key') as $key) {
        expect(Addons::sellable((string) $key))->toBeTrue("{$key} is on sale and nothing applies it");
    }
    // an OV certificate is ordered at no registrar and an L7 profile reaches no router: neither is on sale, and neither is offered
    foreach (['ssl', 'anti-ddos-pro'] as $notYet) {
        expect(Product::query()->where('key', $notYet)->value('state'))->toBe('draft');
    }
    expect(app(PricingRules::class)->addonProducts('web-hosting'))->not->toContain('ssl')->toContain('mail-hosting');

    $unknown = new Service(['product_key' => 'anti-ddos-pro', 'entitlements' => []]);
    expect(fn () => Addons::assertSellable((string) $unknown->product_key))->toThrow(DomainError::class);
});
