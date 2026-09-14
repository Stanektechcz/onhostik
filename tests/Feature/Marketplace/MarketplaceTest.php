<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Marketplace of partner services (audit §5j-1): a partner lists, staff publish, a customer orders from credit (step-up),
 * the platform issues the paid tax document and keeps its share, the partner delivers, the customer accepts — the
 * partner's share becomes a payable commission. A dispute lands with support, a refund returns the credit with a
 * credit note; deliveries nobody answered count as accepted after the window.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('runs listing → publish → order → deliver → accept with the commission, and refunds a disputed order', function () {
    [$partnerOwner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@eshop.cz'], ['name' => 'E-shop Petra s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $wallets = app(WalletService::class);
    $posted = fn () => $wallets->balances($org, 'CZK')['posted']->minor;

    // the partner lists; a customer cannot see a draft; the partner cannot publish (staff do)
    $this->actingAs($partnerOwner, 'sanctum');
    $ph = ['X-Organization' => $partnerOrg->id];
    $this->withHeaders($ph)->postJson('/v1/partner/marketplace/listings', ['key' => 'wp care', 'title' => 'x', 'price_minor' => 1])->assertStatus(422);
    $listing = $this->withHeaders($ph + ['Idempotency-Key' => 'ml-1'])->postJson('/v1/partner/marketplace/listings', ['key' => 'wp-care-basic', 'title' => 'WordPress péče Basic', 'description' => 'Aktualizace, zálohy, dohled.', 'category' => 'care', 'price_minor' => 150000, 'billing' => 'oneoff', 'delivery_days' => 7])->assertCreated()->json();
    expect($listing['state'])->toBe('draft')->and($listing['price'])->toMatchArray(['minor' => 150000, 'currency' => 'CZK'])->and($listing['partner']['name'])->toBe('Agentura Pixel s.r.o.');
    $this->withHeaders($ph + ['Idempotency-Key' => 'ml-2'])->postJson("/v1/partner/marketplace/listings/{$listing['id']}/state", ['state' => 'published'])->assertStatus(409)->assertJsonPath('error', 'marketplace_state_not_allowed');
    expect($this->getJson('/v1/marketplace')->assertOk()->json('data'))->toBe([]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'marketplace.listing.submitted')->exists())->toBeTrue();

    // staff publish; the public catalogue shows it; the partner may pause and resume it
    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'ms-1')->postJson("/v1/staff/marketplace/listings/{$listing['id']}/state", ['state' => 'published', 'reason' => 'ok'])->assertOk()->assertJsonPath('state', 'published');
    $this->flushHeaders();
    expect($this->getJson('/v1/marketplace?category=care')->assertOk()->json('data.0.key'))->toBe('wp-care-basic');
    expect($this->getJson('/v1/marketplace/wp-care-basic')->assertOk()->json('data.title'))->toBe('WordPress péče Basic');
    $this->actingAs($partnerOwner, 'sanctum');
    $this->withHeaders($ph + ['Idempotency-Key' => 'ml-3'])->postJson("/v1/partner/marketplace/listings/{$listing['id']}/state", ['state' => 'paused'])->assertOk()->assertJsonPath('state', 'paused');
    $this->withHeaders($ph + ['Idempotency-Key' => 'ml-4'])->postJson("/v1/partner/marketplace/listings/{$listing['id']}/state", ['state' => 'published'])->assertOk()->assertJsonPath('state', 'published');
    // a price change sends it back to draft (staff publish again)
    $this->withHeaders($ph + ['Idempotency-Key' => 'ml-5'])->putJson("/v1/partner/marketplace/listings/{$listing['id']}", ['price_minor' => 160000])->assertOk()->assertJsonPath('state', 'draft');
    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'ms-2')->postJson("/v1/staff/marketplace/listings/{$listing['id']}/state", ['state' => 'published'])->assertOk();
    $this->flushHeaders();

    // the customer orders: step-up first, then the credit is charged (1 600 + 21 % VAT), the tax document is issued and paid
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-0'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Prosím o převzetí péče o e-shop, WooCommerce.'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-1'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'krátké'])->assertStatus(422);
    $order = $this->withHeaders($h + ['Idempotency-Key' => 'mo-2'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Prosím o převzetí péče o e-shop, WooCommerce.'])->assertCreated()->json();
    expect($order['state'])->toBe('ordered')->and($order['price']['minor'])->toBe(160000)->and($posted())->toBe(500000 - 193600);
    $invoice = Invoice::query()->findOrFail($order['invoice_id']);
    expect($invoice->state)->toBe(Invoice::PAID)->and($invoice->total_minor)->toBe(193600)->and($invoice->meta['marketplace_order_id'])->toBe($order['id']);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'marketplace-assigned')->where('to', 'agentura@pixel.cz')->exists())->toBeTrue();
    expect($this->withHeaders($h)->getJson('/v1/account/marketplace/orders')->assertOk()->json('data.0.id'))->toBe($order['id']);

    // the partner starts and delivers; the customer accepts; the partner's share (80 %) is a payable commission row
    $this->actingAs($partnerOwner, 'sanctum');
    expect($this->withHeaders($ph)->getJson('/v1/partner/marketplace/orders?state=ordered')->assertOk()->json('data.0.partner_share.minor'))->toBe(128000);
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-1'])->postJson("/v1/partner/marketplace/orders/{$order['id']}/start")->assertOk()->assertJsonPath('state', 'in_progress');
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-2'])->postJson("/v1/partner/marketplace/orders/{$order['id']}/deliver", ['note' => 'Péče nastavena, zálohy běží denně, dohled zapnut.'])->assertOk()->assertJsonPath('state', 'delivered');
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'marketplace-delivered')->where('to', 'petra@eshop.cz')->exists())->toBeTrue();
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-3'])->postJson("/v1/account/marketplace/orders/{$order['id']}/accept")->assertOk()->assertJsonPath('state', 'accepted');
    $commission = PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'marketplace')->firstOrFail();
    expect($commission->amount_minor)->toBe(128000)->and($commission->state)->toBe('payable')->and($partners->balance($partner)['payable']->minor)->toBe(128000);
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-4'])->postJson("/v1/account/marketplace/orders/{$order['id']}/accept")->assertStatus(409);

    // a second order is delivered, disputed and refunded by support: the credit comes back, a credit note corrects the document
    $second = $this->withHeaders($h + ['Idempotency-Key' => 'mo-5'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Druhý web, stejná péče prosím.'])->assertCreated()->json();
    $this->actingAs($partnerOwner, 'sanctum');
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-3'])->postJson("/v1/partner/marketplace/orders/{$second['id']}/deliver", ['note' => 'Hotovo.'])->assertOk();
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-6'])->postJson("/v1/account/marketplace/orders/{$second['id']}/cancel")->assertStatus(409)->assertJsonPath('error', 'marketplace_order_started');
    $this->withHeaders($h + ['Idempotency-Key' => 'mo-7'])->postJson("/v1/account/marketplace/orders/{$second['id']}/dispute", ['reason' => 'Nic se na webu nezměnilo, zálohy neběží.'])->assertOk()->assertJsonPath('state', 'disputed');
    $beforeRefund = $posted();
    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    expect($this->getJson('/v1/staff/marketplace/orders?state=open')->assertOk()->json('disputed'))->toBe(1);
    $this->withHeader('Idempotency-Key', 'ms-3')->postJson("/v1/staff/marketplace/orders/{$second['id']}/resolve", ['decision' => 'refund', 'reason' => 'Partner nedodal, zákazník má pravdu.'])->assertOk()->assertJsonPath('state', 'cancelled');
    $this->flushHeaders();
    expect($posted())->toBe($beforeRefund + 193600)->and(Invoice::query()->where('type', 'credit_note')->where('corrects_invoice_id', $second['invoice_id'])->exists())->toBeTrue();
    expect(PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'marketplace')->count())->toBe(1);

    // a delivery nobody answered counts as accepted after the window
    $this->actingAs($owner, 'sanctum');
    $third = $this->withHeaders($h + ['Idempotency-Key' => 'mo-8'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Třetí web, znovu péče prosím.'])->assertCreated()->json();
    $this->actingAs($partnerOwner, 'sanctum');
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-4'])->postJson("/v1/partner/marketplace/orders/{$third['id']}/deliver", ['note' => 'Hotovo, viz report.'])->assertOk();
    MarketplaceOrder::query()->whereKey($third['id'])->update(['delivered_at' => now()->subDays(15)]);
    expect(app(MarketplaceService::class)->autoAccept())->toBe(1)->and(MarketplaceOrder::query()->findOrFail($third['id'])->state)->toBe('accepted');
    expect(PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'marketplace')->sum('amount_minor'))->toBe(256000);
    $this->artisan('onhost:marketplace:auto-accept')->assertSuccessful();

    // a partner cannot order their own listing; an unknown listing is 404
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-5'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Zkusím si objednat vlastní službu.'])->assertStatus(403); // step-up first for the partner owner
    app(StepUpService::class)->grant($partnerOwner, 'totp', null, '127.0.0.1');
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-6'])->postJson('/v1/marketplace/wp-care-basic/order', ['brief' => 'Zkusím si objednat vlastní službu.'])->assertStatus(422)->assertJsonPath('error', 'marketplace_own_listing');
    $this->withHeaders($ph + ['Idempotency-Key' => 'mp-7'])->postJson('/v1/marketplace/neexistuje/order', ['brief' => 'Něco, co neexistuje, prosím.'])->assertNotFound();
});
