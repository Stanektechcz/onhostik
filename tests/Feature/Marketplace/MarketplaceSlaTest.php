<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Marketplace delivery SLA (audit §5l-2): a delivery past its due date warns the partner and the customer once; after the
 * grace the customer may take the refund without a dispute; a delivery before the grace keeps everything as it was.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('warns once past the due date and offers a dispute-free refund after the grace', function () {
    [$partnerOwner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $listing = $marketplace->createListing($partner, ['key' => 'seo-audit', 'title' => 'SEO audit', 'category' => 'seo', 'price_minor' => 200000, 'delivery_days' => 3], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));
    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@eshop.cz'], ['name' => 'E-shop Petra s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $posted = fn () => app(WalletService::class)->balances($org, 'CZK')['posted']->minor;
    $order = $marketplace->order($org, $owner, $listing, ['brief' => 'Audit našeho e-shopu, prosím.'], $ctx);
    $marketplace->start($order, $partner, CommandContext::system('test'));
    expect($marketplace->sla($order->refresh()))->toMatchArray(['overdue' => false, 'refund_available' => false, 'grace_days' => 7]);

    // before the due date nothing happens; the day after, both sides hear it once
    expect($marketplace->sweepOverdue(now()->addDays(2)))->toBe(['warned' => 0, 'offered' => 0]);
    expect($marketplace->sweepOverdue(now()->addDays(4)))->toBe(['warned' => 1, 'offered' => 0]);
    expect($marketplace->sweepOverdue(now()->addDays(5)))->toBe(['warned' => 0, 'offered' => 0]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'marketplace.overdue')->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.delayed')->count())->toBe(1);
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $this->withHeaders($h + ['Idempotency-Key' => 'sla-0'])->postJson("/v1/account/marketplace/orders/{$order->id}/cancel")->assertStatus(409)->assertJsonPath('error', 'marketplace_order_started'); // started: no refund yet
    $this->travelTo(now()->addDays(4));
    expect($this->withHeaders($h)->getJson('/v1/account/marketplace/orders')->assertOk()->json('data.0.sla'))->toMatchArray(['overdue' => true, 'days_overdue' => 1, 'refund_available' => false]);
    $this->travelBack();

    // after the grace the refund is offered and the customer takes it without a dispute
    expect($marketplace->sweepOverdue(now()->addDays(3 + 7)))->toBe(['warned' => 0, 'offered' => 1]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.refund_offered')->count())->toBe(1);
    expect($marketplace->sla($order->refresh())['refund_available'])->toBeTrue();
    $before = $posted();
    $this->withHeaders($h + ['Idempotency-Key' => 'sla-1'])->postJson("/v1/account/marketplace/orders/{$order->id}/cancel")->assertOk()->assertJsonPath('state', 'cancelled');
    expect($posted())->toBe($before + 242000)->and(Invoice::query()->where('type', 'credit_note')->where('corrects_invoice_id', $order->invoice_id)->exists())->toBeTrue();
    expect($marketplace->sweepOverdue(now()->addDays(20)))->toBe(['warned' => 0, 'offered' => 0]); // nothing open any more

    // a delivery inside the grace ends the offer path: an accepted order is never refundable this way
    $second = $marketplace->order($org, $owner, $listing, ['brief' => 'Druhý audit prosím.'], $ctx);
    $marketplace->deliver($second, $partner, 'Hotovo.', CommandContext::system('test'));
    expect($marketplace->sweepOverdue(now()->addDays(20)))->toBe(['warned' => 0, 'offered' => 0])->and($marketplace->sla($second->refresh())['overdue'])->toBeFalse();
    expect(MarketplaceOrder::query()->findOrFail($second->id)->refund_offered_at)->toBeNull();
    $this->artisan('onhost:marketplace:sla')->assertSuccessful();
});
