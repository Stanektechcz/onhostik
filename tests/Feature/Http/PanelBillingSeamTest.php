<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('feeds the panel billing tab with the organization\'s real document, bank details and cost breakdown', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $placed = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'panel-billing:q1', $ctx);
    expect($placed['order']->state)->toBe('PENDING_PAYMENT')->and($placed['bank_instructions'])->not->toBeNull();

    $this->actingAs($owner);
    $seam = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    $document = $payload['billing']['document'];
    expect($document)->not->toBeNull()
        ->and($document['type'])->toBe('proforma')
        ->and($document['state'])->toBe('due')
        ->and($document['number'])->toStartWith('PF-')
        ->and($document['payment_reference'])->not->toBeEmpty()
        ->and($document['lines'])->toHaveCount(1)
        ->and($document['lines'][0]['name'])->not->toBeEmpty()
        ->and($document['lines'][0]['sub'])->toContain('DPH 21 %')
        ->and($document['total'])->toBeGreaterThan(0)
        ->and($document['outstanding'])->toBe($document['total'])
        ->and($document['buyer']['name'])->toBe($org->name)
        ->and($payload['billing']['bank']['iban'])->not->toBeEmpty()
        ->and($payload['billing']['organization']['name'])->toBe($org->name)
        ->and($payload['billing']['breakdown'])->toBe([]) // nothing is subscribed until the proforma is paid
        ->and($payload['billing']['average_6m'])->toEqual(0) // proformas are not tax documents
        ->and($payload['billing']['chart']['months'])->toHaveCount(12)
        ->and($payload['billing']['chart']['months'][11][0])->toBe(['Led', 'Úno', 'Bře', 'Dub', 'Kvě', 'Čvn', 'Čvc', 'Srp', 'Zář', 'Říj', 'Lis', 'Pro'][(int) now()->format('n') - 1])
        ->and($payload['billing']['chart']['months'][0])->toHaveCount(4)
        ->and($payload['billing']['chart']['legend'])->toHaveCount(3)
        ->and($payload['billing']['chart']['legend'][0][1])->toBe('0 %');

    // the panel page points the prototype's narrated card, cost breakdown, row actions and demo history at the seam
    $panel = $this->get('/panel/fakturace')->assertOk()->getContent();
    expect($panel)->toContain('src="/surfaces/api/onhost-panel-billing.api.js?v=')
        ->toContain('ledger: (window.OnhostPanelBilling && window.OnhostPanelBilling.ledger(this)) || {')
        ->toContain('return live.concat(window.ONHOST_PANEL ? [] : demo);')
        ->toContain('onAction: window.OnhostPanelBilling ? (() => window.OnhostPanelBilling.rowAction(this, i, unpaid)) : unpaid')
        ->toContain('rows: (window.OnhostPanelBilling && window.OnhostPanelBilling.breakdown(this)) || [')
        // credit top-ups create a payment intent; document rows are read-only; the prototype's bonus copy is not promised
        ->toContain("window.OnhostPanelBilling.topUp(this, Number(s.topUp), 'card')")
        ->toContain("window.OnhostPanelBilling.topUp(this, base, md.method === 'bank' ? 'bank' : 'card'); return; }")
        ->toContain("readOnly: !!window.ONHOST_PANEL, tableTitle: _('Faktury', 'Invoices'),")
        ->toContain("'method', (window.ONHOST_PANEL ? [['card', _('Platební karta', 'Payment card')")
        ->toContain("', due in 14 days')]\n              ]))],")
        ->toContain("creditBonus: window.ONHOST_PANEL ? '' : '+10 %',")
        ->toContain('const bonus = window.ONHOST_PANEL ? 0 : (base >= 5000 ? Math.round(base * 0.1) : 0);')
        ->toContain("(window.ONHOST_PANEL ? _('nejčastější volba', 'the usual choice') : _('bonus +10 %', 'bonus +10%'))")
        ->toContain("(window.ONHOST_PANEL ? _('měsíc provozu', 'a month of running') : _('bonus +10 % · měsíc provozu', 'bonus +10% · a month of running'))")
        ->toContain("this.money(totalSpend), (window.ONHOST_PANEL ? '' : '+6 %'),")
        ->toContain('sectionCards: window.ONHOST_PANEL ? null : [')
        ->toContain('const spendCols = (window.ONHOST_PANEL && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.chart && window.ONHOST_PANEL.billing.chart.months) || [')
        ->toContain('window.ONHOST_PANEL.billing.chart.legend.map((l, i) => [l[0], l[1], [acc,')
        // widgets, budget rows, quick cards and the narrated project-cost ledger of the billing tab
        ->toContain('widgets: (window.OnhostPanelBilling ? (w => window.OnhostPanelBilling.widgets(this, w)) : (w => w))([')
        ->toContain("        }\n      ]),\n      usage: { real: !!window.ONHOST_PANEL, title: _('Čerpání rozpočtu', 'Budget consumption'),")
        ->toContain('const svTabs = svSel ? ((window.OnhostPanelWorkbench && window.OnhostPanelWorkbench.tabs(this, svSel, SV.tabs)) || SV.tabs[svSel.type] || SV.tabs.web) : [];')
        ->toContain('const real = window.OnhostPanelWorkbench && window.OnhostPanelWorkbench.build(this, sel, tab, _);')
        ->toContain('src="/surfaces/api/onhost-panel-workbench.api.js?v=')
        ->toContain('groups: svGroups.filter(g => !window.ONHOST_PANEL || svTabs.some(t => t.g === g.key)).map(g => {')
        ->not->toContain('cizí Pterodactyl')->not->toContain('Otevřít aaPanel přes SSO')->not->toContain('Proxmox Backup Server v druhé')
        ->toContain('rows: (window.OnhostPanelBilling && window.OnhostPanelBilling.usageRows(this)) || [[')
        ->toContain("ledgerB: window.ONHOST_PANEL ? null : {\n        title: _('Náklady po projektech · srpen'")
        ->toContain('quick: (window.ONHOST_PANEL ? (q => q.slice(0, 1)) : (q => q))([')
        ->toContain("      ]),\n      chart: {\n        title: _('Náklady podle služeb', 'Cost by service'),")
        // overview: real domain count and availability, blank narrated counters, recent events from notifications
        ->toContain('src="/surfaces/api/onhost-panel-overview.api.js?v=')
        ->toContain("['domains', _('Domény a DNS', 'Domains and DNS'), (window.ONHOST_PANEL ? String(((window.ONHOST_PANEL.services || {}).domain || []).length) : '31'),")
        ->toContain("['status', _('Stav infrastruktury', 'Infrastructure status'), ((window.ONHOST_PANEL && window.ONHOST_PANEL.kpis && window.ONHOST_PANEL.kpis.uptime) || '99,993 %'),")
        ->toContain("['housing', _('Housing a racky', 'Housing and racks'), (window.ONHOST_PANEL ? '' : '24 U'),")
        ->toContain('rows: (window.OnhostPanelOverview && window.OnhostPanelOverview.events(this)) || [')
        ->toContain("[_('Zákazník', 'Customer'), ((window.ONHOST_PANEL && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.organization && window.ONHOST_PANEL.billing.terms) && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.organization.name) || 'Skladomat s.r.o.'],") // a staff account without an organisation has no billing block
        ->toContain("[_('Splatnost', 'Payment terms'), (window.ONHOST_PANEL && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.organization && window.ONHOST_PANEL.billing.terms) ? (window.ONHOST_PANEL.billing.terms.due_days + _(' dní · kredit, karta nebo převod', ' days · credit, card or transfer'))")
        ->toContain("((window.ONHOST_PANEL && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.organization && window.ONHOST_PANEL.billing.terms) && window.ONHOST && window.ONHOST.user ? !!window.ONHOST.user.mfa : s.twofa) ? _('zapnuto', 'on') : _('vypnuto', 'off')],")
        ->toContain("wide: window.ONHOST_PANEL ? null : {\n        title: _('Poslední deploye', 'Recent deploys'),")
        ->toContain("readOnly: !!window.ONHOST_PANEL, tableTitle: _('Služby s nejvyšší zátěží', 'Busiest services'),")
        ->toContain("stat(_('Otevřené tikety', 'Open tickets'), String(openTickets), (window.ONHOST_PANEL ? '' : _('1 nový', '1 new')),")
        ->toContain('allTickets() { return this.storeTickets().concat(window.ONHOST_PANEL ? [] : this.state.tickets); }')
        ->toContain('window.ONHOST_PANEL.billing.average_6m != null) ? window.ONHOST_PANEL.billing.average_6m : 17420), \'\',')
        ->not->toContain("creditBonus: '+10 %',")->not->toContain("this.money(this.state.credit), '+10 %',")->not->toContain("[_('Bonus do kreditu', 'Credit bonus'), '+10 %'],");
    expect($payload['billing']['terms']['due_days'])->toBe(14)->and($payload['billing']['organization']['billing_mode'])->toBe($org->refresh()->billing_mode)->toBe('prepaid')
        ->and($payload['billing']['dunning']['suspend_after_days'])->toBeGreaterThan(0)
        // the "Nová služba" wizard orders from the real catalog, regions and consent versions
        ->and($payload['catalog'])->not->toBeEmpty()
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['plans'])->not->toBeEmpty()
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['plans'][0]['monthly'])->toBeGreaterThan(0)
        ->and($payload['consents'])->toHaveKeys(['terms', 'privacy', 'withdrawal_waiver', 'dpa'])
        // §5x: the order centre shows what a plan contains, the add-ons a product carries, VPS images and the game configurator
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['plans'][0]['features'])->not->toBeEmpty()
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['addons']['options'])->not->toBeEmpty()
        ->and(collect($payload['catalog'])->firstWhere('key', 'vps')['images'])->toContain('debian-13')
        ->and($payload)->toHaveKey('game_config');
    expect($panel)->toContain('src="/surfaces/api/onhost-panel-order.api.js?v=')->toContain('src="/surfaces/api/onhost-panel-shop.api.js?v=')
        ->toContain('openNew: (e) => { if (e && e.preventDefault) e.preventDefault(); if (window.OnhostPanelShop) window.OnhostPanelShop.open(this, null); },')
        ->toContain('const ORDER_TYPES = (window.OnhostPanelOrder && window.OnhostPanelOrder.types(this)) || [')
        ->toContain("if (window.OnhostPanelOrder) { this.setState({ modal: null, mStep: 0 }); window.OnhostPanelOrder.place(this, { type: md.type, size: md.size || (orderSize && orderSize[0]), region: md.region, os: md.os, name: name, pay: md.pay || '' }, orderType, orderSize); return; }")
        ->toContain("'region', (window.OnhostPanelOrder && window.OnhostPanelOrder.regions(this)) || [['PRG1'")
        // summary rows that do not apply to the product are dropped by the order module (no location for a domain, no system image for hosting)
        ->toContain("...((window.OnhostPanelOrder && window.OnhostPanelOrder.summary) ? window.OnhostPanelOrder.summary(this, md, [[_('Lokalita', 'Location'), window.OnhostPanelOrder.regionLabel(this, md.region)]]) : [[_('Lokalita', 'Location'), md.region]]),")
        ->toContain("...((window.OnhostPanelOrder && window.OnhostPanelOrder.summary) ? window.OnhostPanelOrder.summary(this, md, [[_('Systém', 'System'), md.os]]) : [[_('Systém', 'System'), md.os]]),")
        ->toContain("const orderSize = orderSizes.find(x => x[0] === md.size) || (md.type === 'domain' ? orderSizes[0] : orderSizes[1]) || orderSizes[0];")
        ->toContain("(window.OnhostPanelOrder && window.OnhostPanelOrder.period) ? window.OnhostPanelOrder.period(this, md) : 'měsíc'")
        // account views: masked password fields, clickable side rows, one-time secrets in the wide block
        ->toContain('<input type="{{ ff.inputType }}" autoComplete="{{ ff.autoComplete }}" value="{{ ff.value }}"')
        ->toContain('<div onClick="{{ sr.on }}" style="{{ sr.style }}">')
        ->toContain('ledgers: [d.ledger, d.ledgerB, d.ledgerC, d.ledgerD].filter(Boolean).filter(L => !window.ONHOST_PANEL || L.real === true).map(L => {')
        ->toContain('hasWidgets: !!(d.widgets && d.widgets.filter(w => !window.ONHOST_PANEL || w.real === true).length),')
        ->toContain('hasSectionCards: !!(d.sectionCards && (!window.ONHOST_PANEL || d.sectionCardsReal === true)),')
        ->toContain('      ledgerC: (window.OnhostPanelBilling && window.OnhostPanelBilling.policyLedger(this)) || {')
        ->toContain("        real: true, title: _('Prvních 7 dní u nás', 'Your first 7 days here'),");

    // the seam script itself is served and keeps its double-execution guard (file responses carry no content in tests)
    $this->get('/surfaces/api/onhost-panel-billing.api.js')->assertOk();
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-billing.api.js')))->toContain('if (window.OnhostPanelBilling) return;');
});
