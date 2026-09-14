<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * Period deliverables with evidence (audit §5o-2): a monthly listing names its checklist; the partner's period report must
 * tick every item (or fill the text ones); the evidence stays on the order for the customer to read.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('requires the listing checklist behind every monthly report and shows the evidence to the customer', function () {
    [$partnerOwner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $checklist = [['key' => 'updates', 'cs' => 'Aktualizace pluginů'], ['key' => 'backup', 'cs' => 'Záloha ověřena'], ['key' => 'uptime', 'cs' => 'Uptime v %', 'kind' => 'text']];
    expect(fn () => $marketplace->normalizeChecklist([['key' => 'A!', 'cs' => 'x']]))->toThrow(DomainError::class, 'Checklist keys');
    expect(fn () => $marketplace->normalizeChecklist([['key' => 'ok', 'kind' => 'photo']]))->toThrow(DomainError::class, 'check, text or file');
    $listing = $marketplace->createListing($partner, ['key' => 'wp-care-monthly', 'title' => 'WordPress péče měsíčně', 'category' => 'care', 'price_minor' => 100000, 'billing' => 'monthly', 'delivery_days' => 3, 'checklist' => $checklist], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));
    expect($marketplace->presentListing($listing->refresh())['checklist'])->toBe([['key' => 'updates', 'cs' => 'Aktualizace pluginů', 'en' => 'Aktualizace pluginů', 'kind' => 'check'], ['key' => 'backup', 'cs' => 'Záloha ověřena', 'en' => 'Záloha ověřena', 'kind' => 'check'], ['key' => 'uptime', 'cs' => 'Uptime v %', 'en' => 'Uptime v %', 'kind' => 'text']]);
    $marketplace->updateListing($partner, $listing, ['description' => 'Měsíční péče.'], CommandContext::system('test'));
    expect($marketplace->checklistFor($listing->refresh()))->toHaveCount(3); // an update without the checklist keeps it

    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@eshop.cz'], ['name' => 'E-shop Petra s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('9000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $order = $marketplace->order($org, $owner, $listing, ['brief' => 'Měsíční péče, prosím.'], $ctx);
    $marketplace->deliver($order, $partner, 'Péče nastavena.', CommandContext::system('test')); // the first delivery is the job itself, no checklist yet
    $marketplace->accept($order->refresh(), $org, $ctx);
    $subscription = Subscription::query()->findOrFail($order->subscription_id);
    expect($marketplace->renewDue($subscription->current_period_end->copy()->addMinute()))->toMatchArray(['renewed' => 1]);
    $this->travelTo($subscription->refresh()->current_period_start->copy()->addDays(20));

    // the period report from the portal: a missing item is refused with the list, a complete one lands as evidence
    $this->actingAs($partnerOwner, 'sanctum');
    $h = ['X-Organization' => $partnerOrg->id];
    $this->withHeaders($h + ['Idempotency-Key' => 'cl-1'])->postJson("/v1/partner/marketplace/orders/{$order->id}/deliver", ['note' => 'Vše hotovo.', 'evidence' => ['updates' => true]])->assertStatus(422)->assertJsonPath('error', 'marketplace_checklist_incomplete')->assertJsonPath('missing', ['backup', 'uptime']);
    $this->withHeaders($h + ['Idempotency-Key' => 'cl-2'])->postJson("/v1/partner/marketplace/orders/{$order->id}/deliver", ['note' => 'Vše hotovo.', 'evidence' => ['updates' => true, 'backup' => 'yes', 'uptime' => '   ']])->assertStatus(422)->assertJsonPath('missing', ['uptime']);
    $row = $this->withHeaders($h + ['Idempotency-Key' => 'cl-3'])->postJson("/v1/partner/marketplace/orders/{$order->id}/deliver", ['note' => 'Pluginy aktualizovány, záloha ověřena, uptime 99,98 %.', 'evidence' => ['updates' => 'true', 'backup' => 1, 'uptime' => '99.98']])->assertOk()->json();
    expect($row['state'])->toBe(MarketplaceOrder::ACCEPTED)->and($row['sla']['period']['served'])->toBeTrue()->and($row['period_evidence'])->toHaveCount(1)
        ->and($row['period_evidence'][0]['items'])->toBe(['updates' => true, 'backup' => true, 'uptime' => '99.98'])->and($row['period_evidence'][0]['note'])->toBe('Pluginy aktualizovány, záloha ověřena, uptime 99,98 %.')->and($row['checklist'])->toHaveCount(3);

    // the customer reads the checklist and the evidence on the order row
    $this->actingAs($owner, 'sanctum');
    $mine = collect($this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/account/marketplace/orders')->assertOk()->json('data'))->firstWhere('id', $order->id);
    expect($mine['checklist'][2]['cs'])->toBe('Uptime v %')->and($mine['period_evidence'][0]['items']['uptime'])->toBe('99.98');

    // a listing without a checklist takes a plain report
    $plain = $marketplace->createListing($partner, ['key' => 'seo-monthly', 'title' => 'SEO měsíčně', 'category' => 'seo', 'price_minor' => 100000, 'billing' => 'monthly', 'delivery_days' => 3], CommandContext::system('test'));
    expect($marketplace->presentListing($plain)['checklist'])->toBe([]);

    // §5p-3: a file item — the partner uploads the report first, the period report consumes it, the customer downloads it
    Storage::fake('local');
    $marketplace->updateListing($partner, $listing, ['checklist' => array_merge($checklist, [['key' => 'report', 'cs' => 'Měsíční report (PDF)', 'kind' => 'file']])], CommandContext::system('test'));
    expect($marketplace->renewDue($subscription->refresh()->current_period_end->copy()->addMinute()))->toMatchArray(['renewed' => 1]);
    $this->travelTo($subscription->refresh()->current_period_start->copy()->addDays(15));
    $this->actingAs($partnerOwner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'cl-4'])->postJson("/v1/partner/marketplace/orders/{$order->id}/deliver", ['note' => 'Vše hotovo.', 'evidence' => ['updates' => true, 'backup' => true, 'uptime' => '99.9']])->assertStatus(422)->assertJsonPath('missing', ['report']);
    $this->flushHeaders(); // withHeaders() persists on the test case: the deliver key must not leak onto the uploads
    $this->withHeaders($h + ['Idempotency-Key' => 'ev-1'])->post("/v1/partner/marketplace/orders/{$order->id}/evidence", ['key' => 'nope', 'file' => UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')])->assertStatus(422)->assertJsonPath('error', 'marketplace_checklist_item_invalid');
    $this->withHeaders($h + ['Idempotency-Key' => 'ev-2'])->post("/v1/partner/marketplace/orders/{$order->id}/evidence", ['key' => 'report', 'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')])->assertStatus(422);
    $uploaded = $this->withHeaders($h + ['Idempotency-Key' => 'ev-3'])->post("/v1/partner/marketplace/orders/{$order->id}/evidence", ['key' => 'report', 'file' => UploadedFile::fake()->create('report září.pdf', 120, 'application/pdf')])->assertCreated()->json();
    expect($uploaded)->toMatchArray(['key' => 'report', 'name' => 'report-z-.pdf', 'mime' => 'application/pdf'])->and($uploaded)->not->toHaveKey('path');
    expect(Storage::disk('local')->allFiles("marketplace-evidence/{$order->id}"))->toHaveCount(1)->and(Storage::disk('local')->allFiles('marketplace-evidence/tmp'))->toBe([]);
    $mine = collect($this->withHeaders($h)->getJson('/v1/partner/marketplace/orders?limit=100')->assertOk()->json('data'))->firstWhere('id', $order->id);
    expect($mine['period_uploads'][0]['key'])->toBe('report');
    $row = $this->withHeaders($h + ['Idempotency-Key' => 'cl-5'])->postJson("/v1/partner/marketplace/orders/{$order->id}/deliver", ['note' => 'Report v příloze.', 'evidence' => ['updates' => true, 'backup' => true, 'uptime' => '99.9']])->assertOk()->json();
    $last = $row['period_evidence'][count($row['period_evidence']) - 1];
    expect($last['items']['report'])->toMatchArray(['file' => 'report-z-.pdf', 'mime' => 'application/pdf'])->and($row['period_uploads'])->toBe([]);
    $this->actingAs($owner, 'sanctum');
    $entries = collect($this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/account/marketplace/orders')->assertOk()->json('data'))->firstWhere('id', $order->id)['period_evidence'];
    $index = count($entries) - 1;
    $this->withHeaders(['X-Organization' => $org->id])->get("/v1/account/marketplace/orders/{$order->id}/evidence/{$index}/report")->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->withHeaders(['X-Organization' => $org->id])->get("/v1/account/marketplace/orders/{$order->id}/evidence/{$index}/updates")->assertNotFound();
    $this->withHeaders(['X-Organization' => $partnerOrg->id])->get("/v1/account/marketplace/orders/{$order->id}/evidence/{$index}/report")->assertForbidden(); // the owner is no member of the partner organization
});
