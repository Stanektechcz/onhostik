/* ONhost panel — overview seams (docs/ui/data-seams.md #14 and #28).
 *
 * The prototype's "Přehled účtu" narrates a fictional customer (GPU spend, deploys, a forum, hardware in stock). On the
 * API-backed panel this module feeds the dashboard from the organization's real state — services, domains, credit,
 * documents due, open tickets, recent notifications — offers the next step when the account is empty, and gives the
 * empty service categories a real "order" call to action. The surface stays byte-identical: SurfaceRenderer points
 * the prototype's literal blocks at these functions; they return null without panel data so demo mode keeps its own. */
(function () {
  if (window.OnhostPanelOverview) return; // the prototype runtime executes helmet scripts twice

  function data() { return window.ONHOST_PANEL || null; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function tr(cmp) { var cs = isCs(cmp); return function (a, b) { return cs ? a : b; }; }
  function money(cmp, n) { return cmp && typeof cmp.money === 'function' ? cmp.money(n || 0) : String(n || 0); }
  function ago(cmp, at) {
    var _ = tr(cmp), m = Math.max(0, Math.round((Date.now() - at) / 60000));
    if (m < 1) return _('právě teď', 'just now');
    if (m < 60) return _('před ' + m + ' min', m + ' min ago');
    var h = Math.round(m / 60);
    if (h < 24) return _('před ' + h + ' h', h + ' h ago');
    return new Date(at).toLocaleDateString(isCs(cmp) ? 'cs-CZ' : 'en-GB', { day: 'numeric', month: 'numeric', year: 'numeric' });
  }
  function tone(kind) {
    return /incident|overdue|suspend|fail|abuse|security/.test(String(kind || '')) ? 'warn' : 'ok';
  }
  function groups() { return (data() && data().services) || {}; }
  function all() { var g = groups(), out = []; Object.keys(g).forEach(function (k) { out = out.concat(g[k] || []); }); return out; }
  function count(cat) { return ((groups())[cat] || []).length; }
  function tickets() { var S = window.OnhostStore; return S && typeof S.tickets === 'function' ? S.tickets().filter(function (t) { return t.state !== 'vyreseny'; }).length : 0; }
  function billing() { return (data() && data().billing) || {}; }
  function due() { var d = billing().document; return d && (d.state === 'due' || d.state === 'overdue') ? d : null; }

  /* Product the "order" call to action pre-selects for a service category. */
  var CAT_PRODUCT = { web: 'web-hosting', domain: 'domain', game: 'game', vps: 'vps', mail: 'mail', bucket: 'object-storage', panels: 'wordpress' };
  function productFor(cat) {
    var d = data(), key = CAT_PRODUCT[cat] || null, catalog = (d && d.catalog) || [];
    if (key === 'domain') return d && d.tlds && d.tlds.length ? 'domain' : null;
    var hit = catalog.filter(function (p) { return p.key === key && p.orderable !== false; })[0];
    return hit ? hit.key : null;
  }
  /* Opens the prototype's "Nová služba" wizard seeded with a real product; without one the customer is sent to the public catalogue. */
  function order(cmp, cat) {
    if (window.OnhostPanelShop && window.OnhostPanelShop.open(cmp, cat === 'domain' ? 'domain' : productFor(cat))) return; // §5x: the order centre in the panel
    var d = data(), first = ((d && d.catalog) || []).filter(function (p) { return p.orderable !== false; })[0];
    var type = productFor(cat) || (first ? first.key : null);
    if (!type) return; // nothing orderable: ordering never leaves the client section
    var product = ((d && d.catalog) || []).filter(function (p) { return p.key === type; })[0];
    var region = (d && d.regions && d.regions[0]) ? d.regions[0].code : 'cz1';
    cmp.setState({ modal: 'order', mStep: 0, userOpen: false, curOpen: false, notifOpen: false, md: { type: type, region: region, size: product && product.plans && product.plans[0] ? product.plans[0].key : (type === 'domain' ? '1' : 'm'), os: 'Debian 12', name: '', pay: '' } });
  }
  function topUp(cmp) { cmp.setState({ modal: 'topup', mStep: 0, userOpen: false, curOpen: false, notifOpen: false, md: { amount: 1000, method: 'bank' } }); }

  /* Six real numbers for the top strip: services, domains, credit, documents due, open tickets, availability. */
  function stats(cmp, stat, _) {
    var d = data(); if (!d) return null;
    var services = all().filter(function (s) { return String(s.apiState || s.state || '').toUpperCase() !== 'TERMINATED'; }), active = services.filter(function (s) { return /ACTIVE|aktiv|běž/i.test(String(s.apiState || s.state || '')); });
    var k = d.kpis || {}, doc = due(), open = tickets();
    return [
      stat(_('Aktivní služby', 'Active services'), String(active.length), services.length !== active.length ? (services.length - active.length) + _(' čeká', ' pending') : '', 3, Math.min(100, active.length * 25), 22, 'ok'),
      stat(_('Domény', 'Domains'), String(count('domain')), '', 7, Math.min(100, count('domain') * 25), 6, 'ok'),
      stat(_('Kredit', 'Credit'), money(cmp, k.credit), '', 11, k.credit > 0 ? 60 : 5, 12, 'ok'),
      stat(_('K úhradě', 'Due'), doc ? money(cmp, doc.outstanding) : money(cmp, 0), doc ? (doc.state === 'overdue' ? _('po splatnosti', 'overdue') : doc.number) : _('nic', 'nothing'), 15, doc ? 80 : 0, 18, doc ? (doc.state === 'overdue' ? 'warn' : 'ok') : 'ok'),
      stat(_('Otevřené tikety', 'Open tickets'), String(open), '', 19, Math.min(100, open * 30), 16, open ? 'warn' : 'ok'),
      stat(_('Dostupnost 30 dní', 'Uptime, 30 days'), active.length && k.uptime ? k.uptime : '—', active.length && k.uptime ? _('vaše služby', 'your services') : _('zatím bez služeb', 'no services yet'), 27, active.length && k.uptime ? 92 : 0, 6, 'ok')
    ];
  }

  /* Four quick actions that exist on this platform. */
  function quick(cmp, _) {
    if (!data()) return null;
    return [
      [_('Objednat službu', 'Order a service'), _('Webhosting, WordPress, e-shop nebo server z katalogu — z kreditu ihned, převodem po připsání.', 'Web hosting, WordPress, an e-shop or a server from the catalogue — at once from credit, or after a bank transfer.'), _('Otevřít průvodce →', 'Open the wizard →'), function () { order(cmp, 'web'); }, 'bolt'],
      [_('Přidat doménu', 'Add a domain'), _('Registrace nové domény s ověřením dostupnosti a cenou obnovy.', 'Register a new domain with an availability check and the renewal price.'), _('Zkontrolovat dostupnost →', 'Check availability →'), function () { order(cmp, 'domain'); }, 'globe'],
      [_('Dobít kredit', 'Top up credit'), _('Převodem s variabilním symbolem nebo kartou; kredit se čerpá jako první.', 'By transfer with a payment reference or by card; credit is drawn first.'), _('Dobít →', 'Top up →'), function () { topUp(cmp); }, 'branch'],
      [_('Napsat podpoře', 'Message support'), _('Tiket s prioritou, odpovídá inženýr, který službu provozuje.', 'A prioritised ticket, answered by the engineer who runs the service.'), _('Založit tiket →', 'Open a ticket →'), function () { cmp.setState({ tab: 'tickets', selected: null, query: '' }); }, 'message'],
      [_('Moje služby', 'My services'), _('Weby, domény a servery se správou, zálohami a nástroji každé služby.', 'Sites, domains and servers with the tools and backups of each service.'), _('Otevřít služby →', 'Open services →'), function () { cmp.setState({ tab: 'svcdesk', selected: null, query: '' }); }, 'bolt'],
      [_('Faktury a doklady', 'Invoices and documents'), _('Doklady ke stažení, stav plateb a historie kreditu.', 'Documents to download, payment state and credit history.'), _('Otevřít fakturaci →', 'Open billing →'), function () { cmp.setState({ tab: 'billing', selected: null, query: '' }); }, 'branch'],
      [_('Zabezpečení účtu', 'Account security'), _('Heslo, dvoufázové ověření, aktivní relace a přihlášení.', 'Password, two-factor authentication, active sessions and sign-ins.'), _('Otevřít zabezpečení →', 'Open security →'), function () { cmp.setState({ tab: 'settings', sub: 'security', selected: null, query: '' }); }, 'globe'],
      [_('Stav služeb', 'Service status'), _('Incidenty a plánované odstávky, živě a bez přihlášení.', 'Incidents and planned maintenance, live and public.'), _('Otevřít stav →', 'Open status →'), function () { window.open('/stav', '_blank', 'noopener'); }, 'message']
    ];
  }

  /* Section cards with the account's real numbers; only sections that exist on the platform. */
  function sectionCards(cmp, _) {
    var d = data(); if (!d) return null;
    var k = d.kpis || {}, doc = due(), n = all().length;
    return [
      ['svcdesk', _('Služby', 'Services'), n + ' ' + (isCs(cmp) ? (n === 1 ? 'služba' : n < 5 ? 'služby' : 'služeb') : (n === 1 ? 'service' : 'services')), _('Webhosting, servery, e-mail — správa, přístupy, soubory, zálohy.', 'Web hosting, servers, e-mail — management, access, files, backups.')],
      ['svcdesk', _('Domény a DNS', 'Domains and DNS'), String(count('domain')), _('Registrace, obnovy, DNS záznamy, DNSSEC a přesměrování.', 'Registration, renewals, DNS records, DNSSEC and redirects.')],
      ['billing', _('Fakturace', 'Billing'), doc ? money(cmp, doc.outstanding) + _(' k úhradě', ' due') : money(cmp, k.credit) + _(' kredit', ' credit'), _('Doklady, kredit, platby převodem i kartou, rozpad nákladů.', 'Documents, credit, transfer and card payments, cost breakdown.')],
      ['tickets', _('Podpora', 'Support'), tickets() + _(' otevřené', ' open'), _('Tikety se SLA a odpověď inženýra přímo v panelu.', 'SLA tickets with the engineer\'s reply right here in the panel.')],
      ['status', _('Stav infrastruktury', 'Infrastructure status'), k.uptime || '—', _('Incidenty a plánované odstávky, které se týkají vašich služeb.', 'Incidents and planned maintenance affecting your services.')],
      ['api', _('API a webhooky', 'API and webhooks'), _('klíče', 'keys'), _('Klíče s rozsahem, webhooky s historií doručení, OpenAPI dokumentace.', 'Scoped keys, webhooks with a delivery history, OpenAPI documentation.')],
      ['team', _('Tým a práva', 'Team and roles'), _('role', 'roles'), _('Pozvánky, role po oblastech, odebrání přístupu.', 'Invitations, per-area roles, access removal.')],
      ['settings', _('Nastavení účtu', 'Account settings'), (window.ONHOST && window.ONHOST.user && window.ONHOST.user.mfa) ? '2FA ✓' : '2FA ✕', _('Profil, heslo, dvoufázové ověření, fakturační údaje.', 'Profile, password, two-factor, billing details.')]
    ];
  }

  /* "Útrata po měsících" from the organization's invoices (the billing payload's twelve columns); hidden until a document exists. */
  function chart(cmp, _) {
    var b = billing(), c = b.chart;
    if (!data()) return null;
    if (!c || !c.months || !c.months.length || !(b.monthly > 0 || (b.document && b.document.number))) return { real: false, legend: [], cols: [] };
    var colors = ['var(--acc,#ec3013)', 'color-mix(in srgb,var(--acc,#ec3013) 45%,transparent)', 'var(--ink,#1a1918)'];
    return { real: true, title: _('Útrata po měsících', 'Spend by month'), note: _('posledních 12 měsíců podle vystavených dokladů, bez DPH', 'last 12 months from issued documents, excl. VAT'), legend: (c.legend || []).map(function (l, i) { return [l[0], l[1], colors[i % colors.length]]; }), cols: c.months };
  }

  /* The next best step: an empty account is invited to its first service; otherwise credit or a domain. */
  function advice(cmp, _) {
    var d = data(); if (!d) return null;
    var n = all().length, k = d.kpis || {}, doc = due();
    if (!n) return { title: _('Zatím tu nemáte žádnou službu', 'You have no service here yet'), lead: _('Vyberte webhosting, WordPress, e-shop nebo server z katalogu. Z kreditu se služba spustí ihned, převodem po připsání platby; doménu můžete přidat rovnou.', 'Pick web hosting, WordPress, an e-shop or a server from the catalogue. Paid from credit it starts at once, by transfer once the money arrives; a domain can be added right away.'), cta: _('Objednat první službu →', 'Order the first service →'), on: function () { order(cmp, 'web'); } };
    if (doc) return { title: _('Doklad ', 'Document ') + doc.number + _(' čeká na úhradu', ' is waiting for payment'), lead: _('Zaplaťte z kreditu, převodem s variabilním symbolem nebo kartou v sekci Fakturace. Po připsání spustíme, co na doklad čeká.', 'Pay from credit, by transfer with the payment reference or by card in Billing. Whatever waits on the document starts once it is paid.'), cta: _('Otevřít fakturaci →', 'Open billing →'), on: function () { cmp.setState({ tab: 'billing', selected: null }); } };
    if (!(k.credit > 0)) return { title: _('Dobijte si kredit', 'Top up your credit'), lead: _('Obnovy a nové objednávky se z kreditu platí okamžitě, bez čekání na převod. Nevyčerpaný kredit nepropadá.', 'Renewals and new orders are paid from credit at once, with no wait for a transfer. Unused credit never expires.'), cta: _('Dobít kredit →', 'Top up →'), on: function () { topUp(cmp); } };
    if (!count('domain')) return { title: _('Přidejte doménu', 'Add a domain'), lead: _('K webhostingu patří vlastní doména: ověříme dostupnost, ukážeme cenu i obnovu a DNS nastavíme na vaše služby.', 'Web hosting deserves its own domain: we check availability, show the price and renewal, and point DNS at your services.'), cta: _('Zkontrolovat doménu →', 'Check a domain →'), on: function () { order(cmp, 'domain'); } };
    return { title: _('Zapněte dvoufázové ověření', 'Turn on two-factor'), lead: _('Kód z autentikátoru ochrání účet, i kdyby heslo uniklo. Nastavení zabere minutu.', 'A code from an authenticator protects the account even if the password leaks. It takes a minute.'), cta: _('Nastavení → Zabezpečení →', 'Settings → Security →'), on: function () { cmp.setState({ tab: 'settings', sub: 'security', selected: null }); } };
  }

  /* A category without services: one row that says so and opens the order wizard for that category. */
  function emptyService(cmp, cat, _) {
    if (!data()) return null;
    var key = cat && cat.key, label = (cat && cat.label) || '';
    var orderable = !!productFor(key);
    return [{
      id: null, name: _('Zatím žádná služba · ', 'No service yet · ') + label, spec: orderable ? _('objednávka z katalogu · z kreditu ihned, převodem po připsání platby', 'order from the catalogue · at once from credit, after a transfer otherwise') : _('tuto kategorii zatím nabízíme na poptávku', 'this category is available on request'),
      meta: '', value: '', state: orderable ? _('OBJEDNAT', 'ORDER') : _('POPTAT', 'ASK'), kind: 'hot',
      on: function () { if (orderable) order(cmp, key); else cmp.setState({ tab: 'tickets', selected: null, query: '' }); }
    }];
  }

  /* Rows for the overview's "Poslední události": {title, meta, value, kind}. */
  function events(cmp) {
    if (!window.ONHOST_PANEL) return null;
    var _ = tr(cmp), S = window.OnhostStore;
    var list = S && typeof S.notifs === 'function' ? S.notifs() : [];
    if (!list.length) {
      return [{ title: _('Zatím žádné události', 'No events yet'), meta: _('objednávky, platby, doklady, incidenty a tikety se objeví zde', 'orders, payments, documents, incidents and tickets show up here'), value: '', kind: 'ok' }];
    }
    return list.slice(0, 5).map(function (n) {
      var body = String(n.body || '').replace(/\s+/g, ' ').trim();
      return {
        title: n.title, meta: ago(cmp, n.at) + (body ? ' · ' + (body.length > 90 ? body.slice(0, 87) + '…' : body) : ''),
        value: n.read ? '' : _('nové', 'new'), kind: tone(n.kind)
      };
    });
  }

  window.OnhostPanelOverview = { events: events, stats: stats, quick: quick, sectionCards: sectionCards, advice: advice, chart: chart, emptyService: emptyService, order: order, topUp: topUp };
})();
