<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * Owner decision R7 covers the marketplace share too (TASK-0097 security review): the partner's share of an accepted order is
 * a commission like any other — pending for 30 days — and the unique index on (invoice_id, kind) decides between two writers
 * of the same share without undoing the customer's acceptance.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('books the marketplace share as a pending commission, and keeps the acceptance when another writer won the unique index', function () {
    [, $partnerOrg] = test()->customerWithOrganization(['email' => 'mcg@agentura.cz'], ['name' => 'Agentura MCG s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $listing = $marketplace->createListing($partner, ['key' => 'r7-care', 'title' => 'Péče R7', 'category' => 'care', 'price_minor' => 150000, 'billing' => 'oneoff', 'delivery_days' => 7], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));
    [$owner, $org] = test()->customerWithOrganization(['email' => 'mcg@obchod.cz'], ['name' => 'Obchod MCG s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');

    $first = $marketplace->order($org, $owner, $listing->fresh(), ['brief' => 'Prosím o převzetí péče o e-shop.'], $ctx);
    $marketplace->deliver($first->fresh(), $partner, 'Hotovo, péče nastavena.', CommandContext::system('test'));
    $marketplace->accept($first->fresh(), $org, $ctx);
    $share = PartnerCommission::query()->where('invoice_id', $first->invoice_id)->where('kind', 'marketplace')->firstOrFail();
    expect($share->state)->toBe('pending')->and($share->payable_at?->toDateString())->toBe(now()->addDays(30)->toDateString());

    // a second order: another writer books the same share the moment this acceptance looked for one
    $second = $marketplace->order($org, $owner, $listing->fresh(), ['brief' => 'Druhý e-shop, stejná péče prosím.'], $ctx);
    $marketplace->deliver($second->fresh(), $partner, 'Hotovo i podruhé.', CommandContext::system('test'));
    $fired = false;
    DB::listen(function (QueryExecuted $query) use (&$fired, $second, $partner): void {
        if ($fired || ! str_contains(strtolower($query->sql), 'from "partner_commissions"')) {
            return;
        }
        $fired = true;
        DB::table('partner_commissions')->insert([
            'id' => 'pcm_race_r7', 'partner_id' => $partner->id, 'organization_id' => $second->organization_id, 'invoice_id' => $second->invoice_id, 'period' => now()->format('Y-m'), 'kind' => 'marketplace',
            'base_minor' => 150000, 'rate_pct' => 80, 'amount_minor' => 120000, 'currency' => 'CZK', 'state' => 'pending', 'payable_at' => now()->addDays(30), 'invoice_paid_at' => now(), 'fragment' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });
    $accepted = $marketplace->accept($second->fresh(), $org, $ctx);

    expect($fired)->toBeTrue()->and($accepted->state)->toBe('accepted')->and(MarketplaceOrder::query()->findOrFail($second->id)->state)->toBe('accepted')
        ->and(PartnerCommission::query()->where('invoice_id', $second->invoice_id)->where('kind', 'marketplace')->pluck('id')->all())->toBe(['pcm_race_r7']);
});
