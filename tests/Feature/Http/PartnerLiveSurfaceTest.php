<?php

declare(strict_types=1);

use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;

/*
 * The partner portal on the API (audit §5l-1, seam #47): every narrated tab reads the partner API when it answers and keeps
 * the prototype literal as fallback; the overview carries the tier table the portal needs.
 */

it('puts the prototype tabs on the partner API and ships the tier table', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $this->actingAs($owner, 'sanctum');
    $html = $this->get('/partner')->assertOk()->getContent();
    foreach ([
        'window.OnhostPartner.sync(this)', 'window.OnhostPartner.company(this)', 'window.OnhostPartner.clients(this)) || this.CLIENTS(cs)', 'window.OnhostPartner.tiers(this)) || [[0,',
        'window.OnhostPartner.rate(this)', 'window.OnhostPartner.kpis(this, mrrTotal, rate, mult)) || [', 'window.OnhostPartner.feed(this)) || [', 'window.OnhostPartner.commRows(this)) || months.map',
        'window.OnhostPartner.payoutLive(this)) || (() => {', 'window.OnhostPartner.payRows(this, balanceNum, openPayoutNo)) || [', 'window.OnhostPartner.requestPayout(this, a, ib)',
        'window.OnhostPartner.saveWhitelabel(this, dom, s.wl)', 'window.OnhostPartner.wlVerified(this)', 'window.OnhostPartner.wlRecord(this,', 'window.OnhostPartner.orgName()', 'window.OnhostPartner.refBase(this)',
        'window.OnhostPartner.files(this)) || [', "if (f[2]) { window.open(f[2], '_blank', 'noopener'); return; }",
    ] as $seam) {
        expect($html)->toContain($seam);
    }
    // the literals survive as fallback
    expect($html)->toContain('this.CLIENTS(cs)')->toContain("'https://onhost.cz/?ref=SINDELAR4821'")->toContain('PO-2026-08');

    $h = ['X-Organization' => $partnerOrg->id];
    $overview = $this->withHeaders($h)->getJson('/v1/partner/overview')->assertOk()->json('data');
    expect($overview['tier']['table'])->toHaveCount(4)->and($overview['tier']['table'][0])->toMatchArray(['name' => 'bronze', 'threshold_minor' => 0, 'rate' => 15])->and($overview['tier']['table'][3]['name'])->toBe('platinum');
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js'));
    expect($js)->toContain("'/partner/overview'")->toContain("'/partner/clients'")->toContain("'/partner/commissions'")->toContain("'/partner/payouts'")->toContain("'/partner/whitelabel'")->toContain("'/partner/assets'")->toContain('requestPayout')->toContain('saveWhitelabel');
});
