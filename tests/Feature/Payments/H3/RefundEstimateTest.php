<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * H3 (phase H, TASK-0121): what a chargeback or a consumer withdrawal says it will return is what the customer PAID for the
 * line, not the line's list total. Since G3 a redemption of loyalty points is a line of its own on the document; the hosting
 * line keeps its list price. The credit note of a return takes the redemption's share back with the line (RedemptionShare), so
 * the money that reaches the customer is less than the line's prorated list total — but the estimate (the panel's "would return
 * now", the chargeback request, the withdrawal's acceptance notice) still showed the list total. Now the estimate subtracts the
 * same share, and it equals what the credit note then really gives back.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config()->set('onhost.billing.fx.fetch', false);
});

/**
 * A year of web hosting "standard" (1 890 Kč + 21 % = 2 286,90 Kč) bought with 300 points (363 Kč with VAT off), paid from the
 * credit, and the service it was delivered as.
 *
 * @return array{0:Service, 1:Invoice, 2:Organization}
 */
function h3PointsHosting(array $customer, int $points = 300): array
{
    [$owner, $org] = $customer;
    LoyaltyPoint::query()->create(['organization_id' => $org->id, 'rule' => 'manual', 'reference' => 'h3:'.uniqid('', true), 'points' => 500, 'note' => 'test']);
    $ctx = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session');
    app(WalletService::class)->topup($org, Money::decimal('10000', 'CZK'), 'card', 'h3-seed-'.uniqid(), $ctx, bankProvider: 'comgate');
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1]], 'CZK', ['country' => 'CZ'], 12, null, $org, 'cs', null, $points);
    $consents = ['terms' => [], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jan Novák'], 'registry_terms_cz' => ['person' => 'Jan Novák']];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'h3-est-'.uniqid(), $ctx)['order']->refresh();
    $service = Service::query()->create(['organization_id' => $org->id, 'family' => 'web', 'product_key' => 'web-hosting', 'name' => 'h3-web-'.uniqid(), 'state' => ServiceStateMachine::ACTIVE, 'entitlements' => [], 'tags' => []]);
    OrderItem::query()->where('order_id', $order->id)->where('product_key', 'web-hosting')->update(['service_id' => $service->id]);
    $statement = Invoice::query()->findOrFail($order->invoice_id);

    return [$service, $statement, $org];
}

it('estimates a chargeback from what was paid for the line, net of its share of the redeemed points, and gives back exactly that', function () {
    [$service, $statement] = h3PointsHosting($this->customerWithOrganization());
    $hosting = $statement->lines()->where('sku', 'web-hosting-standard')->sole();
    expect((int) $hosting->total_minor)->toBe(228690); // the list total stays on the line; the points are a line of their own

    $estimate = app(ChargebackService::class)->estimate($service, 100);
    expect($estimate['lines'])->toHaveCount(1);
    $lineGross = (int) $estimate['lines'][0]['refund_minor'];          // what the credit note credits on the hosting line
    $share = intdiv(36300 * $lineGross, 228690);                       // the points' share that goes back with it
    expect($estimate['lines'][0]['redemption_minor'])->toBe($share)
        ->and($estimate['refund_minor'])->toBe($lineGross - $share)    // against the old code: $lineGross, the list price
        ->and($estimate['unused_minor'])->toBe($lineGross - $share);

    // the credit note of exactly that line gives back exactly the estimate
    $given = app(InvoiceService::class)->giveBack($statement, [(string) $hosting->id => $lineGross], 'H3', CommandContext::system('test')->withScope($statement->organization_id));
    expect($given['to_credit_minor'] + $given['off_document_minor'])->toBe($estimate['refund_minor']);
});

it('applies the chargeback share to what was paid, not to the list price', function () {
    [$service] = h3PointsHosting($this->customerWithOrganization());
    $full = app(ChargebackService::class)->estimate($service, 100);
    $seventy = app(ChargebackService::class)->estimate($service, 70);

    $lineGross = (int) $seventy['lines'][0]['refund_minor'];
    expect($seventy['refund_minor'])->toBe($lineGross - intdiv(36300 * $lineGross, 228690))
        ->and($seventy['refund_minor'])->toBeLessThan((int) round($full['refund_minor'] * 0.7) + 2)
        ->and($seventy['refund_minor'])->toBeGreaterThan((int) round($full['refund_minor'] * 0.7) - 2);
});

it('estimates a consumer withdrawal from what was paid, and the credit it promises is what reaches the credit', function () {
    [$service, $statement] = h3PointsHosting($this->customerWithOrganization());
    $now = CarbonImmutable::now();
    $estimate = app(WithdrawalService::class)->estimate($service, $now);
    $chargeback = app(ChargebackService::class)->estimate($service, 100, $now);

    expect($estimate['refund']->minor)->toBe($chargeback['refund_minor'])
        ->and($estimate['to_credit']->minor)->toBe($chargeback['refund_minor']) // the document is paid: all of it reaches the credit
        ->and($estimate['off_documents']->minor)->toBe(0);

    $hosting = $statement->lines()->where('sku', 'web-hosting-standard')->sole();
    $given = app(InvoiceService::class)->giveBack($statement, [(string) $hosting->id => (int) $estimate['lines'][0]['refund_minor']], 'H3 odstoupení', CommandContext::system('test')->withScope($statement->organization_id));
    expect($given['to_credit_minor'])->toBe($estimate['to_credit']->minor);
});

it('changes nothing for a line bought without points', function () {
    [$service] = h3PointsHosting($this->customerWithOrganization(), 0);
    $estimate = app(ChargebackService::class)->estimate($service, 100);

    expect($estimate['lines'])->toHaveCount(1)
        ->and($estimate['lines'][0]['redemption_minor'])->toBe(0)
        ->and($estimate['refund_minor'])->toBe((int) $estimate['lines'][0]['refund_minor']);
});
