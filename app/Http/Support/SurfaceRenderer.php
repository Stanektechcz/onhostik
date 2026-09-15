<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Serves the pristine prototype surfaces from `apps/surfaces` with only the data seams the
 * template inventory allows (docs/ui/template-inventory.md §6):
 *  1. relative asset URLs → `/surfaces/…` (the files themselves are byte-identical to the prototype);
 *  2. `onhost-store.js` → `api/onhost-store.api.js`, `onhost-integrations.js`/`onhost-domains.js` → API-backed variants;
 *  3. `onhost-data.js` → server-generated `/surfaces/onhost-data.js`;
 *  4. injected `window.ONHOST = { apiBase, csrf, user, surface, demo }` + session bridge before `onhost-shell.js`;
 *  5. panel only: `SVC_DATA().services` / `state.servers` read `window.ONHOST_PANEL`, `liveSimulation` defaults to false.
 * Nothing visual is touched: typography, tokens, spacing and components stay as designed.
 */
final class SurfaceRenderer
{
    public const SURFACES = [
        'public' => 'Onhost.dc.html', 'panel' => 'Onhost-app.dc.html', 'admin' => 'Onhost-admin.dc.html',
        'partner' => 'Onhost-partner.dc.html', 'mobile' => 'Onhost-mobil.dc.html', 'widgets' => 'Onhost-widgets.dc.html',
    ];

    private const BOOT = '<!--__ONHOST_BOOT__-->';

    public function __construct(private readonly string $root) {}

    public function root(): string
    {
        return $this->root;
    }

    /** @param array<string,mixed> $boot the `window.ONHOST` object */
    public function render(string $surface, array $boot, bool $demo): string
    {
        $file = self::SURFACES[$surface] ?? null;
        if ($file === null || ! is_file($this->root.'/'.$file)) {
            abort(404);
        }
        $path = $this->root.'/'.$file;
        $key = 'surface:'.$surface.':'.($demo ? 'demo' : 'api').':'.filemtime($path).':'.filesize($path).':'.$this->assetVersion();
        $template = Cache::remember($key, 3600, fn () => $this->transform((string) file_get_contents($path), $surface, $demo));
        $json = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        // API-seam scripts are versioned by file mtime so browsers never keep a stale copy after a deploy.
        $v = fn (string $file) => '/surfaces/api/'.$file.'?v='.(@filemtime($this->root.'/api/'.$file) ?: '0');
        $inject = '<script>window.ONHOST = '.$json.';</script>'."\n".'<script src="'.$v('onhost-session-bridge.js').'"></script>';
        if (! $demo) {
            // The shell lazy-loads onhost-store.js unless window.OnhostStore already exists, so the API-backed
            // modules are preloaded here (each original module returns early when its global is defined).
            $inject .= "\n".'<script src="'.$v('onhost-store.api.js').'"></script>';
            if (in_array($surface, ['admin', 'panel', 'partner'], true)) {
                $inject .= "\n".'<script src="'.$v('onhost-domains.api.js').'"></script>';
            }
            if ($surface === 'public') {
                $inject .= "\n".'<script src="'.$v('onhost-svc-pages.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-game-config.api.js').'"></script>';
                $inject .= "\n".self::MOBILE_CSS;
                $inject .= "\n".'<script src="'.$v('onhost-cart.api.js').'"></script>';
            }
            if ($surface === 'panel') {
                $inject .= "\n".'<script src="'.$v('onhost-panel-billing.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-overview.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-order.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-workbench.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-tools.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-support.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-account.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-nav.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-projects.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-registrars.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-pages.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-panel-chat.api.js').'"></script>';
            }
            if ($surface === 'partner') {
                $inject .= "\n".'<script src="'.$v('onhost-partner.api.js').'"></script>';
            }
            if ($surface === 'admin') {
                $inject .= "\n".'<script src="'.$v('onhost-integrations.api.js').'"></script>';
                $inject .= "\n".'<script src="'.$v('onhost-admin.api.js').'"></script>';
            }
        }
        if ($surface === 'panel' && ($boot['user'] ?? null) !== null) {
            $inject .= "\n".'<script src="/surfaces/onhost-panel.js?t='.time().'"></script>';
        }

        $html = str_replace(self::BOOT, $inject, $template);
        if (in_array($surface, ['panel', 'admin'], true) && ! $demo && ($boot['user'] ?? null) !== null) {
            $html = $this->identity($html, $boot['user'], $surface);
        }

        return $html;
    }

    /**
     * Seam #33 (api/onhost-admin.api.js): outside demo mode the staff console keeps only API-backed areas and reads them from
     * the operational store and the staff API — ticket queue and thread, customers, incidents and maintenance cards, the
     * burning strip, the overview numbers, the activity log and the notification drawer. The prototype's narrated roster,
     * capacity strip, role switcher, seeded log and access grants are dropped; every hook keeps the literal as fallback.
     */
    /**
     * Seam #46 (api/onhost-partner.api.js): the partner portal gains one API-backed tab — the marketplace (listings, jobs,
     * deliveries). The prototype's tab table, the view flags and the page titles get one more entry each; the markup of the
     * tab is inserted before the payouts block. Every hook keeps the literal in place when the anchor is missing (logged).
     */
    private static function partnerSeams(string $html): string
    {
        $pairs = [
            ["  TAB_SLUG = { overview: 'prehled', clients: 'klienti', commissions: 'provize', payouts: 'vyplaty', whitelabel: 'whitelabel', assets: 'materialy' };",
                "  TAB_SLUG = { overview: 'prehled', clients: 'klienti', commissions: 'provize', payouts: 'vyplaty', whitelabel: 'whitelabel', assets: 'materialy', marketplace: 'marketplace' };"],
            ["      ['assets', _('Materiály', 'Materials'), '']\n    ];",
                "      ['assets', _('Materiály', 'Materials'), ''],\n      ['marketplace', 'Marketplace', (window.OnhostPartner && window.OnhostPartner.badge()) || '']\n    ];"],
            ["      isPayouts: s.tab === 'payouts', isWhitelabel: s.tab === 'whitelabel', isAssets: s.tab === 'assets',",
                "      isPayouts: s.tab === 'payouts', isWhitelabel: s.tab === 'whitelabel', isAssets: s.tab === 'assets',\n      isMarketplace: s.tab === 'marketplace', mkt: (s.tab === 'marketplace' && window.OnhostPartner) ? window.OnhostPartner.vals(this) : { t: {}, note: '', listingHead: [], orderHead: [], listings: [], orders: [], noListings: false, noOrders: false, newListing: () => {}, reload: () => {} },"],
            // the prototype writes the URL on its first render, before componentDidMount reads it — the server-set hash (/partner/marketplace → #/marketplace) would be lost (the same race as the panel, audit §5d)
            ['    this.syncHash(); // URL <- stav', '    if (this.__onhostMounted) this.syncHash(); // URL <- stav (only once mounted: the first render must not overwrite the deep link)'],
            ["  componentDidMount() {\n    const fromHash = this.parseHash();", "  componentDidMount() {\n    this.__onhostMounted = true;\n    const fromHash = this.parseHash();"],
            ['      t, tabs, periods, kpis, tier, top, feed, page: pages[s.tab] || pages.overview,',
                "      t, tabs, periods, kpis, tier, top, feed, page: pages[s.tab] || (s.tab === 'marketplace' && window.OnhostPartner ? window.OnhostPartner.page(this) : pages.overview),"],
            ['    <sc-if value="{{ isPayouts }}" hint-placeholder-val="{{ false }}">',
                self::PARTNER_MARKETPLACE_MARKUP.'    <sc-if value="{{ isPayouts }}" hint-placeholder-val="{{ false }}">'],
            // seam #47 (audit §5l-1): the prototype's own tabs read the partner API when it answers, the literal stays as fallback
            ['    const nav = (e) => { if (e && e.preventDefault) e.preventDefault(); };', "    const nav = (e) => { if (e && e.preventDefault) e.preventDefault(); };\n    if (window.OnhostPartner) window.OnhostPartner.sync(this); // the partner's model and white-label settings arrive from the API once"],
            ["      company: _('Atelier Šindelář · ID 4821', 'Atelier Šindelář · ID 4821'),", "      company: (window.OnhostPartner && window.OnhostPartner.company(this)) || _('Atelier Šindelář · ID 4821', 'Atelier Šindelář · ID 4821'),"],
            ['    const clients = this.CLIENTS(cs);', '    const clients = (window.OnhostPartner && window.OnhostPartner.clients(this)) || this.CLIENTS(cs);'],
            ["      ['clients', _('Klienti', 'Clients'), '10'],", "      ['clients', _('Klienti', 'Clients'), (window.OnhostPartner && window.OnhostPartner.clientBadge(this)) || '10'],"],
            ["      ['payouts', _('Výplaty', 'Payouts'), '1'],", "      ['payouts', _('Výplaty', 'Payouts'), window.OnhostPartner ? window.OnhostPartner.payoutBadge(this) : '1'],"],
            ["    const tiers = [[0, _('Bronz', 'Bronze'), 15], [25000, _('Stříbro', 'Silver'), 18], [50000, _('Zlato', 'Gold'), 22], [120000, _('Platina', 'Platinum'), 26]];", "    const tiers = (window.OnhostPartner && window.OnhostPartner.tiers(this)) || [[0, _('Bronz', 'Bronze'), 15], [25000, _('Stříbro', 'Silver'), 18], [50000, _('Zlato', 'Gold'), 22], [120000, _('Platina', 'Platinum'), 26]];"],
            ["    const rate = s.model === 'share' ? tiers[ti][2] / 100 : 0.15;", "    const liveRate = window.OnhostPartner ? window.OnhostPartner.rate(this) : null;\n    const rate = liveRate != null ? liveRate : (s.model === 'share' ? tiers[ti][2] / 100 : 0.15);"],
            ['    const kpis = [', '    const kpis = (window.OnhostPartner && window.OnhostPartner.kpis(this, mrrTotal, rate, mult)) || ['],
            ['    const feed = [', '    const feed = (window.OnhostPartner && window.OnhostPartner.feed(this)) || ['],
            ['value: this.mny(selC.mrr * rate * 14)', 'value: this.mny(selC.commission != null ? selC.commission : selC.mrr * rate * 14)'],
            ['    const commRows = months.map((m, i) => {', '    const commRows = (window.OnhostPartner && window.OnhostPartner.commRows(this)) || months.map((m, i) => {'],
            // §5m-1: the model switch is a contract change request to finance, not a local toggle
            ['      on: () => this.setState({ model: m[0] })', '      on: () => { if (window.OnhostPartner && window.OnhostPartner.requestModel(this, m[0])) return; this.setState({ model: m[0] }); }'],
            ['      models, modelNote, commHead, commRows, rules,', '      models, modelNote: (window.OnhostPartner && window.OnhostPartner.modelNote(this)) || modelNote, termsLive: !!(window.OnhostPartner && window.OnhostPartner.terms(this)), terms: (window.OnhostPartner && window.OnhostPartner.terms(this)) || [], termsTitle: (window.OnhostPartner && window.OnhostPartner.termsTitle(this)) || \'\', commHead, commRows, rules,'],
            // §5n-1: every contract term with a one-click request under the model buttons
            ["          <span style=\"font-size:13px;color:rgba(32,30,29,.66);max-width:36em\">{{ modelNote }}</span>\n        </div>\n", "          <span style=\"font-size:13px;color:rgba(32,30,29,.66);max-width:36em\">{{ modelNote }}</span>\n        </div>\n".self::PARTNER_TERMS_MARKUP],
            ["lead: _('Model si můžete přepnout kdykoli, změna platí od dalšího měsíce. Zpětně nic nepřepočítáváme.', 'Switch the model whenever you like; the change applies from next month. We never recompute retroactively.')", "lead: _('Model provize je smluvní podmínka: o změnu požádáte tlačítkem níže, finance ji schválí a platí od dalšího měsíce. Zpětně nic nepřepočítáváme.', 'The commission model is a contract term: ask for a change with the buttons below, finance approves it and it applies from next month. We never recompute retroactively.')"],
            ['    const live = (() => {', '    const live = (window.OnhostPartner && window.OnhostPartner.payoutLive(this)) || (() => {'],
            ['    const payData = [', '    const payData = (window.OnhostPartner && window.OnhostPartner.payRows(this, balanceNum, openPayoutNo)) || ['],
            ['        if (a >= 1000 && a <= balanceNum && /^CZ\\d{22}$/.test(ib)) {', "        if (window.OnhostPartner && a >= 1000 && /^CZ\\d{22}$/.test(ib)) { window.OnhostPartner.requestPayout(this, a, ib); return; }\n        if (a >= 1000 && a <= balanceNum && /^CZ\\d{22}$/.test(ib)) {"],
            ["        this.setState({ wlErr: '', wlVerified: true });", "        if (window.OnhostPartner) { window.OnhostPartner.saveWhitelabel(this, dom, s.wl); return; }\n        this.setState({ wlErr: '', wlVerified: true });"],
            ['      wlState: s.wlVerified ?', '      wlState: (s.wlVerified || (window.OnhostPartner && window.OnhostPartner.wlVerified(this))) ?'],
            ["      wlRecord: (domOk ? dom : 'panel.vasefirma.cz') + '.   300  IN  CNAME  wl.onhost.cz.", "      wlRecord: (window.OnhostPartner && window.OnhostPartner.wlRecord(this, domOk ? dom : 'panel.vasefirma.cz')) || (domOk ? dom : 'panel.vasefirma.cz') + '.   300  IN  CNAME  wl.onhost.cz."],
            ["    const brandName = s.wl.hideBrand ? (cs ? 'Atelier Šindelář' : 'Atelier Šindelář') : 'Onhost';", "    const brandName = s.wl.hideBrand ? ((window.OnhostPartner && window.OnhostPartner.orgName()) || (cs ? 'Atelier Šindelář' : 'Atelier Šindelář')) : 'Onhost';"],
            ["    const base = 'https://onhost.cz/?ref=SINDELAR4821';", "    const base = (window.OnhostPartner && window.OnhostPartner.refBase(this)) || 'https://onhost.cz/?ref=SINDELAR4821';"],
            ['    const files = [', '    const files = ((window.OnhostPartner && window.OnhostPartner.files(this)) || ['],
            ["    ].map(f => ({\n      name: f[0], meta: f[1],", "    ]).map(f => ({\n      name: f[0], meta: f[1],"],
            ["      on: (e) => { nav(e); this.flash(_('Stahuje se', 'Downloading'), f[0]", "      on: (e) => { nav(e); if (f[2]) { window.open(f[2], '_blank', 'noopener'); return; } this.flash(_('Stahuje se', 'Downloading'), f[0]"],
        ];
        foreach ($pairs as [$from, $to]) {
            if (substr_count($html, $from) !== 1) {
                Log::warning('surface seam #46 anchor mismatch', ['anchor' => mb_substr($from, 0, 80)]);

                continue;
            }
            $html = str_replace($from, $to, $html);
        }

        return $html;
    }

    /** The marketplace tab markup (seam #46): two tables — listings and jobs — with up to two actions per row. */
    private const PARTNER_TERMS_MARKUP = <<<'HTML'
        <sc-if value="{{ termsLive }}" hint-placeholder-val="{{ false }}">
          <div style="margin-top:16px;border:2px solid #201e1d;background:#f3f2f2;padding:12px 18px;max-width:62em">
            <div style="font-family:var(--font-heading,Archivo);font-weight:900;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.6)">{{ termsTitle }}</div>
            <sc-for list="{{ terms }}" as="tm" hint-placeholder-count="4">
              <div style="display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid rgba(32,30,29,.12);font-size:13px">
                <span><b style="font-family:var(--font-heading,Archivo);font-weight:800">{{ tm.label }}</b> · {{ tm.value }} <span style="color:rgba(32,30,29,.6)">{{ tm.note }}</span></span>
                <button onClick="{{ tm.on }}" style="{{ tm.style }}">{{ tm.action }}</button>
              </div>
            </sc-for>
          </div>
        </sc-if>

HTML;

    private const PARTNER_MARKETPLACE_MARKUP = <<<'HTML'
    <sc-if value="{{ isMarketplace }}" hint-placeholder-val="{{ false }}">
      <div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:30px">
          <button onClick="{{ mkt.newListing }}" style="background:#ec3013;border:none;color:#f3f2f2;font-family:var(--font-heading,Archivo);font-weight:800;font-size:12px;letter-spacing:.05em;text-transform:uppercase;padding:10px 16px;cursor:pointer">{{ mkt.t.newListing }}</button>
          <button onClick="{{ mkt.reload }}" style="background:transparent;border:2px solid #201e1d;color:#201e1d;font-family:var(--font-heading,Archivo);font-weight:800;font-size:12px;letter-spacing:.05em;text-transform:uppercase;padding:8px 14px;cursor:pointer">{{ mkt.t.reload }}</button>
          <span style="font-size:13px;color:rgba(32,30,29,.66);max-width:44em">{{ mkt.note }}</span>
        </div>
        <div style="margin-top:20px;overflow:auto;border:2px solid #201e1d;background:#f3f2f2">
          <table style="width:100%;border-collapse:collapse;font-size:14px;min-width:760px">
            <thead>
              <tr>
                <sc-for list="{{ mkt.listingHead }}" as="h" hint-placeholder-count="4">
                  <th style="{{ h.style }}">{{ h.label }}</th>
                </sc-for>
              </tr>
            </thead>
            <tbody>
              <sc-for list="{{ mkt.listings }}" as="r" hint-placeholder-count="3">
                <tr>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18)"><div style="font-family:var(--font-heading,Archivo);font-weight:800">{{ r.title }}</div><div style="font-size:12px;color:rgba(32,30,29,.6)">{{ r.sub }}</div></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap">{{ r.price }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap"><span style="{{ r.stateStyle }}">{{ r.state }}</span></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap"><button onClick="{{ r.a1On }}" style="{{ r.a1Style }}">{{ r.a1Label }}</button><button onClick="{{ r.a2On }}" style="{{ r.a2Style }}">{{ r.a2Label }}</button></td>
                </tr>
              </sc-for>
            </tbody>
          </table>
        </div>
        <div style="margin-top:24px;font-family:var(--font-heading,Archivo);font-weight:900;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.6)">{{ mkt.t.orders }}</div>
        <div style="margin-top:10px;overflow:auto;border:2px solid #201e1d;background:#f3f2f2">
          <table style="width:100%;border-collapse:collapse;font-size:14px;min-width:760px">
            <thead>
              <tr>
                <sc-for list="{{ mkt.orderHead }}" as="h" hint-placeholder-count="5">
                  <th style="{{ h.style }}">{{ h.label }}</th>
                </sc-for>
              </tr>
            </thead>
            <tbody>
              <sc-for list="{{ mkt.orders }}" as="o" hint-placeholder-count="3">
                <tr>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18)"><div style="font-family:var(--font-heading,Archivo);font-weight:800">{{ o.title }}</div><div style="font-size:12px;color:rgba(32,30,29,.6)">{{ o.brief }}</div></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap">{{ o.due }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap;font-family:var(--font-heading,Archivo);font-weight:800;color:#ae1800">{{ o.share }}</td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);white-space:nowrap"><span style="{{ o.stateStyle }}">{{ o.state }}</span></td>
                  <td style="padding:12px 14px;border-bottom:1px solid rgba(32,30,29,.18);text-align:right;white-space:nowrap"><button onClick="{{ o.a1On }}" style="{{ o.a1Style }}">{{ o.a1Label }}</button><button onClick="{{ o.a2On }}" style="{{ o.a2Style }}">{{ o.a2Label }}</button></td>
                </tr>
              </sc-for>
            </tbody>
          </table>
        </div>
      </div>
    </sc-if>

HTML;

    private static function adminSeams(string $html): string
    {
        $pairs = [
            // sidebar and role: only backed views, one real role (rights are enforced by the API)
            "    const allowed = s.mobile ? role[3].filter(v => ['queue', 'incidents', 'chat', 'teamchat', 'access'].indexOf(v) >= 0) : role[3];" => "    const allowed = (s.mobile ? role[3].filter(v => ['queue', 'incidents', 'chat', 'teamchat', 'access'].indexOf(v) >= 0) : role[3]).filter(v => !window.OnhostAdmin || window.OnhostAdmin.allows(v));",
            "    role: 'l2', chatSel: 'c1'," => "    role: window.OnhostAdmin ? window.OnhostAdmin.role() : 'l2', chatSel: 'c1',",
            "      roleLabel: _('Role a rozsah', 'Role and scope'),\n      roles: ROLES.map(r => ({" => "      roleLabel: window.OnhostAdmin ? '' : _('Role a rozsah', 'Role and scope'),\n      roles: (window.OnhostAdmin ? [] : ROLES).map(r => ({",
            // the ticket queue and thread come from the store
            "  TICKETS = [\n" => "  get TICKETS() { return (window.OnhostAdmin && window.OnhostAdmin.tickets(this)) || this.TICKETS_PROTO; }\n\n  TICKETS_PROTO = [\n",
            '        const msgs = conv[sel.id] || [' => '        const msgs = (window.OnhostAdmin && window.OnhostAdmin.thread(sel)) || conv[sel.id] || [',
            '    const sel = this.TICKETS.find(t => t.id === s.sel) || this.TICKETS[0];' => '    const sel = this.TICKETS.find(t => t.id === s.sel) || this.TICKETS[0] || (window.OnhostAdmin ? window.OnhostAdmin.emptyTicket(this) : undefined);',
            "          send: () => { const t = (s.replyDraft || '').trim(); if (!t) {" => "          send: () => { const t = (s.replyDraft || '').trim(); if (t && window.OnhostAdmin && window.OnhostAdmin.reply(this, sel, t)) { this.setState({ replyDraft: '' }); this.flash(_('Odesláno', 'Sent'), _('Zákazník odpověď uvidí v panelu a e-mailem.', 'The customer sees the reply in the panel and by e-mail.')); return; } if (!t) {",
            "if (t) { this.setState({ replyDraft: '' }); this.pushLog('ticket.reply'," => "if (t) { this.setState({ replyDraft: '' }); if (window.OnhostAdmin && window.OnhostAdmin.reply(this, sel, t)) return; this.pushLog('ticket.reply',",
            // ticket detail: actions, quick replies and the context column come from the store instead of the narrated set
            "          actions: [\n            [_('Knihovna odpovědí', 'Reply library'), true, () => this.setState({ view: allowed.indexOf('macros') >= 0 ? 'macros' : allowed[0] })]," => "          actions: (window.OnhostAdmin ? window.OnhostAdmin.ticketActions(this, sel, _) : [\n            [_('Knihovna odpovědí', 'Reply library'), true, () => this.setState({ view: allowed.indexOf('macros') >= 0 ? 'macros' : allowed[0] })],",
            "          ].map(q => ({\n            label: q[0], on: () => this.setState({ replyDraft: q[1] })," => "          ]).map(q => ({\n            label: q[0], on: () => this.setState({ replyDraft: q[1] }),",
            "          context: [\n            { title: sel.service, meta: _('16 vCPU / 64 GB · PRG1 · PostgreSQL 16 · zálohy immutable'," => "          context: (window.OnhostAdmin ? window.OnhostAdmin.context(this, sel, _) : [\n            { title: sel.service, meta: _('16 vCPU / 64 GB · PRG1 · PostgreSQL 16 · zálohy immutable',",
            "value: '14', dot: dot('off') }\n          ]\n        };\n      })() : s.view === 'teamchat' ? (() => {" => "value: '14', dot: dot('off') }\n          ])\n        };\n      })() : s.view === 'teamchat' ? (() => {",
            // customers from the staff API; the row actions open the customer's tickets
            "      customers: [\n        ['Skladomat s.r.o.', 'ID 84210'," => "      customers: ((window.OnhostAdmin && window.OnhostAdmin.customers(this, _)) || [\n        ['Skladomat s.r.o.', 'ID 84210',",
            '      ].filter(c => match(c[0]) || match(c[2])).map(c => ({' => '      ]).filter(c => match(c[0]) || match(c[2])).map(c => ({',
            "[_('Otevřít účet', 'Open the account'), true, () => { this.pushLog('customer.open'," => "[_('Otevřít účet', 'Open the account'), true, () => { if (typeof c[6] === 'function') { c[6](); return; } this.pushLog('customer.open',",
            "[_('Napsat', 'Message'), false, () => this.flash(_('Zpráva zákazníkovi', 'Message to the customer')," => "[_('Napsat', 'Message'), false, () => typeof c[7] === 'function' ? c[7]() : this.flash(_('Zpráva zákazníkovi', 'Message to the customer'),",
            // incident and maintenance cards
            "      incidents: (s.view === 'maintenance' ? [" => "      incidents: ((window.OnhostAdmin && window.OnhostAdmin.cards(this, _)) || (s.view === 'maintenance' ? [",
            "      ]).map(x => ({\n        title: x.title, body: x.body, age: x.age," => "      ])).map(x => ({\n        title: x.title, body: x.body, age: x.age,",
            "          on: () => { this.pushLog('incident.action', x.title + ' · ' + a[0]);" => "          on: () => { if (typeof a[2] === 'function') { a[2](); return; } this.pushLog('incident.action', x.title + ' · ' + a[0]);",
            // the burning strip
            '      alertOn: breach > 0,' => '      alertOn: window.OnhostAdmin ? window.OnhostAdmin.alert(this, _) !== null : breach > 0,',
            "      alertText: _('4830 · Herní server nedostupný · P1 bez vlastníka · mc-server-liga.cz', '4830 · Game server unreachable · P1 unassigned · mc-server-liga.cz')," => "      alertText: window.OnhostAdmin ? ((window.OnhostAdmin.alert(this, _) || {}).text || '') : _('4830 · Herní server nedostupný · P1 bez vlastníka · mc-server-liga.cz', '4830 · Game server unreachable · P1 unassigned · mc-server-liga.cz'),",
            "      alertClock: _('zbývá 6 min', '6 min left')," => "      alertClock: window.OnhostAdmin ? ((window.OnhostAdmin.alert(this, _) || {}).clock || '') : _('zbývá 6 min', '6 min left'),",
            "      alertTake: () => { this.pushLog('ticket.assign', '4830 → Petr Doležal');" => "      alertTake: () => { if (window.OnhostAdmin) { window.OnhostAdmin.alertTake(this); return; } this.pushLog('ticket.assign', '4830 → Petr Doležal');",
            // roster and capacity strip
            "      onCallTitle: _('Na směně', 'On call'),\n      onCall: [" => "      onCallTitle: window.OnhostAdmin ? '' : _('Na směně', 'On call'),\n      onCall: (window.OnhostAdmin ? [] : [",
            "        [_('vedoucí: Jan Barták', 'lead: Jan Barták'), 'ESC', 'warn', 'Jan B.']\n      ].map(o => ({" => "        [_('vedoucí: Jan Barták', 'lead: Jan Barták'), 'ESC', 'warn', 'Jan B.']\n      ]).map(o => ({",
            "      capacityTitle: _('Kapacita lokalit', 'Location capacity'),\n      capacity: [['PRG1', 78], ['PRG2', 64], ['BRQ', 51], ['FRA', 42]].map(c => ({" => "      capacityTitle: window.OnhostAdmin && !window.OnhostAdmin.capacity().length ? '' : _('Kapacita lokalit', 'Location capacity'),\n      capacity: ((window.OnhostAdmin && window.OnhostAdmin.capacity()) || [['PRG1', 78], ['PRG2', 64], ['BRQ', 51], ['FRA', 42]]).map(c => ({",
            // seeded activity log, access grants, overview numbers, notification drawer
            "    log: [\n      ['09:41', 'grant.request'," => "    log: window.OnhostAdmin ? [] : [\n      ['09:41', 'grant.request',",
            '      log: s.log.map(l => ({' => '      log: ((window.OnhostAdmin && window.OnhostAdmin.log(this)) || s.log).map(l => ({',
            "    grants: [\n" => "    grants: window.OnhostAdmin ? [] : [\n",
            "      kpis: [\n        { label: _('Medián první odpovědi', 'Median first reply'), value: '18 min'," => "      kpis: (window.OnhostAdmin && window.OnhostAdmin.kpis(this, _)) || [\n        { label: _('Medián první odpovědi', 'Median first reply'), value: '18 min',",
            "      notifCount: '4'," => "      notifCount: window.OnhostAdmin ? String(window.OnhostAdmin.unread()) : '4',",
            "      notifs: [\n        [_('P1 bez vlastníka · zbývá 6 minut', 'P1 unassigned · 6 minutes left')," => "      notifs: ((window.OnhostAdmin && window.OnhostAdmin.notifs(this, _)) || [\n        [_('P1 bez vlastníka · zbývá 6 minut', 'P1 unassigned · 6 minutes left'),",
            "      ].map(n => ({\n        title: n[0], meta: n[1], ago: n[2], dot: dot(n[3])," => "      ]).map(n => ({\n        title: n[0], meta: n[1], ago: n[2], dot: dot(n[3]),",
            "        { label: _('Směna', 'Shift'), value: '08–20 · 2', dot: dot('ok') }," => "        { label: window.OnhostAdmin ? _('Otevřené incidenty', 'Open incidents') : _('Směna', 'Shift'), value: window.OnhostAdmin ? String(window.OnhostAdmin.openIncidents()) : '08–20 · 2', dot: dot('ok') },",
            // the narrated per-role dashboards and timeline give way to the generic overview fed above
            "    const dashPanels = DASHROLE[s.role] || null;\n    const dashTl = DASHTL[s.role] || null;" => "    const dashPanels = window.OnhostAdmin ? null : (DASHROLE[s.role] || null);\n    const dashTl = window.OnhostAdmin ? null : (DASHTL[s.role] || null);",
            '      roleNote: role[2],' => "      roleNote: window.OnhostAdmin ? '' : role[2],",
            '        panels: (dashPanels || [' => '        panels: ((window.OnhostAdmin ? window.OnhostAdmin.dashPanels(this, _) : dashPanels) || [',
            '        timeline: (dashTl || [' => '        timeline: ((window.OnhostAdmin ? window.OnhostAdmin.timeline(this, _) : dashTl) || [',
            "        { label: _('Incidenty', 'Incidents'), value: '1', dot: dot('warn') }" => "        { label: _('Incidenty', 'Incidents'), value: window.OnhostAdmin ? String(window.OnhostAdmin.openIncidents()) : '1', dot: dot(window.OnhostAdmin && !window.OnhostAdmin.openIncidents() ? 'ok' : 'warn') }",
            "        [_('Nový incident', 'New incident'), true, () => { this.pushLog('incident.open'," => "        [_('Nový incident', 'New incident'), true, () => { if (window.OnhostAdmin) { window.OnhostAdmin.newIncident(this, _); return; } this.pushLog('incident.open',",
            "        [_('Předat směnu', 'Hand over the shift'), false, () => this.setState({ view: 'shift' })]," => "        [window.OnhostAdmin ? _('Fronta tiketů', 'Ticket queue') : _('Předat směnu', 'Hand over the shift'), false, () => this.setState({ view: window.OnhostAdmin ? 'queue' : 'shift' })],",
            "        [_('Kompenzace incidentu', 'Incident compensation'), false, () => { this.setState({ view: allowed.indexOf('money') >= 0 ? 'money' : allowed[0] });" => "        [window.OnhostAdmin ? _('Doklady', 'Documents') : _('Kompenzace incidentu', 'Incident compensation'), false, () => { if (window.OnhostAdmin) { this.setState({ view: 'invoices' }); return; } this.setState({ view: allowed.indexOf('money') >= 0 ? 'money' : allowed[0] });",
            "        ['dash', _('Přehled', 'Overview'), '4', 'warn']," => "        ['dash', _('Přehled', 'Overview'), window.OnhostAdmin ? '' : '4', window.OnhostAdmin ? 'ok' : 'warn'],",
            "        ['incidents', _('Incidenty', 'Incidents'), '1', 'warn']," => "        ['incidents', _('Incidenty', 'Incidents'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).incidents : '1', window.OnhostAdmin && !window.OnhostAdmin.openIncidents() ? 'ok' : 'warn'],",
            "        ['maintenance', _('Kalendář odstávek', 'Maintenance calendar'), '4', 'warn']," => "        ['maintenance', _('Kalendář odstávek', 'Maintenance calendar'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).maintenance : '4', window.OnhostAdmin ? 'ok' : 'warn'],",
            "        ['invoices', _('Faktury a doklady', 'Invoices'), '6', 'warn']," => "        ['invoices', _('Faktury a doklady', 'Invoices'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).invoices : '6', window.OnhostAdmin ? 'ok' : 'warn'],",
            "        ['customers', _('Zákazníci', 'Customers'), '412', 'off']" => "        ['customers', _('Zákazníci', 'Customers'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).customers : '412', 'off']",
            // table views backed by the staff API (audit §5f-2): game panels, the fleet, the jobs, the automation rules, the renewals ahead — counts in the sidebar, rows from the module
            '        const t = T[s.view];' => '        const t = (window.OnhostAdmin && window.OnhostAdmin.table(this, _, s.view)) || T[s.view];',
            // console deep links (audit §5g): `#/<console view>` resolves to the backed view (the prototype's VIEW_SLUG maps `automation` to another view), and the
            // first render must not overwrite the hash the link carried before componentDidMount reads it (the same race the panel had, §5d-2)
            "  viewForSlug(slug) {\n    const keys = Object.keys(this.VIEW_SLUG);\n" => "  viewForSlug(slug) {\n    if (window.OnhostAdmin && window.OnhostAdmin.allows && window.OnhostAdmin.allows(slug)) return slug;\n    const keys = Object.keys(this.VIEW_SLUG);\n",
            "    const next = '#/' + slug + (s.sel && (s.view === 'ticket' || s.view === 'queue' || s.view === 'customers') ? '/' + s.sel : '');\n    if (location.hash !== next) {" => "    const next = '#/' + slug + (s.sel && (s.view === 'ticket' || s.view === 'queue' || s.view === 'customers') ? '/' + s.sel : '');\n    if (location.hash !== next && this.__onhostMounted) {",
            "  componentDidMount() {\n    const p = this.props || {};\n    if (p.startRole) this.setState({ role: p.startRole });" => "  componentDidMount() {\n    this.__onhostMounted = true;\n    const p = this.props || {};\n    if (p.startRole) this.setState({ role: p.startRole });",
            "        ['fleet', _('Infrastruktura', 'Fleet'), '81', 'ok']," => "        ['fleet', _('Infrastruktura', 'Fleet'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).fleet : '81', window.OnhostAdmin ? window.OnhostAdmin.counts(this).fleetDot : 'ok'],",
            "        ['renewals', _('Obnovy a expirace', 'Renewals'), '7', 'warn']," => "        ['renewals', _('Obnovy a expirace', 'Renewals'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).renewals : '7', window.OnhostAdmin ? window.OnhostAdmin.counts(this).renewalsDot : 'warn'],",
            "        ['money', _('Kredity a platby', 'Credits and payments'), '6', 'warn']," => "        ['money', _('Kredity a platby', 'Credits and payments'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).money : '6', window.OnhostAdmin ? window.OnhostAdmin.counts(this).moneyDot : 'warn'],",
            "        ['automation', _('Automatizace', 'Automation'), '7', 'off']," => "        ['automation', _('Automatizace', 'Automation'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).automation : '7', window.OnhostAdmin ? 'ok' : 'off'],",
            "        ['jobsadm', _('Běhové úlohy', 'Jobs'), '6', 'off']," => "        ['jobsadm', _('Běhové úlohy', 'Jobs'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).jobsadm : '6', window.OnhostAdmin ? 'ok' : 'off'],",
            "        ['gnodes', _('Uzly a Wings', 'Nodes and Wings'), '6', 'warn']," => "        ['gnodes', window.OnhostAdmin ? _('Herní uzly', 'Game nodes') : _('Uzly a Wings', 'Nodes and Wings'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).gnodes : '6', window.OnhostAdmin ? window.OnhostAdmin.counts(this).gnodesDot : 'warn'],",
            "        ['geggs', _('Šablony a images', 'Templates and images'), '14', 'ok']," => "        ['geggs', window.OnhostAdmin ? _('Šablony her', 'Game templates') : _('Šablony a images', 'Templates and images'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).geggs : '14', 'ok'],",
            "        ['galloc', _('Alokace a IP', 'Allocations and IPs'), '412', 'ok']," => "        ['galloc', window.OnhostAdmin ? _('Alokace a porty', 'Allocations and ports') : _('Alokace a IP', 'Allocations and IPs'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).galloc : '412', 'ok'],",
            "        ['gprov', _('Provisioning fronta', 'Provisioning queue'), '2', 'warn']," => "        ['gprov', _('Provisioning fronta', 'Provisioning queue'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).gprov : '2', window.OnhostAdmin ? window.OnhostAdmin.counts(this).gprovDot : 'warn'],",
            // §5o: the page headers of the two repurposed views
            "      nodecost: [_('Náklady na uzel', 'Cost per node'), _('Bez tohoto čísla je každá sleva odhad.', 'Without this number every discount is a guess.')]," => "      nodecost: window.OnhostAdmin ? [_('Kapacita a nákup uzlů', 'Capacity and node purchases'), _('Forecast fondů podle trendu, návrhy nákupu a objednávky uzlů u dodavatele.', 'Pool forecast from the trend, purchase proposals and vendor node orders.')] : [_('Náklady na uzel', 'Cost per node'), _('Bez tohoto čísla je každá sleva odhad.', 'Without this number every discount is a guess.')],",
            "      coupons: [_('Slevy a kupóny', 'Discounts and coupons'), _('Kupón bez data ukončení nezakládáme — nekonečná sleva je špatně napsaný ceník.', 'No coupon without an end date — a permanent discount is a badly written price list.')]," => "      coupons: window.OnhostAdmin ? [_('Věrnost a kampaně', 'Loyalty and campaigns'), _('Misijní kampaně s odhadem nákladů dřív, než je otevřete.', 'Mission campaigns with the cost forecast before they open.')] : [_('Slevy a kupóny', 'Discounts and coupons'), _('Kupón bez data ukončení nezakládáme — nekonečná sleva je špatně napsaný ceník.', 'No coupon without an end date — a permanent discount is a badly written price list.')],",
            // §5o: two prototype table views repurposed — loyalty campaigns (`coupons`) and capacity requests (`nodecost`); labels and counts from the API
            "        ['coupons', _('Slevy a kupóny', 'Discounts'), '6', 'off']," => "        ['coupons', window.OnhostAdmin ? _('Věrnost a kampaně', 'Loyalty and campaigns') : _('Slevy a kupóny', 'Discounts'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).coupons : '6', window.OnhostAdmin ? 'ok' : 'off'],",
            "        ['nodecost', _('Náklady na uzel', 'Cost per node'), '6', 'warn']," => "        ['nodecost', window.OnhostAdmin ? _('Kapacita a nákup uzlů', 'Capacity and node purchases') : _('Náklady na uzel', 'Cost per node'), window.OnhostAdmin ? window.OnhostAdmin.counts(this).nodecost : '6', window.OnhostAdmin ? window.OnhostAdmin.counts(this).nodecostDot : 'warn'],",
        ];
        foreach ($pairs as $from => $to) {
            $count = substr_count($html, $from);
            if ($count !== 1) {
                Log::warning('surface seam #33 anchor mismatch', ['anchor' => mb_substr($from, 0, 60), 'count' => $count]);

                continue;
            }
            $html = str_replace($from, $to, $html);
        }
        // the ticket actions close right after the "Uzavřít" entry (the closing line alone is not unique in the template)
        $html = (string) preg_replace(
            '~(\[_\(\'Uzavřít\', \'Close\'\), false, \(\) => \{ this\.pushLog\(\'ticket\.close\', sel\.id\);[^\n]*\n)          \]\.map\(a => \(\{ label: a\[0\], on: a\[2\], style: btn\(a\[1\]\) \}\)\),\n          quick: \[~u',
            "$1          ]).map(a => ({ label: a[0], on: a[2], style: btn(a[1]) })),\n          quick: (window.OnhostAdmin ? window.OnhostAdmin.quick(this, sel, _) : [",
            $html,
            1,
            $count,
        );
        if ($count !== 1) {
            Log::warning('surface seam #33 anchor mismatch', ['anchor' => 'ticket actions close', 'count' => $count]);
        }

        return $html;
    }

    /** Header identity: the prototype's demo person (panel: Hana Nováková, admin: Petr Doležal) → the signed-in user, text nodes only. */
    /** The public account box, the panel user menu and the admin user menu sign out through the API (bridge-patched OnhostSession.signOut). */
    private static function signOutSeams(string $html): string
    {
        $call = 'if (window.OnhostSession && window.OnhostSession.__onhostBridged) { window.OnhostSession.signOut(); return; }';

        return str_replace(
            [
                "      logout: (e) => { if (e && e.preventDefault) e.preventDefault(); try { localStorage.removeItem('onhost.session'); } catch (x) {}",
                "        on: () => { if (m[2] === 'logout') { this.setState({ userOpen: false }); this.flash(_('Odhlášeno', 'Signed out'),",
                "        [_('Odhlásit se', 'Sign out'), '', () => { this.setState({ userOpen: false }); this.pushLog('auth.logout',",
            ],
            [
                "      logout: (e) => { if (e && e.preventDefault) e.preventDefault(); {$call} try { localStorage.removeItem('onhost.session'); } catch (x) {}",
                "        on: () => { if (m[2] === 'logout') { this.setState({ userOpen: false }); {$call} this.flash(_('Odhlášeno', 'Signed out'),",
                "        [_('Odhlásit se', 'Sign out'), '', () => { this.setState({ userOpen: false }); {$call} this.pushLog('auth.logout',",
            ],
            $html,
        );
    }

    private function identity(string $html, array $user, string $surface): string
    {
        $name = htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = preg_split('/\s+/u', trim((string) ($user['name'] ?? ''))) ?: [];
        $initials = mb_strtoupper(mb_substr((string) ($parts[0] ?? ''), 0, 1).mb_substr((string) ($parts[count($parts) - 1] ?? ''), 0, 1));
        if ($name === '') {
            return $html;
        }
        if ($surface === 'panel') {
            $html = str_replace(['>Hana Nováková</span>', '>Hana Nováková</div>', '>hana@skladomat.cz</div>'], ['>'.$name.'</span>', '>'.$name.'</div>', '>'.$email.'</div>'], $html);
            if ($initials !== '') {
                $html = (string) preg_replace('~(<span style="width:34px;height:34px;background:var\(--acc,#ec3013\)[^>]*>)HN(</span>)~', '$1'.$initials.'$2', $html, 1);
            }
        } else {
            $html = str_replace(['>Petr Doležal</span>', '>Petr Doležal</div>', '>petr.dolezal@onhost.cz · ID 118</div>'], ['>'.$name.'</span>', '>'.$name.'</div>', '>'.$email.' · '.htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES).'</div>'], $html);
            if ($initials !== '') {
                $html = (string) preg_replace('~(\n\s+)PD(<span style="\{\{ presenceDot \}\}">)~', '$1'.$initials.'$2', $html, 1);
            }
        }

        return $html;
    }

    /** Pure text transform of the prototype HTML (cached per file version). */
    public function transform(string $html, string $surface, bool $demo): string
    {
        // 1. relative assets → /surfaces/…  (support.js, _ds/…, assets/…, onhost-*.js, ios-frame.jsx); prototype scripts are
        //    versioned by the asset version so browsers refetch them after a deploy (the design-system bundle is content-addressed)
        $v = $this->assetVersion();
        $html = (string) preg_replace('~(src|href)="(?:\./)?((?:_ds|assets)/[^"]+|ios-frame\.jsx)"~', '$1="/surfaces/$2"', $html);
        $html = (string) preg_replace('~src="(?:\./)?(support\.js|onhost-[a-z0-9-]+\.js)"~', 'src="/surfaces/$1?v='.$v.'"', $html);

        // 2. data seams → API-backed variants (demo mode keeps the prototype's local store)
        if (! $demo) {
            $html = str_replace(
                ['src="/surfaces/onhost-store.js?v='.$v.'"', 'src="/surfaces/onhost-integrations.js?v='.$v.'"', 'src="/surfaces/onhost-domains.js?v='.$v.'"'],
                ['src="/surfaces/api/onhost-store.api.js?v='.$v.'"', 'src="/surfaces/api/onhost-integrations.api.js?v='.$v.'"', 'src="/surfaces/api/onhost-domains.api.js?v='.$v.'"'],
                $html,
            );
        }

        // 4. boot object + session bridge right before the shell (the shell must see window.ONHOST)
        $shell = '<script src="/surfaces/onhost-shell.js?v='.$v.'"></script>';
        if (str_contains($html, $shell)) {
            $html = str_replace($shell, self::BOOT."\n".$shell, $html);
        } else {
            $html = (string) preg_replace('~</helmet>~', self::BOOT."\n</helmet>", $html, 1);
        }

        // 4b. public checkout: the order number comes from the control plane (bridge sets window.__onhostOrder before the prototype's handler runs);
        //     the ".cz domain" upsell needs a checked domain name, so it is not pre-selected (domains are ordered in the panel)
        if ($surface === 'public' && ! $demo) {
            // product pages (onhost-svc-*.js) and the web hosting landing sell the catalogue's plans; cart lines carry their SKU (seam #23)
            $html = str_replace(
                [
                    'this.svcMods = [ms[0].webPages, ms[1].computePages, ms[2].otherPages, ms[3].morePages, ms[4].devPages, ms[5].corpPages];',
                    "      webPlans: (cs ? [\n        ['Start', 89,",
                    "      ]).map((p, i) => ({\n        name: p[0], price: this.czk(p[1]), per: cs ? '/měs' : '/mo', desc: p[2], feats: p[3], badge: p[4],",
                    "      this.addToCart(name, price || 0);\n    };\n  }",
                    // the game hosting landing sells the game product's plans (audit §5g-1): the same cards as the wizard and the cart, SKU-resolvable by "Gamehosting <plan>"
                    "    const gameSlots = [\n      { p: 149, cs: ['Squad', '',",
                    "'Support within 10 min']] }\n    ];\n\n    const cfgBase",
                ],
                [
                    'this.svcMods = [ms[0].webPages, ms[1].computePages, ms[2].otherPages, ms[3].morePages, ms[4].devPages, ms[5].corpPages].map(fn => (window.OnhostSvcPages ? window.OnhostSvcPages.wrap(fn) : fn));',
                    "      webPlans: ((window.ONHOST_DATA && window.ONHOST_DATA.webPlans && window.ONHOST_DATA.webPlans(cs)) || (cs ? [\n        ['Start', 89,",
                    "      ])).map((p, i) => ({\n        name: p[0], price: this.czk(p[1]), per: cs ? '/měs' : '/mo', desc: p[2], feats: p[3], badge: p[4],",
                    "      this.addToCart(name, price || 0, (window.OnhostSvcPages && window.OnhostSvcPages.sku(name, price)) || '');\n    };\n  }",
                    "    const gameSlots = (((window.ONHOST_DATA && window.ONHOST_DATA.gameSlots && (window.ONHOST_DATA.gameSlots(cs) || []).length) ? window.ONHOST_DATA.gameSlots(cs) : null) || [\n      { p: 149, cs: ['Squad', '',",
                    "'Support within 10 min']] }\n    ]);\n\n    const cfgBase",
                ],
                $html,
            );
            $html = self::gameSeams($html); // the game landing sells games (audit §5v/§5w)
            $html = str_replace(
                ["const id = 'OH-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random() * 9000) + 1000);", 'co: { domain: true, backup: true, ddos: false, mail: false }', '<label style="{{ po.style }}">'],
                ["const id = (window.__onhostOrder && window.__onhostOrder.number) || ('OH-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random() * 9000) + 1000));", 'co: { domain: false, backup: true, ddos: false, mail: false }', '<label style="{{ po.style }}" onClick="{{ po.on }}">'],
                $html,
            ); // the payment tiles define `po.on` (setState pay) but the prototype never wired it — the seam makes the method selectable
        }

        // 4c. public cart rules, per-line add-ons, domains in the cart, real promo codes, guest checkout, registration hints and the
        //     product pages' extra blocks (complete parameters, add-ons, configurator) — data from onhost-cart.api.js / onhost-svc-pages.api.js
        if ($surface === 'public' && ! $demo) {
            $html = self::cartSeams($html);
            $html = self::checkoutSeams($html);
        }

        // 4d. every surface's "sign out" is a prototype stub (localStorage + a toast): the bridge's OnhostSession.signOut()
        //     ends the server session and returns to the public site
        if (! $demo) {
            $html = self::signOutSeams($html);
        }

        // 4e. panel and staff console: amounts keep their haléře (3 363,80 Kč), and the web-order banner tells the truth
        //     while fulfilment waits on a node (the store flags such orders from GET /v1/orders → provisioning.stalled)
        if (in_array($surface, ['panel', 'admin'], true) && ! $demo) {
            $html = self::panelSeams($html);
        }

        // 5c. panel: notifications top right and readable; the dashboard, the empty service categories, the order wizard's
        //     payment choice, the account area (api/onhost-panel-account.api.js) and the pending transfers of the billing
        //     tab come from the control plane (api/onhost-panel-overview.api.js, api/onhost-panel-billing.api.js)
        if ($surface === 'panel' && ! $demo) {
            $acct = 'window.OnhostPanelAccount';
            $helpers = '{ stat, pill, bar, dot, rowStyle, match }';
            $html = str_replace(
                [
                    '<div style="position:fixed;right:24px;bottom:24px;z-index:80;background:var(--ink,#1a1918);color:#f3f2f2;border-left:4px solid var(--neon,#b8ff2e);padding:14px 18px;box-shadow:0 18px 40px rgba(0,0,0,.24);animation:ohIn .25s ease;max-width:360px">',
                    '<div style="font-family:var(--font-heading);font-weight:800;font-size:13px;margin-bottom:2px">{{ toast.title }}</div>',
                    '<div style="font-size:12px;color:rgba(243,242,242,.7);line-height:1.45">{{ toast.body }}</div>',
                    'this.toastTimer = setTimeout(() => this.setState({ toast: null }), 3800);',
                    "      stats: [\n        stat(_('Běžící služby', 'Running services'),",
                    "      quick: [\n        [_('Spustit server', 'Launch a server'),",
                    "      sectionCards: [\n        ['hardware', _('Hardware a e-shop', 'Hardware and shop'),",
                    "      advice: {\n        title: _('Rezervujte GPU na září', 'Reserve GPU for September'),",
                    "on: (d.advice || {}).onKind === 'chat' ? (() => this.setState({ chatOpen: true, chatUnread: 0 })) : (() => this.flash(d.advice.title,",
                    '        list: svList.map(x => ({',
                    "          on: () => this.setState({ svcId: x.id, svcTab: null, svcGo: null, wbDir: '/', wbF: { a: '', b: '', c: '' } }),",
                    "                text(_('Jméno instance', 'Instance name'), _('objeví se v panelu i v DNS', 'appears in the panel and in DNS'), 'name', 'app-prod-3')\n              ],",
                    "    if (T('api')) sets.api = {",
                    '    const settingsSecurity = {',
                    '    const settingsAccount = {',
                    '    const settingsSessions = {',
                    "    if (T('team')) sets.team = {",
                    "      ledgerD: {\n        title: _('Rozpočty na projekt', 'Budgets per project'),",
                    "      chart: {\n        title: _('Útrata po měsících', 'Spend by month'), note: _('posledních 12 měsíců, bez DPH', 'last 12 months, excl. VAT'),",
                    '      hasChart: !!d.chart,',
                    "        ['hardware', _('Hardware a e-shop', 'Hardware and shop'), '184'],\n        ['reseller', _('Reseller program', 'Reseller programme'), '42']\n",
                    "        ['costs', _('Náklady a optimalizace', 'Costs and optimisation'), '−' + this.money(2340)],",
                    "        ['audit', _('Oznámení a audit', 'Notifications and audit'), '3'],",
                    "        ['windows', _('Servisní okna', 'Maintenance windows'), '2'],",
                    "        ['monitoring', _('Monitoring a alerty', 'Monitoring and alerts'), '2', [",
                ],
                [
                    '<div role="status" aria-live="polite" style="position:fixed;right:24px;top:72px;z-index:120;background:var(--ink,#1a1918);color:#ffffff;border-left:4px solid var(--neon,#b8ff2e);padding:16px 20px;box-shadow:0 18px 40px rgba(0,0,0,.3);animation:ohIn .25s ease;max-width:460px;min-width:300px">',
                    '<div style="font-family:var(--font-heading);font-weight:800;font-size:15px;margin-bottom:4px;line-height:1.3">{{ toast.title }}</div>',
                    '<div style="font-size:13.5px;color:rgba(255,255,255,.9);line-height:1.5;word-break:break-word">{{ toast.body }}</div>',
                    'this.toastTimer = setTimeout(() => this.setState({ toast: null }), 8000);',
                    "      stats: (window.OnhostPanelOverview && window.OnhostPanelOverview.stats(this, stat, _)) || [\n        stat(_('Běžící služby', 'Running services'),",
                    "      quick: (window.OnhostPanelOverview && window.OnhostPanelOverview.quick(this, _)) || [\n        [_('Spustit server', 'Launch a server'),",
                    "      sectionCardsReal: true, sectionCards: (window.OnhostPanelOverview && window.OnhostPanelOverview.sectionCards(this, _)) || [\n        ['hardware', _('Hardware a e-shop', 'Hardware and shop'),",
                    "      advice: (window.OnhostPanelOverview && window.OnhostPanelOverview.advice(this, _)) || {\n        title: _('Rezervujte GPU na září', 'Reserve GPU for September'),",
                    "on: typeof (d.advice || {}).on === 'function' ? d.advice.on : (d.advice || {}).onKind === 'chat' ? (() => this.setState({ chatOpen: true, chatUnread: 0 })) : (() => this.flash(d.advice.title,",
                    '        list: (svList.length ? svList : ((window.OnhostPanelOverview && window.OnhostPanelOverview.emptyService(this, svCat, _)) || [])).map(x => ({',
                    "          on: x.on || (() => this.setState({ svcId: x.id, svcTab: null, svcGo: null, wbDir: '/', wbF: { a: '', b: '', c: '' } })),",
                    "                text(_('Jméno instance', 'Instance name'), _('objeví se v panelu i v DNS', 'appears in the panel and in DNS'), 'name', 'app-prod-3')\n              ].filter(f => !(window.OnhostPanelOrder && window.OnhostPanelOrder.hidden && window.OnhostPanelOrder.hidden(this, md, f))).map(f => (window.OnhostPanelOrder && window.OnhostPanelOrder.relabel) ? window.OnhostPanelOrder.relabel(this, md, f) : f).concat((window.OnhostPanelOrder && window.OnhostPanelOrder.payOptions) ? [choice(_('Platba', 'Payment'), _('kredit se čerpá první', 'credit is drawn first'), 'pay', window.OnhostPanelOrder.payOptions(this, md, orderSize))] : []),",
                    "    if (T('api')) sets.api = ({$acct} ? {$acct}.apiView(this, _, {$helpers}) : null) || {",
                    "    const settingsSecurity = ({$acct} ? {$acct}.securityView(this, _, {$helpers}) : null) || {",
                    "    const settingsAccount = ({$acct} ? {$acct}.accountView(this, _, {$helpers}) : null) || {",
                    "    const settingsSessions = ({$acct} ? {$acct}.sessionsView(this, _, {$helpers}) : null) || {",
                    "    if (T('team')) sets.team = ({$acct} ? {$acct}.teamView(this, _, {$helpers}) : null) || {",
                    "      ledgerD: (window.OnhostPanelBilling && window.OnhostPanelBilling.pendingLedger(this)) || {\n        title: _('Rozpočty na projekt', 'Budgets per project'),",
                    "      chart: (window.OnhostPanelOverview && window.OnhostPanelOverview.chart(this, _)) || {\n        title: _('Útrata po měsících', 'Spend by month'), note: _('posledních 12 měsíců, bez DPH', 'last 12 months, excl. VAT'),",
                    '      hasChart: !!(d.chart && (!window.ONHOST_PANEL || d.chart.real === true || d.chart.cols === spendCols)),',
                    "        ['svcdesk', _('Nová služba a katalog', 'New service and catalogue'), ''],\n        ['reseller', _('Reseller program', 'Reseller programme'), '']\n",
                    "        ['costs', _('Náklady a optimalizace', 'Costs and optimisation'), ''],",
                    "        ['audit', _('Oznámení a audit', 'Notifications and audit'), ''],",
                    "        ['windows', _('Servisní okna', 'Maintenance windows'), ''],",
                    "        ['monitoring', _('Monitoring a alerty', 'Monitoring and alerts'), '', [",
                ],
                $html,
            );
        }

        // 5. panel: services/servers from window.ONHOST_PANEL, live simulation off by default
        if ($surface === 'panel' && ! $demo) {
            $html = (string) preg_replace('~(\n\s+services: )\{(\n\s+domain: \[)~', '$1(window.ONHOST_PANEL && window.ONHOST_PANEL.services) || {$2', $html, 1);
            $html = (string) preg_replace('~(\n\s+servers: )\[(\n\s+\{ id: \'app-prod\')~', '$1(window.ONHOST_PANEL && window.ONHOST_PANEL.servers) || [$2', $html, 1);
            $html = str_replace('&quot;liveSimulation&quot;:{&quot;editor&quot;:&quot;boolean&quot;,&quot;default&quot;:true', '&quot;liveSimulation&quot;:{&quot;editor&quot;:&quot;boolean&quot;,&quot;default&quot;:false', $html);
            // account KPIs: wallet credit and 30-day availability come from window.ONHOST_PANEL.kpis (prototype literals stay as fallback)
            $kpi = 'window.ONHOST_PANEL && window.ONHOST_PANEL.kpis';
            $html = (string) preg_replace('~(\n\s+modal: null, mStep: 0, md: \{\}, )credit: 4200,~', '$1credit: ('.$kpi.' && window.ONHOST_PANEL.kpis.credit != null ? window.ONHOST_PANEL.kpis.credit : 4200),', $html, 1);
            $html = str_replace('this.money(4200)', 'this.money(this.state.credit)', $html);
            $html = str_replace("stat(_('Dostupnost 30 dní', 'Uptime, 30 days'), '99,99 %',", "stat(_('Dostupnost 30 dní', 'Uptime, 30 days'), ((".$kpi." && window.ONHOST_PANEL.kpis.uptime) || '99,99 %'),", $html);
            // projects (seam #35, api/onhost-panel-projects.api.js) and connected registrar accounts (seam #36, api/onhost-panel-registrars.api.js):
            // two settings pages the prototype does not have, rendered through its generic view like the account area; deep links
            // /panel/projekty and /panel/registratori (the WAPI password uses the account seam's `password` field kind)
            $html = str_replace(
                [
                    "    if (T('team')) sets.team = ({$acct} ? {$acct}.teamView(this, _, {$helpers}) : null) || {",
                    "    svcdesk: 'sluzba', hardware: 'hardware', housing: 'housing'\n  };",
                ],
                [
                    "    if (T('projects')) sets.projects = (window.OnhostPanelProjects ? window.OnhostPanelProjects.view(this, _, {$helpers}) : null) || sets.overview;\n    if (T('registrars')) sets.registrars = (window.OnhostPanelRegistrars ? window.OnhostPanelRegistrars.view(this, _, {$helpers}) : null) || sets.overview;\n    if (T('team')) sets.team = ({$acct} ? {$acct}.teamView(this, _, {$helpers}) : null) || {",
                    "    svcdesk: 'sluzba', hardware: 'hardware', housing: 'housing', projects: 'projekty', registrars: 'registratori', costs: 'naklady'\n  };",
                ],
                $html,
            );
            // the remaining narrated areas — notifications and audit, maintenance windows, costs, personal data, monitoring, backups —
            // read the organization's real data through api/onhost-panel-pages.api.js (seam #37); the narrated set stays as the fallback
            foreach (['audit', 'windows', 'costs', 'privacy', 'monitoring', 'backups'] as $page) {
                $html = str_replace("    if (T('{$page}')) sets.{$page} = {", "    if (T('{$page}')) sets.{$page} = (window.OnhostPanelPages ? window.OnhostPanelPages.{$page}(this, _, {$helpers}) : null) || {", $html);
            }
            // quick select (⌘K, seam #34): the header search answers with the basic actions, the organization's services, domains and
            // tickets; Enter takes the first hit, Escape clears (api/onhost-panel-nav.api.js `hits()` / `enter()`)
            $html = str_replace(
                [
                    '<div style="justify-self:center;display:flex;align-items:center;gap:8px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 28%,transparent);background:var(--field,#f8f4f4);padding:0 10px;height:38px;width:clamp(220px,30vw,440px)',
                    '<input type="text" value="{{ query }}" onInput="{{ onQuery }}" placeholder="{{ searchPh }}" style="flex:1;min-width:0;border:0;background:transparent;color:var(--fg,#201e1d);font-size:13px;padding:0;outline:none">',
                    '      query: s.query, onQuery: (e) => this.setState({ query: e.target.value }),',
                ],
                [
                    '<div style="position:relative;justify-self:center;display:flex;align-items:center;gap:8px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 28%,transparent);background:var(--field,#f8f4f4);padding:0 10px;height:38px;width:clamp(220px,30vw,440px)',
                    '<input type="text" value="{{ query }}" onInput="{{ onQuery }}" onKeyDown="{{ onQueryKey }}" placeholder="{{ searchPh }}" style="flex:1;min-width:0;border:0;background:transparent;color:var(--fg,#201e1d);font-size:13px;padding:0;outline:none" autocomplete="off">'
                    ."\n".'            <sc-if value="{{ quickHits }}" hint-placeholder-val="{{ false }}">'
                    ."\n".'              <div style="position:absolute;top:40px;left:-2px;right:-2px;z-index:70;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 28%,transparent);background:var(--panel,#fff);max-height:60vh;overflow:auto;box-shadow:0 12px 30px rgba(0,0,0,.14)">'
                    ."\n".'                <sc-for list="{{ quickHits }}" as="qh" hint-placeholder-count="4">'
                    ."\n".'                  <button onClick="{{ qh.on }}" style="{{ qh.style }}"><span style="font-family:var(--font-heading);font-weight:800;font-size:12px;color:var(--fg,#201e1d)">{{ qh.label }}</span><span style="font-size:11px;color:color-mix(in srgb,var(--fg,#201e1d) 60%,transparent)">{{ qh.meta }}</span></button>'
                    ."\n".'                </sc-for>'
                    ."\n".'              </div>'
                    ."\n".'            </sc-if>',
                    "      query: s.query, onQuery: (e) => this.setState({ query: e.target.value }),\n      quickHits: (window.OnhostPanelNav && window.OnhostPanelNav.hits) ? window.OnhostPanelNav.hits(this, _) : null,\n      onQueryKey: (e) => { if (e.key === 'Escape') { this.setState({ query: '' }); return; } if (e.key === 'Enter' && window.OnhostPanelNav && window.OnhostPanelNav.enter) { e.preventDefault(); window.OnhostPanelNav.enter(this, _); } },",
                ],
                $html,
            );
            // … and the full-page search shows the same real groups instead of the prototype's narrated lists
            $html = str_replace(
                ["        const groups = [\n          [_('Služby', 'Services'), s.servers.filter(x => hit(x.name) || hit(x.spec) || hit(x.site) || hit(x.os))", "        ];\n        const total = groups.reduce((a, g) => a + g[1].length, 0);"],
                ["        const groups = ((window.OnhostPanelNav && window.OnhostPanelNav.searchGroups) ? window.OnhostPanelNav.searchGroups(this, _) : [\n          [_('Služby', 'Services'), s.servers.filter(x => hit(x.name) || hit(x.spec) || hit(x.site) || hit(x.os))", "        ]);\n        const total = groups.reduce((a, g) => a + g[1].length, 0);"],
                $html,
            );
            // a deep link may only open a tab the sidebar offers (seam #29): the prototype's narrated areas (servers, deploys,
            // monitoring, forum, …) stay unreachable by URL outside demo mode
            $html = str_replace("    const tab = this.tabForSlug(parts[0]);\n    if (!tab) return null;", "    const tab = this.tabForSlug(parts[0]);\n    if (!tab || (window.OnhostPanelNav && window.OnhostPanelNav.allows && !window.OnhostPanelNav.allows(tab))) return null;", $html);
            // the top strip's "Aktivní služby" counts the organization's ACTIVE services (domains aside), like the overview KPI
            $html = str_replace("{ label: _('Aktivní služby', 'Active services'), value: String(s.servers.filter(x => x.state !== 'paused').length),", "{ label: _('Aktivní služby', 'Active services'), value: (window.ONHOST_PANEL ? String(Object.keys(window.ONHOST_PANEL.services || {}).filter(k => k !== 'domain').reduce((n, k) => n + (window.ONHOST_PANEL.services[k] || []).filter(x => x.apiState === 'ACTIVE').length, 0)) : String(s.servers.filter(x => x.state !== 'paused').length)),", $html);
            // billing tab: the invoice card, the cost breakdown and the history row actions come from the organization's
            // documents (api/onhost-panel-billing.api.js); the prototype's narrated demo history is dropped outside demo mode
            $html = str_replace(
                [
                    "ledger: {\n        title: _('Faktura 2026-08-0412', 'Invoice 2026-08-0412'),",
                    'return live.concat(demo);',
                    "onAction: unpaid\n                ? () => {\n                    S.payInvoice(i.id, cs ? 'karta' : 'card');",
                    "rows: [\n          { title: _('Servery a VPS', 'Servers and VPS'), meta: _('5 instancí', '5 instances'), value: this.money(8420), kind: 'ok' },",
                ],
                [
                    "ledger: (window.OnhostPanelBilling && window.OnhostPanelBilling.ledger(this)) || {\n        title: _('Faktura 2026-08-0412', 'Invoice 2026-08-0412'),",
                    'return live.concat(window.ONHOST_PANEL ? [] : demo);',
                    "onAction: window.OnhostPanelBilling ? (() => window.OnhostPanelBilling.rowAction(this, i, unpaid)) : unpaid\n                ? () => {\n                    S.payInvoice(i.id, cs ? 'karta' : 'card');",
                    "rows: (window.OnhostPanelBilling && window.OnhostPanelBilling.breakdown(this)) || [\n          { title: _('Servery a VPS', 'Servers and VPS'), meta: _('5 instancí', '5 instances'), value: this.money(8420), kind: 'ok' },",
                ],
                $html,
            );
            // credit top-up: the billing form and the "Dobít kredit" wizard create a real payment intent (POST /v1/payments/init)
            // instead of adding to a local number; the prototype's "+10 % bonus" copy is not a rule of the control plane and is
            // therefore not promised (document rows are read-only: no edit/duplicate/remove on invoices)
            $real = 'window.ONHOST_PANEL';
            $html = str_replace(
                [
                    "submit: () => {\n          const n = Number(s.topUp);",
                    "note: _('od ', 'from ') + this.money(5000) + _(' přidáváme 10 %', ' up we add 10%'),",
                    "tableTitle: _('Faktury', 'Invoices'),",
                    "_('Od pěti tisíc přidáváme deset procent navíc, zůstatek se převádí.', 'From five thousand up we add ten percent; the balance rolls over.')",
                    "note: _('kredit se čerpá dřív než platební karta · od ' + this.money(5000) + ' bonus 10 %', 'credit is drawn before your card · 10% bonus from ' + this.money(5000)),",
                    'const bonus = base >= 5000 ? Math.round(base * 0.1) : 0;',
                    "[_('Bonus 10 %', 'Bonus 10%'), bonus ? '+ ' + this.money(bonus) : _('od ' + this.money(5000), 'from ' + this.money(5000))],",
                    "'method', [\n                ['card', _('Karta ···4417', 'Card ···4417'),",
                    "', due in 14 days')]\n              ])],",
                    "_('karta ···4417', 'card ···4417')",
                    "this.setState(st => ({ modal: null, mStep: 0, credit: st.credit + base + bonus, tab: 'billing' }));",
                    "creditBonus: '+10 %',",
                    "this.money(this.state.credit), '+10 %',",
                    "[_('Bonus do kreditu', 'Credit bonus'), '+10 %'],",
                    "meta: _('čerpá se automaticky, bonus 10 %', 'drawn automatically, 10% bonus'), aux: '+10 %',",
                    "_('bonus +10 % · měsíc provozu', 'bonus +10% · a month of running')",
                    "_('bonus +10 %', 'bonus +10%')",
                    "this.money(totalSpend), '+6 %',",
                    "this.money(17420), '',",
                    "sectionCardsNote: _('spočítáno na vašich číslech, nikoli na ceníkovém příkladu', 'calculated on your numbers, not on a price-list example'),\n      sectionCards: [",
                    'const spendCols = [',
                    "legend: [[_('Servery', 'Servers'), '44 %', acc], ['GPU', '27 %', 'color-mix(in srgb,' + acc + ' 45%,transparent)'], [_('Data a síť', 'Data and network'), '29 %', 'var(--ink,#1a1918)']],",
                ],
                [
                    "submit: window.OnhostPanelBilling ? (() => window.OnhostPanelBilling.topUp(this, Number(s.topUp), 'card')) : () => {\n          const n = Number(s.topUp);",
                    "note: {$real} ? _('kartou přes platební bránu · kredit hned po zaplacení', 'by card through the gateway · credit right after paying') : _('od ', 'from ') + this.money(5000) + _(' přidáváme 10 %', ' up we add 10%'),",
                    "readOnly: !!{$real}, tableTitle: _('Faktury', 'Invoices'),",
                    "({$real} ? _('Kartou nebo převodem; zůstatek se převádí a čerpá automaticky.', 'By card or bank transfer; the balance rolls over and is drawn automatically.') : _('Od pěti tisíc přidáváme deset procent navíc, zůstatek se převádí.', 'From five thousand up we add ten percent; the balance rolls over.'))",
                    "note: {$real} ? _('kredit se čerpá dřív než platební karta · nevyčerpaný nepropadá', 'credit is drawn before your card · unused credit never expires') : _('kredit se čerpá dřív než platební karta · od ' + this.money(5000) + ' bonus 10 %', 'credit is drawn before your card · 10% bonus from ' + this.money(5000)),",
                    "const bonus = {$real} ? 0 : (base >= 5000 ? Math.round(base * 0.1) : 0);",
                    "{$real} ? [_('Doklad', 'Document'), _('potvrzení platby na fakturační e-mail', 'payment receipt to your billing e-mail')] : [_('Bonus 10 %', 'Bonus 10%'), bonus ? '+ ' + this.money(bonus) : _('od ' + this.money(5000), 'from ' + this.money(5000))],",
                    "'method', ({$real} ? [['card', _('Platební karta', 'Payment card'), _('přes platební bránu, kredit hned po zaplacení', 'through the payment gateway, credit right after paying')], ['bank', _('Bankovní převod', 'Bank transfer'), _('1–2 pracovní dny · variabilní symbol a QR kód dostanete po potvrzení', '1–2 working days · the reference and QR code come after confirming')]] : [\n                ['card', _('Karta ···4417', 'Card ···4417'),",
                    "', due in 14 days')]\n              ]))],",
                    "({$real} ? _('platební karta', 'payment card') : _('karta ···4417', 'card ···4417'))",
                    "if (window.OnhostPanelBilling) { this.setState(st => ({ modal: null, mStep: 0, tab: 'billing' })); window.OnhostPanelBilling.topUp(this, base, md.method === 'bank' ? 'bank' : 'card'); return; }\n              this.setState(st => ({ modal: null, mStep: 0, credit: st.credit + base + bonus, tab: 'billing' }));",
                    "creditBonus: {$real} ? '' : '+10 %',",
                    "this.money(this.state.credit), ({$real} ? '' : '+10 %'),",
                    "[_('Bonus do kreditu', 'Credit bonus'), {$real} ? _('žádný', 'none') : '+10 %'],",
                    "meta: {$real} ? _('čerpá se automaticky', 'drawn automatically') : _('čerpá se automaticky, bonus 10 %', 'drawn automatically, 10% bonus'), aux: {$real} ? '' : '+10 %',",
                    "({$real} ? _('měsíc provozu', 'a month of running') : _('bonus +10 % · měsíc provozu', 'bonus +10% · a month of running'))",
                    "({$real} ? _('nejčastější volba', 'the usual choice') : _('bonus +10 %', 'bonus +10%'))",
                    "this.money(totalSpend), ({$real} ? '' : '+6 %'),",
                    "this.money(({$real} && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.average_6m != null) ? window.ONHOST_PANEL.billing.average_6m : 17420), '',",
                    // the "what if" price cards narrate a fictional fleet (app-prod, L40S, housing); there is no real-data source for them yet
                    "sectionCardsNote: _('spočítáno na vašich číslech, nikoli na ceníkovém příkladu', 'calculated on your numbers, not on a price-list example'),\n      sectionCards: {$real} ? null : [",
                    // cost chart (overview + billing): twelve months of invoiced amounts split into servers / hosting / other
                    "const spendCols = ({$real} && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.chart && window.ONHOST_PANEL.billing.chart.months) || [",
                    "legend: ({$real} && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.chart) ? window.ONHOST_PANEL.billing.chart.legend.map((l, i) => [l[0], l[1], [acc, 'color-mix(in srgb,' + acc + ' 45%,transparent)', 'var(--ink,#1a1918)'][i]]) : [[_('Servery', 'Servers'), '44 %', acc], ['GPU', '27 %', 'color-mix(in srgb,' + acc + ' 45%,transparent)'], [_('Data a síť', 'Data and network'), '29 %', 'var(--ink,#1a1918)']],",
                ],
                $html,
            );
            // billing widgets/usage/quick cards and the narrated "cost by project" ledger; overview cards and the recent-events
            // strip (api/onhost-panel-overview.api.js reads the organization's notifications)
            $html = str_replace(
                [
                    "widgets: [\n        {\n          kind: 'donut', title: _('Struktura faktury', 'Invoice structure'), value: this.money(totalSpend),",
                    "        }\n      ],\n      usage: { title: _('Čerpání rozpočtu', 'Budget consumption'),",
                    "rows: [[_('Servery', 'Servers'), '44 %', 44], ['GPU', '27 %', 27], [_('Data a síť', 'Data and network'), '19 %', 19], [_('Kredit vyčerpán', 'Credit used'), '38 %', 38]] },",
                    "ledgerB: {\n        title: _('Náklady po projektech · srpen', 'Cost by project · August'),",
                    "quick: [\n        [_('Dobít kredit', 'Top up credit'),",
                    "      ],\n      chart: {\n        title: _('Náklady podle služeb', 'Cost by service'),",
                    "['hardware', _('Hardware a e-shop', 'Hardware and shop'), '184 ' + _('skladem', 'in stock'),",
                    "['housing', _('Housing a racky', 'Housing and racks'), '24 U',",
                    "['reseller', _('Reseller program', 'Reseller programme'), '42 ' + _('klientů', 'clients'),",
                    "['status', _('Stav infrastruktury', 'Infrastructure status'), '99,993 %',",
                    "['domains', _('Domény a DNS', 'Domains and DNS'), '31',",
                    "['backups', _('Zálohy', 'Backups'), '186 ' + _('bodů', 'points'),",
                    "rows: [\n          { title: _('Deploy ', 'Deploy ') + s.deploys[0].repo,",
                    "String(s.servers.filter(x => x.state !== 'paused').length), '+2',",
                    "stat(_('Otevřené tikety', 'Open tickets'), String(openTickets), _('1 nový', '1 new'),",
                    // per-service workbench: tabs from the service's feature catalogue, panels from the service API (api/onhost-panel-workbench.api.js)
                    '    const svTabs = svSel ? (SV.tabs[svSel.type] || SV.tabs.web) : [];',
                    "  WB_BUILD(sel, tab, _) {\n    if (!sel) return null;\n    return this.WB_CORE(sel, tab, _) || this.WB_GENERIC(sel, tab, _);",
                    '        groups: svGroups.map(g => {',
                    '        nav: svGroups.reduce((acc, g) => {',
                    "stat(_('Zdraví služeb', 'Service health'), '94 / 100', '', 3, 70, 8, 'ok')]",
                    // the per-section "resource usage" widget: narrated rows unless a seam marked them real; otherwise the real servers' averages
                    "usageTitle: (d.usage && d.usage.title) || _('Využití zdrojů', 'Resource usage'),",
                    "usage: ((d.usage && d.usage.rows) || [['CPU', avgCpu + ' %', avgCpu], ['RAM', avgRam + ' %', avgRam], ['NVMe', '78 %', 78], [_('Přenos', 'Transfer'), '8,4 TB', 42]]).map(u => ({",
                    "usageNote: (d.usage && d.usage.note) || _('Součet přes všechny služby, klouzavý průměr za 24 hodin.', 'Summed across all services, 24-hour rolling average.'),",
                    "usage: { title: _('Čerpání rozpočtu', 'Budget consumption'),",
                    // service header: the "key figure" is the activation date; the tab count and "4× daily backups" are narrated
                    "           stat(_('Parametr', 'Key figure'), svSel.value, '', 5, 55, 14, 'ok'),\n           stat(_('Záložek', 'Tabs'), String(svTabs.length), '', 4, 48, 10, 'ok'),\n           stat(_('Zálohy', 'Backups'), '4× ' + _('denně', 'daily'), '', 6, 64, 12, 'ok')]",
                    // overview "Účet a fakturace" side block, the narrated deploys strip and the busiest-services table
                    "[_('Zákazník', 'Customer'), 'Skladomat s.r.o.'],",
                    "[_('Tarif', 'Plan'), _('Firemní, měsíčně', 'Business, monthly')],",
                    "[_('Nejbližší faktura', 'Next invoice'), _('1. 9. · ', '1 Sep · ') + this.money(totalSpend)],",
                    "[_('Splatnost', 'Payment terms'), _('30 dní, převodem', '30 days, transfer')],",
                    "[_('Dvoufázové ověření', 'Two-factor'), s.twofa ? _('zapnuto', 'on') : _('vypnuto', 'off')],\n          [_('SLA', 'SLA'), _('99,99 %, kredity automaticky', '99.99%, credits automatic')]",
                    "wide: {\n        title: _('Poslední deploye', 'Recent deploys'),",
                    "tableTitle: _('Služby s nejvyšší zátěží', 'Busiest services'), tableNote: _('živě, obnova po 2 s', 'live, refreshed every 2 s'),",
                    // tickets: only the organization's real tickets (store) count, list and search — never the prototype's narrated ones
                    'allTickets() { return this.storeTickets().concat(this.state.tickets); }',
                    "value: String(s.tickets.length) + _(' za 30 dní', ' in 30 days'),",
                    "[_('Tikety', 'Tickets'), s.tickets.filter(t => hit(t.subject) || hit(t.service) || hit(t.id))",
                ],
                [
                    "widgets: (window.OnhostPanelBilling ? (w => window.OnhostPanelBilling.widgets(this, w)) : (w => w))([\n        {\n          kind: 'donut', title: _('Struktura faktury', 'Invoice structure'), value: this.money(totalSpend),",
                    "        }\n      ]),\n      usage: { title: _('Čerpání rozpočtu', 'Budget consumption'),",
                    "rows: (window.OnhostPanelBilling && window.OnhostPanelBilling.usageRows(this)) || [[_('Servery', 'Servers'), '44 %', 44], ['GPU', '27 %', 27], [_('Data a síť', 'Data and network'), '19 %', 19], [_('Kredit vyčerpán', 'Credit used'), '38 %', 38]] },",
                    "ledgerB: {$real} ? null : {\n        title: _('Náklady po projektech · srpen', 'Cost by project · August'),",
                    "quick: ({$real} ? (q => q.slice(0, 1)) : (q => q))([\n        [_('Dobít kredit', 'Top up credit'),", // only the real top-up card; the cap / payment-method / export cards are narrated
                    "      ]),\n      chart: {\n        title: _('Náklady podle služeb', 'Cost by service'),",
                    "['hardware', _('Hardware a e-shop', 'Hardware and shop'), ({$real} ? '' : '184 ' + _('skladem', 'in stock')),",
                    "['housing', _('Housing a racky', 'Housing and racks'), ({$real} ? '' : '24 U'),",
                    "['reseller', _('Reseller program', 'Reseller programme'), ({$real} ? '' : '42 ' + _('klientů', 'clients')),",
                    "['status', _('Stav infrastruktury', 'Infrastructure status'), (({$real} && window.ONHOST_PANEL.kpis && window.ONHOST_PANEL.kpis.uptime) || '99,993 %'),",
                    "['domains', _('Domény a DNS', 'Domains and DNS'), ({$real} ? String(((window.ONHOST_PANEL.services || {}).domain || []).length) : '31'),",
                    "['backups', _('Zálohy', 'Backups'), ({$real} ? '' : '186 ' + _('bodů', 'points')),",
                    "rows: (window.OnhostPanelOverview && window.OnhostPanelOverview.events(this)) || [\n          { title: _('Deploy ', 'Deploy ') + s.deploys[0].repo,",
                    "({$real} ? String(Object.keys((window.ONHOST_PANEL && window.ONHOST_PANEL.services) || []).filter(k => k !== 'domain').reduce((n, k) => n + (window.ONHOST_PANEL.services[k] || []).filter(x => x.apiState === 'ACTIVE').length, 0)) : String(s.servers.filter(x => x.state !== 'paused').length)), ({$real} ? '' : '+2'),",
                    "stat(_('Otevřené tikety', 'Open tickets'), String(openTickets), ({$real} ? '' : _('1 nový', '1 new')),",
                    '    const svTabs = svSel ? ((window.OnhostPanelWorkbench && window.OnhostPanelWorkbench.tabs(this, svSel, SV.tabs)) || SV.tabs[svSel.type] || SV.tabs.web) : [];',
                    "  WB_BUILD(sel, tab, _) {\n    if (!sel) return null;\n    const real = window.OnhostPanelWorkbench && window.OnhostPanelWorkbench.build(this, sel, tab, _);\n    if (real) return real;\n    return this.WB_CORE(sel, tab, _) || this.WB_GENERIC(sel, tab, _);",
                    "        groups: svGroups.filter(g => !{$real} || svTabs.some(t => t.g === g.key)).map(g => {",
                    "        nav: svGroups.filter(g => !{$real} || svTabs.some(t => t.g === g.key)).reduce((acc, g) => {",
                    "stat(_('Zdraví služeb', 'Service health'), ({$real} ? ((window.ONHOST_PANEL.kpis && window.ONHOST_PANEL.kpis.uptime) || '—') : '94 / 100'), '', 3, 70, 8, 'ok')]",
                    "usageTitle: (d.usage && (!{$real} || d.usage.real) && d.usage.title) || _('Využití zdrojů', 'Resource usage'),",
                    "usage: ((d.usage && (!{$real} || d.usage.real) && d.usage.rows) || ({$real} ? [['CPU', isFinite(avgCpu) ? avgCpu + ' %' : '—', isFinite(avgCpu) ? avgCpu : 0], ['RAM', isFinite(avgRam) ? avgRam + ' %' : '—', isFinite(avgRam) ? avgRam : 0]] : [['CPU', avgCpu + ' %', avgCpu], ['RAM', avgRam + ' %', avgRam], ['NVMe', '78 %', 78], [_('Přenos', 'Transfer'), '8,4 TB', 42]])).map(u => ({",
                    "usageNote: (d.usage && (!{$real} || d.usage.real) && d.usage.note) || ({$real} ? _('průměr běžících serverů z posledního měření', 'average of running servers from the last measurement') : _('Součet přes všechny služby, klouzavý průměr za 24 hodin.', 'Summed across all services, 24-hour rolling average.')),",
                    "usage: { real: !!{$real}, title: _('Čerpání rozpočtu', 'Budget consumption'),",
                    "           stat({$real} ? _('Aktivní od', 'Active since') : _('Parametr', 'Key figure'), svSel.value, '', 5, 55, 14, 'ok'),\n           stat({$real} ? _('Tarif', 'Plan') : _('Záložek', 'Tabs'), {$real} ? (svSel.product || '—') : String(svTabs.length), '', 4, 48, 10, 'ok'),\n           stat(_('Zálohy', 'Backups'), {$real} ? ((window.OnhostPanelWorkbench && (window.OnhostPanelWorkbench.state.features[svSel.id] || {}).features && window.OnhostPanelWorkbench.state.features[svSel.id].features.backups && window.OnhostPanelWorkbench.state.features[svSel.id].features.backups.enabled) ? (_('denně · ', 'daily · ') + (window.OnhostPanelWorkbench.state.features[svSel.id].features.backups.limit || '') + _(' dní', ' days')) : '—') : '4× ' + _('denně', 'daily'), '', 6, 64, 12, 'ok')]",
                    "[_('Zákazník', 'Customer'), ({$real} && window.ONHOST_PANEL.billing && window.ONHOST_PANEL.billing.organization.name) || 'Skladomat s.r.o.'],",
                    "[_('Tarif', 'Plan'), {$real} ? (window.ONHOST_PANEL.billing.organization.billing_mode === 'postpaid' ? _('Firemní, na fakturu', 'Business, invoiced') : _('Předplacený kredit', 'Prepaid credit')) : _('Firemní, měsíčně', 'Business, monthly')],",
                    "[_('Nejbližší faktura', 'Next invoice'), {$real} ? ((window.ONHOST_PANEL.billing.document && /^(due|overdue)$/.test(window.ONHOST_PANEL.billing.document.state)) ? (window.ONHOST_PANEL.billing.document.due || '') + ' · ' + this.money(window.ONHOST_PANEL.billing.document.outstanding) : _('nic k úhradě', 'nothing due')) : _('1. 9. · ', '1 Sep · ') + this.money(totalSpend)],",
                    "[_('Splatnost', 'Payment terms'), {$real} ? (window.ONHOST_PANEL.billing.terms.due_days + _(' dní · kredit, karta nebo převod', ' days · credit, card or transfer')) : _('30 dní, převodem', '30 days, transfer')],",
                    "[_('Dvoufázové ověření', 'Two-factor'), ({$real} && window.ONHOST && window.ONHOST.user ? !!window.ONHOST.user.mfa : s.twofa) ? _('zapnuto', 'on') : _('vypnuto', 'off')],\n          [_('SLA', 'SLA'), {$real} ? ((window.ONHOST_PANEL.billing.sla || []).join(', ') || 'standard') + _(' · kredity automaticky', ' · credits automatic') : _('99,99 %, kredity automaticky', '99.99%, credits automatic')]",
                    "wide: {$real} ? null : {\n        title: _('Poslední deploye', 'Recent deploys'),",
                    "readOnly: !!{$real}, tableTitle: _('Služby s nejvyšší zátěží', 'Busiest services'), tableNote: {$real} ? _('podle posledního měření', 'from the last measurement') : _('živě, obnova po 2 s', 'live, refreshed every 2 s'),",
                    "allTickets() { return this.storeTickets().concat({$real} ? [] : this.state.tickets); }",
                    "value: String(({$real} ? this.allTickets() : s.tickets).length) + _(' za 30 dní', ' in 30 days'),",
                    "[_('Tikety', 'Tickets'), ({$real} ? this.allTickets() : s.tickets).filter(t => hit(t.subject) || hit(t.service) || hit(t.id))",
                ],
                $html,
            );
        }

        // 5a. panel tickets: the customer's real conversation (api/onhost-panel-support.api.js) replaces the narrated "Tiket 4821" thread;
        //     the list's "Odpovědět" opens the thread here instead of the staff queue; replies, closing and ratings go through the store
        if ($surface === 'panel' && ! $demo) {
            $html = str_replace(
                [
                    "          if (t.live && window.OnhostSession) {\n            window.OnhostSession.handoff({ kind: 'ticket', id: t.storeId });\n            window.location.href = 'Onhost-admin.dc.html#/fronta';\n            return;\n          }\n",
                    "          { key: 'ticketService', label: _('Služba', 'Service'), kind: 'select', options: s.servers.map(x => x.name).concat([_('Účet a fakturace', 'Account and billing')]) },",
                    "    ticketSubject: '', ticketBody: '', ticketPriority: 'P3', ticketService: 'app-prod',",
                    "      thread: {\n        title: _('Tiket 4821 · Pomalé dotazy po migraci', 'Ticket 4821 · Slow queries after the migration'),",
                    "          on: () => this.flash(a[0], a[1])\n        })),",
                    "          this.setState({ threadDraft: '' });\n          this.flash(_('Odpověď odeslána', 'Reply sent'), _('Tiket 4821 · inženýr Petr Doležal má notifikaci, reakce do 22 minut.', 'Ticket 4821 · engineer Petr Doležal is notified; reply within 22 minutes.'));\n",
                ],
                [
                    "          if (t.live && window.OnhostPanelSupport) {\n            window.OnhostPanelSupport.open(this, t.storeId);\n            return;\n          }\n",
                    "          { key: 'ticketService', label: _('Služba', 'Service'), kind: 'select', options: window.OnhostPanelSupport ? [_('Účet a fakturace', 'Account and billing')].concat(window.OnhostPanelSupport.serviceNames()) : s.servers.map(x => x.name).concat([_('Účet a fakturace', 'Account and billing')]) },",
                    "    ticketSubject: '', ticketBody: '', ticketPriority: 'P3', ticketService: (window.ONHOST_PANEL ? 'Účet a fakturace' : 'app-prod'),",
                    "      thread: window.OnhostPanelSupport ? window.OnhostPanelSupport.thread(this, cs) : {\n        title: _('Tiket 4821 · Pomalé dotazy po migraci', 'Ticket 4821 · Slow queries after the migration'),",
                    "          on: () => (typeof a[3] === 'function' ? a[3]() : this.flash(a[0], a[1]))\n        })),",
                    "          this.setState({ threadDraft: '' });\n          if (typeof d.thread.onSend === 'function') { d.thread.onSend(t); this.flash(_('Odpověď odeslána', 'Reply sent'), _('Zpráva je v tiketu, podpora má notifikaci.', 'The message is in the ticket; support is notified.')); return; }\n          this.flash(_('Odpověď odeslána', 'Reply sent'), _('Tiket 4821 · inženýr Petr Doležal má notifikaci, reakce do 22 minut.', 'Ticket 4821 · engineer Petr Doležal is notified; reply within 22 minutes.'));\n",
                ],
                $html,
            );
        }

        // 5b. panel: the "Nová služba" wizard orders from the real catalog and regions through /v1/cart + /v1/orders
        //     (api/onhost-panel-order.api.js); narrated detail cards, chart widgets, "wide" strips and section cards are
        //     shown only when flagged `real` (data from the control plane) or when they are purely descriptive
        if ($surface === 'panel' && ! $demo) {
            $order = 'window.OnhostPanelOrder';
            $html = str_replace(
                [
                    'const ORDER_TYPES = [',
                    'const orderSizes = ORDER_SIZES[md.type] || ORDER_SIZES.server;',
                    'const orderSize = orderSizes.find(x => x[0] === md.size) || orderSizes[1];',
                    "'region', [['PRG1', 'PRG1 · ' + _('Praha Malešice', 'Prague Malešice'), '78 % ' + _('kapacity', 'capacity')],",
                    "'os', [['Debian 12', 'Debian 12', _('doporučeno', 'recommended')],",
                    "note: _('objednávka bez závazku · server běží do 55 sekund od potvrzení', 'no commitment · the server runs within 55 seconds of confirming'),",
                    "[_('Lokalita', 'Location'), md.region],",
                    "[_('Systém', 'System'), md.os],",
                    "choice(_('Velikost', 'Size'), _('měnitelná za provozu', 'changeable live'), 'size', orderSizes.map(x => [x[0], x[1], this.money(x[2]) + _(' / měsíc', ' / month')])),",
                    "[_('Cena měsíčně bez DPH', 'Monthly price excl. VAT'), this.money(orderSize[2])],",
                    "[_('Dnes zaplatíte (poměrná část)', 'Charged today (pro rata)'), this.money(Math.round(orderSize[2] * 0.23)), true]",
                    "footNote: _('Poměrnou část účtujeme jen za zbytek měsíce. Kredit ' + this.money(s.credit) + ' se použije první.', 'We charge pro rata for the rest of the month only. Your ' + this.money(s.credit) + ' credit is used first.'),",
                    "              this.setState(st => ({\n                modal: null, mStep: 0, tab: 'servers', selected: null,",
                    // narrated blocks: only `real` ones survive on the API-backed panel
                    'ledgers: [d.ledger, d.ledgerB, d.ledgerC, d.ledgerD].filter(Boolean).map(L => {',
                    'hasWidgets: !!(d.widgets && d.widgets.length),',
                    '      widgets: (d.widgets || []).map(w => {',
                    'hasWide: !!d.wide, wide: d.wide || { rows: [] },',
                    // sidebar from the offer (api/onhost-panel-nav.api.js): group definitions, service-desk categories, custom click handlers
                    "    const groupDefs = [\n      ['infra', _('Provoz', 'Operations'), [\n",
                    "      cats: [\n        { key: 'domain', label: _('Domény a DNS', 'Domains and DNS'), crumb: _('Domény', 'Domains') },\n",
                    "          on: () => this.setState(Object.assign({ tab: sb[1], selected: null, query: '', filter: 'all', userOpen: false, curOpen: false, notifOpen: false, mobileNav: false, svcId: null, svcTab: null, svcGo: null, wbDir: '/' }, patch)),",
                    "      on: () => this.setState(st => Object.assign(\n        { tab: x[0], selected: null, filter: 'all', query: '', userOpen: false, curOpen: false, notifOpen: false, mobileNav: subs ? st.mobileNav : false },\n",
                    // the chat talks to the ONhost AI assistant (api/onhost-panel-chat.api.js): real answers, chips from proposed actions, hand-off to a ticket
                    "  askAi(text) {\n    const now = this.clock();\n",
                    "    chatOpen: false, chatUnread: 2, chatTyping: false, chatAgent: null, chatDraft: '', aiPrompt: '',\n    chatMsgs: [\n",
                    "      chatMeta: s.chatAgent === 'joining' ? _('hledáme inženýra od té služby…', 'finding the engineer who runs it…') : s.chatAgent ? _('Petr Doležal · inženýr databází · připojen', 'Petr Doležal · database engineer · joined') : s.chatTyping ? _('píše odpověď…', 'writing an answer…') : _('vidí vaše služby · odpoví do 10 s', 'sees your services · answers within 10 s'),",
                    "      chatTypingLabel: _('Asistent hledá v metrikách a dokumentaci…', 'The assistant is reading metrics and docs…'),",
                    "      chatChips: [\n        [_('Proč je db-primary pomalá?', 'Why is db-primary slow?'),",
                    "      ].map(c => ({\n        label: c[0], on: () => this.askAi(c[1]),\n",
                    "_('Předat živému inženýrovi s celým kontextem', 'Hand to a live engineer with full context'),",
                    "      chatEscalate: () => {\n        if (s.chatAgent && s.chatAgent !== 'joining') {\n",
                    "        text: m.text, ago: m.ago, who: m.who === 'me' ? _('Vy', 'You') : m.who === 'op' ? 'Petr Doležal · Onhost' : 'Onhost AI',",
                    "          isSelect: f.kind === 'select', isArea: f.kind === 'area', isText: f.kind !== 'select' && f.kind !== 'area',",
                    '<input type="text" value="{{ ff.value }}" onChange="{{ ff.on }}" onInput="{{ ff.on }}" placeholder="{{ ff.ph }}"',
                    '      sideRows: ((d.side && d.side.rows) || []).map(x => ({ title: x.title, meta: x.meta, value: x.value, dot: dot(x.kind) })),',
                    '            <sc-for list="{{ sideRows }}" as="sr" hint-placeholder-count="4">'."\n".'              <div style="padding:11px 16px;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 13%,transparent);display:flex;align-items:flex-start;gap:10px">',
                    "      infraLabel: _('Devět lokalit, vše v provozu', 'Nine locations, all operational'),",
                    'hasSectionCards: !!d.sectionCards,',
                    "      ledgerC: {\n        title: _('Co se stane, když nezaplatíte', 'What happens if you do not pay'),",
                    // descriptive blocks that carry no customer data stay visible
                    "        title: _('Co je u herních serverů v ceně', 'What game hosting includes'),",
                    "        title: _('Co je v ceně housingu', 'What housing includes'),",
                    "        title: _('Jak reseller program funguje', 'How the reseller programme works'),",
                    "        title: _('Co asistent zvládne', 'What the assistant handles'), note: _('a kde vždy předdá člověku', 'and where it always defers to a human'),",
                    "        title: _('Fakturace a platby', 'Billing and payments'), note: _('co používáme pro doklady', 'what we use for documents'),",
                    "        title: _('Co znamenají pojmy, které u nás uvidíte', 'What the terms you see here mean'),",
                    "        title: _('Citlivé akce se dvěma lidmi', 'Sensitive actions need two people'),",
                    "        title: _('Jaké okno si můžete vybrat', 'Which window you can choose'),",
                    "        title: _('Co si v panelu můžete přenastavit', 'What you can change in the panel'),",
                    "        title: _('Prvních 7 dní u nás', 'Your first 7 days here'),",
                    "      sectionCards: [\n        ['hardware', _('Hardware a e-shop', 'Hardware and shop'),",
                    "sectionCardsTitle: _('Úrovně priority a co znamenají', 'Priority tiers and what they mean'),",
                    "sectionCardsTitle: _('Kde se komunita schazí', 'Where the community meets'),",
                    "sectionCardsTitle: _('Kolekce', 'Collections'),",
                    // customers never learn which vendor panels run behind their services (blueprint: the executor is an implementation detail)
                    "_('Vyvezeme ho ve formátu, který přečte i cizí Pterodactyl. Odejít od nás musí být stejně snadné jako přijít.', 'Exported in a format any other Pterodactyl reads. Leaving must be as easy as arriving.')",
                    "_('Přijali jsme standardní JSON z Pterodactylu. Konflikty ukážeme před uložením — nepřepíšeme nic mlčky.', 'We accepted a standard Pterodactyl JSON. Conflicts are shown before saving — we overwrite nothing silently.')",
                    "_('do cizího panelu se přihlašujete naším účtem · heslo do aaPanelu nikdo nezná, ani my', 'you sign in to the third-party panel with our account · nobody knows the aaPanel password, not even us')",
                    "_('Otevřít aaPanel přes SSO', 'Open aaPanel via SSO')",
                    "'SSO průchod do aaPanelu'",
                    "['Zálohy PBS', 'PBS backups', 'Připojíme Proxmox Backup Server v druhé lokalitě a zapneme ověřování záloh.', 'We attach a Proxmox Backup Server in a second location and enable verification.']",
                ],
                [
                    "const ORDER_TYPES = ({$order} && {$order}.types(this)) || [",
                    "const orderSizes = ({$order} && {$order}.sizes(this, md.type)) || ORDER_SIZES[md.type] || ORDER_SIZES.server;",
                    "const orderSize = orderSizes.find(x => x[0] === md.size) || (md.type === 'domain' ? orderSizes[0] : orderSizes[1]) || orderSizes[0];",
                    "'region', ({$order} && {$order}.regions(this)) || [['PRG1', 'PRG1 · ' + _('Praha Malešice', 'Prague Malešice'), '78 % ' + _('kapacity', 'capacity')],",
                    // game servers pick their template (egg) in the "system and image" step (api/onhost-panel-order.api.js images())
                    "'os', ({$order} && {$order}.images(this, md.type)) || [['Debian 12', 'Debian 12', _('doporučeno', 'recommended')],",
                    "note: {$real} ? _('bez závazku · z kreditu se služba spouští ihned, jinak po připsání platby', 'no commitment · paid from credit the service starts at once, otherwise once the payment arrives') : _('objednávka bez závazku · server běží do 55 sekund od potvrzení', 'no commitment · the server runs within 55 seconds of confirming'),",
                    // summary rows that do not apply to the product (location of a domain, system image of web hosting) are dropped by the order module
                    "...(({$order} && {$order}.summary) ? {$order}.summary(this, md, [[_('Lokalita', 'Location'), {$order}.regionLabel(this, md.region)]]) : [[_('Lokalita', 'Location'), md.region]]),",
                    "...(({$order} && {$order}.summary) ? {$order}.summary(this, md, [[_('Systém', 'System'), md.os]]) : [[_('Systém', 'System'), md.os]]),",
                    "choice(_('Velikost', 'Size'), _('měnitelná za provozu', 'changeable live'), 'size', orderSizes.map(x => [x[0], x[1], x[3] != null ? x[3] : this.money(x[2]) + _(' / měsíc', ' / month')])),",
                    "[md.type === 'domain' ? _('Cena bez DPH', 'Price excl. VAT') : _('Cena měsíčně bez DPH', 'Monthly price excl. VAT'), ({$order} && {$order}.priceLabel) ? {$order}.priceLabel(this, md, orderSize) : this.money(orderSize[2])],\n                ...({$real} ? [[_('Souhlas', 'Consent'), _('VOP, ochrana údajů, DPA a SLA · potvrzením souhlasíte se zahájením ihned', 'terms, privacy, DPA and SLA · confirming starts the service at once')]] : []),",
                    "{$real} ? [_('Platba', 'Payment'), ({$order} && {$order}.payLabel ? {$order}.payLabel(this, md, orderSize) : (this.state.credit >= orderSize[2] * 1.21 ? _('z kreditu · zůstatek ', 'from credit · balance ') + this.money(this.state.credit) : _('zálohovou fakturou (převodem), nebo nejdřív dobijte kredit', 'by proforma (bank transfer), or top up credit first'))), true] : [_('Dnes zaplatíte (poměrná část)', 'Charged today (pro rata)'), this.money(Math.round(orderSize[2] * 0.23)), true]",
                    "footNote: {$real} ? _('Účtujeme celé období dopředu (' + (({$order} && {$order}.period) ? {$order}.period(this, md) : 'měsíc') + '). Kredit ' + this.money(s.credit) + ' se použije první; bez kreditu vystavíme zálohovou fakturu.', 'We charge the whole period (a ' + (({$order} && {$order}.period) ? {$order}.period(this, md) : 'month') + ') up front. Your ' + this.money(s.credit) + ' credit is used first; without credit we issue a proforma.') : _('Poměrnou část účtujeme jen za zbytek měsíce. Kredit ' + this.money(s.credit) + ' se použije první.', 'We charge pro rata for the rest of the month only. Your ' + this.money(s.credit) + ' credit is used first.'),",
                    "              if ({$order}) { this.setState({ modal: null, mStep: 0 }); {$order}.place(this, { type: md.type, size: md.size || (orderSize && orderSize[0]), region: md.region, os: md.os, name: name, pay: md.pay || '' }, orderType, orderSize); return; }\n              this.setState(st => ({\n                modal: null, mStep: 0, tab: 'servers', selected: null,",
                    "ledgers: [d.ledger, d.ledgerB, d.ledgerC, d.ledgerD].filter(Boolean).filter(L => !{$real} || L.real === true).map(L => {",
                    "hasWidgets: !!(d.widgets && d.widgets.filter(w => !{$real} || w.real === true).length),",
                    "      widgets: (d.widgets || []).filter(w => !{$real} || w.real === true).map(w => {",
                    "hasWide: !!(d.wide && (!{$real} || d.wide.real === true)), wide: (d.wide && (!{$real} || d.wide.real === true)) ? d.wide : { rows: [] },",
                    "    const groupDefs = (window.OnhostPanelNav && window.OnhostPanelNav.groups(this, _, { openTickets: openTickets, count: (c) => this.SVC_COUNT(c), total: this.SVC_TOTAL() })) || [\n      ['infra', _('Provoz', 'Operations'), [\n",
                    "      cats: (window.OnhostPanelNav && window.OnhostPanelNav.cats(this, _)) || [\n        { key: 'domain', label: _('Domény a DNS', 'Domains and DNS'), crumb: _('Domény', 'Domains') },\n",
                    "          on: () => sb[4] ? sb[4](this) : this.setState(Object.assign({ tab: sb[1], selected: null, query: '', filter: 'all', userOpen: false, curOpen: false, notifOpen: false, mobileNav: false, svcId: null, svcTab: null, svcGo: null, wbDir: '/' }, patch)),",
                    "      on: () => x[4] ? x[4](this) : this.setState(st => Object.assign(\n        { tab: x[0], selected: null, filter: 'all', query: '', userOpen: false, curOpen: false, notifOpen: false, mobileNav: subs ? st.mobileNav : false },\n",
                    "  askAi(text) {\n    if (window.OnhostPanelChat && window.OnhostPanelChat.ask(this, text)) return;\n    const now = this.clock();\n",
                    "    chatOpen: false, chatUnread: window.ONHOST_PANEL ? 0 : 2, chatTyping: false, chatAgent: null, chatDraft: '', aiPrompt: '',\n    chatMsgs: (window.OnhostPanelChat && window.OnhostPanelChat.welcome()) || [\n",
                    "      chatMeta: (window.OnhostPanelChat && window.OnhostPanelChat.meta(this, _)) || (s.chatAgent === 'joining' ? _('hledáme inženýra od té služby…', 'finding the engineer who runs it…') : s.chatAgent ? _('Petr Doležal · inženýr databází · připojen', 'Petr Doležal · database engineer · joined') : s.chatTyping ? _('píše odpověď…', 'writing an answer…') : _('vidí vaše služby · odpoví do 10 s', 'sees your services · answers within 10 s')),",
                    "      chatTypingLabel: window.ONHOST_PANEL ? _('Asistent hledá ve vašem účtu a v dokumentaci…', 'The assistant is reading your account and the docs…') : _('Asistent hledá v metrikách a dokumentaci…', 'The assistant is reading metrics and docs…'),",
                    "      chatChips: ((window.OnhostPanelChat && window.OnhostPanelChat.chips(this, _)) || [\n        [_('Proč je db-primary pomalá?', 'Why is db-primary slow?'),",
                    "      ]).map(c => ({\n        label: c[0], on: () => typeof c[1] === 'function' ? c[1]() : this.askAi(c[1]),\n",
                    "(window.ONHOST_PANEL ? _('Předat podpoře s celým přepisem (založí tiket)', 'Hand to support with the full transcript (opens a ticket)') : _('Předat živému inženýrovi s celým kontextem', 'Hand to a live engineer with full context')),",
                    "      chatEscalate: () => {\n        if (window.OnhostPanelChat && window.OnhostPanelChat.escalate(this)) return;\n        if (s.chatAgent && s.chatAgent !== 'joining') {\n",
                    "        text: m.text, ago: m.ago, who: m.who === 'me' ? _('Vy', 'You') : m.who === 'op' ? (window.ONHOST_PANEL ? _('Podpora ONhost', 'ONhost support') : 'Petr Doležal · Onhost') : (window.ONHOST_PANEL ? 'ONhost AI' : 'Onhost AI'),",
                    // form fields of API-backed views can be password / e-mail inputs with a proper autocomplete hint (masked passwords, password-manager fill)
                    "          isSelect: f.kind === 'select', isArea: f.kind === 'area', isText: f.kind !== 'select' && f.kind !== 'area', inputType: f.kind === 'password' ? 'password' : (f.kind === 'email' ? 'email' : 'text'), autoComplete: f.autocomplete || (f.kind === 'password' ? 'new-password' : 'off'),",
                    '<input type="{{ ff.inputType }}" autoComplete="{{ ff.autoComplete }}" value="{{ ff.value }}" onChange="{{ ff.on }}" onInput="{{ ff.on }}" placeholder="{{ ff.ph }}"',
                    // side-panel rows of API-backed views (webhooks, invitations) can carry an action: the row becomes clickable
                    "      sideRows: ((d.side && d.side.rows) || []).map(x => ({ title: x.title, meta: x.meta, value: x.value, dot: dot(x.kind), on: typeof x.on === 'function' ? x.on : undefined, style: 'padding:11px 16px;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 13%,transparent);display:flex;align-items:flex-start;gap:10px' + (typeof x.on === 'function' ? ';cursor:pointer' : '') })),",
                    '            <sc-for list="{{ sideRows }}" as="sr" hint-placeholder-count="4">'."\n".'              <div onClick="{{ sr.on }}" style="{{ sr.style }}">',
                    // the sidebar's status line reflects the public status components instead of narrating "nine locations"
                    "      infraLabel: window.ONHOST_PANEL ? ((window.ONHOST_PANEL.kpis && window.ONHOST_PANEL.kpis.incidents) ? _('Probíhá incident · viz Stav služeb', 'Incident in progress · see Service status') : _('Vše v provozu · Stav služeb', 'All operational · Service status')) : _('Devět lokalit, vše v provozu', 'Nine locations, all operational'),",
                    "hasSectionCards: !!(d.sectionCards && (!{$real} || d.sectionCardsReal === true)),",
                    "      ledgerC: (window.OnhostPanelBilling && window.OnhostPanelBilling.policyLedger(this)) || {\n        title: _('Co se stane, když nezaplatíte', 'What happens if you do not pay'),",
                    "        real: true, title: _('Co je u herních serverů v ceně', 'What game hosting includes'),",
                    "        real: true, title: _('Co je v ceně housingu', 'What housing includes'),",
                    "        real: true, title: _('Jak reseller program funguje', 'How the reseller programme works'),",
                    "        real: true, title: _('Co asistent zvládne', 'What the assistant handles'), note: _('a kde vždy předdá člověku', 'and where it always defers to a human'),",
                    "        real: true, title: _('Fakturace a platby', 'Billing and payments'), note: _('co používáme pro doklady', 'what we use for documents'),",
                    "        real: true, title: _('Co znamenají pojmy, které u nás uvidíte', 'What the terms you see here mean'),",
                    "        real: true, title: _('Citlivé akce se dvěma lidmi', 'Sensitive actions need two people'),",
                    "        real: true, title: _('Jaké okno si můžete vybrat', 'Which window you can choose'),",
                    "        real: true, title: _('Co si v panelu můžete přenastavit', 'What you can change in the panel'),",
                    "        real: true, title: _('Prvních 7 dní u nás', 'Your first 7 days here'),",
                    "      sectionCardsReal: true, sectionCards: [\n        ['hardware', _('Hardware a e-shop', 'Hardware and shop'),",
                    "sectionCardsReal: true, sectionCardsTitle: _('Úrovně priority a co znamenají', 'Priority tiers and what they mean'),",
                    "sectionCardsReal: true, sectionCardsTitle: _('Kde se komunita schazí', 'Where the community meets'),",
                    "sectionCardsReal: true, sectionCardsTitle: _('Kolekce', 'Collections'),",
                    "_('Vyvezeme ho ve standardním formátu, který přečte i jiný poskytovatel. Odejít od nás musí být stejně snadné jako přijít.', 'Exported in a standard format any other provider reads. Leaving must be as easy as arriving.')",
                    "_('Přijali jsme standardní JSON. Konflikty ukážeme před uložením — nepřepíšeme nic mlčky.', 'We accepted a standard JSON. Conflicts are shown before saving — we overwrite nothing silently.')",
                    "_('do správcovského rozhraní se přihlašujete naším účtem · heslo do něj nikdo nezná, ani my', 'you sign in to the management interface with our account · nobody knows its password, not even us')",
                    "_('Otevřít správu přes SSO', 'Open management via SSO')",
                    "'SSO průchod do správy'",
                    "['Zálohy mimo lokalitu', 'Off-site backups', 'Připojíme záložní úložiště v druhé lokalitě a zapneme ověřování záloh.', 'We attach backup storage in a second location and enable verification.']",
                ],
                $html,
            );
        }

        // 6. admin: system settings (provider onboarding, nodes, health) are a server-rendered page — linked from the user menu
        if ($surface === 'partner' && ! $demo) {
            $html = self::partnerSeams($html); // seam #46: the marketplace tab of the partner portal (audit §5k-1)
        }
        if ($surface === 'admin' && ! $demo) {
            $html = self::adminSeams($html);
            $html = str_replace(
                "        [_('Klientský panel zákazníka', 'Customer client panel'), _('otevře se v novém okně', 'opens in a new window'), () => { this.setState({ userOpen: false }); window.open('Onhost-app.dc.html', '_blank'); }],",
                "        [_('Nastavení systému · integrace', 'System settings · integrations'), _('providery, uzly, zdraví, přístupy', 'providers, nodes, health, credentials'), () => { this.setState({ userOpen: false }); location.href = '/sprava/nastaveni/integrace'; }],\n"
                ."        [_('Klientský panel zákazníka', 'Customer client panel'), _('otevře se v novém okně', 'opens in a new window'), () => { this.setState({ userOpen: false }); window.open('Onhost-app.dc.html', '_blank'); }],",
                $html,
            );
        }

        // 7. vendor neutrality: customer-facing surfaces never name a registrar or panel vendor (the prototype's migration copy names WEDOS)
        if ($surface !== 'admin' && ! $demo) {
            $html = self::neutralizeVendors($html, $surface);
        }

        return $html;
    }

    /**
     * Seam #24: the prototype's cart implies discounts (10 % / 18 % for longer terms, ONHOST10 = 10 %), sells four generic upsells
     * and fakes the domain search; every number now comes from the catalogue and the rules staff approved (window.OnhostCart),
     * add-ons hang under the cart line they belong to, domains are lines of their own, guests finish the order with the details
     * of step 2, the registration hint matches the real password rule, and the product pages gain the complete parameter
     * listing, the add-on catalogue and the configurator (window.OnhostSvcPages.extra).
     */
    /** Panel/admin: haléře when an amount has them; the web-order banner reports a stalled fulfilment instead of the usual 90 seconds. */
    public static function panelSeams(string $html): string
    {
        return str_replace(
            [
                "  money(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: c.dec, maximumFractionDigits: c.dec }).format(n * c.rate);",
                "      hoText: ho ? (ho.id + ' · ' + (ho.items || []).map(i => i.name + (i.qty > 1 ? ' ×' + i.qty : '')).join(', ') + ' · nasazujeme, obvykle do 90 sekund') : '',",
                // deep links on boot (audit §5d-2): the first render wrote the default hash before componentDidMount could read the
                // one the link carried (/panel/fakturace → #/fakturace), so the section was lost — the URL is only written back once mounted
                "  syncHash() {\n    const s = this.state;\n    const slug = this.TAB_SLUG[s.tab] || s.tab;\n    const next = '#/' + slug + (s.tab === 'svcdesk' && s.svcCat ? '/' + s.svcCat : '');\n    if (location.hash !== next) {",
                "  componentDidMount() {\n    const p = this.props || {};\n    const patch = {};\n    if (p.startTab) patch.tab = p.startTab;",
            ],
            [
                "  money(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const __x = Math.round(n * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: __d, maximumFractionDigits: __d }).format(__x);",
                "      hoText: ho ? (ho.id + ' · ' + (ho.items || []).map(i => i.name + (i.qty > 1 ? ' ×' + i.qty : '')).join(', ') + ((window.OnhostStore && window.OnhostStore.orderReview && window.OnhostStore.orderReview(ho.id)) ? ' · objednávku ještě kontrolujeme, služby zřídíme hned po dokončení' : ((window.OnhostStore && window.OnhostStore.orderStalled && window.OnhostStore.orderStalled(ho.id)) ? ' · nasazení trvá déle než obvykle, zkoušíme znovu' : ' · nasazujeme, obvykle do 90 sekund'))) : '',",
                "  syncHash() {\n    const s = this.state;\n    const slug = this.TAB_SLUG[s.tab] || s.tab;\n    const next = '#/' + slug + (s.tab === 'svcdesk' && s.svcCat ? '/' + s.svcCat : '');\n    if (location.hash !== next && this.__onhostMounted) {",
                "  componentDidMount() {\n    this.__onhostMounted = true;\n    const p = this.props || {};\n    const patch = {};\n    if (p.startTab) patch.tab = p.startTab;",
            ],
            $html,
        );
    }

    public static function cartSeams(string $html): string
    {
        $html = str_replace(
            [
                '  COMMITS = [[1, 0], [12, 0.1], [24, 0.18]];',
                "      const d = (this.COMMITS.find(c => c[0] === it.commit) || [1, 0])[1];\n      net += it.price * it.qty * (1 - d);",
                '    const promoOff = this.state.promoOk ? net * 0.1 : 0;',
                '    return { count: items.reduce((a, x) => a + x.qty, 0), net: after, promoOff, vat: after * 0.21, total: after * 1.21 };',
                "    const items = (s.cartItems || []).map(it => {\n      const d = (this.COMMITS.find(c => c[0] === it.commit) || [1, 0])[1];\n      const line = it.price * it.qty * (1 - d);",
                "        meta: (it.commit === 1 ? _('měsíčně, bez závazku', 'monthly, no commitment') : _('závazek ' + it.commit + ' měsíců · sleva ' + Math.round(d * 100) + ' %', it.commit + '-month term · ' + Math.round(d * 100) + '% off')),",
                "      promoOffLabel: _('Sleva ONHOST10', 'ONHOST10 discount'),",
                "      applyPromo: (e) => { if (e && e.preventDefault) e.preventDefault(); this.setState(st => ({ promoOk: (st.promo || '').trim().toUpperCase() === 'ONHOST10' })); },",
                "      promoState: s.promoOk ? _('Kód uplatněn — sleva 10 %', 'Code applied — 10% off') : ((s.promo || '').trim() ? _('Zkuste ONHOST10', 'Try ONHOST10') : ''),",
                "      commitOpts: [[1, cs ? '1 měsíc' : '1 month', 0], [12, cs ? '12 měsíců' : '12 months', 10], [24, cs ? '24 měsíců' : '24 months', 18]].map(c => ({",
                "        on: () => this.setState({ commit: c[0] })\n      })),\n      coUpsells: [",
                "        const ups = [['domain', 219], ['backup', 79], ['ddos', 149], ['mail', 39]].reduce((acc, x) => acc + (s.co[x[0]] ? x[1] : 0), 0);",
                "        const rows = (s.cartItems || []).map(it => ({ k: it.name + (it.qty > 1 ? ' × ' + it.qty : ''), v: this.mny(it.price * it.qty) }));",
                "        rows.push({ k: cs ? 'Závazek a slevy' : 'Term and discounts', v: m.count && m.promoOff ? '− ' + this.mny(m.promoOff) : '—' });",
                "        rows.push({ k: cs ? 'Doplňky' : 'Add-ons', v: ups ? '+ ' + this.mny(ups) : '—' });",
                "    const tlds = [['.cz', 219], ['.com', 289], ['.dev', 349], ['.io', 899], ['.gg', 1490], ['.eu', 189]];\n    const results = tlds.map(",
                "v.length < 8 ? (cs ? 'Heslo musí mít alespoň osm znaků.' : 'Use at least eight characters.') : !/[0-9]/.test(v) ? (cs ? 'Přidejte alespoň jednu číslici.' : 'Add at least one digit.') : ''),",
                "      passHint: view === 'register' ? (cs ? 'Osm znaků a alespoň jedna číslice.' : 'Eight characters with at least one digit.') : ''",
                "      hasConfig: !!p.config,\n",
                '      webPlans: ((window.ONHOST_DATA && window.ONHOST_DATA.webPlans && window.ONHOST_DATA.webPlans(cs)) || (cs ? [',
                "    const upsells = (cs ? [\n      ['Zálohy navíc', 'Snapshoty po hodinách, 30 dní zpět', 79],",
                "      upsellTitle: _('Přidat k objednávce', 'Add to your order'),",
                // amounts show haléře only when they have them (2 286,90 Kč), whole crowns stay whole
                "  mny(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: c.dec, maximumFractionDigits: c.dec }).format(n * c.rate);",
                "  czk(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: c.dec, maximumFractionDigits: c.dec }).format(n * 1.21 * c.rate);",
                // one term per order, monthly unless the customer picks a year: the prototype defaulted every line to 12 months
                'step: 1, commit: 12, co: {',
                "      netLabel: _('Bez DPH', 'Excl. VAT'), vatLabel: _('DPH 21 %', 'VAT 21%'), totalLabel: _('Celkem měsíčně', 'Monthly total'),",
                "      if (i >= 0) items[i] = Object.assign({}, items[i], { qty: items[i].qty + 1 });\n      else items.push({ id, name: name, price: price || 0, qty: 1, commit: st.commit || 12, meta: meta || '' });\n      return { cartItems: items, cartOpen: true, menu: null, langOpen: false, orderDone: null, step: st.orderDone ? 1 : st.step };",
                '          on: () => this.setState(st => ({ cartItems: st.cartItems.map(x => x.id === it.id ? Object.assign({}, x, { commit: c[0] }) : x) }))',
            ],
            [
                '  get COMMITS() { return window.OnhostCart ? window.OnhostCart.commits() : [[1, 0], [12, 0], [24, 0]]; }',
                "      const d = window.OnhostCart ? window.OnhostCart.discount(it) : (this.COMMITS.find(c => c[0] === it.commit) || [1, 0])[1];\n      net += window.OnhostCart ? window.OnhostCart.lineNet(it, d) : it.price * it.qty * (1 - d);",
                '    const promoOff = window.OnhostCart ? window.OnhostCart.promoOff(this.state, items) : (this.state.promoOk ? net * 0.1 : 0);',
                "    const __quoted = window.OnhostCart && window.OnhostCart.totals ? window.OnhostCart.totals(this) : null;\n    if (__quoted) return __quoted;\n    return { count: items.reduce((a, x) => a + x.qty, 0), net: after, promoOff, vat: after * 0.21, total: after * 1.21, commitOff: window.OnhostCart ? window.OnhostCart.commitOff(items) : 0, addonsOff: window.OnhostCart ? window.OnhostCart.addonsTotal(items) : 0 };",
                "    const items = (s.cartItems || []).map(it => {\n      const __row = window.OnhostCart ? window.OnhostCart.cartRow(it, this, _) : null;\n      if (__row) return __row;\n      const d = window.OnhostCart ? window.OnhostCart.discount(it) : (this.COMMITS.find(c => c[0] === it.commit) || [1, 0])[1];\n      const line = window.OnhostCart ? window.OnhostCart.lineNet(it, d) : it.price * it.qty * (1 - d);",
                "        meta: (it.commit === 1 ? _('měsíčně, bez závazku', 'monthly, no commitment') : (window.OnhostCart && window.OnhostCart.termLabel ? window.OnhostCart.termLabel(it, d, _) : (d ? _('závazek ' + it.commit + ' měsíců · sleva ' + Math.round(d * 100) + ' %', it.commit + '-month term · ' + Math.round(d * 100) + '% off') : _('závazek ' + it.commit + ' měsíců', it.commit + '-month term')))),",
                "      promoOffLabel: _('Sleva ' + String(s.promo || '').toUpperCase(), String(s.promo || '').toUpperCase() + ' discount'),",
                "      applyPromo: (e) => { if (e && e.preventDefault) e.preventDefault(); if (window.OnhostCart) { window.OnhostCart.applyPromo(this); return; } this.setState(st => ({ promoOk: (st.promo || '').trim().toUpperCase() === 'ONHOST10' })); },",
                "      promoState: window.OnhostCart ? window.OnhostCart.promoState(s, cs) : (s.promoOk ? _('Kód uplatněn — sleva 10 %', 'Code applied — 10% off') : ((s.promo || '').trim() ? _('Zkuste ONHOST10', 'Try ONHOST10') : '')),",
                "      commitOpts: (window.OnhostCart ? window.OnhostCart.commitOpts(cs, s.cartItems) : [[1, cs ? '1 měsíc' : '1 month', 0], [12, cs ? '12 měsíců' : '12 months', 10], [24, cs ? '24 měsíců' : '24 months', 18]]).map(c => ({",
                "        on: () => this.setState(st => ({ commit: c[0], cartItems: (st.cartItems || []).map(x => (window.OnhostCart && window.OnhostCart.isDomain(x)) ? x : Object.assign({}, x, { commit: c[0] })) }))\n      })),\n      coItemAddons: window.OnhostCart ? window.OnhostCart.itemAddons(this) : [],\n      coUpsells: [",
                "        const ups = window.OnhostCart ? 0 : [['domain', 219], ['backup', 79], ['ddos', 149], ['mail', 39]].reduce((acc, x) => acc + (s.co[x[0]] ? x[1] : 0), 0);",
                "        const rows = window.OnhostCart ? window.OnhostCart.summaryRows(this, cs) : (s.cartItems || []).map(it => ({ k: it.name + (it.qty > 1 ? ' × ' + it.qty : ''), v: this.mny(it.price * it.qty) }));",
                "        rows.push({ k: cs ? 'Závazek a slevy' : 'Term and discounts', v: m.count && (m.promoOff || m.commitOff) ? '− ' + this.mny(m.promoOff + (m.commitOff || 0)) : '—' });",
                "        rows.push({ k: cs ? 'Doplňky' : 'Add-ons', v: (ups || m.addonsOff) ? '+ ' + this.mny(ups || m.addonsOff) : '—' });",
                "    if (window.OnhostCart && window.OnhostCart.searchDomains(raw, this)) return;\n    const tlds = [['.cz', 219], ['.com', 289], ['.dev', 349], ['.io', 899], ['.gg', 1490], ['.eu', 189]];\n    const results = tlds.map(",
                "v.length < 12 ? (cs ? 'Heslo musí mít alespoň 12 znaků.' : 'Use at least 12 characters.') : !/[0-9]/.test(v) ? (cs ? 'Přidejte alespoň jednu číslici.' : 'Add at least one digit.') : !/[a-zA-Z]/.test(v) ? (cs ? 'Přidejte alespoň jedno písmeno.' : 'Add at least one letter.') : ''),",
                "      passHint: view === 'register' ? (cs ? 'Alespoň 12 znaků, písmena i číslice.' : 'At least 12 characters with letters and digits.') : ''",
                "      hasConfig: !!p.config,\n      x: (window.OnhostSvcPages && window.OnhostSvcPages.extra) ? window.OnhostSvcPages.extra(this, s.svc, cs) : { hasCmp: false, hasDetails: false, hasAddons: false, hasBuilder: false },\n",
                "      wx: (window.OnhostSvcPages && window.OnhostSvcPages.extra) ? window.OnhostSvcPages.extra(this, 'web-hosting', cs) : { hasCmp: false, hasDetails: false, hasAddons: false, hasBuilder: false },\n      webPlans: ((window.ONHOST_DATA && window.ONHOST_DATA.webPlans && window.ONHOST_DATA.webPlans(cs)) || (cs ? [",
                "    const upsells = window.OnhostCart ? window.OnhostCart.upsells(this, _) : (cs ? [\n      ['Zálohy navíc', 'Snapshoty po hodinách, 30 dní zpět', 79],",
                "      upsellTitle: upsells.length ? _('Přidat k objednávce', 'Add to your order') : '',",
                // ↓ replacements of the money and term anchors above (the order of the pairs must match)
                "  mny(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const __x = Math.round(n * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: __d, maximumFractionDigits: __d }).format(__x);",
                "  czk(n) {\n    const c = this.CUR[this.state.currency] || this.CUR.czk;\n    const __x = Math.round(n * 1.21 * c.rate * 100) / 100, __d = Number.isInteger(__x) ? c.dec : Math.max(c.dec, 2);\n    const v = new Intl.NumberFormat(this.state.lang === 'cs' ? 'cs-CZ' : 'en-US', { minimumFractionDigits: __d, maximumFractionDigits: __d }).format(__x);",
                'step: 1, commit: 1, co: {',
                "      netLabel: _('Bez DPH', 'Excl. VAT'), vatLabel: _('DPH 21 %', 'VAT 21%'), totalLabel: (window.OnhostCart && window.OnhostCart.totalLabel) ? window.OnhostCart.totalLabel(s, cs) : _('Celkem měsíčně', 'Monthly total'),",
                "      const __term =(!st.cartItems || !st.cartItems.length) && st.period === 'year' ? 12 : (st.commit || 1);\n      if (i >= 0) items[i] = Object.assign({}, items[i], { qty: items[i].qty + 1 });\n      else items.push({ id, name: name, price: price || 0, qty: 1, commit: __term, meta: meta || '' });\n      return { cartItems: items, commit: __term, cartOpen: true, menu: null, langOpen: false, orderDone: null, step: st.orderDone ? 1 : st.step };",
                '          on: () => this.setState(st => ({ commit: c[0], cartItems: st.cartItems.map(x => (window.OnhostCart && window.OnhostCart.isDomain(x)) ? x : Object.assign({}, x, { commit: c[0] })) }))',
            ],
            $html,
        );
        // templates: an "add to cart" button next to every free domain, add-ons under every cart line in the checkout, extra product page blocks
        $html = str_replace(
            [
                '<span style="margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:13px;white-space:nowrap">{{ rs.price }}</span>',
                self::CHECKOUT_UPSELLS,
                "  </section>\n  </sc-if>\n\n  <sc-if value=\"{{ svc.hasPanels }}\" hint-placeholder-val=\"{{ false }}\">",
                "  <section style=\"border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n    <div style=\"max-width:1360px;margin:0 auto;padding:52px 32px 20px\">\n      <h2 style=\"font-size:38px;line-height:1.03;letter-spacing:-.025em;margin:0 0 8px;max-width:26ch\">{{ nv.migTitle }}</h2>",
            ],
            [
                "<span style=\"margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:13px;white-space:nowrap\">{{ rs.price }}</span>\n                <sc-if value=\"{{ rs.action }}\" hint-placeholder-val=\"{{ false }}\"><button onClick=\"{{ rs.on }}\" class=\"btn btn-primary\" style=\"font-size:12px;padding:8px 12px;border:0;cursor:pointer;white-space:nowrap\">{{ rs.action }}</button></sc-if>",
                self::CHECKOUT_ITEM_ADDONS,
                "  </section>\n  </sc-if>\n".self::extraBlocks('svc.x', false)."\n  <sc-if value=\"{{ svc.hasPanels }}\" hint-placeholder-val=\"{{ false }}\">",
                self::extraBlocks('wx', true)."\n  <section style=\"border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n    <div style=\"max-width:1360px;margin:0 auto;padding:52px 32px 20px\">\n      <h2 style=\"font-size:38px;line-height:1.03;letter-spacing:-.025em;margin:0 0 8px;max-width:26ch\">{{ nv.migTitle }}</h2>",
            ],
            $html,
        );

        return $html;
    }

    /**
     * Seam #24 (checkout): the confirmation reflects the real order (awaiting a bank transfer → the payment instructions
     * and the total, paid → provisioning), the details step collects the company name and the billing address the
     * invoice needs, and both reach the guest account.
     */
    public static function checkoutSeams(string $html): string
    {
        $html = str_replace(
            [
                "        ['card', 'Karta', 'Visa, Mastercard · Stripe'], ['bank', 'Bankovní převod', 'QR platba, okamžité spárování'],\n        ['wallet', 'Apple Pay / Google Pay', 'Jedním dotykem'], ['paypal', 'PayPal', 'Bez zadávání karty'],\n        ['crypto', 'Krypto', 'BTC, ETH, USDC'], ['invoice', 'Faktura pro firmy', 'Splatnost 14 dní'],\n        ['sepa', 'SEPA inkaso', 'Pro dlouhodobé závazky']\n",
                "        ['card', 'Card', 'Visa, Mastercard · Stripe'], ['bank', 'Bank transfer', 'QR payment, instant matching'],\n        ['wallet', 'Apple Pay / Google Pay', 'One tap'], ['paypal', 'PayPal', 'No card details'],\n        ['crypto', 'Crypto', 'BTC, ETH, USDC'], ['invoice', 'Company invoice', '14-day terms'],\n        ['sepa', 'SEPA direct debit', 'For long commitments']\n",
                "coEta: 'Server běží za ~90 sekund od zaplacení',",
                "coEta: 'Your server runs ~90 seconds after payment',",
                "cof: { email: '', name: '', ico: '', dic: '', terms: false }",
            ],
            [
                "        ['card', 'Karta', 'Visa, Mastercard · platební brána'], ['bank', 'Bankovní převod', 'QR platba, zálohová faktura'],\n        ['wallet', 'Apple Pay / Google Pay', 'Jedním dotykem']\n",
                "        ['card', 'Card', 'Visa, Mastercard · payment gateway'], ['bank', 'Bank transfer', 'QR payment, pro forma invoice'],\n        ['wallet', 'Apple Pay / Google Pay', 'One tap']\n",
                "coEta: (window.OnhostCart && window.OnhostCart.eta) ? window.OnhostCart.eta(this.state, true) : 'Server běží za ~90 sekund od zaplacení',",
                "coEta: (window.OnhostCart && window.OnhostCart.eta) ? window.OnhostCart.eta(this.state, false) : 'Your server runs ~90 seconds after payment',",
                "cof: Object.assign({ email: '', name: '', ico: '', dic: '', terms: false }, (window.OnhostCart && window.OnhostCart.prefill) ? window.OnhostCart.prefill() : {})",
            ],
            $html,
        );
        $html = str_replace(
            [
                "      email: cof.email, name: cof.name, ico: cof.ico, dic: cof.dic, terms: cof.terms,\n      onEmail: setF('email'), onName: setF('name'), onIco: setF('ico'), onDic: setF('dic'), onTerms: setF('terms'),",
                '        return { rows, net: this.mny(net), vat: this.mny(net * 0.21), total: this.mny(net * 1.21 + s.credit) };',
                "      doneKicker: cs ? 'Objednávka přijata' : 'Order received',\n      doneTitle: cs ? 'Server startuje' : 'Your server is starting',\n      doneLead: cs ? 'Fakturu a přihlašovací údaje jsme poslali na váš e-mail. Průběh vidíte v klientském panelu, obvykle do devadesáti sekund je hotovo.' : 'The invoice and credentials are in your inbox. Progress shows in the client panel — usually done within ninety seconds.',",
            ],
            [
                "      email: cof.email, name: cof.name, ico: cof.ico, dic: cof.dic, terms: cof.terms,\n"
                ."      company: cof.company || '', street: cof.street || '', city: cof.city || '', zip: cof.zip || '',\n"
                ."      onCompany: setF('company'), onStreet: setF('street'), onCity: setF('city'), onZip: setF('zip'),\n"
                ."      styleCompany: fieldStyle('company'), styleStreet: fieldStyle('street'), styleCity: fieldStyle('city'), styleZip: fieldStyle('zip'),\n"
                ."      lblCompany: cs ? 'Název firmy' : 'Company name', lblAddress: cs ? 'Fakturační adresa' : 'Billing address', lblStreet: cs ? 'Ulice a číslo popisné' : 'Street and number', lblCity: cs ? 'Město' : 'City', lblZip: cs ? 'PSČ' : 'Postal code',\n"
                ."      hasDoneRows: !!(window.OnhostCart && window.__onhostOrder && window.OnhostCart.doneRows(window.__onhostOrder, this, cs).length), doneRows: (window.OnhostCart && window.__onhostOrder) ? window.OnhostCart.doneRows(window.__onhostOrder, this, cs) : [],\n"
                ."      onEmail: setF('email'), onName: setF('name'), onIco: setF('ico'), onDic: setF('dic'), onTerms: setF('terms'),",
                "        const __o = s.orderDone && window.__onhostOrder && window.__onhostOrder.total != null ? window.__onhostOrder : null;\n        return __o ? { rows: window.OnhostCart ? window.OnhostCart.orderSummaryRows(__o, this, cs) : rows, net: this.mny(((__o.subtotal || 0) - (__o.discount || 0)) / 100), vat: this.mny((__o.tax || 0) / 100), total: this.mny(__o.total / 100) } : { rows, net: this.mny(net), vat: this.mny(net * 0.21), total: this.mny(net * 1.21 + s.credit) };",
                "      doneKicker: (window.OnhostCart && window.__onhostOrder) ? window.OnhostCart.doneCopy(window.__onhostOrder, cs).kicker : (cs ? 'Objednávka přijata' : 'Order received'),\n"
                ."      doneTitle: (window.OnhostCart && window.__onhostOrder) ? window.OnhostCart.doneCopy(window.__onhostOrder, cs).title : (cs ? 'Server startuje' : 'Your server is starting'),\n"
                ."      doneLead: (window.OnhostCart && window.__onhostOrder) ? window.OnhostCart.doneCopy(window.__onhostOrder, cs).lead : (cs ? 'Fakturu a přihlašovací údaje jsme poslali na váš e-mail. Průběh vidíte v klientském panelu, obvykle do devadesáti sekund je hotovo.' : 'The invoice and credentials are in your inbox. Progress shows in the client panel — usually done within ninety seconds.'),",
            ],
            $html,
        );
        $html = str_replace(
            [
                "              <span style=\"font-family:var(--font-heading);font-weight:800;font-size:22px;letter-spacing:-.01em\">{{ co.orderId }}</span>\n            </div>\n            <a href=\"#\" onClick=\"{{ co.doneOn }}\" class=\"btn btn-primary\" style=\"font-size:15px;padding:14px 20px\">{{ co.doneCta }}</a>",
                "              <div class=\"oh-form-2\" style=\"display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px\">\n                <label style=\"display:block\">\n                  <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ t.authIco }}</span>",
                "            </sc-if>\n          </div>\n        </sc-if>\n        <sc-if value=\"{{ coIs3 }}\" hint-placeholder-val=\"{{ false }}\">",
            ],
            [
                "              <span style=\"font-family:var(--font-heading);font-weight:800;font-size:22px;letter-spacing:-.01em\">{{ co.orderId }}</span>\n            </div>\n"
                ."            <sc-if value=\"{{ co.hasDoneRows }}\" hint-placeholder-val=\"{{ false }}\">\n              <div style=\"margin:-12px 0 24px\">\n                <sc-for list=\"{{ co.doneRows }}\" as=\"dr2\" hint-placeholder-count=\"5\">\n"
                ."                  <div style=\"display:flex;gap:12px;padding:9px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);font-size:14px\">\n"
                ."                    <span style=\"color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent)\">{{ dr2.k }}</span>\n                    <span style=\"margin-left:auto;font-family:var(--font-heading);font-weight:800;white-space:nowrap;text-align:right\">{{ dr2.v }}</span>\n"
                ."                  </div>\n                </sc-for>\n              </div>\n            </sc-if>\n"
                .'            <a href="#" onClick="{{ co.doneOn }}" class="btn btn-primary" style="font-size:15px;padding:14px 20px">{{ co.doneCta }}</a>',
                "              <label style=\"display:block\">\n                <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ co.lblCompany }}</span>\n                <input placeholder=\"Nová s.r.o.\" value=\"{{ co.company }}\" onChange=\"{{ co.onCompany }}\" style=\"{{ co.styleCompany }}\" />\n              </label>\n"
                ."              <div class=\"oh-form-2\" style=\"display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px\">\n                <label style=\"display:block\">\n                  <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ t.authIco }}</span>",
                "            </sc-if>\n"
                ."            <div style=\"font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-top:4px\">{{ co.lblAddress }}</div>\n"
                ."            <label style=\"display:block\">\n              <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ co.lblStreet }}</span>\n              <input placeholder=\"Dlouhá 12\" value=\"{{ co.street }}\" onChange=\"{{ co.onStreet }}\" style=\"{{ co.styleStreet }}\" />\n            </label>\n"
                ."            <div class=\"oh-form-2\" style=\"display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px\">\n"
                ."              <label style=\"display:block\">\n                <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ co.lblCity }}</span>\n                <input placeholder=\"Praha\" value=\"{{ co.city }}\" onChange=\"{{ co.onCity }}\" style=\"{{ co.styleCity }}\" />\n              </label>\n"
                ."              <label style=\"display:block\">\n                <span style=\"display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px\">{{ co.lblZip }}</span>\n                <input placeholder=\"110 00\" value=\"{{ co.zip }}\" onChange=\"{{ co.onZip }}\" style=\"{{ co.styleZip }}\" />\n              </label>\n            </div>\n"
                ."          </div>\n        </sc-if>\n        <sc-if value=\"{{ coIs3 }}\" hint-placeholder-val=\"{{ false }}\">",
            ],
            $html,
        );

        return $html;
    }

    /** The prototype's generic checkout upsells (four fixed checkboxes for the whole cart). */
    private const CHECKOUT_UPSELLS = <<<'HTML'
              <sc-for list="{{ coUpsells }}" as="cu4" hint-placeholder-count="4">
                <label style="display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);cursor:pointer;font-size:15px">
                  <input type="checkbox" checked="{{ cu4.on }}" onChange="{{ cu4.toggle }}" style="width:18px;height:18px;accent-color:var(--acc,#ec3013)">
                  <span style="font-weight:600">{{ cu4.name }}</span>
                  <span style="margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:14px;white-space:nowrap">{{ cu4.priceLabel }}</span>
                </label>
              </sc-for>
HTML;

    /** Every cart line followed by its own add-ons (switches, sliders, choices, add-on products) — `coItemAddons` from OnhostCart.itemAddons. */
    private const CHECKOUT_ITEM_ADDONS = <<<'HTML'
              <sc-for list="{{ coItemAddons }}" as="cia" hint-placeholder-count="2">
                <div style="padding:12px 0 6px;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)">
                  <div style="display:flex;align-items:baseline;gap:10px">
                    <span style="font-family:var(--font-heading);font-weight:800;font-size:15px">{{ cia.name }}</span>
                    <span style="margin-left:auto;font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);white-space:nowrap">{{ cia.meta }}</span>
                  </div>
                  <sc-if value="{{ cia.none }}" hint-placeholder-val="{{ false }}">
                    <div style="font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);padding:8px 0 6px">{{ cia.noneLabel }}</div>
                  </sc-if>
                  <sc-for list="{{ cia.addons }}" as="cua" hint-placeholder-count="4">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;padding:10px 0 10px 14px;border-top:1px solid color-mix(in srgb,var(--fg,#201e1d) 12%,transparent);font-size:14px">
                      <sc-if value="{{ cua.isCheck }}" hint-placeholder-val="{{ false }}">
                        <input type="checkbox" checked="{{ cua.on }}" onChange="{{ cua.toggle }}" style="width:18px;height:18px;accent-color:var(--acc,#ec3013)">
                      </sc-if>
                      <span style="font-weight:600">{{ cua.name }}</span>
                      <span style="font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent)">{{ cua.desc }}</span>
                      <sc-if value="{{ cua.isRange }}" hint-placeholder-val="{{ false }}">
                        <span style="display:flex;align-items:center;gap:8px;flex:1 1 220px">
                          <input type="range" min="{{ cua.min }}" max="{{ cua.max }}" step="{{ cua.step }}" value="{{ cua.raw }}" onChange="{{ cua.change }}" style="flex:1;accent-color:var(--acc,#ec3013)" />
                          <span style="font-family:var(--font-heading);font-weight:800;font-size:13px;white-space:nowrap">{{ cua.value }}</span>
                        </span>
                      </sc-if>
                      <sc-if value="{{ cua.isChips }}" hint-placeholder-val="{{ false }}">
                        <span style="display:flex;flex-wrap:wrap;gap:6px">
                          <sc-for list="{{ cua.choices }}" as="cch" hint-placeholder-count="3">
                            <button onClick="{{ cch.on }}" style="{{ cch.style }}">{{ cch.label }}</button>
                          </sc-for>
                        </span>
                      </sc-if>
                      <span style="margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:14px;white-space:nowrap">{{ cua.priceLabel }}</span>
                    </div>
                  </sc-for>
                </div>
              </sc-for>
HTML;

    /** Extra product page blocks (complete parameters, add-ons, configurator; optionally the comparison table) bound to `{P}.*`. */
    private static function extraBlocks(string $p, bool $withCompare): string
    {
        $cell = 'font-size:14px;padding:13px 12px;border-left:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);text-wrap:pretty';
        $head = 'font-family:var(--font-heading);font-weight:800;font-size:14px;padding:11px 12px;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);border-left:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)';
        $first = 'font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent);padding:11px 12px 11px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)';
        $section = 'border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)';
        $muted = 'color:color-mix(in srgb,var(--fg,#201e1d) 65%,transparent)';
        $table = function (string $cols, string $rows, string $firstLabel) use ($cell, $head, $first): string {
            return "<div class=\"oh-tab\" style=\"display:grid;grid-template-columns:1.3fr repeat(3,1fr);border-top:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n"
                ."        <div style=\"{$first}\">{{ {$firstLabel} }}</div>\n"
                ."        <sc-for list=\"{{ {$cols} }}\" as=\"xc\" hint-placeholder-count=\"3\"><div style=\"{$head}\">{{ xc }}</div></sc-for>\n"
                ."        <sc-for list=\"{{ {$rows} }}\" as=\"xr\" hint-placeholder-count=\"8\">\n"
                ."          <div style=\"display:grid;grid-template-columns:1.3fr repeat(3,1fr);grid-column:1 / -1;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)\">\n"
                ."            <div style=\"font-size:14px;padding:13px 12px 13px 0;text-wrap:pretty\">{{ xr.label }}</div>\n"
                ."            <sc-for list=\"{{ xr.cells }}\" as=\"xx\" hint-placeholder-count=\"3\"><div style=\"{$cell}\">{{ xx }}</div></sc-for>\n"
                ."          </div>\n        </sc-for>\n      </div>";
        };
        $out = '';
        if ($withCompare) {
            $out .= "\n  <sc-if value=\"{{ {$p}.hasCmp }}\" hint-placeholder-val=\"{{ false }}\">\n  <section id=\"compare\" style=\"{$section}\">\n    <div style=\"max-width:1360px;margin:0 auto;padding:52px 32px 58px\">\n      <h2 style=\"font-size:40px;margin:0 0 26px;letter-spacing:-.022em;text-wrap:balance\">{{ {$p}.cmpTitle }}</h2>\n      ".$table("{$p}.cmp.cols", "{$p}.cmpRows", "{$p}.cmpFirst")."\n    </div>\n  </section>\n  </sc-if>\n";
        }
        $out .= "\n  <sc-if value=\"{{ {$p}.hasDetails }}\" hint-placeholder-val=\"{{ false }}\">\n  <section id=\"details\" style=\"{$section}\">\n    <div style=\"max-width:1360px;margin:0 auto;padding:44px 32px 48px\">\n"
            ."      <div style=\"display:flex;flex-wrap:wrap;align-items:center;gap:14px 24px;margin-bottom:12px\">\n        <h2 style=\"font-size:32px;margin:0;letter-spacing:-.02em;text-wrap:balance\">{{ {$p}.detailsTitle }}</h2>\n        <button onClick=\"{{ {$p}.detailsOn }}\" class=\"btn btn-secondary\" style=\"font-size:13px\">{{ {$p}.detailsToggle }}</button>\n      </div>\n"
            ."      <p style=\"font-size:15px;line-height:1.55;{$muted};margin:0 0 22px;max-width:64ch;text-wrap:pretty\">{{ {$p}.detailsLead }}</p>\n"
            ."      <sc-if value=\"{{ {$p}.detailsOpen }}\" hint-placeholder-val=\"{{ false }}\">\n      ".$table("{$p}.detailCols", "{$p}.detailRows", "{$p}.detailsFirst")."\n      </sc-if>\n    </div>\n  </section>\n  </sc-if>\n";
        $out .= "\n  <sc-if value=\"{{ {$p}.hasAddons }}\" hint-placeholder-val=\"{{ false }}\">\n  <section id=\"addons\" style=\"{$section}\">\n    <div style=\"max-width:1360px;margin:0 auto;padding:52px 32px 56px\">\n"
            ."      <h2 style=\"font-size:40px;margin:0 0 10px;letter-spacing:-.022em;text-wrap:balance\">{{ {$p}.addonsTitle }}</h2>\n      <p style=\"font-size:16px;line-height:1.55;{$muted};margin:0 0 26px;max-width:64ch;text-wrap:pretty\">{{ {$p}.addonsLead }}</p>\n"
            ."      <div class=\"oh-cards3\" style=\"display:grid;grid-template-columns:repeat(3,1fr);border-top:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent);border-left:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)\">\n"
            ."        <sc-for list=\"{{ {$p}.addons }}\" as=\"xa\" hint-placeholder-count=\"6\">\n          <div style=\"border-right:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent);padding:22px 20px 24px\">\n"
            ."            <div style=\"display:flex;align-items:baseline;gap:10px\"><h4 style=\"margin:0;font-size:18px;line-height:1.2;text-wrap:pretty\">{{ xa.name }}</h4><span style=\"margin-left:auto;font-family:var(--font-heading);font-weight:800;font-size:13px;white-space:nowrap;color:var(--accDeep,#ae1800)\">{{ xa.price }}</span></div>\n"
            ."            <p style=\"font-size:13px;line-height:1.5;{$muted};margin:8px 0 0;text-wrap:pretty\">{{ xa.desc }}</p>\n          </div>\n        </sc-for>\n      </div>\n    </div>\n  </section>\n  </sc-if>\n";
        $out .= "\n  <sc-if value=\"{{ {$p}.hasBuilder }}\" hint-placeholder-val=\"{{ false }}\">\n  <section id=\"builder\" style=\"{$section}\">\n    <div class=\"oh-split\" style=\"max-width:1360px;margin:0 auto;padding:0 32px;display:grid;grid-template-columns:1.05fr .95fr\">\n"
            ."      <div style=\"padding:52px 48px 56px 0;border-right:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n        <h2 style=\"font-size:36px;margin:0 0 12px;letter-spacing:-.02em;text-wrap:balance\">{{ {$p}.builder.title }}</h2>\n"
            ."        <p style=\"font-size:16px;line-height:1.55;{$muted};margin:0 0 28px;max-width:52ch;text-wrap:pretty\">{{ {$p}.builder.lead }}</p>\n        <div style=\"display:flex;flex-direction:column;gap:22px\">\n"
            ."          <sc-for list=\"{{ {$p}.builder.sliders }}\" as=\"xs\" hint-placeholder-count=\"4\">\n            <div>\n              <div style=\"display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:8px\">\n"
            ."                <span style=\"font-family:var(--font-heading);font-weight:800;font-size:14px;letter-spacing:.03em;text-transform:uppercase\">{{ xs.label }}</span>\n                <span style=\"font-family:var(--font-heading);font-weight:800;font-size:20px\">{{ xs.value }}</span>\n              </div>\n"
            ."              <input type=\"range\" min=\"{{ xs.min }}\" max=\"{{ xs.max }}\" step=\"{{ xs.step }}\" value=\"{{ xs.raw }}\" onChange=\"{{ xs.on }}\" style=\"width:100%;accent-color:var(--acc,#ec3013)\" />\n"
            ."              <div style=\"display:flex;justify-content:space-between;font-size:11px;{$muted};margin-top:4px\"><span>{{ xs.minLabel }}</span><span>{{ xs.maxLabel }}</span></div>\n            </div>\n          </sc-for>\n"
            ."          <sc-for list=\"{{ {$p}.builder.selects }}\" as=\"xe\" hint-placeholder-count=\"1\">\n            <div>\n              <div style=\"font-family:var(--font-heading);font-weight:800;font-size:14px;letter-spacing:.03em;text-transform:uppercase;margin-bottom:8px\">{{ xe.label }}</div>\n"
            ."              <div style=\"display:flex;flex-wrap:wrap;gap:8px\"><sc-for list=\"{{ xe.choices }}\" as=\"xo\" hint-placeholder-count=\"3\"><button onClick=\"{{ xo.on }}\" style=\"{{ xo.style }}\">{{ xo.label }}</button></sc-for></div>\n              <div style=\"font-size:12px;{$muted};margin-top:6px\">{{ xe.desc }}</div>\n            </div>\n          </sc-for>\n"
            ."          <sc-if value=\"{{ {$p}.builder.hasToggles }}\" hint-placeholder-val=\"{{ false }}\">\n            <div>\n              <div style=\"font-family:var(--font-heading);font-weight:800;font-size:14px;letter-spacing:.03em;text-transform:uppercase;margin-bottom:8px\">{{ {$p}.builder.togglesLabel }}</div>\n"
            ."              <div style=\"display:flex;flex-wrap:wrap;gap:8px\"><sc-for list=\"{{ {$p}.builder.toggles }}\" as=\"xt\" hint-placeholder-count=\"5\"><button onClick=\"{{ xt.on }}\" style=\"{{ xt.style }}\" title=\"{{ xt.desc }}\">{{ xt.label }} · {{ xt.price }}</button></sc-for></div>\n            </div>\n          </sc-if>\n        </div>\n      </div>\n"
            ."      <div style=\"padding:52px 0 56px 48px\">\n        <h6 style=\"color:var(--accDeep,#ae1800);margin:0 0 12px\">{{ {$p}.builder.summary }}</h6>\n        <div style=\"border-top:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n"
            ."          <div style=\"display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)\"><span style=\"font-size:13px;letter-spacing:.05em;text-transform:uppercase;{$muted}\">{{ {$p}.builder.baseLabel }}</span><span style=\"font-family:var(--font-heading);font-weight:800;font-size:15px;text-align:right\">{{ {$p}.builder.basePrice }}</span></div>\n"
            ."          <sc-for list=\"{{ {$p}.builder.rows }}\" as=\"xw\" hint-placeholder-count=\"5\">\n            <div style=\"display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)\"><span style=\"font-size:13px;letter-spacing:.05em;text-transform:uppercase;{$muted}\">{{ xw.k }}</span><span style=\"font-family:var(--font-heading);font-weight:800;font-size:15px;text-align:right\">{{ xw.v }}</span></div>\n          </sc-for>\n        </div>\n"
            ."        <div style=\"display:flex;align-items:baseline;gap:8px;margin-top:22px\"><span style=\"font-family:var(--font-heading);font-weight:800;font-size:40px;letter-spacing:-.025em\">{{ {$p}.builder.price }}</span><span style=\"font-size:14px;{$muted}\">{{ {$p}.builder.unit }}</span></div>\n"
            ."        <div style=\"font-size:12px;{$muted};margin-top:4px\">{{ {$p}.builder.vat }}</div>\n        <p style=\"font-size:13px;line-height:1.55;{$muted};margin:14px 0 20px;max-width:46ch;text-wrap:pretty\">{{ {$p}.builder.note }}</p>\n"
            ."        <a href=\"#\" onClick=\"{{ {$p}.builder.buy }}\" class=\"btn btn-primary\" style=\"font-size:15px;padding:15px 22px\">{{ {$p}.builder.cta }}</a>\n      </div>\n    </div>\n  </section>\n  </sc-if>\n";

        return $out;
    }

    /** Prototype literals that name WEDOS (a competitor in the migration copy, ONhost's registrar behind the scenes) → neutral wording; a final sweep catches anything new. */
    public static function neutralizeVendors(string $html, string $surface): string
    {
        if ($surface === 'panel') {
            // the toolkit fills these prototype tabs with real tools (seam #31); their labels say what the customer gets
            $html = str_replace(
                ["T('addons', 'Příplatkové služby', 'Add-ons', 'extra')", "T('addons', 'Příplatkové služby', 'Add-ons', 'plan')", "T('phpcli', 'PHP-CLI', 'PHP-CLI', 'php')", "T('le', \"Let's Encrypt\", \"Let's Encrypt\", 'ssl')", "T('relay', 'Relay a routování', 'Relay and routing', 'route')"],
                ["T('addons', 'Nástroje a doplňky', 'Tools and add-ons', 'extra')", "T('addons', 'Nástroje a doplňky', 'Tools and add-ons', 'plan')", "T('phpcli', 'Nastavení PHP', 'PHP settings', 'php')", "T('le', 'Wildcard, HTTP/3 a HSTS', 'Wildcard, HTTP/3 and HSTS', 'ssl')", "T('relay', 'Přesměrování a routování', 'Forwarding and routing', 'route')"],
                $html,
            );
        }
        if ($surface === 'public') {
            $html = str_replace(
                [
                    "migFrom: 'wedos', migSize: 'm'", "'migrace přesun wedos forpsi'", "'migration move wedos forpsi'",
                    "'Migraci z Wedosu udělali celou za nás, včetně pošty.", "'They did the whole Wedos migration for us, mail included.",
                    "migFrom: [['wedos', 'Wedos'], ['forpsi', 'Forpsi'],", "s.migFrom || 'wedos'",
                    "'Přišli z Wedosu na jeden VPS.", "'Came from Wedos on one VPS.",
                ],
                [
                    "migFrom: 'other', migSize: 'm'", "'migrace přesun forpsi'", "'migration move forpsi'",
                    "'Migraci od původního poskytovatele udělali celou za nás, včetně pošty.", "'They did the whole migration from our previous host for us, mail included.",
                    "migFrom: [['forpsi', 'Forpsi'],", "s.migFrom || 'other'",
                    "'Přišli od jiného poskytovatele na jeden VPS.", "'Came from another provider on one VPS.",
                ],
                $html,
            );
        }
        if ($surface === 'panel') {
            $html = str_replace(
                [
                    "['migrac', 'migrat', 'wedos', 'forpsi',",
                    "'DMARC a SPF při migraci z Wedosu' : 'DMARC and SPF when migrating from Wedos'",
                    "_('Wedos, Forpsi, Active24, Vercel', 'Wedos, Forpsi, Active24, Vercel')",
                    "_('Migrace z Wedosu krok za krokem', 'Migrating from Wedos step by step')",
                    "_('Wedos, Forpsi, Active24, Vercel i vlastní server — vše krok za krokem.', 'Wedos, Forpsi, Active24, Vercel or your own box — step by step.')",
                    "_('Wedos, Forpsi, Active24, Vercel, Netlify i vlastní železo.', 'Wedos, Forpsi, Active24, Vercel, Netlify or your own metal.')",
                    "_('Migrace z Wedosu', 'Migration from Wedos')",
                    "['Wedos', '612', 100, acc],",
                    "'shop, 412 GB, PostgreSQL'), 'Wedos', 'ok',",
                    "options: ['Wedos', 'Forpsi', 'Active24', 'Vercel', 'Netlify',",
                    "_('Z Wedosu na Onhost', 'From Wedos to Onhost')",
                ],
                [
                    "['migrac', 'migrat', 'forpsi',",
                    "'DMARC a SPF při migraci od jiného poskytovatele' : 'DMARC and SPF when migrating from another provider'",
                    "_('Forpsi, Active24, Vercel a další', 'Forpsi, Active24, Vercel and more')",
                    "_('Migrace od jiného poskytovatele krok za krokem', 'Migrating from another provider step by step')",
                    "_('Forpsi, Active24, Vercel i vlastní server — vše krok za krokem.', 'Forpsi, Active24, Vercel or your own box — step by step.')",
                    "_('Forpsi, Active24, Vercel, Netlify i vlastní železo.', 'Forpsi, Active24, Vercel, Netlify or your own metal.')",
                    "_('Migrace od jiného poskytovatele', 'Migration from another provider')",
                    "[_('jiný poskytovatel', 'other provider'), '612', 100, acc],",
                    "'shop, 412 GB, PostgreSQL'), _('jiný poskytovatel', 'other provider'), 'ok',",
                    "options: ['Forpsi', 'Active24', 'Vercel', 'Netlify',",
                    "_('Od jiného poskytovatele na Onhost', 'From another provider to Onhost')",
                ],
                $html,
            );
        }
        // safety net for copy added to the prototype later: declined forms first, lowercase (JS keys) become a neutral key
        $html = (string) preg_replace(['/\bWedosu\b/u', '/\bWedosem\b/u', '/\bWEDOS\b/u', '/\bWedos\b/u', '/\bwedos\b/u'], ['jiného poskytovatele', 'jiným poskytovatelem', 'jiný poskytovatel', 'jiný poskytovatel', 'other'], $html);

        return $html;
    }

    /** Version stamp for prototype scripts: newest mtime of support.js, the shell and the API seams. */
    public function assetVersion(): string
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }
        $times = [0];
        // the renderer itself is part of the version: a deploy that changes a seam must never serve a cached transform
        foreach (array_merge([__FILE__, $this->root.'/support.js', $this->root.'/onhost-shell.js', $this->root.'/onhost-store.js'], glob($this->root.'/api/*.js') ?: []) as $file) {
            $times[] = (int) @filemtime($file);
        }

        return $version = (string) max($times);
    }

    /** Safe path inside apps/surfaces for the static asset route. */
    public function assetPath(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            return null;
        }
        $full = $this->root.'/'.$relative;
        $real = realpath($full);
        $rootReal = realpath($this->root);
        if ($real === false || $rootReal === false || ! str_starts_with($real, $rootReal) || ! is_file($real)) {
            return null;
        }

        return $real;
    }

    public static function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'js', 'mjs', 'jsx' => 'text/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'json', 'map' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'txt', 'md' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    /**
     * Seam §5w (api/onhost-game-config.api.js): the game hosting landing sells games, not sizes. The prototype's three
     * slot plans become the game offer — category tabs, a search and a tile per game with its artwork and "Již od" price —
     * followed by the configurator of the chosen game (server type, size presets, sliders, version, inputs) with a sticky
     * summary. The home page's product list shows one game row under "Vše" and every game under "Gaming", each opening
     * the configurator; the prototype's visual switcher is dropped from the public site.
     */
    private static function gameSeams(string $html): string
    {
        $muted = 'color:color-mix(in srgb,var(--fg,#201e1d) 62%,transparent)';
        $line = 'color-mix(in srgb,var(--fg,#201e1d) 18%,transparent)';
        $cap = 'font-family:var(--font-heading);font-weight:800;font-size:13px;letter-spacing:.06em;text-transform:uppercase';
        $offer = <<<HTML
  <sc-if value="{{ gcx.has }}" hint-placeholder-val="{{ false }}">
  <section id="game-offer" style="border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent);scroll-margin-top:80px">
    <div style="max-width:1360px;margin:0 auto;padding:52px 32px 22px">
      <h6 style="color:var(--accDeep,#ae1800);margin:0 0 10px">{{ gcx.kicker }}</h6>
      <h2 style="font-size:42px;line-height:1.02;letter-spacing:-.025em;margin:0 0 10px">{{ gcx.title }}</h2>
      <p style="font-size:16px;line-height:1.55;{$muted};margin:0 0 24px;max-width:60ch;text-wrap:pretty">{{ gcx.lead }}</p>
      <div class="oh-gtools" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
        <sc-for list="{{ gcx.tabs }}" as="gtb" hint-placeholder-count="5"><button onClick="{{ gtb.on }}" style="{{ gtb.style }}">{{ gtb.label }}</button></sc-for>
        <input type="search" value="{{ gcx.q }}" onInput="{{ gcx.onSearch }}" placeholder="{{ gcx.searchPh }}" aria-label="{{ gcx.searchPh }}" style="margin-left:auto;min-width:220px;flex:0 1 280px;box-sizing:border-box;padding:10px 12px;border:2px solid {$line};background:transparent;color:var(--fg,#201e1d);font-size:14px" />
      </div>
    </div>
    <div class="oh-ggrid" style="max-width:1360px;margin:0 auto;padding:0 32px 56px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px">
      <sc-for list="{{ gcx.tiles }}" as="gt" hint-placeholder-count="8">
        <a href="#" onClick="{{ gt.on }}" style="{{ gt.style }}" style-hover="border-color:var(--acc,#ec3013)">
          <div style="{{ gt.artStyle }}">
            <sc-if value="{{ gt.hasArt }}" hint-placeholder-val="{{ false }}"><img src="{{ gt.art }}" alt="{{ gt.label }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block" /></sc-if>
            <sc-if value="{{ gt.noArt }}" hint-placeholder-val="{{ true }}"><span style="position:relative;padding:12px 14px;font-family:var(--font-heading);font-weight:800;font-size:20px;letter-spacing:.08em;line-height:1.05;color:#fff;text-shadow:0 2px 0 rgba(0,0,0,.35)">{{ gt.artText }}</span></sc-if>
            <sc-if value="{{ gt.hasBadge }}" hint-placeholder-val="{{ false }}"><span style="position:absolute;top:10px;right:10px;background:var(--ink,#1a1918);color:#f3f2f2;font-size:11px;font-weight:600;letter-spacing:.04em;padding:4px 8px">{{ gt.badge }}</span></sc-if>
          </div>
          <div style="padding:14px 16px 16px;display:flex;flex-direction:column;gap:4px;flex:1">
            <span style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--accDeep,#ae1800)">{{ gt.cat }}</span>
            <span style="font-family:var(--font-heading);font-weight:800;font-size:18px;line-height:1.15">{{ gt.label }}</span>
            <span style="font-size:13px;line-height:1.45;{$muted};text-wrap:pretty">{{ gt.note }}</span>
            <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-top:auto;padding-top:12px">
              <span><span style="display:block;font-size:11px;{$muted}">{{ gt.fromLabel }}</span><span style="font-family:var(--font-heading);font-weight:800;font-size:22px;letter-spacing:-.02em">{{ gt.from }}</span><span style="font-size:12px;{$muted}"> {{ gt.per }}</span></span>
              <span style="{{ gt.ctaStyle }}">{{ gt.cta }}</span>
            </div>
          </div>
        </a>
      </sc-for>
      <sc-if value="{{ gcx.noTiles }}" hint-placeholder-val="{{ false }}"><p style="grid-column:1 / -1;font-size:15px;{$muted};margin:0">{{ gcx.noTilesText }}</p></sc-if>
    </div>
  </section>
  </sc-if>

  <sc-if value="{{ gcx.hasGame }}" hint-placeholder-val="{{ false }}">
  <section id="game-config" style="border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent);background:var(--surface,#eae9e9);scroll-margin-top:80px">
    <div class="oh-gcfg" style="max-width:1360px;margin:0 auto;padding:40px 32px 56px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr);gap:32px;align-items:start">
      <div style="min-width:0">
        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:26px">
          <div style="{{ gcx.gameArtStyle }}">
            <sc-if value="{{ gcx.gameHasArt }}" hint-placeholder-val="{{ false }}"><img src="{{ gcx.gameArt }}" alt="{{ gcx.gameTitle }}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block" /></sc-if>
          </div>
          <div style="min-width:0;flex:1">
            <a href="#" onClick="{{ gcx.onChange }}" style="font-size:13px;font-weight:600">{{ gcx.change }}</a>
            <h2 style="font-size:34px;line-height:1.05;letter-spacing:-.02em;margin:4px 0 4px">{{ gcx.gameTitle }}</h2>
            <p style="font-size:14px;{$muted};margin:0">{{ gcx.gameNote }}</p>
          </div>
        </div>
        <sc-if value="{{ gcx.hasVariants }}" hint-placeholder-val="{{ false }}">
          <div style="{$cap};margin:0 0 10px">{{ gcx.stepType }}</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px;margin-bottom:30px">
            <sc-for list="{{ gcx.variants }}" as="gv" hint-placeholder-count="7">
              <button onClick="{{ gv.on }}" style="{{ gv.style }}">
                <span style="font-family:var(--font-heading);font-weight:800;font-size:16px">{{ gv.name }}</span>
                <span style="font-size:12px;line-height:1.4;{$muted}">{{ gv.note }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--accDeep,#ae1800);margin-top:4px">{{ gv.from }}</span>
              </button>
            </sc-for>
          </div>
        </sc-if>
        <div style="{$cap};margin:0 0 10px">{{ gcx.stepSize }}</div>
        <div class="oh-gpre" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:30px">
          <sc-for list="{{ gcx.presets }}" as="gp2" hint-placeholder-count="3">
            <button onClick="{{ gp2.on }}" style="{{ gp2.style }}">
              <span style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;{$muted}">{{ gp2.hint }}</span>
              <span style="font-family:var(--font-heading);font-weight:800;font-size:17px">{{ gp2.name }}</span>
              <span style="font-size:12px;line-height:1.4">{{ gp2.sub }}</span>
              <span style="font-family:var(--font-heading);font-weight:800;font-size:15px;color:var(--accDeep,#ae1800);margin-top:6px">{{ gp2.price }}</span>
            </button>
          </sc-for>
        </div>
        <div style="{$cap};margin:0 0 14px">{{ gcx.stepFine }}</div>
        <div class="oh-gsl" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 28px;background:var(--bg,#f3f2f2);border:2px solid {$line};padding:20px 22px">
          <sc-for list="{{ gcx.sliders }}" as="gcs" hint-placeholder-count="6">
            <div style="min-width:0">
              <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px"><span style="font-family:var(--font-heading);font-weight:800;font-size:14px">{{ gcs.label }}</span><span style="font-family:var(--font-heading);font-weight:800;font-size:18px;color:var(--accDeep,#ae1800)">{{ gcs.value }}</span></div>
              <input type="range" min="{{ gcs.min }}" max="{{ gcs.max }}" step="{{ gcs.step }}" value="{{ gcs.raw }}" onChange="{{ gcs.on }}" aria-label="{{ gcs.label }}" style="width:100%;margin:8px 0 2px;accent-color:var(--acc,#ec3013)" />
              <div style="display:flex;justify-content:space-between;gap:8px;font-size:11px;{$muted}"><span>{{ gcs.minLabel }}</span><span style="text-align:center">{{ gcs.desc }}</span><span>{{ gcs.maxLabel }}</span></div>
            </div>
          </sc-for>
        </div>
        <sc-if value="{{ gcx.hasVersions }}" hint-placeholder-val="{{ false }}">
          <div style="{$cap};margin:28px 0 10px">{{ gcx.versionsLabel }}</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px"><sc-for list="{{ gcx.versions }}" as="gcv" hint-placeholder-count="4"><button onClick="{{ gcv.on }}" style="{{ gcv.style }}">{{ gcv.label }}</button></sc-for></div>
        </sc-if>
        <sc-if value="{{ gcx.hasInputs }}" hint-placeholder-val="{{ false }}">
          <div style="{$cap};margin:28px 0 10px">{{ gcx.inputsLabel }}</div>
          <sc-for list="{{ gcx.inputs }}" as="gci" hint-placeholder-count="1">
            <label style="display:block;margin-bottom:14px"><span style="display:block;font-size:14px;font-weight:600;margin-bottom:6px">{{ gci.label }}</span>
              <input type="text" value="{{ gci.value }}" maxlength="{{ gci.maxlength }}" onChange="{{ gci.on }}" autocomplete="off" spellcheck="false" style="width:100%;box-sizing:border-box;padding:12px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent);background:var(--bg,#f3f2f2);color:var(--fg,#201e1d);font-size:15px;font-family:ui-monospace,monospace" />
              <span style="display:block;font-size:12px;{$muted};margin-top:6px">{{ gci.hint }} <sc-if value="{{ gci.hasHelp }}" hint-placeholder-val="{{ false }}"><a href="{{ gci.help }}" target="_blank" rel="noopener noreferrer">steamcommunity.com ↗</a></sc-if></span>
            </label>
          </sc-for>
        </sc-if>
      </div>
      <aside class="oh-gsum" style="position:sticky;top:96px;background:var(--bg,#f3f2f2);border:2px solid var(--fg,#201e1d);box-shadow:var(--shadow-lg);padding:22px 22px 24px;min-width:0">
        <h6 style="color:var(--accDeep,#ae1800);margin:0 0 10px">{{ gcx.summary }}</h6>
        <sc-for list="{{ gcx.rows }}" as="gcw" hint-placeholder-count="8"><div style="display:flex;align-items:baseline;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid {$line}"><span style="font-size:13px;{$muted}">{{ gcw.k }}</span><span style="font-family:var(--font-heading);font-weight:800;font-size:14px;text-align:right">{{ gcw.v }}</span></div></sc-for>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:18px;flex-wrap:wrap"><span style="font-family:var(--font-heading);font-weight:800;font-size:40px;letter-spacing:-.025em">{{ gcx.price }}</span><span style="font-size:14px;{$muted}">{{ gcx.unit }}</span></div>
        <div style="font-size:12px;{$muted};margin:2px 0 16px">{{ gcx.net }}</div>
        <p style="font-size:13px;font-weight:600;color:var(--accInk,#ae1800);margin:0 0 10px">{{ gcx.blocked }}</p>
        <a href="#" onClick="{{ gcx.buy }}" class="btn btn-primary" style="{{ gcx.ctaStyle }}">{{ gcx.cta }}</a>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:16px">
          <sc-for list="{{ gcx.perks }}" as="gk" hint-placeholder-count="3"><span style="font-size:13px"><span style="color:var(--accDeep,#ae1800);font-weight:800">✓</span> {{ gk }}</span></sc-for>
        </div>
        <p style="font-size:12px;line-height:1.5;{$muted};margin:14px 0 0">{{ gcx.note }}</p>
      </aside>
    </div>
  </section>
  </sc-if>

HTML;
        // the slot plans section of the game landing → the game offer and the configurator, moved up under the hero
        $at = strpos($html, '{{ t.gmPlansTitle }}</h2>');
        $start = $at === false ? false : strrpos(substr($html, 0, $at), '  <section ');
        $endAnchor = "<a href=\"#\" onClick=\"{{ gpl.go }}\" style=\"{{ gpl.ctaStyle }}\">{{ t.order }}</a>\n        </div>\n      </sc-for>\n    </div>\n  </section>\n";
        $end = $at === false ? false : strpos($html, $endAnchor, $at);
        $feat = strpos($html, '{{ t.gmFeatTitle }}</h2>');
        $featStart = $feat === false ? false : strrpos(substr($html, 0, $feat), '  <section ');
        if ($start !== false && $end !== false && $featStart !== false && $featStart < $start) {
            $html = substr($html, 0, $start).substr($html, $end + strlen($endAnchor));
            $html = substr($html, 0, $featStart).$offer.substr($html, $featStart);
        } else {
            logger()->warning('surface seam §5w: game plans anchor missing');
        }

        return str_replace(
            [
                "      games: ['Minecraft', 'CS2',",
                "      products: prods.filter(p => s.filter === 'all' || p.cat === s.filter).map(p => {",
                "price: this.czk(p.price), go: (e) => { e.preventDefault(); this.setState({ view: 'pricing' }); window.scrollTo(0, 0); } };",
                "gmCta1: 'Vybrat plán',",
                '<a href="#" onClick="{{ goPricing }}" class="btn btn-primary" style="font-size:15px;padding:15px 22px">{{ t.gmCta1 }}</a>',
                "gmCta1: 'Pick a plan',",
                'switcherOn: this.props.showSwitcher !== false,',
                '<div style="max-width:1360px;margin:0 auto;padding:0 32px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;min-height:38px">',
                '<div style="max-width:1360px;margin:0 auto;padding:12px 32px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">',
            ],
            [
                "      gcx: window.OnhostGameConfig ? window.OnhostGameConfig.view(this, cs) : { has: false },\n      games: (window.OnhostGameConfig && window.OnhostGameConfig.labels(cs).length) ? window.OnhostGameConfig.labels(cs) : ['Minecraft', 'CS2',",
                "      products: prods.filter(p => (s.filter === 'all' && !p.sub) || (p.cat === s.filter && !(s.filter === 'game' && p.game && !p.sub))).map(p => {",
                "price: (p.game ? (cs ? 'od ' : 'from ') + this.czk(Math.round(p.price * 1.21) / 1.21) : this.czk(p.price)), go: (e) => { e.preventDefault(); if (p.game) { nav('game')(e); if (p.egg && window.OnhostGameConfig) window.OnhostGameConfig.select(this, p.group, p.egg); return; } this.setState({ view: 'pricing' }); window.scrollTo(0, 0); } };",
                "gmCta1: 'Vybrat hru',",
                '<a href="#" onClick="{{ gcx.goOffer }}" class="btn btn-primary" style="font-size:15px;padding:15px 22px">{{ t.gmCta1 }}</a>',
                "gmCta1: 'Pick a game',",
                'switcherOn: false,',
                '<div class="oh-topbar" style="max-width:1360px;margin:0 auto;padding:0 32px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;min-height:38px">',
                '<div class="oh-hdr" style="max-width:1360px;margin:0 auto;padding:12px 32px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">',
            ],
            $html,
        );
    }

    /** Public header on a phone (§5w): the topbar keeps the status and the switches, the menu lives behind the burger, nothing overflows. */
    private const MOBILE_CSS = <<<'CSS'
<style>
  .oh-hdr { flex-wrap: nowrap !important; }
  @media (max-width: 1379px) {
    .oh-hdr > nav { display: none !important; }
    .oh-hdr > div:last-child > div:first-child { display: flex !important; }
  }
  @media (max-width: 860px) {
    .oh-topbar { padding: 6px 14px !important; gap: 8px !important; min-height: 0 !important; flex-wrap: nowrap !important; }
    .oh-topbar > a, .oh-topbar > span:nth-of-type(2), .oh-topbar > nav { display: none !important; }
    .oh-topbar > span:first-child { font-size: 11px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
    .oh-topbar > div:last-child { margin-left: auto; gap: 6px !important; flex: 0 0 auto; }
    .oh-topbar > div:last-child button { padding: 5px 7px !important; font-size: 10px !important; }
    .oh-topbar > div:last-child > button:last-child { font-size: 0 !important; gap: 0 !important; }
    .oh-topbar > div:last-child > button:last-child::before { content: '◐'; font-size: 13px; line-height: 1; }
    .oh-hdr { padding: 10px 14px !important; gap: 8px !important; flex-wrap: nowrap !important; }
    .oh-hdr > a:first-child { margin-right: 0 !important; }
    .oh-hdr > a:first-child img { height: 26px !important; }
    .oh-hdr > div:last-child { gap: 6px !important; min-width: 0; }
    .oh-hdr > div:last-child > a.btn { display: none !important; }
    .oh-hdr > div:last-child > button.btn { display: none !important; }
    header .oh-mega { max-height: calc(100vh - 140px); overflow-y: auto; padding: 18px 16px !important; gap: 16px !important; }
    .oh-ggrid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px !important; padding-left: 14px !important; padding-right: 14px !important; }
    .oh-gtools input { margin-left: 0 !important; flex: 1 1 100% !important; min-width: 0 !important; }
    .oh-gcfg { grid-template-columns: minmax(0, 1fr) !important; padding: 28px 14px 40px !important; gap: 22px !important; }
    .oh-gsum { position: static !important; }
    .oh-gpre, .oh-gsl { grid-template-columns: minmax(0, 1fr) !important; }
  }
  @media (max-width: 480px) {
    .oh-ggrid { grid-template-columns: minmax(0, 1fr) !important; }
    .oh-hdr > div:last-child > a[aria-label] span { display: none; }
  }
  @media (min-width: 861px) and (max-width: 1180px) {
    .oh-ggrid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
  }
</style>
CSS;
}
