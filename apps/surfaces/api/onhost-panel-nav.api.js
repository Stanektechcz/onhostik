/* onhost-panel-nav.api.js — window.OnhostPanelNav (seam #29): the customer panel's sidebar built from the offer.
 *
 * The prototype's sidebar lists every narrated area (backups, monitoring, deploys, costs, affiliate, hardware, forum…).
 * Outside demo mode the sidebar follows what ONhost sells and what the organization runs: `window.ONHOST_PANEL.nav`
 * (domains/Catalog/PanelNavigation.php) carries the service categories with their visibility — a category staff switched
 * off or the catalogue stopped selling disappears, a category the customer already has a service in never does — and
 * the optional links. Every entry is a real, working view: overview, services per category, the order wizard, tickets,
 * knowledge base, status page, billing and the account settings.
 *
 * Group definitions keep the prototype's shape: [key, label, badge, subs?, handler?]; subs are [label, tab, patch, count?, handler?].
 * A handler (5th element) replaces the default tab switch — used for the order wizard and for external pages.
 */
(function () {
  'use strict';
  if (window.OnhostPanelNav) return;

  function data() { return window.ONHOST_PANEL || null; }
  function nav() { var d = data(); return d && d.nav && d.nav.categories ? d.nav : null; }
  function isCs(cmp) { return !(cmp && cmp.state && cmp.state.lang === 'en'); }
  function L(cmp, o) { return o ? (isCs(cmp) ? (o.cs || o.en || '') : (o.en || o.cs || '')) : ''; }
  function visible() { var n = nav(); return n ? n.categories.filter(function (c) { return c.visible; }) : []; }
  function links() { var n = nav(); return (n && n.links) || {}; }

  /* The wizard opens from the sidebar with the first orderable product preselected (api/onhost-panel-overview.api.js). */
  function openWizard(cmp) {
    if (window.OnhostPanelOverview && window.OnhostPanelOverview.order) { window.OnhostPanelOverview.order(cmp, null); return; }
    cmp.setState({ modal: 'order', mStep: 0, md: {} });
  }
  function external(url) { return function () { window.open(url, '_blank', 'noopener'); }; }

  /* "Služby" lands on the current category when it is still listed, otherwise on the first one; the click also folds the group like the prototype does. */
  function openServices(cats) {
    return function (cmp) {
      cmp.setState(function (st) {
        var keys = cats.map(function (c) { return c[2].svcCat; });
        var active = st.tab === 'svcdesk';
        var open = st.subs && st.subs.svcdesk !== undefined ? !!st.subs.svcdesk : active;
        return {
          tab: 'svcdesk', svcCat: keys.indexOf(st.svcCat) >= 0 ? st.svcCat : keys[0], selected: null, filter: 'all', query: '', userOpen: false, curOpen: false, notifOpen: false,
          svcId: null, svcTab: null, svcGo: null, wbDir: '/', subs: Object.assign({}, st.subs || {}, { svcdesk: active ? !open : true })
        };
      });
    };
  }

  /* h: { openTickets, count(cat), total } from the prototype's render scope. */
  function groups(cmp, _, h) {
    var n = nav();
    if (!n) return null;
    h = h || {};
    var cats = visible().map(function (c) { return [L(cmp, c.label), 'svcdesk', { svcCat: c.key }, h.count ? h.count(c.key) : 0]; });
    var lk = links();
    var support = [[_('Tikety', 'Tickets'), 'tickets', {}]];
    if (lk.kb !== false) support.push([_('Znalostní báze', 'Knowledge base'), 'kb', {}, 0, external('/napoveda')]);
    if (lk.status !== false) support.push([_('Stav služeb', 'Service status'), 'status', {}, 0, external('/stav')]);
    var settings = [
      [_('Nastavení účtu', 'Account settings'), 'settings', { sub: 'account' }],
      [_('Zabezpečení a 2FA', 'Security and 2FA'), 'settings', { sub: 'security' }],
      [_('Relace a zařízení', 'Sessions and devices'), 'settings', { sub: 'sessions' }]
    ];
    if (lk.team !== false) settings.push([_('Tým a práva', 'Team and roles'), 'team', {}]);
    if (lk.projects !== false) settings.push([_('Projekty', 'Projects'), 'projects', {}]);
    if (lk.registrars !== false) settings.push([_('Připojené registrátory', 'Connected registrars'), 'registrars', {}]);
    if (lk.api !== false) settings.push([_('API klíče a webhooky', 'API keys and webhooks'), 'api', {}]);
    var due = ((data().billing || {}).pending || []).length;
    var services = ['svcdesk', _('Služby', 'Services'), h.total != null && h.total > 0 ? String(h.total) : '', cats.length ? cats : null, openServices(cats)];
    var infra = [['overview', _('Přehled', 'Overview'), ''], services];
    if (lk.monitoring !== false) infra.push(['monitoring', _('Monitoring', 'Monitoring'), '']);
    if (lk.backups !== false) infra.push(['backups', _('Zálohy', 'Backups'), '']);
    var account = [['billing', _('Fakturace', 'Billing'), due ? String(due) : '']];
    if (lk.costs !== false) account.push(['costs', _('Náklady', 'Costs'), '']);
    if (lk.audit !== false) account.push(['audit', _('Oznámení a audit', 'Notifications and audit'), '']);
    if (lk.windows !== false) account.push(['windows', _('Servisní okna', 'Maintenance windows'), '']);
    if (lk.privacy !== false) account.push(['privacy', _('Osobní údaje', 'Personal data'), '']);
    account.push(['settings', _('Nastavení', 'Settings'), '', settings]);
    return [
      ['infra', _('Provoz', 'Operations'), infra],
      ['market', _('Objednat', 'Order'), [
        ['order', _('Nová služba', 'New service'), '', null, openWizard]
      ]],
      ['support', _('Podpora', 'Support'), [
        ['tickets', _('Podpora', 'Support'), h.openTickets ? String(h.openTickets) : '', support]
      ]],
      ['account', _('Účet a správa', 'Account and administration'), account]
    ];
  }

  /* The service desk's category list: the listed categories first; the prototype falls back to the second entry for an
   * unknown category, so the list always carries at least two (hidden ones are reachable only by URL, never listed). */
  function cats(cmp, _) {
    var n = nav();
    if (!n) return null;
    var out = visible().map(function (c) { return { key: c.key, label: L(cmp, c.label), crumb: L(cmp, c.crumb), visible: true }; });
    if (out.length < 2) {
      n.categories.filter(function (c) { return !c.visible; }).forEach(function (c) { if (out.length < 2) out.push({ key: c.key, label: L(cmp, c.label), crumb: L(cmp, c.crumb), visible: false }); });
    }
    return out;
  }

  /* ⌘K quick select (seam #34): the basic actions, then the organization's services, domains and tickets that match the query. */
  function serviceGroups() { var d = data(); return (d && d.services) || {}; }
  function catOf(id) { var g = serviceGroups(), out = null; Object.keys(g).forEach(function (k) { if ((g[k] || []).some(function (x) { return x.id === id; })) out = k; }); return out; }
  function actions(cmp) {
    var cs = isCs(cmp), O = window.OnhostPanelOverview, lk = links();
    var go = function (patch) { return function () { cmp.setState(Object.assign({ query: '', selected: null, userOpen: false, notifOpen: false }, patch)); }; };
    var out = [
      [cs ? 'Nová služba' : 'New service', cs ? 'webhosting, WordPress, server nebo doména z katalogu' : 'hosting, WordPress, a server or a domain from the catalogue', function () { cmp.setState({ query: '' }); openWizard(cmp); }, 'objednat order koupit'],
      [cs ? 'Přidat doménu' : 'Add a domain', cs ? 'registrace s ověřením dostupnosti' : 'registration with an availability check', function () { cmp.setState({ query: '' }); if (O && O.order) O.order(cmp, 'domain'); else openWizard(cmp); }, 'domena registrace dns'],
      [cs ? 'Dobít kredit' : 'Top up credit', cs ? 'převodem nebo kartou, čerpá se jako první' : 'by transfer or card, drawn first', function () { cmp.setState({ query: '' }); if (O && O.topUp) O.topUp(cmp); else go({ tab: 'billing' })(); }, 'kredit platba dobit topup penize'],
      [cs ? 'Nový tiket' : 'New ticket', cs ? 'napsat podpoře' : 'message support', go({ tab: 'tickets' }), 'podpora support pomoc problem'],
      [cs ? 'Faktury a doklady' : 'Invoices and documents', cs ? 'fakturace, platby, historie kreditu' : 'billing, payments, credit history', go({ tab: 'billing' }), 'faktura fakturace doklad platba'],
      [cs ? 'Moje služby' : 'My services', cs ? 'seznam služeb a jejich správa' : 'the service list and its tools', go({ tab: 'svcdesk' }), 'sluzby servery weby'],
      [cs ? 'Domény a DNS' : 'Domains and DNS', cs ? 'záznamy, obnovy, přesměrování' : 'records, renewals, forwarding', go({ tab: 'svcdesk', svcCat: 'domain' }), 'dns zaznamy domeny'],
      [cs ? 'Nastavení účtu' : 'Account settings', cs ? 'fakturační údaje, jazyk, oznámení' : 'billing details, language, notifications', go({ tab: 'settings', sub: 'account' }), 'ucet profil nastaveni'],
      [cs ? 'Zabezpečení a 2FA' : 'Security and 2FA', cs ? 'heslo, dvoufázové ověření, relace' : 'password, two-factor, sessions', go({ tab: 'settings', sub: 'security' }), 'heslo 2fa bezpecnost relace'],
      [cs ? 'Stav služeb' : 'Service status', cs ? 'incidenty a plánované odstávky' : 'incidents and planned maintenance', external('/stav'), 'stav status incident odstavka'],
      [cs ? 'Znalostní báze' : 'Knowledge base', cs ? 'návody a postupy' : 'guides and how-tos', external('/napoveda'), 'navod napoveda dokumentace'],
      [cs ? 'Zeptat se AI asistenta' : 'Ask the AI assistant', cs ? 'stav účtu, návody, zálohy a akce nad službami' : 'account, guides, backups and service actions', function () { cmp.setState({ query: '', chatOpen: true, userOpen: false, notifOpen: false }); }, 'ai asistent chat zeptat'],
      [cs ? 'Odebírat kalendář (ICS)' : 'Subscribe to the calendar (ICS)', cs ? 'obnovy, expirace domén, splatnosti a údržby ve vašem kalendáři' : 'renewals, domain expiries, due dates and maintenance in your calendar', function () {
        cmp.setState({ query: '' });
        if (!window.OnhostApi) return;
        window.OnhostApi.get('/calendar/feed').then(function (r) { var url = r && r.data && r.data.url; if (url) window.open(url, '_blank', 'noopener'); }).catch(function () {});
      }, 'kalendar ics obnova expirace splatnost udrzba calendar']
    ];
    if (lk.team !== false) out.push([cs ? 'Tým a práva' : 'Team and roles', cs ? 'pozvánky a role' : 'invitations and roles', go({ tab: 'team' }), 'tym kolegove pozvanka']);
    if (lk.api !== false) out.push([cs ? 'API klíče a webhooky' : 'API keys and webhooks', cs ? 'klíče, webhooky, Discord' : 'keys, webhooks, Discord', go({ tab: 'api' }), 'api klic webhook discord token']);
    if (lk.projects !== false) out.push([cs ? 'Projekty' : 'Projects', cs ? 'rozdělení služeb, útrata po projektech, role v projektu' : 'service partitioning, spend per project, project roles', go({ tab: 'projects', projSel: null }), 'projekt projekty rozpocet utrata role']);
    if (lk.costs !== false) out.push([cs ? 'Náklady a předpověď kreditu' : 'Costs and credit forecast', cs ? 'obnovy, útrata po projektech, kdy dojde kredit' : 'renewals, spend per project, when the credit runs out', go({ tab: 'costs' }), 'naklady utrata kredit predpoved rozpocet']);
    if (lk.audit !== false) out.push([cs ? 'Oznámení a audit' : 'Notifications and audit', cs ? 'kdo co udělal, oznámení a jejich nastavení' : 'who did what, notifications and their settings', go({ tab: 'audit' }), 'audit log historie oznameni']);
    if (lk.windows !== false) out.push([cs ? 'Servisní okna' : 'Maintenance windows', cs ? 'plánované údržby vašich služeb' : 'planned maintenance of your services', go({ tab: 'windows' }), 'udrzba servisni okno odstavka']);
    if (lk.privacy !== false) out.push([cs ? 'Osobní údaje a odchod' : 'Personal data and leaving', cs ? 'export dat, smazání účtu, přenos' : 'data export, account deletion, switching', go({ tab: 'privacy' }), 'gdpr export smazani osobni udaje odchod']);
    if (lk.monitoring !== false) out.push([cs ? 'Monitoring' : 'Monitoring', cs ? 'dostupnost webů a poslední kontroly' : 'site availability and the latest checks', go({ tab: 'monitoring' }), 'monitoring dostupnost uptime kontrola']);
    if (lk.backups !== false) out.push([cs ? 'Zálohy' : 'Backups', cs ? 'poslední zálohy všech služeb' : 'the latest backups of every service', go({ tab: 'backups' }), 'zaloha zalohy backup obnova']);
    if (lk.registrars !== false) out.push([cs ? 'Připojit účet WEDOS (API)' : 'Connect a WEDOS account (API)', cs ? 'domény z vlastního účtu, upozornění, párování s hostingem' : 'domains from your own account, notices, pairing with hosting', go({ tab: 'registrars', rconSel: null }), 'wedos registrator api pripojit domeny parovani']);
    return out;
  }
  function norm(s) { var t = String(s || '').toLowerCase(); try { t = t.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) {} return t; }
  function hits(cmp, _) {
    var q = norm(cmp.state.query || '').trim();
    if (q === '') return null;
    var style = 'display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;text-align:left;border:0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 12%,transparent);background:transparent;cursor:pointer;padding:9px 12px;font-family:var(--font-body)';
    var out = [], cs = isCs(cmp);
    actions(cmp).forEach(function (a) { if (norm(a[0] + ' ' + a[1] + ' ' + (a[3] || '')).indexOf(q) >= 0) out.push({ label: a[0], meta: a[1], on: a[2], style: style }); });
    var g = serviceGroups();
    Object.keys(g).forEach(function (k) {
      (g[k] || []).forEach(function (s) {
        if (norm([s.name, s.spec, s.type, s.meta].join(' ')).indexOf(q) < 0) return;
        out.push({ label: s.name, meta: [s.spec || s.type, s.state].filter(Boolean).join(' · '), style: style, on: function () { cmp.setState({ query: '', tab: 'svcdesk', svcCat: catOf(s.id) || k, svcId: s.id, selected: null, userOpen: false, notifOpen: false }); } });
      });
    });
    var S = window.OnhostStore;
    if (S && typeof S.tickets === 'function') {
      S.tickets().forEach(function (t) {
        if (norm('#' + t.id + ' ' + t.subject + ' ' + (t.svc || '')).indexOf(q) < 0) return;
        out.push({ label: '#' + t.id + ' · ' + t.subject, meta: (cs ? 'tiket · ' : 'ticket · ') + t.state, style: style, on: function () { cmp.setState({ query: '', tab: 'tickets', selected: t.id, userOpen: false, notifOpen: false }); } });
      });
    }
    return out.length ? out.slice(0, 12) : [{ label: cs ? 'Nic nenalezeno' : 'Nothing found', meta: cs ? 'zkuste název služby, doménu, číslo tiketu nebo akci (kredit, tiket, doména…)' : 'try a service name, a domain, a ticket number or an action', style: style, on: function () { cmp.setState({ query: '' }); } }];
  }
  function enter(cmp, _) { var h = hits(cmp, _); if (h && h[0] && h[0].on) h[0].on(); }
  /* The full-page search the prototype shows while typing: the same real groups (actions, services, domains, tickets, sections) instead of its narrated lists. */
  function searchGroups(cmp, _) {
    var q = norm(cmp.state.query || '').trim(), cs = isCs(cmp);
    var hit = function (v) { return q !== '' && norm(v).indexOf(q) >= 0; };
    var g = serviceGroups();
    var services = [], domains = [];
    Object.keys(g).forEach(function (k) {
      (g[k] || []).forEach(function (s) {
        if (!hit([s.name, s.spec, s.type, s.meta].join(' '))) return;
        var item = { title: s.name, meta: [s.spec || s.type, s.state].filter(Boolean).join(' · '), on: function () { cmp.setState({ query: '', tab: 'svcdesk', svcCat: k, svcId: s.id, selected: null }); } };
        (k === 'domain' ? domains : services).push(item);
      });
    });
    var S = window.OnhostStore, tickets = [];
    if (S && typeof S.tickets === 'function') {
      S.tickets().forEach(function (t) { if (hit('#' + t.id + ' ' + t.subject + ' ' + (t.svc || ''))) tickets.push({ title: '#' + t.id + ' · ' + t.subject, meta: (cs ? 'tiket · ' : 'ticket · ') + t.state, on: function () { cmp.setState({ query: '', tab: 'tickets', selected: t.id }); } }); });
    }
    var acts = actions(cmp).filter(function (a) { return hit(a[0] + ' ' + a[1] + ' ' + (a[3] || '')); }).map(function (a) { return { title: a[0], meta: a[1], on: a[2] }; });
    var sections = [];
    (groups(cmp, _, {}) || []).forEach(function (grp) {
      (grp[2] || []).forEach(function (entry) {
        if (hit(entry[1])) sections.push({ title: entry[1], meta: cs ? 'otevřít sekci' : 'open the section', on: entry[4] ? function () { entry[4](cmp); } : function () { cmp.setState(Object.assign({ query: '', tab: entry[0], selected: null }, entry[2] || {})); } });
        (entry[3] || []).forEach(function (sub) { if (Array.isArray(sub) && hit(sub[0])) sections.push({ title: sub[0], meta: cs ? 'otevřít sekci' : 'open the section', on: sub[4] ? function () { sub[4](cmp); } : function () { cmp.setState(Object.assign({ query: '', tab: sub[1], selected: null }, sub[2] || {})); } }); });
      });
    });
    return [
      [cs ? 'Akce' : 'Actions', acts.slice(0, 6)],
      [cs ? 'Služby' : 'Services', services.slice(0, 6)],
      [cs ? 'Domény a DNS' : 'Domains and DNS', domains.slice(0, 6)],
      [cs ? 'Tikety' : 'Tickets', tickets.slice(0, 6)],
      [cs ? 'Sekce panelu' : 'Panel sections', sections.slice(0, 6)]
    ];
  }

  /* Tabs a deep link (#/slug) may open: the ones the sidebar offers. The prototype's narrated areas are not among them. */
  function allows(tab) {
    if (!nav()) return true;
    var lk = links();
    var open = ['overview', 'svcdesk', 'order', 'tickets', 'billing', 'settings'];
    if (lk.team !== false) open.push('team');
    if (lk.api !== false) open.push('api');
    if (lk.projects !== false) open.push('projects');
    if (lk.registrars !== false) open.push('registrars');
    ['audit', 'windows', 'costs', 'privacy', 'monitoring', 'backups'].forEach(function (k) { if (lk[k] !== false) open.push(k); });
    return open.indexOf(tab) >= 0;
  }

  window.OnhostPanelNav = { groups: groups, cats: cats, visible: visible, allows: allows, hits: hits, enter: enter, actions: actions, searchGroups: searchGroups };
})();
