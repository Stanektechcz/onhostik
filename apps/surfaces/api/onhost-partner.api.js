/* onhost-partner.api.js — window.OnhostPartner (seams #46 and #47): the partner portal outside demo mode.
 * Seam #46 adds the Marketplace tab (listings, jobs, deliveries). Seam #47 puts the prototype's narrated tabs on the partner
 * API: clients, the tier and rate, the overview KPIs and feed, the monthly commissions, the balance and payouts (a real
 * payout request), the white-label settings (saved, verified by the scheduler) and the assets with the partner's own link.
 * Every helper returns null until its data arrived, so the prototype's literal stays as the fallback. */
(function () {
  if (window.OnhostPartner) return;
  var S = { listings: null, orders: null, overview: null, clients: null, commissions: null, payouts: null, whitelabel: null, assets: null, model: null, terms: null, busy: {}, error: null, synced: false };
  function A() { return window.OnhostApi || null; }
  function cs(cmp) { return !cmp || !cmp.state || (cmp.state.lang || 'cs') === 'cs'; }
  function tr(cmp, a, b) { return cs(cmp) ? a : b; }
  function money(m) { return m && m.minor != null ? (Math.round(m.minor) / 100).toLocaleString('cs-CZ', { maximumFractionDigits: 2 }) + ' ' + (m.currency || '') : '—'; }
  function major(m) { return m && m.minor != null ? Math.round(m.minor) / 100 : 0; }
  function when(iso, cmp) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString(cs(cmp) ? 'cs-CZ' : 'en-GB', { day: 'numeric', month: 'numeric' }); }
  function load(cmp, key, path) {
    if (S[key] !== null || S.busy[key] || !A()) return;
    S.busy[key] = true;
    A().get(path).then(function (r) { S[key] = r.data !== undefined ? r.data : r; }).catch(function (e) { S[key] = { __error: (e && e.message) || 'error' }; S.error = (e && e.message) || 'error'; }).then(function () { delete S.busy[key]; if (cmp && cmp.forceUpdate) cmp.forceUpdate(); });
  }
  function ok(v) { return v && !v.__error ? v : null; }
  function reload(cmp) { ['listings', 'orders', 'overview', 'clients', 'commissions', 'payouts', 'whitelabel', 'model', 'terms'].forEach(function (k) { S[k] = null; }); S.error = null; if (cmp && cmp.forceUpdate) cmp.forceUpdate(); }
  function act(cmp, method, path, body, done) {
    var a = A(); if (!a) return;
    var p = method === 'put' ? a.put(path, body || {}, { 'Idempotency-Key': a.key() }) : a.post(path, body || {}, a.key());
    p.then(function () { if (done) done(); reload(cmp); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
  }

  /* ── seam #47: live data for the prototype's own tabs ─────────────────────────────────────────────────────────── */
  function overview(cmp) { load(cmp, 'overview', '/partner/overview'); return ok(S.overview); }
  function clientsRaw(cmp) { load(cmp, 'clients', '/partner/clients'); return ok(S.clients); }
  function commissions(cmp) { load(cmp, 'commissions', '/partner/commissions'); return ok(S.commissions); }
  function payouts(cmp) { load(cmp, 'payouts', '/partner/payouts'); return ok(S.payouts); }
  function whitelabel(cmp) { load(cmp, 'whitelabel', '/partner/whitelabel'); return ok(S.whitelabel); }
  function assets(cmp) { load(cmp, 'assets', '/partner/assets'); return ok(S.assets); }
  function orgName() { var b = window.ONHOST && window.ONHOST.user && window.ONHOST.user.organization; return (b && b.name) || null; }
  /* clients in the prototype's row shape: {id, name, contact, st, mrr (major), since, svc, services [[name, major]], paid} */
  function clients(cmp) {
    var list = clientsRaw(cmp);
    if (!Array.isArray(list)) return null;
    return list.map(function (c) {
      return { id: c.id, name: c.name || '—', contact: c.contact || '', st: c.st || 'ok', mrr: major(c.mrr), since: c.since || '', svc: c.svc || '', services: (c.services || []).map(function (s) { return [s[0], major(s[1])]; }), commission: major(c.commission) };
    });
  }
  /* the tier table [[threshold major, label, pct]] from the platform's tiers; the label localised */
  function tiers(cmp) {
    var o = overview(cmp);
    if (!o || !o.tier || !Array.isArray(o.tier.table)) return null;
    var names = { bronze: ['Bronz', 'Bronze'], silver: ['Stříbro', 'Silver'], gold: ['Zlato', 'Gold'], platinum: ['Platina', 'Platinum'] };
    return o.tier.table.map(function (t) { var n = names[t.name] || [t.name, t.name]; return [Math.round((t.threshold_minor || 0) / 100), tr(cmp, n[0], n[1]), t.rate]; });
  }
  function rate(cmp) { var o = overview(cmp); return o && o.partner && o.partner.rate != null ? o.partner.rate / 100 : null; }
  function company(cmp) { var o = overview(cmp); var n = orgName(); return o && o.partner ? (n || tr(cmp, 'Partner', 'Partner')) + ' · ' + o.partner.code : null; }
  /* §5m-1: the commission model is a contract term — clicking the other model asks finance; the note shows the request's state */
  function modelInfo(cmp) { load(cmp, 'model', '/partner/model'); return ok(S.model); }
  function requestModel(cmp, model) {
    var info = modelInfo(cmp), o = overview(cmp);
    if (!o || !o.partner) return false; // no API yet: the prototype toggle stays
    var current = (info && info.model) || o.partner.model;
    if (model === current) return true;
    if (info && info.request && info.request.state === 'requested') { cmp.flash(tr(cmp, 'Žádost už čeká na finance', 'A request is already waiting for finance'), tr(cmp, 'Rozhodnutí přijde e-mailem i sem do portálu.', 'The decision arrives by e-mail and here in the portal.')); return true; }
    var note = window.prompt(tr(cmp, 'Požádat finance o změnu modelu na ' + (model === 'share' ? 'podíl z objemu' : 'jednorázově + bonus') + '. Změna platí od dalšího měsíce. Poznámka (volitelné):', 'Ask finance to switch the model to ' + (model === 'share' ? 'revenue share' : 'one-off + bonus') + '. The change applies from next month. Note (optional):'), '');
    if (note === null) return true;
    var a = A(); if (!a) return true;
    a.post('/partner/model', { model: model, note: note || null }, a.key()).then(function () { S.model = null; if (cmp.forceUpdate) cmp.forceUpdate(); cmp.flash(tr(cmp, 'Žádost odeslána', 'Request sent'), tr(cmp, 'Finance rozhodne; schválená změna platí od 1. dne příštího měsíce.', 'Finance decides; an approved change applies from the 1st of next month.')); }).catch(function (e) { cmp.flash(tr(cmp, 'Žádost neprošla', 'Request failed'), (e && e.message) || 'error'); });
    return true;
  }
  function modelNote(cmp) {
    var info = modelInfo(cmp);
    if (!info) return null;
    var name = function (m) { return m === 'share' ? tr(cmp, 'podíl z objemu', 'revenue share') : tr(cmp, 'jednorázově + bonus', 'one-off + bonus'); };
    var base = tr(cmp, 'Platný model: ', 'Model in force: ') + name(info.model) + '. ';
    if (info.pending_model) return base + tr(cmp, 'Schválená změna na ', 'Approved change to ') + name(info.pending_model) + tr(cmp, ' platí od ', ' applies from ') + (info.model_effective_from || '') + '.';
    if (info.request && info.request.state === 'requested') return base + tr(cmp, 'Žádost o změnu na ', 'A request for ') + name(info.request.to) + tr(cmp, ' čeká na finance.', ' is waiting for finance.');
    if (info.request && info.request.state === 'rejected') return base + tr(cmp, 'Poslední žádost finance zamítly', 'The last request was rejected') + (info.request.decision_note ? ': ' + info.request.decision_note : '.');
    return base + tr(cmp, 'Model je smluvní podmínka — změnu schvalují finance a platí od dalšího měsíce.', 'The model is a contract term — finance approves a change, effective next month.');
  }
  /* §5n-1: every contract term with a one-click request — the block under the model buttons */
  var TERM_LABELS = {
    model: ['Model provize', 'Commission model'], rate_lock: ['Zámek sazby', 'Rate lock'], payout_terms: ['Výplatní podmínky', 'Payout terms'], whitelabel_scope: ['Rozsah white-labelu', 'White-label scope']
  };
  var TERM_VALUES = {
    share: ['podíl z objemu', 'revenue share'], oneoff: ['jednorázově + bonus', 'one-off + bonus'], '3': ['3 měsíce', '3 months'], '6': ['6 měsíců', '6 months'], '12': ['12 měsíců', '12 months'],
    on_request: ['na vyžádání', 'on request'], monthly: ['měsíčně automaticky', 'monthly, automatic'], quarterly: ['čtvrtletně automaticky', 'quarterly, automatic'], basic: ['základní (doména, skrytá značka)', 'basic (domain, hidden brand)'], full: ['plný (vlastní e-mail, ceny, podpora)', 'full (own mail, prices, support)']
  };
  function termValue(cmp, kind, v) { if (v === null || v === undefined || v === '') return kind === 'rate_lock' ? tr(cmp, 'bez zámku', 'no lock') : '—'; if (kind === 'rate_lock' && /^\d{4}-/.test(String(v))) return tr(cmp, 'do ', 'until ') + v; var t = TERM_VALUES[String(v)]; return t ? tr(cmp, t[0], t[1]) : String(v); }
  function termsInfo(cmp) { load(cmp, 'terms', '/partner/changes'); return ok(S.terms); }
  function termsTitle(cmp) { return tr(cmp, 'Smluvní podmínky · změnu schvalují finance, platí od dalšího měsíce', 'Contract terms · finance approves a change, effective next month'); }
  function terms(cmp) {
    var info = termsInfo(cmp);
    if (!info) return null;
    var BTN = 'background:transparent;border:2px solid #201e1d;color:#201e1d;font-family:var(--font-heading,Archivo);font-weight:800;font-size:11px;letter-spacing:.05em;text-transform:uppercase;padding:6px 12px;cursor:pointer';
    var current = { model: info.model, rate_lock: info.rate_locked_until, payout_terms: info.payout_terms, whitelabel_scope: info.whitelabel_scope };
    return ['model', 'rate_lock', 'payout_terms', 'whitelabel_scope'].map(function (kind) {
      var open = info.open && info.open[kind], pending = info.pending && info.pending[kind], note = '';
      if (open) note = tr(cmp, '· žádost o ', '· request for ') + termValue(cmp, kind, open.to) + tr(cmp, ' čeká na finance', ' waits for finance');
      else if (pending) note = tr(cmp, '· schváleno: ', '· approved: ') + termValue(cmp, kind, pending.to) + tr(cmp, ' od ', ' from ') + (pending.effective_from || '');
      else if (kind === 'rate_lock' && info.rate) note = '· ' + tr(cmp, 'sazba ', 'rate ') + info.rate + ' %';
      else if (kind === 'payout_terms' && info.min_payout) note = '· ' + tr(cmp, 'minimum ', 'minimum ') + money(info.min_payout);
      return { kind: kind, label: tr(cmp, TERM_LABELS[kind][0], TERM_LABELS[kind][1]), value: termValue(cmp, kind, current[kind]), note: note, action: open ? tr(cmp, 'Čeká', 'Waiting') : tr(cmp, 'Požádat o změnu', 'Request a change'), style: BTN + (open ? ';opacity:.5' : ''), on: function () { requestChange(cmp, kind); } };
    });
  }
  function requestChange(cmp, kind) {
    var info = termsInfo(cmp); if (!info) return;
    if (info.open && info.open[kind]) { cmp.flash(tr(cmp, 'Žádost už čeká na finance', 'A request is already waiting for finance'), tr(cmp, 'Rozhodnutí přijde e-mailem i sem do portálu.', 'The decision arrives by e-mail and here in the portal.')); return; }
    var values = (info.kinds && info.kinds[kind]) || [];
    var listed = values.map(function (v) { return v + ' = ' + termValue(cmp, kind, v); }).join('\n');
    var value = window.prompt(tr(cmp, 'Nová hodnota pro ', 'New value for ') + tr(cmp, TERM_LABELS[kind][0], TERM_LABELS[kind][1]) + ':\n' + listed, values[0] || '');
    if (value === null) return;
    value = String(value).trim().split(' ')[0];
    if (values.indexOf(value) < 0) { cmp.flash(tr(cmp, 'Neznámá hodnota', 'Unknown value'), listed); return; }
    var note = window.prompt(tr(cmp, 'Poznámka pro finance (volitelné):', 'Note for finance (optional):'), '');
    if (note === null) return;
    var a = A(); if (!a) return;
    a.post('/partner/changes', { kind: kind, value: value, note: note || null }, a.key()).then(function () { S.terms = null; S.model = null; if (cmp.forceUpdate) cmp.forceUpdate(); cmp.flash(tr(cmp, 'Žádost odeslána', 'Request sent'), tr(cmp, 'Finance rozhodnou; schválená změna platí od 1. dne příštího měsíce.', 'Finance decides; an approved change applies from the 1st of next month.')); }).catch(function (e) { cmp.flash(tr(cmp, 'Žádost neprošla', 'Request failed'), (e && e.message) || 'error'); });
  }
  /* tab badges: the real client count and the payouts waiting for approval (empty = no badge) */
  function clientBadge(cmp) { var list = clientsRaw(cmp); return Array.isArray(list) ? String(list.length) : null; }
  function payoutBadge(cmp) { var p = payouts(cmp); if (!p || !Array.isArray(p.payouts)) return ''; var n = p.payouts.filter(function (x) { return x.state === 'requested' || x.state === 'approved'; }).length; return n ? String(n) : ''; }
  function kpis(cmp, mrrTotal, rateNow, mult) {
    var o = overview(cmp);
    if (!o || !o.kpis) return null;
    var k = o.kpis, dPos = 'font-size:12px;margin-top:6px;color:#ae1800;font-family:var(--font-heading,Archivo);font-weight:700', dMut = 'font-size:12px;margin-top:6px;color:rgba(32,30,29,.55)';
    var rateLabel = Math.round(rateNow * 100) + ' %';
    return [
      { label: tr(cmp, 'Objem klientů', 'Client volume'), value: cmp.mny(major(k.volume) * mult), delta: tr(cmp, 'měsíční objem předplatných vašich klientů', 'monthly subscription volume of your clients'), deltaStyle: dMut },
      { label: tr(cmp, 'Provize z objemu / měs.', 'Commission on volume / mo'), value: cmp.mny(major(k.commission_monthly) * mult), delta: tr(cmp, rateLabel + ' z objemu portfolia · k výplatě až po zaplacení', rateLabel + ' of the portfolio · payable once paid'), deltaStyle: dMut },
      { label: tr(cmp, 'Aktivní klienti', 'Active clients'), value: String(k.active_clients || 0), delta: tr(cmp, (k.clients || 0) + ' celkem', (k.clients || 0) + ' in total'), deltaStyle: dPos },
      { label: tr(cmp, 'Výpovědi', 'Notices'), value: String(k.churn || 0), delta: tr(cmp, 'klienti končící s obdobím', 'clients ending with the period'), deltaStyle: dMut }
    ];
  }
  function feed(cmp) {
    var o = overview(cmp);
    if (!o || !Array.isArray(o.feed)) return null;
    var tagS = function (color) { return 'font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-family:var(--font-heading,Archivo);font-weight:800;margin-top:6px;margin-left:72px;color:' + color; };
    if (!o.feed.length) return [{ when: '—', text: tr(cmp, 'Zatím žádný pohyb: první provize vznikne z první zaplacené faktury vašeho klienta.', 'Nothing yet: the first commission comes with the first paid invoice of your client.'), tag: tr(cmp, 'start', 'start'), tagStyle: tagS('rgba(32,30,29,.55)') }];
    return o.feed.map(function (f) { return { when: when(f.when, cmp), text: f.text || '', tag: f.tag || '', tagStyle: tagS(f.tag === 'dobropis' ? '#ae1800' : 'rgba(32,30,29,.55)') }; });
  }
  function commRows(cmp) {
    var c = commissions(cmp);
    if (!c || !Array.isArray(c.months)) return null;
    var states = { accruing: ['počítá se', 'accruing'], requested: ['ke schválení', 'to approve'], paid: ['vyplaceno', 'paid'] };
    var rows = c.months.map(function (m) {
      var st = states[m.state] || [m.state, m.state];
      return {
        month: m.period, clients: String(m.clients || 0), base: money(m.base), rate: (m.rate || 0) + ' %', total: money(m.amount), state: tr(cmp, st[0], st[1]),
        stateStyle: 'font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-family:var(--font-heading,Archivo);font-weight:800;padding:4px 8px;' + (m.state === 'accruing' ? 'background:#ec3013;color:#f3f2f2' : 'border:1px solid rgba(32,30,29,.35);color:rgba(32,30,29,.7)')
      };
    });
    return rows.length ? rows : [{ month: tr(cmp, 'zatím nic', 'nothing yet'), clients: '0', base: money(c.balance && c.balance.payable ? { minor: 0, currency: c.balance.payable.currency } : null), rate: '', total: '', state: tr(cmp, 'čeká na první zaplacenou fakturu', 'awaiting the first paid invoice'), stateStyle: 'font-size:10px;letter-spacing:.1em;text-transform:uppercase;font-family:var(--font-heading,Archivo);font-weight:800;padding:4px 8px;border:1px solid rgba(32,30,29,.35);color:rgba(32,30,29,.7)' }];
  }
  /* the payouts tab: {docs, earned, held, paidBase, heldBase} in major units, from the balance of the commission engine */
  function payoutLive(cmp) {
    var c = commissions(cmp);
    if (!c || !c.balance) return null;
    var docs = (c.months || []).reduce(function (n, m) { return n + ((m.lines || []).length); }, 0);
    var r = (c.rules && c.rules.rate) || 0;
    var earned = major(c.balance.payable), held = major(c.balance.held);
    return { docs: docs, earned: earned, held: held, paidBase: r ? Math.round(earned / (r / 100)) : 0, heldBase: r ? Math.round(held / (r / 100)) : 0 };
  }
  function payRows(cmp, balanceNum, openNo) {
    var p = payouts(cmp);
    if (!p || !Array.isArray(p.payouts)) return null;
    var labels = { requested: ['požádáno', 'requested'], approved: ['schváleno', 'approved'], paid: ['vyplaceno', 'paid'], rejected: ['zamítnuto', 'rejected'] };
    var rows = p.payouts.map(function (x) { var st = labels[x.state] || [x.state, x.state]; return [x.number, when(x.paid_at || x.requested_at, cmp), major(x.amount), x.method === 'offset' ? tr(cmp, 'zápočet proti faktuře', 'offset against invoice') : tr(cmp, 'bankovní převod', 'bank transfer'), x.state === 'paid' ? 'paid' : 'open', tr(cmp, st[0], st[1])]; });
    if (balanceNum > 0) rows.push([openNo, '—', balanceNum, tr(cmp, 'čeká na žádost', 'awaiting request'), 'open', tr(cmp, 'otevřeno', 'open')]);
    return rows;
  }
  function requestPayout(cmp, amount, iban) {
    var a = A(); if (!a) return;
    a.post('/partner/payouts', { amount: amount, iban: iban, method: 'bank_transfer' }, a.key()).then(function (r) {
      var d = r.data || r; reload(cmp);
      cmp.flash(tr(cmp, 'Žádost přijata', 'Request received'), (d.number || '') + ' — ' + tr(cmp, 'samofakturaci vystavíme a peníze odejdou do pěti pracovních dnů.', 'we issue the self-billed invoice and the money leaves within five working days.'));
      cmp.setState({ payAmount: '', payTouched: false });
    }).catch(function (e) { cmp.flash(tr(cmp, 'Žádost neprošla', 'Request failed'), (e && e.message) || 'error'); });
  }
  /* white-label: the settings come from the API once (sync), saving goes through PUT, verification is the scheduler's job */
  function sync(cmp) {
    if (S.synced || !cmp) return;
    var o = overview(cmp), w = whitelabel(cmp);
    if (!o || !w) return;
    S.synced = true;
    setTimeout(function () {
      cmp.setState({ model: (o.partner && o.partner.model) || 'share', wlDomain: w.domain || 'panel.vasefirma.cz', wlVerified: !!w.verified_at, wlErr: '', wl: { hideBrand: !!w.hide_brand, ownMail: !!w.own_mail, ownPrices: !!w.own_prices, ownSupport: !!w.own_support } });
    }, 0);
  }
  function wlVerified(cmp) { var w = whitelabel(cmp); return !!(w && w.verified_at); }
  function cnameTarget() { var w = ok(S.whitelabel); return (w && w.cname_target) || null; }
  function wlRecord(cmp, host) { var t = cnameTarget(); return t ? host + '.   300  IN  CNAME  ' + t + '.' : null; }
  function saveWhitelabel(cmp, domain, wl) {
    var a = A(); if (!a) return;
    a.put('/partner/whitelabel', { domain: domain, hide_brand: !!wl.hideBrand, own_mail: !!wl.ownMail, own_prices: !!wl.ownPrices, own_support: !!wl.ownSupport }, { 'Idempotency-Key': a.key() }).then(function (r) {
      var d = r.data || r; S.whitelabel = null; S.synced = false; if (cmp.forceUpdate) cmp.forceUpdate();
      cmp.setState({ wlErr: '', wlVerified: !!(d.whitelabel && d.whitelabel.verified_at) });
      cmp.flash(tr(cmp, 'Uloženo', 'Saved'), domain + ' — ' + tr(cmp, 'CNAME ověříme do hodiny; certifikát vystavíme po ověření.', 'we verify the CNAME within the hour; the certificate follows the verification.'));
    }).catch(function (e) { cmp.setState({ wlErr: (e && e.message) || 'error' }); });
  }
  function refBase(cmp) { var o = overview(cmp); return o && o.partner && o.partner.code ? location.origin + '/?ref=' + encodeURIComponent(o.partner.code) : null; }
  function files(cmp) { var a = assets(cmp); if (!Array.isArray(a)) return null; return a.map(function (f) { return [f.name || f.key, f.type || (String(f.url || '').split('.').pop() || '').toUpperCase(), f.url || '']; }); }

  /* ── seam #46: the marketplace tab ───────────────────────────────────────────────────────────────────────────── */
  var BTN = 'background:transparent;border:2px solid #201e1d;color:#201e1d;font-family:var(--font-heading,Archivo);font-weight:800;font-size:11px;padding:6px 10px;cursor:pointer;margin-left:6px';
  var PRIMARY = 'background:#ec3013;border:2px solid #ec3013;color:#f3f2f2;font-family:var(--font-heading,Archivo);font-weight:800;font-size:11px;padding:6px 10px;cursor:pointer;margin-left:6px';
  var HIDDEN = 'display:none';
  var TH = 'text-align:left;padding:10px 14px;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:rgba(32,30,29,.55);border-bottom:2px solid #201e1d';
  function pill(tone) { var c = { ok: '#1f7a3f', warn: '#b8860b', off: 'rgba(32,30,29,.45)', hot: '#ae1800' }[tone] || '#201e1d'; return 'display:inline-block;padding:2px 8px;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#f3f2f2;background:' + c; }
  var LISTING_STATE = { draft: ['koncept · čeká na zveřejnění', 'draft · awaiting publication', 'warn'], published: ['zveřejněno', 'published', 'ok'], paused: ['pozastaveno', 'paused', 'off'], retired: ['staženo', 'retired', 'off'] };
  var ORDER_STATE = { ordered: ['nová zakázka', 'new job', 'warn'], in_progress: ['pracujeme', 'in progress', 'ok'], delivered: ['dodáno · čeká na převzetí', 'delivered · awaiting acceptance', 'ok'], accepted: ['převzato', 'accepted', 'ok'], disputed: ['reklamace', 'disputed', 'hot'], cancelled: ['zrušeno', 'cancelled', 'off'], ended: ['ukončeno', 'ended', 'off'] };

  function newListing(cmp) {
    var key = window.prompt(tr(cmp, 'Klíč nabídky (malá písmena, číslice, pomlčky):', 'Listing key (lowercase letters, digits, dashes):'), 'wp-care-basic');
    if (!key) return;
    var title = window.prompt(tr(cmp, 'Název (3–120 znaků):', 'Title (3–120 characters):'), ''); if (!title) return;
    var category = window.prompt(tr(cmp, 'Kategorie: care, seo, security, backup, migration, development, design, content', 'Category: care, seo, security, backup, migration, development, design, content'), 'care'); if (!category) return;
    var price = window.prompt(tr(cmp, 'Cena bez DPH (celé koruny):', 'Net price (whole units):'), '1500'); if (!price) return;
    var billing = window.prompt(tr(cmp, 'Účtování: oneoff (jednorázově) / monthly (měsíčně)', 'Billing: oneoff / monthly'), 'oneoff'); if (!billing) return;
    var days = window.prompt(tr(cmp, 'Dodání do (dní):', 'Delivery within (days):'), '7'); if (days === null) return;
    var description = window.prompt(tr(cmp, 'Popis pro zákazníky:', 'Description for customers:'), '') || '';
    var checklist = [];
    if (billing.trim() === 'monthly') { // §5o-2: the monthly deliverable is a matter of facts — the items the partner ticks every period
      var cl = window.prompt(tr(cmp, 'Checklist měsíčního plnění (položky oddělené čárkou, např. aktualizace, záloha, uptime%):', 'Monthly deliverable checklist (comma-separated, e.g. updates, backup, uptime%):'), tr(cmp, 'aktualizace, záloha, uptime%', 'updates, backup, uptime%'));
      if (cl === null) return;
      checklist = cl.split(',').map(function (s) { return s.trim(); }).filter(Boolean).map(function (label) { var text = /%$/.test(label); var raw = label.replace(/%$/, ''); return { key: raw.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 30) || 'item', cs: raw, en: raw, kind: text ? 'text' : 'check' }; });
    }
    act(cmp, 'post', '/partner/marketplace/listings', { key: key.trim().toLowerCase(), title: title.trim(), category: category.trim(), price_minor: Math.round(Number(String(price).replace(',', '.')) * 100), billing: billing.trim(), delivery_days: Math.max(1, Number(days) || 7), description: description, checklist: checklist });
  }
  /* §5p-3: a file behind a checklist item — a file picker, multipart upload, then the report prompt again */
  function uploadEvidence(cmp, o, item) {
    var a = A(); if (!a) return;
    var input = document.createElement('input'); input.type = 'file'; input.accept = '.pdf,.png,.jpg,.jpeg,.txt,.csv,.zip,.log';
    input.onchange = function () {
      var file = input.files && input.files[0]; if (!file) return;
      var form = new FormData(); form.append('key', item.key); form.append('file', file);
      a.upload('/partner/marketplace/orders/' + encodeURIComponent(o.id) + '/evidence', form).then(function (r) { var scan = r && (r.data || r).scan ? (r.data || r).scan.result : null; cmp.flash(tr(cmp, 'Soubor nahrán', 'File uploaded'), file.name + ' · ' + scanLabel(cmp, scan) + ' · ' + tr(cmp, 'teď odevzdejte plnění', 'now send the report')); reload(cmp); }).catch(function (e) { cmp.flash(tr(cmp, 'Nahrání selhalo', 'Upload failed'), (e && e.message) || 'error'); });
    };
    input.click();
  }
  function listingRows(cmp) {
    return (S.listings || []).map(function (l) {
      var st = LISTING_STATE[l.state] || [l.state, l.state, 'off'];
      var a1 = null, a2 = null;
      if (l.state === 'published') a1 = [tr(cmp, 'Pozastavit', 'Pause'), BTN, function () { act(cmp, 'post', '/partner/marketplace/listings/' + encodeURIComponent(l.id) + '/state', { state: 'paused' }); }];
      if (l.state === 'paused') a1 = [tr(cmp, 'Obnovit', 'Resume'), PRIMARY, function () { act(cmp, 'post', '/partner/marketplace/listings/' + encodeURIComponent(l.id) + '/state', { state: 'published' }); }];
      if (l.state !== 'retired') a2 = [tr(cmp, 'Upravit cenu', 'Edit price'), BTN, function () {
        var price = window.prompt(tr(cmp, 'Nová cena bez DPH (změna vrátí nabídku ke schválení):', 'New net price (the listing goes back for approval):'), String((l.price.minor || 0) / 100)); if (!price) return;
        act(cmp, 'put', '/partner/marketplace/listings/' + encodeURIComponent(l.id), { price_minor: Math.round(Number(String(price).replace(',', '.')) * 100) });
      }];
      return {
        title: l.title, sub: l.key + ' · ' + l.category + ' · ' + (l.billing === 'monthly' ? tr(cmp, 'měsíčně', 'monthly') : tr(cmp, 'jednorázově', 'one-off')) + ' · ' + tr(cmp, 'dodání do ', 'delivery within ') + l.delivery_days + ' d' + (l.orders ? ' · ' + l.orders + '× ' + tr(cmp, 'převzato', 'accepted') : ''),
        price: money(l.price), state: tr(cmp, st[0], st[1]), stateStyle: pill(st[2]),
        a1Label: a1 ? a1[0] : '', a1Style: a1 ? a1[1] : HIDDEN, a1On: a1 ? a1[2] : function () {},
        a2Label: a2 ? a2[0] : '', a2Style: a2 ? a2[1] : HIDDEN, a2On: a2 ? a2[2] : function () {}
      };
    });
  }
  /* §5t-5: what the virus scan said about an uploaded file */
  function scanLabel(cmp, scan) { return scan === 'clean' ? tr(cmp, 'antivir: čistý', 'antivirus: clean') : (scan === 'unavailable' ? tr(cmp, 'antivir: prověřuje se', 'antivirus: being checked') : (scan === 'infected' ? tr(cmp, 'antivir: zablokován', 'antivirus: blocked') : tr(cmp, 'antivir: bez kontroly', 'antivirus: not scanned'))); }
  function orderRows(cmp) {
    return (S.orders || []).map(function (o) {
      var st = ORDER_STATE[o.state] || [o.state, o.state, 'off'];
      var a1 = null, a2 = null;
      if (o.state === 'ordered') a1 = [tr(cmp, 'Převzít', 'Start'), PRIMARY, function () { act(cmp, 'post', '/partner/marketplace/orders/' + encodeURIComponent(o.id) + '/start', {}); }];
      if (o.state === 'ordered' || o.state === 'in_progress' || o.state === 'disputed') a2 = [tr(cmp, 'Označit dodáno', 'Mark delivered'), o.state === 'in_progress' ? PRIMARY : BTN, function () {
        var note = window.prompt(tr(cmp, 'Co bylo dodáno (zákazník to uvidí):', 'What was delivered (the customer sees it):'), ''); if (!note) return;
        act(cmp, 'post', '/partner/marketplace/orders/' + encodeURIComponent(o.id) + '/deliver', { note: note });
      }];
      if (o.state === 'accepted' && o.subscription && (o.subscription.state === 'active' || o.subscription.state === 'past_due')) a2 = [tr(cmp, 'Odevzdat měsíční plnění', 'Report the monthly deliverable'), (o.sla && o.sla.period && !o.sla.period.served) ? PRIMARY : BTN, function () { // §5n-2 + §5o-2: the period report with its checklist
        var evidence = {}, items = o.checklist || [], uploaded = (o.period_uploads || []).map(function (u) { return u.key; });
        for (var i = 0; i < items.length; i++) {
          var it = items[i], label = cs(cmp) ? it.cs : it.en;
          if (it.kind === 'file') { // §5p-3: the file goes up first, the report consumes it
            if (uploaded.indexOf(it.key) >= 0) continue;
            if (window.confirm(tr(cmp, 'Nahrát soubor: ', 'Upload a file: ') + label + '?')) { uploadEvidence(cmp, o, it); } else { cmp.flash(tr(cmp, 'Plnění neodevzdáno', 'Report not sent'), tr(cmp, 'Nejdřív nahrajte soubor k položce ', 'Upload the file for ') + label + '.'); }
            return;
          }
          if (it.kind === 'text') { var v = window.prompt(label + ':', ''); if (v === null) return; evidence[it.key] = v; }
          else { if (!window.confirm(tr(cmp, 'Splněno: ', 'Done: ') + label + '?')) { cmp.flash(tr(cmp, 'Plnění neodevzdáno', 'Report not sent'), tr(cmp, 'Každá položka checklistu musí být splněna.', 'Every checklist item must be done.')); return; } evidence[it.key] = true; }
        }
        var note = window.prompt(tr(cmp, 'Co bylo v tomto období uděláno (zákazník to uvidí):', 'What was done this period (the customer sees it):'), ''); if (!note) return;
        act(cmp, 'post', '/partner/marketplace/orders/' + encodeURIComponent(o.id) + '/deliver', { note: note, evidence: evidence });
      }];
      var overdue = o.sla && o.sla.overdue;
      var sub = (o.brief || '').slice(0, 140) + (o.subscription ? ' · ' + tr(cmp, 'předplatné do ', 'subscription until ') + when(o.subscription.period_end, cmp) + (o.subscription.cancel_at_period_end ? tr(cmp, ' (končí)', ' (ending)') : '') : '') + (overdue ? ' · ' + tr(cmp, 'PO TERMÍNU', 'OVERDUE') + (o.sla.refund_available ? tr(cmp, ' · zákazník může žádat vrácení', ' · the customer may ask for a refund') : '') : '');
      return {
        title: (o.listing ? o.listing.title : '—') + (o.dispute_reason ? ' · ' + tr(cmp, 'reklamace: ', 'dispute: ') + o.dispute_reason.slice(0, 80) : ''), brief: sub,
        due: when(o.due_at, cmp), share: money(o.partner_share), state: tr(cmp, st[0], st[1]), stateStyle: pill(overdue ? 'hot' : st[2]),
        a1Label: a1 ? a1[0] : '', a1Style: a1 ? a1[1] : HIDDEN, a1On: a1 ? a1[2] : function () {},
        a2Label: a2 ? a2[0] : '', a2Style: a2 ? a2[1] : HIDDEN, a2On: a2 ? a2[2] : function () {}
      };
    });
  }
  function vals(cmp) {
    load(cmp, 'listings', '/partner/marketplace/listings');
    load(cmp, 'orders', '/partner/marketplace/orders?limit=100');
    var loading = S.listings === null || S.orders === null;
    var open = (S.orders || []).filter(function (o) { return o.state === 'ordered' || o.state === 'in_progress' || o.state === 'disputed'; }).length;
    return {
      t: { newListing: tr(cmp, 'Nová nabídka', 'New listing'), reload: tr(cmp, 'Obnovit', 'Refresh'), orders: tr(cmp, 'Zakázky od zákazníků', 'Jobs from customers') },
      note: S.error ? S.error : (loading ? tr(cmp, 'načítám…', 'loading…') : (open ? open + ' ' + tr(cmp, 'otevřených zakázek · dodávejte v termínu, po dodání zákazník převezme nebo reklamuje', 'open jobs · deliver on time, the customer accepts or disputes afterwards') : tr(cmp, 'Nabídky schvaluje ONhost; platforma si nechává provizi, váš podíl je splatný po převzetí zákazníkem.', 'ONhost approves listings; the platform keeps its commission, your share is payable once the customer accepts.'))),
      listingHead: [[tr(cmp, 'Nabídka', 'Listing')], [tr(cmp, 'Cena bez DPH', 'Net price')], [tr(cmp, 'Stav', 'State')], ['']].map(function (h) { return { label: h[0], style: TH }; }),
      orderHead: [[tr(cmp, 'Zakázka', 'Job')], [tr(cmp, 'Termín', 'Due')], [tr(cmp, 'Váš podíl', 'Your share')], [tr(cmp, 'Stav', 'State')], ['']].map(function (h) { return { label: h[0], style: TH }; }),
      listings: listingRows(cmp), orders: orderRows(cmp),
      noListings: !loading && !(S.listings || []).length, noOrders: !loading && !(S.orders || []).length,
      newListing: function () { newListing(cmp); }, reload: function () { reload(cmp); }
    };
  }
  function page(cmp) {
    return { kicker: tr(cmp, 'Partnerský program', 'Partner programme'), title: 'Marketplace', lead: tr(cmp, 'Vaše služby v katalogu ONhost: zákazníci je objednají z kreditu, vy dodáte, po převzetí je váš podíl splatný s ostatními provizemi.', 'Your services in the ONhost catalogue: customers order from credit, you deliver, once accepted your share is payable with the other commissions.') };
  }
  function badge() { var n = (S.orders || []).filter(function (o) { return o.state === 'ordered'; }).length; return n ? String(n) : ''; }
  window.OnhostPartner = {
    vals: vals, page: page, badge: badge, reload: reload,
    sync: sync, clients: clients, tiers: tiers, rate: rate, company: company, orgName: orgName, kpis: kpis, feed: feed, commRows: commRows, clientBadge: clientBadge, payoutBadge: payoutBadge, requestModel: requestModel, modelNote: modelNote, terms: terms, termsTitle: termsTitle, requestChange: requestChange,
    payoutLive: payoutLive, payRows: payRows, requestPayout: requestPayout, wlVerified: wlVerified, cnameTarget: cnameTarget, wlRecord: wlRecord, saveWhitelabel: saveWhitelabel, refBase: refBase, files: files
  };
})();
