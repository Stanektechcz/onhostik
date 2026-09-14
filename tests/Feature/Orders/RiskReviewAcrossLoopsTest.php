<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Risk\RiskWeights;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/*
 * Risk model review across loops (audit §5o-4): the review lists held referrals next to held orders, counts both into
 * one precision table, exports both to CSV and carries the shared weight table.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function reviewQuote(Organization $org)
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
}

it('lists held referrals next to held orders with one precision table', function () {
    // a held order (new account + disposable mail) that staff reject
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $held = app(CheckoutService::class)->placeOrder(reviewQuote($org), $org, $owner, $consents, ['mode' => 'wallet'], 'rv-1', $ctx)['order']->refresh();
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'rv-rj')->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'reject', 'reason' => 'fraud pattern'])->assertOk();

    // two referrals the loop scored: one held for finance, one refused
    [, $referrer] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $code = app(ReferralService::class)->code($referrer);
    [, $referredA] = $this->customerWithOrganization(['email' => 'a@firma.cz'], ['name' => 'A s.r.o.']);
    [, $referredB] = $this->customerWithOrganization(['email' => 'b@firma.cz'], ['name' => 'B s.r.o.']);
    Referral::query()->create(['referrer_organization_id' => $referrer->id, 'referred_organization_id' => $referredA->id, 'code' => $code, 'state' => Referral::HELD, 'score' => 60, 'signals' => ['referrer_risk', 'rapid_signup']]);
    Referral::query()->create(['referrer_organization_id' => $referrer->id, 'referred_organization_id' => $referredB->id, 'code' => $code, 'state' => Referral::REFUSED, 'score' => 100, 'signals' => ['disposable_email', 'same_address'], 'reason' => 'disposable_email', 'decided_at' => now()]);

    $review = $this->getJson('/v1/staff/orders/risk-review?days=30')->assertOk()->json('data');
    expect($review['held'])->toBe(3)->and($review['orders'])->toHaveCount(1)->and($review['referrals'])->toHaveCount(2);
    $refA = collect($review['referrals'])->firstWhere('organization', 'A s.r.o.');
    expect($refA)->toMatchArray(['loop' => 'referral', 'outcome' => 'pending', 'state' => Referral::HELD, 'score' => 60, 'reasons' => ['referrer_risk', 'rapid_signup'], 'referrer' => 'Firma Jan s.r.o.']);
    expect(collect($review['referrals'])->firstWhere('organization', 'B s.r.o.'))->toMatchArray(['outcome' => 'rejected', 'decision_reason' => 'disposable_email']);
    // the precision table counts both loops: disposable_email fired on the rejected order and the refused referral
    expect($review['signals']['disposable_email'])->toMatchArray(['held' => 2, 'rejected' => 2, 'released' => 0, 'precision' => 1.0])
        ->and($review['signals']['referrer_risk'])->toMatchArray(['held' => 1, 'pending' => 1, 'precision' => null])
        ->and($review['weights'])->toHaveKey('referrer_risk')->and($review['weights']['disposable_email'])->toBe(app(RiskWeights::class)->weights()['disposable_email']);

    // the CSV carries the loop column and every row of both loops
    $csv = $this->get('/v1/staff/orders/risk-review?days=30&format=csv')->assertOk()->getContent();
    expect($csv)->toStartWith('loop;order;organization;score;reasons;outcome')->toContain('"order";"'.$held->number.'"')->toContain('"referral";"'.$code.'";"A s.r.o.";"60";"referrer_risk|rapid_signup";"pending"');
});
