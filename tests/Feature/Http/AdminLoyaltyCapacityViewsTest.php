<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;

/*
 * Campaign forecast and capacity requests in the console (audit §5o-5, §5o-7): two prototype table views are repurposed
 * outside demo mode — `coupons` shows the loyalty campaigns with the cost forecast before a save, `nodecost` the capacity
 * pools, requests and vendor orders with their actions; the daily capacity pass runs on demand.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('relabels the two views, ships their tables and runs the capacity pass on demand', function () {
    $html = $this->actingAs($this->staff('platform_owner'))->get('/sprava')->assertOk()->getContent();
    expect($html)->toContain("_('Věrnost a kampaně', 'Loyalty and campaigns')")->toContain("_('Kapacita a nákup uzlů', 'Capacity and node purchases')")
        ->toContain('window.OnhostAdmin.counts(this).coupons')->toContain('window.OnhostAdmin.counts(this).nodecostDot')->toContain("_('Misijní kampaně s odhadem nákladů dřív, než je otevřete.'")->toContain("_('Forecast fondů podle trendu, návrhy nákupu a objednávky uzlů u dodavatele.'");
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js'));
    expect($js)->toContain("'coupons', 'nodecost']")->toContain('function loyaltyTable(')->toContain('function capacityTable(')->toContain("'/staff/loyalty/campaigns/forecast'")->toContain("'/staff/capacity/forecast/run'")
        ->toContain("'/staff/capacity/requests?state=all'")->toContain('Založit herní server')->toContain("action: 'command.send'")->toContain("window.open('/sprava/konzole/' + sid")->toContain("'X-Organization': org");

    // the pass on demand: the forecast rows come back with the plan (nothing short in a fresh lab)
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $run = $this->withHeader('Idempotency-Key', 'cap-run-1')->postJson('/v1/staff/capacity/forecast/run')->assertOk()->json('data');
    expect($run)->toHaveKeys(['warned', 'plan', 'forecast'])->and($run['plan'])->toHaveKeys(['proposed', 'ordered', 'delivered']);
    $this->actingAs($this->staff('support_agent'), 'sanctum');
    $this->postJson('/v1/staff/capacity/forecast/run')->assertStatus(403);
});
