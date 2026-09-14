<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Partial SLA credits (audit §5m-2): a delivery that came late but was accepted credits the customer a share of the net
 * price per day late (capped), the partner's share carries the credit, and both the SLA block and the customer hear it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('credits the customer per day late when a late delivery is accepted and takes it from the partner share', function () {
    [, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $listing = $marketplace->createListing($partner, ['key' => 'seo-audit', 'title' => 'SEO audit', 'category' => 'seo', 'price_minor' => 200000, 'delivery_days' => 3], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));
    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@eshop.cz'], ['name' => 'E-shop Petra s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $posted = fn () => app(WalletService::class)->balances($org, 'CZK')['posted']->minor;

    // on time: no credit at all
    $onTime = $marketplace->order($org, $owner, $listing, ['brief' => 'Audit, prosím.'], $ctx);
    $marketplace->start($onTime, $partner, CommandContext::system('test'));
    $marketplace->deliver($onTime, $partner, 'Hotovo.', CommandContext::system('test'));
    expect($marketplace->daysLate($onTime->refresh()))->toBe(0)->and($marketplace->lateCredit($onTime)->minor)->toBe(0);
    $before = $posted();
    $marketplace->accept($onTime, $org, $ctx);
    expect($posted())->toBe($before)->and($onTime->refresh()->late_credit_minor)->toBe(0);
    $shareOnTime = (int) $onTime->partner_minor;

    // two days late: 2 × 5 % of the net price = 200 Kč credited, the partner share 200 Kč lighter, the commission row follows
    $late = $marketplace->order($org, $owner, $listing, ['brief' => 'Druhý audit.'], $ctx);
    $marketplace->start($late, $partner, CommandContext::system('test'));
    $this->travelTo(now()->addDays(5));
    $marketplace->deliver($late, $partner, 'Hotovo, omlouváme se.', CommandContext::system('test'));
    $late->refresh();
    expect($marketplace->daysLate($late))->toBe(2)->and($marketplace->lateCredit($late))->toEqual(Money::minor(20000, 'CZK'));
    expect($marketplace->sla($late))->toMatchArray(['days_late' => 2]);
    $before = $posted();
    $marketplace->accept($late, $org, $ctx);
    app(OutboxPublisher::class)->relayPending();
    $this->travelBack();
    $late->refresh();
    expect($posted())->toBe($before + 20000)->and($late->late_credit_minor)->toBe(20000)->and((int) $late->partner_minor)->toBe($shareOnTime - 20000);
    expect(WalletTopup::query()->where('organization_id', $org->id)->where('source', 'marketplace')->where('note', 'Kredit za pozdní dodání')->where('bucket', 'purchased')->count())->toBe(1);
    expect(PartnerCommission::query()->where('kind', 'marketplace')->where('invoice_id', $late->invoice_id)->value('amount_minor'))->toBe($shareOnTime - 20000);
    expect($marketplace->sla($late)['late_credit'])->toEqual(Money::minor(20000, 'CZK'));
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.late_credit')->where('body', 'like', '%2 dní%')->exists())->toBeTrue();
    $this->actingAs($owner, 'sanctum');
    $row = collect($this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/account/marketplace/orders')->assertOk()->json('data'))->firstWhere('id', $late->id);
    expect($row['sla'])->toMatchArray(['days_late' => 2])->and($row['sla']['late_credit']['minor'])->toBe(20000);

    // the cap and the knobs: twenty days late stays at 50 %, a zero rate switches the credit off, the partner never goes negative
    $probe = MarketplaceOrder::query()->findOrFail($late->id);
    $probe->forceFill(['due_at' => now()->subDays(20), 'delivered_at' => now()]);
    expect($marketplace->daysLate($probe))->toBe(20)->and($marketplace->lateCredit($probe)->minor)->toBe(100000);
    config()->set('onhost.marketplace.late_credit_cap_pct', 20);
    expect($marketplace->lateCredit($probe)->minor)->toBe(40000);
    config()->set('onhost.marketplace.late_credit_pct_per_day', 0);
    expect($marketplace->lateCredit($probe)->minor)->toBe(0);
});
