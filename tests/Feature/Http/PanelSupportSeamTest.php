<?php

declare(strict_types=1);

/* Seam #26: the customer's real ticket conversation replaces the prototype's narrated "Tiket 4821" thread in the panel. */

it('opens real tickets in the panel thread, replies and rates through the store instead of sending customers to the staff queue', function () {
    [$owner] = $this->customerWithOrganization();
    $panel = $this->actingAs($owner)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('/surfaces/api/onhost-panel-support.api.js')
        ->toContain("if (t.live && window.OnhostPanelSupport) {\n            window.OnhostPanelSupport.open(this, t.storeId);")
        ->toContain('thread: window.OnhostPanelSupport ? window.OnhostPanelSupport.thread(this, cs) : {')
        ->toContain("on: () => (typeof a[3] === 'function' ? a[3]() : this.flash(a[0], a[1]))")
        ->toContain("if (typeof d.thread.onSend === 'function') { d.thread.onSend(t);")
        ->not->toContain("window.location.href = 'Onhost-admin.dc.html#/fronta';\n            return;\n          }\n          this.flash('#' + t.id");

    $this->get('/surfaces/api/onhost-panel-support.api.js')->assertOk();
    $module = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-support.api.js'));
    $store = (string) file_get_contents(base_path('apps/surfaces/api/onhost-store.api.js'));
    expect($module)->toContain('window.OnhostPanelSupport = {')->and($module)->toContain("S.replyTicket(t.id, 'zakaznik', text)")->and($module)->toContain("S.setTicketState(t.id, 'vyreseny', 'zakaznik')")
        ->and($store)->toContain("A.post('/tickets/' + t.apiId + '/csat', { score: score")->and($store)->toContain("who: m.author_name || ''");

    // demo mode keeps the prototype's narrated thread
    config()->set('onhost.ui.demo', true);
    $demo = $this->get('/panel')->assertOk()->getContent();
    expect($demo)->not->toContain('window.OnhostPanelSupport.thread(this, cs)');
});
