<?php

declare(strict_types=1);

use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;

/*
 * The partner portal's marketplace tab (audit §5k-1, seam #46): outside demo mode the prototype gains one API-backed tab —
 * the tab table, the view flag, the page title and the markup are injected by the renderer; the module is preloaded.
 */

it('serves the partner surface with the marketplace tab seams and the partner module', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $this->actingAs($owner, 'sanctum');
    $html = $this->get('/partner')->assertOk()->getContent();
    expect($html)->toContain('onhost-partner.api.js')->toContain("marketplace: 'marketplace'")->toContain("['marketplace', 'Marketplace'")
        ->toContain("isMarketplace: s.tab === 'marketplace'")->toContain('<sc-if value="{{ isMarketplace }}"')->toContain('{{ mkt.listings }}')->toContain('{{ mkt.orders }}')
        ->toContain('window.OnhostPartner.page(this)')->toContain('if (this.__onhostMounted) this.syncHash();')->toContain('this.__onhostMounted = true;'); // deep links survive the first render
    // the prototype's own tabs are untouched
    expect($html)->toContain("['assets', _('Materiály', 'Materials'), '']")->toContain('<sc-if value="{{ isPayouts }}"');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js')))->toContain('window.OnhostPartner = {')->toContain('/partner/marketplace/listings')->toContain('/partner/marketplace/orders');
});
