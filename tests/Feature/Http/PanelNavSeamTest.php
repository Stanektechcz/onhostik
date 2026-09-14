<?php

declare(strict_types=1);

/* The panel's sidebar and chat are seamed to the offer and to the AI assistant (docs/ui/data-seams.md #29, #30); the prototype's surface switcher stays a design-review tool. */

it('seams the sidebar, the service-desk categories and the chat of the customer panel, and drops the surface switcher', function () {
    [$user] = $this->customerWithOrganization();
    $this->actingAs($user);
    $panel = $this->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain('src="/surfaces/api/onhost-panel-nav.api.js?v=')
        ->toContain('src="/surfaces/api/onhost-panel-chat.api.js?v=')
        ->toContain('const groupDefs = (window.OnhostPanelNav && window.OnhostPanelNav.groups(this, _, { openTickets: openTickets, count: (c) => this.SVC_COUNT(c), total: this.SVC_TOTAL() })) || [')
        ->toContain('cats: (window.OnhostPanelNav && window.OnhostPanelNav.cats(this, _)) || [')
        // a sidebar entry may carry its own click handler (order wizard, knowledge base, status page)
        ->toContain('on: () => sb[4] ? sb[4](this) : this.setState(Object.assign({ tab: sb[1],')
        ->toContain('on: () => x[4] ? x[4](this) : this.setState(st => Object.assign(')
        // chat: questions go to the assistant, chips carry proposed actions, the footer hands over to a real ticket
        ->toContain('if (window.OnhostPanelChat && window.OnhostPanelChat.ask(this, text)) return;')
        ->toContain('chatMsgs: (window.OnhostPanelChat && window.OnhostPanelChat.welcome()) || [')
        ->toContain('chatChips: ((window.OnhostPanelChat && window.OnhostPanelChat.chips(this, _)) || [')
        ->toContain("on: () => typeof c[1] === 'function' ? c[1]() : this.askAi(c[1]),")
        ->toContain('if (window.OnhostPanelChat && window.OnhostPanelChat.escalate(this)) return;')
        ->toContain('chatUnread: window.ONHOST_PANEL ? 0 : 2,');

    $shell = $this->get('/surfaces/onhost-shell.js')->assertOk()->getContent();
    expect($shell)->toContain('function boot() { if (EMBED || (window.ONHOST && !window.ONHOST.demo)) return;');
    // the command script keeps the palette (⌘K) but its assistant drawer gives way to the chat: no dock, ⌘J and OnhostCommand.assistant open the chat
    $command = $this->get('/surfaces/onhost-command.js')->assertOk()->getContent();
    expect($command)->toContain('if (!product) document.body.appendChild(dock);')
        ->toContain("if (meta && k === 'j') { e.preventDefault(); if (palOpen) setPalOpen(false); if (product) { panelChat(); return; } openAsk(!askOpen); return; }")
        ->toContain('assistant: function (open) { if (product) { panelChat(); return; } openAsk(open); },')
        ->toContain('function panelChat() {');
    expect(str_contains($command, "\n    document.body.appendChild(dock);"))->toBeFalse();
    expect($panel)->toContain('infraLabel: window.ONHOST_PANEL ? ((window.ONHOST_PANEL.kpis && window.ONHOST_PANEL.kpis.incidents) ?');
});

it('keeps the prototype sidebar, chat and switcher in demo mode', function () {
    config()->set('onhost.ui.demo', true);
    [$user] = $this->customerWithOrganization();
    $this->actingAs($user);
    $panel = $this->get('/panel')->assertOk()->getContent();
    expect(str_contains($panel, 'window.OnhostPanelNav'))->toBeFalse();
    expect(str_contains($panel, 'window.OnhostPanelChat'))->toBeFalse();
    $shell = $this->get('/surfaces/onhost-shell.js')->assertOk(); // the untouched file is streamed as-is in demo mode
    expect((string) file_get_contents($shell->baseResponse->getFile()->getPathname()))->toContain('function boot() { if (EMBED) return;');
});
