<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Commands\CommandContext;

/*
 * Green hosting (audit §5j-10): the region's energy profile, a node override, the footprint estimate of an organization,
 * the line on the invoice and the public profile with the badge.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('estimates the footprint from the services, stamps it on invoices and publishes the profile and the badge', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org); // 8 GB RAM on games01 (cz1)
    $green = app(GreenService::class);
    expect($green->region('cz1'))->toMatchArray(['source' => 'renewable', 'gco2_per_kwh' => 20.0, 'pue' => 1.25, 'renewable_pct' => 100])->and($green->region('nowhere')['renewable_pct'])->toBe(0);

    $footprint = $green->footprint($org);
    // 8 GB × 3.5 W × 730 h × PUE 1.25 = 25.55 kWh → × 20 g = 511 g CO₂e
    expect($footprint['services'])->toBe(1)->and($footprint['kwh'])->toBe(25.55)->and($footprint['gco2'])->toBe(511.0)->and($footprint['renewable_pct'])->toBe(100)->and($footprint['rows'][0])->toMatchArray(['service_id' => $service->id, 'region' => 'cz1', 'ram_gb' => 8.0, 'source' => 'renewable']);

    // a node override: the operator tags a node on grid power
    $node = Node::query()->findOrFail($service->node_id);
    $node->forceFill(['tags' => array_merge((array) $node->tags, ['energy' => ['source' => 'grid', 'gco2_per_kwh' => 400, 'renewable_pct' => 30]])])->save();
    expect($green->nodeEnergy($node->refresh()))->toMatchArray(['source' => 'grid', 'gco2_per_kwh' => 400.0, 'renewable_pct' => 30, 'pue' => 1.25]);
    $grid = $green->footprint($org);
    expect($grid['gco2'])->toBe(10220.0)->and($grid['renewable_pct'])->toBe(30);
    $node->forceFill(['tags' => array_diff_key((array) $node->tags, ['energy' => true])])->save();

    // the customer sees it; the invoice carries the month's estimate; the public profile and the badge answer without a session
    $this->actingAs($owner, 'sanctum');
    $mine = $this->withHeader('X-Organization', $org->id)->getJson('/v1/account/green')->assertOk()->json('data');
    expect($mine['kwh'])->toBe(25.55)->and($mine['badge_url'])->toContain('/green/badge.svg?pct=100');
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'game-8', 'description' => 'Herní server', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 30000, 'discount' => 0, 'net' => 30000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 6300, 'total' => 36300]], CommandContext::system('test'));
    $invoice = $invoices->issue($draft, CommandContext::system('test'));
    expect($invoice->meta['green'])->toMatchArray(['kwh' => 25.55, 'gco2' => 511.0, 'renewable_pct' => 100, 'services' => 1]);
    expect($this->withHeader('X-Organization', $org->id)->getJson("/v1/invoices/{$invoice->id}")->assertOk()->json('data.green.kwh'))->toBe(25.55);
    expect($invoices->pdfBinary($invoice))->not->toBe('');
    $this->flushHeaders();
    auth()->forgetGuards();
    $platform = $this->getJson('/v1/green')->assertOk()->json('data');
    expect($platform['renewable_pct'])->toBe(100)->and(collect($platform['regions'])->firstWhere('region', 'cz1')['nodes'])->toBeGreaterThanOrEqual(1);
    $this->get('/green/badge.svg?pct=100')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('100 % obnoviteln');
    $this->get('/green/badge.svg?pct=42&lang=en')->assertOk()->assertSee('42 % renewable hosting');
});
