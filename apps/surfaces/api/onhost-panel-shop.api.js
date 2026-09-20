/* ONhost panel — the order centre (audit §5x, docs/ui/data-seams.md #16).
 *
 * Ordering stays in the client section: "Nová služba", the sidebar's Objednat entry, the overview's quick actions and
 * the category pages open this full-screen order centre instead of the prototype's three-field wizard. Every service
 * type gets the choices it really has:
 *   web hosting / WordPress / e-shop / mail  → plan cards with what they contain, the site domain, add-ons, term
 *   custom web hosting                        → priced sliders and switches (the catalogue's options)
 *   VPS / VDS                                 → plan, system image, location, hostname, SSH key, extra resources
 *   managed database                          → plan, engine, location
 *   game server                               → the game (artwork tiles), server type, size presets, sliders, version, inputs
 *   domain                                    → availability search over the sold TLDs, registration term
 * The summary is priced by the server (PUT /v1/cart → POST /v1/cart/quote, the same path as the public checkout), the
 * customer confirms the required documents and pays from credit, by bank transfer (proforma) or by card, and the
 * confirmation stays in the panel. Data: window.ONHOST_PANEL (catalog with plans/features/addons, regions, tlds,
 * consents, game_config, kpis.credit). */
(function () {
  if (window.OnhostPanelShop) return; // the prototype runtime executes helmet scripts twice

  var ICON = { web: '🌐', managed: '⚡', mail: '✉', cloud: '🖥', data: '🗄', game: '🎮', domain: '🔗', apps: '⚙' };
  var GROUPS = [
    ['web', ['web-hosting', 'web-custom', 'wordpress', 'eshop', 'mail'], 'Weby a e-mail', 'Websites and mail'],
    ['servers', ['vps', 'vds', 'database', 'apps', 'object-storage'], 'Servery a data', 'Servers and data'],
    ['game', ['game'], 'Herní servery', 'Game servers'],
    ['domain', ['domain'], 'Domény', 'Domains']
  ];
  var TILE_ART = { 'minecraft-java': 'linear-gradient(180deg,#6aa84f 0 34%,#8b5a2b 34% 100%)', 'minecraft-bedrock': 'linear-gradient(180deg,#3d9ab8 0 34%,#5b6770 34% 100%)', hytale: 'linear-gradient(135deg,#2b2f6b,#6a3fa0 60%,#d7a63a)' };
  var S = null, root = null, cmpRef = null, quoteTimer = null, quoteSeq = 0;

  function D() { return window.ONHOST_PANEL || {}; }
  function cs() { return !(cmpRef && cmpRef.state && cmpRef.state.lang === 'en'); }
  function _(a, b) { return cs() ? a : b; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function kc(n) { var v = Math.round((Number(n) || 0) * 100) / 100; return v.toLocaleString(cs() ? 'cs-CZ' : 'en-US', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) + ' Kč'; }
  function gross(n) { return kc(Math.round((Number(n) || 0) * 1.21)); }
  function catalog() { return (D().catalog || []).filter(function (p) { return p.family !== 'addon'; }); }
  function product(key) { return catalog().filter(function (p) { return p.key === key; })[0] || null; }
  function credit() { var k = D().kpis || {}; return typeof k.credit === 'number' ? k.credit : ((cmpRef && cmpRef.state && typeof cmpRef.state.credit === 'number') ? cmpRef.state.credit : 0); }
  function snap(o, v) { var st = Number(o.step) || 1, mn = o.min != null ? Number(o.min) : 0, mx = o.max != null ? Number(o.max) : Infinity; v = mn + Math.round(Math.max(0, Number(v) - mn) / st) * st; return Math.min(mx, Math.max(mn, v)); }
  function flash(t, b) { if (cmpRef && typeof cmpRef.flash === 'function') cmpRef.flash(t, b); }

  /* ── state ─────────────────────────────────────────────────────────────── */
  function fresh(key) {
    var p = key && key !== 'domain' ? product(key) : null, reg = (D().regions || [])[0];
    var plan = p ? ((p.plans || []).filter(function (x) { return x.highlighted; })[0] || (p.plans || [])[0]) : null;
    return {
      step: key ? 1 : 0, key: key || null, plan: plan ? plan.key : null, period: 'month', region: reg ? reg.code : null,
      name: '', domain: '', image: p && p.images && p.images[0] ? p.images[0] : '', engine: p && p.engines && p.engines[0] ? p.engines[0] : '', ssh: '',
      opts: {}, addonPlans: {}, game: { group: null, egg: null, opts: {}, version: null, env: {}, cat: 'all' },
      dq: '', dyears: 1, dresults: null, dpick: null, dbusy: false,
      pay: null, consents: {}, promo: '', quote: null, quoteErr: null, quoteBusy: false, placing: false, done: null
    };
  }
  function set(patch, requote) { S = Object.assign({}, S, patch); render(); if (requote) scheduleQuote(); }

  /* ── pricing (local estimate; the quote is authoritative) ─────────────── */
  function optionQty(o, v) {
    if (o.kind === 'addon') return v ? 1 : 0;
    if (o.kind === 'select') { var c = (o.choices || []).filter(function (x) { return x.key === v; })[0]; return c ? Number(c.units) || 0 : 0; }
    return Math.max(0, snap(o, v) - (o['default'] != null ? Number(o['default']) : 0));
  }
  function gameEstimate() {
    var gc = D().game_config; if (!gc) return 0;
    var g = currentGame(); if (!g) return Number(gc.base_price) || 0;
    var vals = gameValues(g);
    return (gc.options || []).reduce(function (t, o) { return t + Math.max(0, (vals[o.key] || 0) - (o['default'] != null ? Number(o['default']) : 0)) * (Number(o.price) || 0); }, Number(gc.base_price) || 0);
  }
  function estimate() {
    if (!S.key) return 0;
    if (S.key === 'domain') { var t = tld(S.dpick); return t ? Number(t.register) * S.dyears : 0; }
    var p = product(S.key); if (!p) return 0;
    if (p.family === 'game') return gameEstimate();
    var plan = (p.plans || []).filter(function (x) { return x.key === S.plan; })[0]; if (!plan) return 0;
    var base = S.period === 'year' && plan.yearly ? plan.yearly / 12 : plan.monthly;
    var add = ((p.addons && p.addons.options) || []).reduce(function (t, o) { return t + (o.key in S.opts ? optionQty(o, S.opts[o.key]) * (Number(o.price) || 0) : 0); }, 0);
    ((p.addons && p.addons.products) || []).forEach(function (ap) { var k = S.addonPlans[ap.key]; var pl = k && (ap.plans || []).filter(function (x) { return x.key === k; })[0]; if (pl) add += pl.unit_year ? pl.price / 12 : pl.price; });
    return base + add;
  }

  /* ── games ─────────────────────────────────────────────────────────────── */
  function currentGroup() { var gc = D().game_config; return gc && S.game.group ? (gc.groups || []).filter(function (x) { return x.key === S.game.group; })[0] || null : null; }
  function currentGame() {
    var gc = D().game_config, grp = currentGroup(); if (!gc || !grp) return null;
    var key = grp.eggs.indexOf(S.game.egg) >= 0 ? S.game.egg : grp.eggs[0];
    return (gc.games || []).filter(function (x) { return x.key === key; })[0] || null;
  }
  function floorOf(g, o) { return g && g.min && g.min[o.key] != null ? Number(g.min[o.key]) : (o['default'] != null ? Number(o['default']) : Number(o.min) || 0); }
  function gameValues(g) {
    var gc = D().game_config, out = {};
    (gc.options || []).forEach(function (o) { var f = floorOf(g, o), c = S.game.opts[o.key]; out[o.key] = snap(Object.assign({}, o, { min: f }), Math.max(f, c != null ? Number(c) : f)); });
    return out;
  }

  /* ── domains ───────────────────────────────────────────────────────────── */
  function tldOf(n) { var m = String(n || '').toLowerCase().match(/\.([a-z0-9-]{2,})$/); return m ? m[1] : null; }
  function tld(name) { var t = tldOf(name); return t ? (D().tlds || []).filter(function (x) { return x.tld === t; })[0] || null : null; }
  function searchDomains() {
    var q = String(S.dq || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '').replace(/\s+/g, '');
    if (!q) return;
    var names = q.indexOf('.') > 0 ? [q] : (D().tlds || []).slice(0, 8).map(function (t) { return q + '.' + t.tld; });
    set({ dbusy: true, dresults: null, dpick: null });
    window.OnhostApi.post('/domains/check', { names: names, currency: 'CZK' }).then(function (r) {
      set({ dbusy: false, dresults: (r.data || r || []).map(function (x) { return { name: x.fqdn || x.name || x.domain, available: x.available === true, note: x.reason || '', premium: x.reason === 'premium' }; }) });
    }).catch(function (e) { set({ dbusy: false, dresults: [] }); flash(_('Ověření dostupnosti selhalo', 'Availability check failed'), (e && e.message) || ''); });
  }

  /* ── cart items for the chosen configuration ──────────────────────────── */
  function items() {
    if (!S.key) return null;
    if (S.key === 'domain') {
      if (!S.dpick) return null;
      var t = tld(S.dpick), years = t && (t.periods || []).indexOf(S.dyears) >= 0 ? S.dyears : (t ? t.default_period || 1 : 1);
      return [{ product_key: 'domain', qty: 1, config: { fqdn: S.dpick, period_years: years, action: 'register' } }];
    }
    var p = product(S.key); if (!p) return null;
    var config = {}, name = String(S.name || '').trim(), host = name.toLowerCase().replace(/[^a-z0-9.-]+/g, '-').replace(/^-+|-+$/g, '');
    if (name) config.label = name;
    if (S.region && (p.family === 'cloud' || p.family === 'data' || p.family === 'game')) config.region_code = S.region;
    if (p.family === 'game') {
      var gc = D().game_config, g = currentGame(); if (!gc || !g) return null;
      config.egg = g.key; config.options = gameValues(g);
      if (S.game.version) config.version = S.game.version;
      if (Object.keys(S.game.env || {}).length) config.environment = S.game.env;
      if (host) config.hostname = host;
      return [{ line_id: 'l1', product_key: gc.product_key, plan_key: gc.plan_key, qty: 1, period: 'month', config: config }];
    }
    if (!S.plan) return null;
    if (p.family === 'cloud') { if (S.image) config.image = S.image; if (host) config.hostname = host; if (S.ssh.trim()) config.ssh_keys = S.ssh.split(/\n+/).map(function (x) { return x.trim(); }).filter(Boolean); }
    if (p.family === 'data' && S.engine) config.engine = S.engine;
    if ((p.family === 'web' || p.family === 'managed' || p.family === 'mail') && /^[a-z0-9-]+(\.[a-z0-9-]+)+$/i.test(String(S.domain || '').trim())) config.domain = S.domain.trim().toLowerCase();
    var options = {};
    ((p.addons && p.addons.options) || []).forEach(function (o) { if (o.key in S.opts && optionQty(o, S.opts[o.key]) > 0) options[o.key] = S.opts[o.key]; else if (S.key === 'web-custom' && o.kind === 'slider') options[o.key] = snap(o, S.opts[o.key] != null ? S.opts[o.key] : (o['default'] != null ? o['default'] : o.min)); });
    if (Object.keys(options).length) config.options = options;
    var out = [{ line_id: 'l1', product_key: p.key, plan_key: S.plan, qty: 1, period: S.period === 'year' ? 'year' : 'month', config: config }];
    ((p.addons && p.addons.products) || []).forEach(function (ap, i) { if (S.addonPlans[ap.key]) out.push({ line_id: 'a' + (i + 1), product_key: ap.key, plan_key: S.addonPlans[ap.key], qty: 1, period: S.period === 'year' ? 'year' : 'month', config: { parent_line_id: 'l1' } }); });
    return out;
  }
  function missing() {
    var out = [];
    if (!S.key) return [_('vyberte službu', 'pick a service')];
    if (S.key === 'domain') { if (!S.dpick) out.push(_('vyberte volnou doménu', 'pick an available domain')); return out; }
    var p = product(S.key);
    if (p && p.family === 'game') {
      var g = currentGame();
      if (!g) return [_('vyberte hru', 'pick a game')];
      (g.inputs || []).forEach(function (i) { var v = String((S.game.env || {})[i.env] || ''); var ok = v && (!i.pattern || new RegExp('^(?:' + i.pattern + ')$').test(v)) && (i.min == null || v.length >= i.min) && (i.max == null || v.length <= i.max); if (!ok) out.push(i.label); });
      return out;
    }
    if (!S.plan) out.push(_('vyberte tarif', 'pick a plan'));
    return out;
  }

  /* ── quote ─────────────────────────────────────────────────────────────── */
  function scheduleQuote() {
    clearTimeout(quoteTimer);
    if (S.step < 2 || missing().length) { if (S.quote || S.quoteErr) set({ quote: null, quoteErr: null }); return; }
    quoteTimer = setTimeout(runQuote, 450);
  }
  function runQuote() {
    var list = items(); if (!list) return;
    if (D().needs_organization) return;
    var seq = ++quoteSeq, A = window.OnhostApi;
    S.quoteBusy = true; render();
    A.put('/cart', { items: list, commit_months: S.period === 'year' ? 12 : 1, currency: 'CZK', promo_code: S.promo.trim() || null })
      .then(function () { return A.post('/cart/quote', {}); })
      .then(function (q) { if (seq !== quoteSeq) return; set({ quote: q.data || q, quoteErr: null, quoteBusy: false }); })
      .catch(function (e) { if (seq !== quoteSeq) return; set({ quote: null, quoteErr: (e && e.message) || _('Cenu se nepodařilo spočítat.', 'The price could not be calculated.'), quoteBusy: false }); });
  }
  function minor(v) { return typeof v === 'number' ? v / 100 : (v && v.decimal ? Number(v.decimal) : Number(v) || 0); }

  function lineName(l) { // a game line names the game, not the configurator plan
    if (l.family !== 'game') return l.name;
    var grp = currentGroup(), g = currentGame();
    return _('Herní server', 'Game server') + (grp ? ' · ' + grp.label : '') + (g && g.variant && grp && grp.eggs.length > 1 ? ' · ' + g.variant : '');
  }
  function docLabel(key) {
    var map = { terms: [_('obchodními podmínkami', 'terms of service'), '/dokumenty/vop'], privacy: [_('zásadami ochrany osobních údajů', 'privacy policy'), '/dokumenty/ochrana-osobnich-udaju'], dpa: [_('smlouvou o zpracování osobních údajů', 'data processing agreement'), '/dokumenty/dpa'], sla: [_('podmínkami SLA', 'SLA'), '/dokumenty/sla'], withdrawal_waiver: [_('okamžité spuštění služby před uplynutím lhůty pro odstoupení', 'immediate start within the withdrawal period'), '/dokumenty/odstoupeni'], registrar_terms: [_('podmínkami registrace domén', 'domain registration terms'), '/dokumenty/podminky-registrace-domen'] };
    if (map[key]) return map[key];
    if (/^registry_terms_/.test(key)) { var t = (D().tlds || []).filter(function (x) { return 'registry_terms_' + x.tld === key; })[0]; return [_('pravidly registru .', 'registry rules of .') + key.replace('registry_terms_', ''), (t && t.registry_terms_url) || '/dokumenty']; }
    return [key, '/dokumenty'];
  }
  function requiredDocs() { return (S.quote && S.quote.required_documents) || ['terms', 'privacy']; }

  /* The spendable credit after an order (available + promo + credit line, holds already deducted) into the panel state. */
  function refreshCredit() {
    return window.OnhostApi.get('/wallet').then(function (r) {
      var d = r.data || r, v = d.spendable ? (d.spendable.decimal != null ? Number(d.spendable.decimal) : (d.spendable.minor || 0) / 100) : null;
      if (v == null) return;
      if (window.ONHOST_PANEL && window.ONHOST_PANEL.kpis) window.ONHOST_PANEL.kpis.credit = v;
      if (cmpRef && cmpRef.setState) cmpRef.setState({ credit: v });
      if (root) render();
    }).catch(function () { /* the balance refreshes on the next page load */ });
  }

  /* ── order ─────────────────────────────────────────────────────────────── */
  function place() {
    var miss = missing(), docs = requiredDocs();
    if (miss.length) { set({ quoteErr: _('Doplňte: ', 'Fill in: ') + miss.join(', ') }); return; }
    if (!S.quote) { runQuote(); return; }
    var unchecked = docs.filter(function (k) { return !S.consents[k]; });
    if (unchecked.length) { set({ quoteErr: _('Potvrďte prosím souhlas s dokumenty níže.', 'Please confirm the documents below.') }); return; }
    var total = minor(S.quote.total), pay = S.pay || (credit() >= total ? 'wallet' : 'bank');
    if (pay === 'wallet' && credit() < total) { set({ quoteErr: _('Kredit nestačí — zvolte převod nebo kartu, nebo kredit dobijte.', 'Credit is not enough — choose transfer or card, or top up.') }); return; }
    var person = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.name) || '', consents = {};
    docs.forEach(function (k) { var t = /^registry_terms_/.test(k) ? tld('x.' + k.replace('registry_terms_', '')) : null; consents[k] = { version: (D().consents || {})[k] || 'current', person: person, url: t ? t.registry_terms_url : undefined }; });
    var payment = pay === 'card' ? { mode: 'gateway', method: 'card', return_urls: { success: location.origin + '/panel/fakturace', cancel: location.origin + '/panel/fakturace' } } : { mode: pay };
    set({ placing: true, quoteErr: null });
    window.OnhostApi.post('/orders', { quote_id: S.quote.quote_id, consents: consents, payment: payment, source: 'panel' }, 'panel-shop:' + S.quote.quote_id + ':' + pay)
      .then(function (r) {
        var res = r.data || r, o = res.order || res, url = res.redirect_url || (res.order && res.order.redirect_url);
        if (pay === 'card' && url) { location.href = url; return; }
        set({ placing: false, done: { number: o.number || o.id || '', pay: pay, total: total, state: o.state || '' } });
        refreshCredit(); // the header, the billing tab and the next order see the balance after this order
        if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh();
      })
      .catch(function (e) { set({ placing: false, quoteErr: (e && e.message) || _('Objednávka neprošla, zkuste to znovu.', 'The order failed, please try again.') }); });
  }

  /* ── rendering ─────────────────────────────────────────────────────────── */
  var CSS = '.ohs{position:fixed;inset:0;z-index:9000;background:var(--bg,#f3f2f2);color:var(--fg,#201e1d);display:flex;flex-direction:column;font-family:var(--font-body,system-ui,sans-serif)}' +
    '.ohs *{box-sizing:border-box}.ohs h1,.ohs h2,.ohs h3{font-family:var(--font-heading,inherit);margin:0;letter-spacing:-.015em}' +
    '.ohs-top{display:flex;align-items:center;gap:18px;padding:14px 26px;border-bottom:2px solid var(--fg,#201e1d);background:var(--ink,#1a1918);color:#f3f2f2}' +
    '.ohs-top h1{font-size:18px;color:#f3f2f2}.ohs-steps{display:flex;gap:6px;margin-left:12px;flex-wrap:wrap}.ohs-steps span{font-size:12px;font-weight:700;letter-spacing:.04em;padding:6px 10px;border:1px solid rgba(243,242,242,.3);color:rgba(243,242,242,.7)}' +
    '.ohs-steps span.on{background:var(--acc,#ec3013);border-color:var(--acc,#ec3013);color:#fff}.ohs-steps span.ok{color:#f3f2f2;border-color:rgba(243,242,242,.6)}' +
    '.ohs-x{margin-left:auto;background:transparent;border:1px solid rgba(243,242,242,.4);color:#f3f2f2;font-size:14px;font-weight:700;padding:8px 12px;cursor:pointer}' +
    '.ohs-body{flex:1;overflow:auto}.ohs-grid{max-width:1320px;margin:0 auto;padding:26px 26px 60px;display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:28px;align-items:start}' +
    '.ohs-sec{margin-bottom:28px}.ohs-h{font-family:var(--font-heading,inherit);font-weight:800;font-size:13px;letter-spacing:.07em;text-transform:uppercase;margin:0 0 12px;color:var(--accDeep,#ae1800)}' +
    '.ohs-lead{font-size:14px;color:color-mix(in srgb,var(--fg,#201e1d) 62%,transparent);margin:4px 0 18px;max-width:70ch}' +
    '.ohs-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}' +
    '.ohs-card{position:relative;display:flex;flex-direction:column;gap:6px;text-align:left;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 20%,transparent);background:var(--surface,#fff);color:inherit;padding:16px;cursor:pointer;font:inherit;min-width:0}' +
    '.ohs-card:hover{border-color:var(--acc,#ec3013)}.ohs-card.on{border-color:var(--acc,#ec3013);box-shadow:inset 0 0 0 1px var(--acc,#ec3013);background:color-mix(in srgb,var(--acc,#ec3013) 6%,var(--surface,#fff))}' +
    '.ohs-card[disabled]{opacity:.5;cursor:not-allowed}.ohs-card b{font-family:var(--font-heading,inherit);font-size:17px}.ohs-card small{font-size:12.5px;line-height:1.45;color:color-mix(in srgb,var(--fg,#201e1d) 62%,transparent)}' +
    '.ohs-price{font-family:var(--font-heading,inherit);font-weight:800;font-size:20px;margin-top:auto;padding-top:6px}.ohs-price i{font-style:normal;font-size:12px;font-weight:400;color:color-mix(in srgb,var(--fg,#201e1d) 60%,transparent)}' +
    '.ohs-badge{position:absolute;top:10px;right:10px;background:var(--acc,#ec3013);color:#fff;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:3px 7px}' +
    '.ohs-feats{margin:4px 0 0;padding:0;list-style:none;font-size:12.5px;display:flex;flex-direction:column;gap:3px}.ohs-feats li:before{content:"✓ ";color:var(--accDeep,#ae1800);font-weight:800}' +
    '.ohs-chips{display:flex;flex-wrap:wrap;gap:8px}.ohs-chip{border:2px solid color-mix(in srgb,var(--fg,#201e1d) 22%,transparent);background:transparent;color:inherit;font:inherit;font-weight:700;font-size:13px;padding:8px 12px;cursor:pointer}' +
    '.ohs-chip.on{border-color:var(--acc,#ec3013);background:color-mix(in srgb,var(--acc,#ec3013) 10%,transparent)}' +
    '.ohs-field{display:block;margin-bottom:14px}.ohs-field span{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.ohs-field em{display:block;font-style:normal;font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 58%,transparent);margin-top:5px}' +
    '.ohs input[type=text],.ohs input[type=search],.ohs textarea,.ohs select{width:100%;padding:11px 12px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 30%,transparent);background:var(--surface,#fff);color:inherit;font:inherit;font-size:14px}' +
    '.ohs input:focus,.ohs textarea:focus{outline:none;border-color:var(--acc,#ec3013)}.ohs input[type=range]{width:100%;accent-color:var(--acc,#ec3013)}' +
    '.ohs-sl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 26px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 16%,transparent);background:var(--surface,#fff);padding:18px}' +
    '.ohs-sl div>p{display:flex;justify-content:space-between;margin:0 0 4px;font-size:13px;font-weight:700}.ohs-sl div>p b{color:var(--accDeep,#ae1800)}.ohs-sl em{display:flex;justify-content:space-between;font-style:normal;font-size:11px;color:color-mix(in srgb,var(--fg,#201e1d) 55%,transparent)}' +
    '.ohs-tog{display:flex;align-items:flex-start;gap:10px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 16%,transparent);background:var(--surface,#fff);padding:12px 14px;cursor:pointer}.ohs-tog.on{border-color:var(--acc,#ec3013)}' +
    '.ohs-tog input{margin-top:3px;accent-color:var(--acc,#ec3013)}.ohs-tog b{display:block;font-size:14px}.ohs-tog small{font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 60%,transparent)}.ohs-tog i{margin-left:auto;font-style:normal;font-weight:700;font-size:13px;white-space:nowrap}' +
    '.ohs-togs{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}' +
    '.ohs-sum{position:sticky;top:0;border:2px solid var(--fg,#201e1d);background:var(--surface,#fff);padding:20px}.ohs-sum h3{font-size:16px;margin-bottom:10px}' +
    '.ohs-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid color-mix(in srgb,var(--fg,#201e1d) 12%,transparent);font-size:13px}.ohs-row span:last-child{font-weight:700;text-align:right}' +
    '.ohs-total{display:flex;align-items:baseline;gap:8px;margin:14px 0 2px;flex-wrap:wrap}.ohs-total b{font-family:var(--font-heading,inherit);font-size:34px;letter-spacing:-.02em}.ohs-total i{font-style:normal;font-size:13px;color:color-mix(in srgb,var(--fg,#201e1d) 60%,transparent)}' +
    '.ohs-btn{display:block;width:100%;text-align:center;border:0;background:var(--acc,#ec3013);color:#fff;font-family:var(--font-heading,inherit);font-weight:800;font-size:15px;padding:14px 18px;cursor:pointer;margin-top:12px}.ohs-btn[disabled]{opacity:.45;cursor:not-allowed}' +
    '.ohs-btn2{display:inline-block;border:2px solid var(--fg,#201e1d);background:transparent;color:inherit;font-family:var(--font-heading,inherit);font-weight:800;font-size:14px;padding:11px 16px;cursor:pointer;text-decoration:none}' +
    '.ohs-err{color:var(--accDeep,#ae1800);font-size:13px;font-weight:600;margin:10px 0 0}.ohs-muted{font-size:12px;color:color-mix(in srgb,var(--fg,#201e1d) 58%,transparent)}' +
    '.ohs-pay{display:flex;flex-direction:column;gap:8px;margin-top:14px}.ohs-consent{display:flex;gap:8px;font-size:12.5px;line-height:1.45;margin-top:8px}.ohs-consent input{margin-top:2px;accent-color:var(--acc,#ec3013)}' +
    '.ohs-art{aspect-ratio:460/215;background-size:cover;background-position:center;margin:-16px -16px 8px;display:flex;align-items:flex-end;padding:10px;color:#fff;font-family:var(--font-heading,inherit);font-weight:800;letter-spacing:.06em;text-shadow:0 2px 0 rgba(0,0,0,.35)}' +
    '.ohs-dom{display:flex;align-items:center;gap:12px;padding:12px 14px;border:2px solid color-mix(in srgb,var(--fg,#201e1d) 16%,transparent);background:var(--surface,#fff);margin-bottom:8px}.ohs-dom.on{border-color:var(--acc,#ec3013)}' +
    '.ohs-dom b{font-size:15px}.ohs-dom .ok{color:#2f8f4e;font-weight:700;font-size:12px}.ohs-dom .no{color:var(--accDeep,#ae1800);font-weight:700;font-size:12px}.ohs-dom button{margin-left:auto}' +
    '.ohs-mbar{display:none}' +
    '.ohs-done{max-width:640px;margin:60px auto;padding:0 24px;text-align:left}.ohs-done h2{font-size:34px;margin:10px 0 12px}' +
    '@media (max-width:980px){.ohs-mbar{display:flex;align-items:center;gap:12px;padding:10px 14px;border-top:2px solid var(--fg,#201e1d);background:var(--surface,#fff)}.ohs-mbar b{font-family:var(--font-heading,inherit);font-size:20px}.ohs-mbar .ohs-btn{width:auto;margin:0 0 0 auto;padding:11px 14px}.ohs-grid{grid-template-columns:minmax(0,1fr);padding:18px 14px 40px}.ohs-sum{position:static}.ohs-sl{grid-template-columns:minmax(0,1fr)}.ohs-top{padding:12px 14px;flex-wrap:wrap}.ohs-steps{margin-left:0;order:3;width:100%}}';

  function card(on, inner, act, val, disabled, extra) { return '<button type="button" class="ohs-card' + (on ? ' on' : '') + '" data-a="' + act + '" data-v="' + esc(val) + '"' + (disabled ? ' disabled' : '') + (extra || '') + '>' + inner + '</button>'; }
  function chip(on, label, act, val) { return '<button type="button" class="ohs-chip' + (on ? ' on' : '') + '" data-a="' + act + '" data-v="' + esc(val) + '">' + esc(label) + '</button>'; }
  function fromPrice(p) {
    if (p.key === 'game' && D().game_config) { var gs = D().game_config.groups || []; return gs.length ? Math.min.apply(null, gs.map(function (g) { return g.from; })) : null; }
    var ps = (p.plans || []).map(function (x) { return x.monthly; }).filter(function (x) { return x > 0; });
    return ps.length ? Math.min.apply(null, ps) : null;
  }

  function viewPick() {
    var html = '<div class="ohs-sec"><h2 style="font-size:30px">' + _('Co chcete spustit?', 'What do you want to start?') + '</h2><p class="ohs-lead">' + _('Vyberte typ služby. Konfigurace, cena i platba proběhnou tady v panelu — služba se po zaplacení nasadí automaticky.', 'Pick a service type. Configuration, price and payment happen right here in the panel — the service is deployed automatically once paid.') + '</p></div>';
    GROUPS.forEach(function (g) {
      var rows = g[1].map(function (k) { return k === 'domain' ? ((D().tlds || []).length ? { key: 'domain', family: 'domain', name: _('Registrace domény', 'Domain registration'), description: _('ověření dostupnosti a registrace na vaše jméno · ', 'availability check and registration in your name · ') + (D().tlds || []).slice(0, 5).map(function (t) { return '.' + t.tld; }).join(' '), orderable: true, plans: [], __from: Math.min.apply(null, (D().tlds || []).map(function (t) { return Number(t.register); })), __year: true } : null) : product(k); }).filter(Boolean);
      if (!rows.length) return;
      html += '<div class="ohs-sec"><div class="ohs-h">' + esc(cs() ? g[2] : g[3]) + '</div><div class="ohs-cards">' + rows.map(function (p) {
        var from = p.__from != null ? p.__from : fromPrice(p), off = p.orderable === false;
        return card(false, '<span style="font-size:22px">' + (ICON[p.family] || '•') + '</span><b>' + esc(p.name) + '</b><small>' + esc(p.description || '') + '</small><div class="ohs-price">' + (off ? '<i>' + _('na vyžádání', 'on request') + '</i>' : (from != null ? '<i>' + _('od', 'from') + '</i> ' + gross(from) + ' <i>' + (p.__year ? _('/ rok', '/ yr') : _('/ měs.', '/ mo')) + '</i>' : '')) + '</div>', 'pick', p.key, off);
      }).join('') + '</div></div>';
    });
    return html;
  }

  function planCards(p) {
    return '<div class="ohs-sec"><div class="ohs-h">' + _('Tarif', 'Plan') + '</div>' +
      ((p.plans || []).some(function (x) { return x.yearly; }) ? '<div class="ohs-chips" style="margin-bottom:12px">' + chip(S.period === 'month', _('Měsíčně', 'Monthly'), 'period', 'month') + chip(S.period === 'year', _('Ročně · 2 měsíce zdarma', 'Yearly · 2 months free'), 'period', 'year') + '</div>' : '') +
      '<div class="ohs-cards">' + (p.plans || []).map(function (pl) {
        var price = S.period === 'year' && pl.yearly ? pl.yearly / 12 : pl.monthly;
        return card(S.plan === pl.key, (pl.highlighted ? '<span class="ohs-badge">' + _('Doporučeno', 'Recommended') + '</span>' : '') + '<b>' + esc(pl.name) + '</b><small>' + esc(pl.spec || '') + '</small><ul class="ohs-feats">' + (pl.features || []).map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul><div class="ohs-price">' + gross(price) + ' <i>' + _('/ měs. s DPH', '/ mo incl. VAT') + '</i></div>', 'plan', pl.key);
      }).join('') + '</div></div>';
  }
  function slider(o, value, act, min) {
    var mn = min != null ? min : (o.min != null ? o.min : 0), mx = o.max != null ? o.max : 100, unit = o.unit ? ' ' + o.unit : '';
    return '<div><p><span>' + esc(o.label) + '</span><b>' + esc(value + unit) + '</b></p><input type="range" min="' + mn + '" max="' + mx + '" step="' + (o.step || 1) + '" value="' + value + '" data-in="' + act + '" data-k="' + esc(o.key) + '" aria-label="' + esc(o.label) + '"/><em><span>' + mn + unit + '</span><span>' + esc(o.desc || '') + '</span><span>' + mx + unit + '</span></em></div>';
  }
  function addonsBlock(p, absolute) { // backups sold as an add-on product are not offered twice as a select
    var a = p.addons || {}, opts = a.options || [], html = '';
    var sliders = opts.filter(function (o) { return o.kind === 'slider'; }), selects = opts.filter(function (o) { return o.kind === 'select' && !(o.key === 'backup' && (a.products || []).some(function (x) { return /^backup/.test(x.key); })); }), toggles = opts.filter(function (o) { return o.kind === 'addon'; });
    if (sliders.length) html += '<div class="ohs-sec"><div class="ohs-h">' + (absolute ? _('Parametry', 'Parameters') : _('Navýšení prostředků', 'Extra resources')) + '</div><div class="ohs-sl">' + sliders.map(function (o) { var v = S.opts[o.key] != null ? S.opts[o.key] : (o['default'] != null ? o['default'] : o.min || 0); return slider(o, snap(o, v), 'opt'); }).join('') + '</div><p class="ohs-muted" style="margin-top:6px">' + sliders.map(function (o) { return esc(o.label) + ' + ' + kc(o.price) + ' / ' + esc(o.unit || _('ks', 'pc')); }).join(' · ') + _(' · měsíčně bez DPH', ' · monthly excl. VAT') + '</p></div>';
    selects.forEach(function (o) { var cur = S.opts[o.key] != null ? S.opts[o.key] : ((o.choices || [])[0] || {}).key; html += '<div class="ohs-sec"><div class="ohs-h">' + esc(o.label) + '</div><div class="ohs-chips">' + (o.choices || []).map(function (c) { return chip(cur === c.key, c.label + (c.units ? ' · + ' + kc(c.units * o.price) : ''), 'optsel:' + o.key, c.key); }).join('') + '</div></div>'; });
    var prods = a.products || [];
    if (toggles.length || prods.length) {
      html += '<div class="ohs-sec"><div class="ohs-h">' + _('Doplňkové služby', 'Add-ons') + '</div><div class="ohs-togs">' + toggles.map(function (o) { var on = !!S.opts[o.key]; return '<label class="ohs-tog' + (on ? ' on' : '') + '"><input type="checkbox" data-in="optbool" data-k="' + esc(o.key) + '"' + (on ? ' checked' : '') + '/><span><b>' + esc(o.label) + '</b><small>' + esc(o.desc || '') + '</small></span><i>+ ' + kc(o.price) + '</i></label>'; }).join('') +
        prods.map(function (ap) { return (ap.plans || []).map(function (pl) { var on = S.addonPlans[ap.key] === pl.key; return '<label class="ohs-tog' + (on ? ' on' : '') + '"><input type="checkbox" data-in="addonplan" data-k="' + esc(ap.key) + '" data-v="' + esc(pl.key) + '"' + (on ? ' checked' : '') + '/><span><b>' + esc(ap.name + ((ap.plans || []).length > 1 ? ' · ' + pl.name : '')) + '</b><small>' + esc(pl.desc || ap.desc || '') + '</small></span><i>+ ' + kc(pl.price) + (pl.unit_year ? _(' / rok', ' / yr') : '') + '</i></label>'; }).join(''); }).join('') + '</div><p class="ohs-muted" style="margin-top:6px">' + _('Ceny doplňků jsou měsíčně bez DPH, pokud není uvedeno jinak.', 'Add-on prices are monthly excl. VAT unless stated otherwise.') + '</p></div>';
    }
    return html;
  }
  function regionBlock() {
    var r = D().regions || []; if (r.length < 2) return '';
    return '<div class="ohs-sec"><div class="ohs-h">' + _('Lokalita', 'Location') + '</div><div class="ohs-chips">' + r.map(function (x) { return chip(S.region === x.code, x.code.toUpperCase() + (x.name ? ' · ' + x.name : ''), 'region', x.code); }).join('') + '</div></div>';
  }
  function field(label, key, value, ph, hint, extra) { return '<label class="ohs-field"><span>' + esc(label) + '</span><input type="text" data-in="field" data-k="' + key + '" value="' + esc(value) + '" placeholder="' + esc(ph || '') + '"' + (extra || '') + '/>' + (hint ? '<em>' + esc(hint) + '</em>' : '') + '</label>'; }
  function ownedDomains() {
    var out = [], groups = D().services || {};
    Object.keys(groups).forEach(function (k) { (groups[k] || []).forEach(function (s) { if (s.type === 'domain' && s.fqdn) out.push(s.fqdn); }); });
    return out;
  }

  function viewConfigure() {
    var p = S.key === 'domain' ? null : product(S.key), html = '<div class="ohs-sec"><button type="button" class="ohs-btn2" data-a="back" data-v="0" style="padding:7px 12px;font-size:12px">← ' + _('Jiná služba', 'Another service') + '</button></div>';
    if (S.key === 'domain') {
      html += '<div class="ohs-sec"><h2 style="font-size:28px">' + _('Registrace domény', 'Domain registration') + '</h2><p class="ohs-lead">' + _('Napište název bez koncovky a ověříme všechny nabízené koncovky, nebo celou doménu včetně koncovky.', 'Type a name without the extension to check every offered extension, or the whole domain.') + '</p>' +
        '<div style="display:flex;gap:8px"><input type="search" data-in="field" data-k="dq" value="' + esc(S.dq) + '" placeholder="' + _('mojefirma nebo mojefirma.cz', 'mycompany or mycompany.cz') + '" autocomplete="off"/><button type="button" class="ohs-btn" data-a="dsearch" style="width:auto;margin:0;white-space:nowrap">' + (S.dbusy ? _('Ověřuji…', 'Checking…') : _('Ověřit dostupnost', 'Check availability')) + '</button></div></div>';
      if (S.dresults) {
        html += '<div class="ohs-sec">' + (S.dresults.length ? S.dresults.map(function (r) { var t = tld(r.name); return '<div class="ohs-dom' + (S.dpick === r.name ? ' on' : '') + '"><div><b>' + esc(r.name) + '</b><div class="' + (r.available ? 'ok' : 'no') + '">' + (r.available ? _('volná', 'available') : (r.premium ? _('prémiové jméno — napište podpoře', 'premium name — write to support') : _('obsazená', 'taken'))) + '</div></div>' + (r.available && t ? '<span class="ohs-muted">' + gross(t.register) + _(' / rok · obnova ', ' / yr · renewal ') + gross(t.renew) + '</span><button type="button" class="' + (S.dpick === r.name ? 'ohs-chip on' : 'ohs-chip') + '" data-a="dpick" data-v="' + esc(r.name) + '">' + (S.dpick === r.name ? _('Vybráno ✓', 'Selected ✓') : _('Vybrat', 'Select')) + '</button>' : '') + '</div>'; }).join('') : '<p class="ohs-muted">' + _('Nic nenalezeno.', 'Nothing found.') + '</p>') + '</div>';
      }
      var pt = tld(S.dpick);
      if (pt) html += '<div class="ohs-sec"><div class="ohs-h">' + _('Doba registrace', 'Registration term') + '</div><div class="ohs-chips">' + (pt.periods || [1]).map(function (y) { return chip(S.dyears === y, y + ' ' + (y === 1 ? _('rok', 'year') : (y < 5 ? _('roky', 'years') : _('let', 'years'))) + ' · ' + gross(pt.register * y), 'dyears', y); }).join('') + '</div><p class="ohs-muted" style="margin-top:8px">' + _('Držitelem domény bude vaše organizace. Obnovu hlídáme za vás.', 'Your organisation will hold the domain. We take care of the renewal.') + '</p></div>';
      return html;
    }
    if (!p) return html;
    html += '<div class="ohs-sec"><h2 style="font-size:28px">' + (ICON[p.family] || '') + ' ' + esc(p.name) + '</h2><p class="ohs-lead">' + esc(p.description || '') + '</p></div>';
    if (p.family === 'game') return html + viewGame();
    if (S.key === 'web-custom') {
      html += addonsBlock(p, true);
    } else {
      html += planCards(p);
    }
    if (p.family === 'cloud') {
      if ((p.images || []).length) html += '<div class="ohs-sec"><div class="ohs-h">' + _('Operační systém', 'Operating system') + '</div><div class="ohs-chips">' + p.images.map(function (im) { return chip(S.image === im, im.replace(/-/g, ' ').replace(/^\w/, function (c) { return c.toUpperCase(); }), 'image', im); }).join('') + '</div></div>';
      html += regionBlock();
      html += '<div class="ohs-sec"><div class="ohs-h">' + _('Přístup', 'Access') + '</div>' + field(_('Hostname', 'Hostname'), 'name', S.name, 'app-prod-1', _('objeví se v panelu i v reverzním DNS', 'shown in the panel and in reverse DNS')) +
        '<label class="ohs-field"><span>' + _('Veřejný SSH klíč (volitelné)', 'Public SSH key (optional)') + '</span><textarea rows="3" data-in="field" data-k="ssh" placeholder="ssh-ed25519 AAAA… user@pc">' + esc(S.ssh) + '</textarea><em>' + _('Bez klíče pošleme heslo roota do e-mailu a panelu. Více klíčů oddělte novým řádkem.', 'Without a key the root password goes to your e-mail and panel. One key per line.') + '</em></label></div>';
      html += addonsBlock(p, false);
    } else if (p.family === 'data') {
      if ((p.engines || []).length) html += '<div class="ohs-sec"><div class="ohs-h">' + _('Databázový engine', 'Database engine') + '</div><div class="ohs-chips">' + p.engines.map(function (en) { return chip(S.engine === en, en.replace('postgresql', 'PostgreSQL').replace('mariadb', 'MariaDB').replace('redis', 'Redis').replace('-', ' '), 'engine', en); }).join('') + '</div></div>';
      html += regionBlock() + '<div class="ohs-sec">' + field(_('Název instance', 'Instance name'), 'name', S.name, 'db-prod', '') + '</div>' + addonsBlock(p, false);
    } else {
      var owned = ownedDomains();
      html += '<div class="ohs-sec"><div class="ohs-h">' + (p.family === 'mail' ? _('Doména pro e-mail', 'Mail domain') : _('Doména webu', 'Site domain')) + '</div>' +
        field(_('Doména', 'Domain'), 'domain', S.domain, 'mojefirma.cz', p.family === 'mail' ? _('doména, na které budou schránky', 'the domain the mailboxes use') : _('volitelné · bez domény dostane web dočasnou adresu, doménu připojíte později', 'optional · without a domain the site gets a temporary address, attach one later'), ' list="ohs-owned"') +
        (owned.length ? '<datalist id="ohs-owned">' + owned.map(function (d) { return '<option value="' + esc(d) + '">'; }).join('') + '</datalist><div class="ohs-chips">' + owned.slice(0, 6).map(function (d) { return chip(S.domain === d, d, 'domain', d); }).join('') + '</div>' : '') +
        '<p class="ohs-muted" style="margin-top:8px">' + _('Doménu ještě nemáte? ', 'No domain yet? ') + '<a href="#" data-a="pick" data-v="domain">' + _('Zaregistrujte ji zvlášť', 'Register one separately') + '</a>.</p></div>';
      if (S.key !== 'web-custom') html += addonsBlock(p, false);
    }
    return html;
  }

  function viewGame() {
    var gc = D().game_config, html = '';
    if (!gc || !(gc.groups || []).length) return '<p class="ohs-muted">' + _('Herní servery teď nelze objednat.', 'Game servers cannot be ordered right now.') + '</p>';
    var grp = currentGroup(), cats = [{ key: 'all', label: _('Všechny', 'All') }].concat(gc.categories || []);
    if (grp && !S.game.browse) { // the chosen game stays as one compact card; the full list comes back on demand
      var art0 = grp.art ? 'background-image:url(' + esc(grp.art) + ')' : 'background:' + (TILE_ART[grp.key] || 'linear-gradient(135deg,#1a1918,#4a4644)');
      html += '<div class="ohs-sec"><div class="ohs-h">1 · ' + _('Hra', 'Game') + '</div><div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px 16px;border:2px solid var(--acc,#ec3013);background:var(--surface,#fff);padding:10px"><div style="flex:0 0 140px;aspect-ratio:460/215;background-size:cover;background-position:center;' + art0 + '"></div><div style="flex:1;min-width:160px"><b style="font-family:var(--font-heading,inherit);font-size:18px">' + esc(grp.label) + '</b><div class="ohs-muted">' + esc(grp.note || '') + '</div></div><button type="button" class="ohs-chip" data-a="gbrowse">' + _('Změnit hru', 'Change game') + '</button></div></div>';
    } else
    html += '<div class="ohs-sec"><div class="ohs-h">1 · ' + _('Hra', 'Game') + '</div><div class="ohs-chips" style="margin-bottom:12px">' + cats.map(function (c) { return chip(S.game.cat === c.key, c.label, 'gcat', c.key); }).join('') + '</div><div class="ohs-cards">' +
      (gc.groups || []).filter(function (x) { return S.game.cat === 'all' || x.category === S.game.cat; }).map(function (x) {
        var art = x.art ? 'background-image:url(' + esc(x.art) + ')' : 'background:' + (TILE_ART[x.key] || 'linear-gradient(135deg,#1a1918,#4a4644)');
        return card(grp && grp.key === x.key, '<div class="ohs-art" style="' + art + '">' + (x.art ? '' : esc(x.label.replace('Minecraft ', '').toUpperCase())) + '</div><b>' + esc(x.label) + '</b><small>' + esc(x.note || '') + '</small><div class="ohs-price"><i>' + _('od', 'from') + '</i> ' + gross(x.from) + ' <i>' + _('/ měs.', '/ mo') + '</i></div>', 'ggroup', x.key);
      }).join('') + '</div></div>';
    var g = currentGame();
    if (!grp || !g) return html;
    var n = 2;
    if (grp.eggs.length > 1) {
      html += '<div class="ohs-sec"><div class="ohs-h">' + (n++) + ' · ' + _('Typ serveru', 'Server type') + '</div><div class="ohs-cards">' + grp.eggs.map(function (k) { var x = (gc.games || []).filter(function (y) { return y.key === k; })[0]; return x ? card(g.key === x.key, '<b>' + esc(x.variant || x.label) + '</b><small>' + esc(x.variant_note || x.note || '') + '</small><div class="ohs-price"><i>' + _('od', 'from') + '</i> ' + gross(x.from) + '</div>', 'gegg', x.key) : ''; }).join('') + '</div></div>';
    }
    var vals = gameValues(g);
    html += '<div class="ohs-sec"><div class="ohs-h">' + (n++) + ' · ' + _('Výkon serveru', 'Server resources') + '</div><div class="ohs-sl">' + (gc.options || []).map(function (o) { return slider(o, vals[o.key], 'gopt', floorOf(g, o)); }).join('') + '</div>' +
      (g.slot_mb ? '<p class="ohs-muted" style="margin-top:6px">' + _('Orientačně ≈ ', 'About ≈ ') + Math.floor((vals.ram_gb || 1) * 1024 / g.slot_mb) + _(' hráčů při této RAM', ' players with this RAM') + '</p>' : '') + '</div>';
    if ((g.versions || []).length) html += '<div class="ohs-sec"><div class="ohs-h">' + _('Verze hry', 'Game version') + '</div><div class="ohs-chips">' + [chip(!S.game.version, _('Nejnovější', 'Latest'), 'gver', '')].concat(g.versions.map(function (v) { return chip(S.game.version === v, v, 'gver', v); })).join('') + '</div></div>';
    if ((g.inputs || []).length) html += '<div class="ohs-sec"><div class="ohs-h">' + _('Údaje, které hra potřebuje', 'What the game needs') + '</div>' + g.inputs.map(function (i) { return '<label class="ohs-field"><span>' + esc(i.label) + '</span><input type="text" data-in="genv" data-k="' + esc(i.env) + '" value="' + esc((S.game.env || {})[i.env] || '') + '" autocomplete="off" spellcheck="false" style="font-family:ui-monospace,monospace"' + (i.max ? ' maxlength="' + i.max + '"' : '') + '/><em>' + esc(i.hint || '') + (i.help_url ? ' <a href="' + esc(i.help_url) + '" target="_blank" rel="noopener noreferrer">' + _('Kde ho získat ↗', 'Where to get it ↗') + '</a>' : '') + '</em></label>'; }).join('') + '</div>';
    html += regionBlock() + '<div class="ohs-sec">' + field(_('Název serveru', 'Server name'), 'name', S.name, 'mc-parta', _('uvidíte ho v seznamu služeb', 'shown in your service list')) + '</div>';
    return html;
  }

  function viewSummary() {
    var html = '<aside class="ohs-sum"><h3>' + _('Souhrn objednávky', 'Order summary') + '</h3>';
    if (!S.key) return html + '<p class="ohs-muted">' + _('Vyberte službu vlevo.', 'Pick a service on the left.') + '</p></aside>';
    var q = S.quote, miss = missing();
    if (q && (q.lines || []).length) {
      html += (q.lines || []).map(function (l) { return '<div class="ohs-row"><span>' + esc(lineName(l)) + (l.period === 'year' ? _(' · ročně', ' · yearly') : '') + '</span><span>' + kc(minor(l.net)) + '</span></div>' + ((l.config && l.config.options_priced) || []).map(function (o) { return '<div class="ohs-row ohs-muted" style="padding-left:10px"><span>+ ' + esc(o.label) + ' × ' + o.qty + '</span><span>' + kc(minor(o.net)) + '</span></div>'; }).join(''); }).join('') +
        (minor(q.discount) ? '<div class="ohs-row"><span>' + _('Sleva', 'Discount') + '</span><span>− ' + kc(minor(q.discount)) + '</span></div>' : '') +
        '<div class="ohs-row"><span>' + _('Bez DPH', 'Excl. VAT') + '</span><span>' + kc(minor(q.subtotal) - minor(q.discount)) + '</span></div><div class="ohs-row"><span>' + _('DPH', 'VAT') + '</span><span>' + kc(minor(q.tax)) + '</span></div>' +
        '<div class="ohs-total"><b>' + kc(minor(q.total)) + '</b><i>' + _('k úhradě', 'to pay') + '</i></div>' + (q.renewal_total ? '<div class="ohs-muted">' + _('Obnova ', 'Renewal ') + kc(minor(q.renewal_total)) + (S.period === 'year' ? _(' ročně', ' yearly') : _(' měsíčně', ' monthly')) + _(' bez DPH', ' excl. VAT') + '</div>' : '');
    } else {
      html += '<div class="ohs-total"><b>' + gross(estimate()) + '</b><i>' + (S.key === 'domain' ? _('s DPH', 'incl. VAT') : _('/ měs. s DPH', '/ mo incl. VAT')) + '</i></div><div class="ohs-muted">' + (S.quoteBusy ? _('Počítám přesnou cenu…', 'Calculating the exact price…') : (miss.length ? _('Doplňte: ', 'Missing: ') + esc(miss.join(', ')) : _('odhad · přesnou cenu spočítáme v dalším kroku', 'estimate · the exact price is calculated in the next step'))) + '</div>';
    }
    if (S.step === 1) {
      html += '<button type="button" class="ohs-btn" data-a="next"' + (miss.length ? ' disabled' : '') + '>' + _('Pokračovat ke shrnutí →', 'Continue to summary →') + '</button>';
    } else if (S.step === 2 && D().needs_organization) {
      html += profileForm();
    } else if (S.step === 2) {
      var total = q ? minor(q.total) : 0, cr = credit();
      html += '<div class="ohs-h" style="margin-top:18px">' + _('Platba', 'Payment') + '</div><div class="ohs-pay">' +
        [['wallet', _('Z kreditu', 'From credit'), _('zůstatek ', 'balance ') + kc(cr) + (q && cr < total ? _(' — nestačí', ' — not enough') : _(' · spuštění ihned', ' · starts at once')), q && cr < total],
          ['bank', _('Bankovní převod', 'Bank transfer'), _('zálohová faktura · spustíme po připsání platby', 'proforma · starts once paid'), false],
          ['card', _('Platební karta', 'Payment card'), _('platební brána · spuštění ihned', 'payment gateway · starts at once'), false]].map(function (m) {
          var on = (S.pay || (q && cr >= total ? 'wallet' : 'bank')) === m[0];
          return '<label class="ohs-tog' + (on ? ' on' : '') + '" style="' + (m[3] ? 'opacity:.5' : '') + '"><input type="radio" name="ohs-pay" data-in="pay" value="' + m[0] + '"' + (on ? ' checked' : '') + (m[3] ? ' disabled' : '') + '/><span><b>' + m[1] + '</b><small>' + m[2] + '</small></span></label>';
        }).join('') + '</div>' +
        '<label class="ohs-field" style="margin-top:12px"><span>' + _('Slevový kód', 'Promo code') + '</span><input type="text" data-in="field" data-k="promo" value="' + esc(S.promo) + '" placeholder="' + _('volitelné', 'optional') + '"/></label>' +
        requiredDocs().map(function (k) { var d = docLabel(k); return '<label class="ohs-consent"><input type="checkbox" data-in="consent" data-k="' + esc(k) + '"' + (S.consents[k] ? ' checked' : '') + '/><span>' + (k === 'withdrawal_waiver' ? _('Žádám o ', 'I request ') + '<a href="' + esc(d[1]) + '" target="_blank" rel="noopener">' + esc(d[0]) + '</a>' : _(/^[zsšž]/i.test(d[0]) ? 'Souhlasím se ' : 'Souhlasím s ', 'I agree to the ') + '<a href="' + esc(d[1]) + '" target="_blank" rel="noopener">' + esc(d[0]) + '</a>') + '</span></label>'; }).join('') +
        '<button type="button" class="ohs-btn" data-a="order"' + (S.placing || !q ? ' disabled' : '') + '>' + (S.placing ? _('Odesílám…', 'Placing…') : _('Závazně objednat', 'Place the order')) + '</button>' +
        '<button type="button" class="ohs-btn2" data-a="back" data-v="1" style="width:100%;margin-top:8px">' + _('← Upravit konfiguraci', '← Edit the configuration') + '</button>';
    }
    if (S.quoteErr) html += '<p class="ohs-err">' + esc(S.quoteErr) + '</p>';
    return html + '</aside>';
  }

  /* A signed-in account without a customer profile (staff, an invited user who left their organisation): the order needs
   * one — the name and address the invoice carries. POST /v1/organizations makes the user its owner. */
  function profileForm() {
    var p = S.profile || {}, company = p.type === 'company';
    var inp = function (k, label, ph, req) { return '<label class="ohs-field"><span>' + esc(label) + (req ? ' *' : '') + '</span><input type="text" data-in="profile" data-k="' + k + '" value="' + esc(p[k] || '') + '" placeholder="' + esc(ph || '') + '"/></label>'; };
    return '<div class="ohs-h" style="margin-top:18px">' + _('Fakturační údaje', 'Billing details') + '</div><p class="ohs-muted" style="margin:0 0 10px">' + _('Tento účet zatím nemá zákaznický profil. Údaje uvidíte na faktuře a později je změníte v Nastavení.', 'This account has no customer profile yet. The details go on the invoice; change them later in Settings.') + '</p>' +
      '<div class="ohs-chips" style="margin-bottom:12px">' + chip(!company, _('Soukromá osoba', 'Private person'), 'ptype', 'person') + chip(company, _('Firma / OSVČ', 'Company'), 'ptype', 'company') + '</div>' +
      inp('name', company ? _('Název firmy', 'Company name') : _('Jméno a příjmení', 'Full name'), company ? 'Firma s.r.o.' : 'Jan Novák', true) +
      (company ? inp('ico', _('IČO', 'Company ID'), '12345678', true) + inp('dic', _('DIČ', 'VAT ID'), 'CZ12345678', false) : '') +
      inp('street', _('Ulice a číslo', 'Street'), 'Vodičkova 12', true) + inp('city', _('Město', 'City'), 'Praha', true) + inp('postal_code', _('PSČ', 'Postcode'), '110 00', true) +
      inp('billing_email', _('E-mail pro faktury', 'Billing e-mail'), (window.ONHOST && window.ONHOST.user && window.ONHOST.user.email) || '', false) +
      '<button type="button" class="ohs-btn" data-a="profile"' + (S.placing ? ' disabled' : '') + '>' + (S.placing ? _('Ukládám…', 'Saving…') : _('Uložit a pokračovat k platbě', 'Save and continue to payment')) + '</button>';
  }
  function saveProfile() {
    var p = Object.assign({ type: 'person' }, S.profile || {}), miss = ['name', 'street', 'city', 'postal_code'].concat(p.type === 'company' ? ['ico'] : []).filter(function (k) { return !String(p[k] || '').trim(); });
    if (miss.length) { set({ quoteErr: _('Vyplňte povinné údaje profilu.', 'Fill in the required profile fields.') }); return; }
    set({ placing: true, quoteErr: null });
    var body = { name: p.name.trim(), type: p.type, street: p.street.trim(), city: p.city.trim(), postal_code: p.postal_code.trim(), country: 'CZ' };
    ['ico', 'dic', 'billing_email'].forEach(function (k) { if (String(p[k] || '').trim()) body[k] = String(p[k]).trim(); });
    window.OnhostApi.post('/organizations', body, window.OnhostApi.key()).then(function (r) {
      var d = r.data || r, id = d.organization_id || (d.organization && d.organization.id);
      if (window.ONHOST && window.ONHOST.user) window.ONHOST.user.organization = { id: id, name: body.name }; // the API calls carry the new organisation from now on
      if (window.ONHOST_PANEL) { delete window.ONHOST_PANEL.catalog; }
      return ensureData().then(function () { S.placing = false; S.quote = null; render(); runQuote(); });
    }).catch(function (e) { set({ placing: false, quoteErr: (e && e.message) || _('Profil se nepodařilo uložit.', 'The profile could not be saved.') }); });
  }

  function viewReview() {
    var p = S.key === 'domain' ? null : product(S.key), rows = [];
    if (S.key === 'domain') rows.push([_('Doména', 'Domain'), S.dpick], [_('Doba', 'Term'), S.dyears + ' ' + _('let/roky', 'years')]);
    else if (p && p.family === 'game') { var g = currentGame(), grp = currentGroup(), v = g ? gameValues(g) : {}; rows.push([_('Hra', 'Game'), (grp ? grp.label : '') + (g && g.variant && grp && grp.eggs.length > 1 ? ' · ' + g.variant : '')], [_('Výkon', 'Resources'), v.ram_gb + ' GB RAM · ' + v.vcpu + ' vCPU · ' + v.nvme_gb + ' GB NVMe'], [_('Zálohy / porty / DB', 'Backups / ports / DB'), v.backups + ' / ' + v.allocations + ' / ' + v.databases]); if (S.game.version) rows.push([_('Verze', 'Version'), S.game.version]); }
    else if (p) { var pl = (p.plans || []).filter(function (x) { return x.key === S.plan; })[0]; rows.push([_('Služba', 'Service'), p.name + (pl && S.key !== 'web-custom' ? ' · ' + pl.name : '')], [_('Účtování', 'Billing'), S.period === 'year' ? _('ročně', 'yearly') : _('měsíčně', 'monthly')]); if (S.domain) rows.push([_('Doména', 'Domain'), S.domain]); if (S.image) rows.push([_('Systém', 'System'), S.image]); if (S.engine && p.family === 'data') rows.push(['Engine', S.engine]); }
    if (S.name) rows.push([_('Název', 'Name'), S.name]);
    if (S.region && p && /cloud|data|game/.test(p.family)) rows.push([_('Lokalita', 'Location'), S.region.toUpperCase()]);
    return '<div class="ohs-sec"><h2 style="font-size:28px">' + _('Zkontrolujte objednávku', 'Review your order') + '</h2><p class="ohs-lead">' + _('Cenu spočítal server včetně DPH a slev. Po zaplacení se služba nasadí automaticky a uvidíte ji v sekci Služby.', 'The server priced the order including VAT and discounts. Once paid, the service deploys automatically and appears under Services.') + '</p></div>' +
      '<div class="ohs-sec" style="border:2px solid color-mix(in srgb,var(--fg,#201e1d) 16%,transparent);background:var(--surface,#fff);padding:6px 18px">' + rows.map(function (r) { return '<div class="ohs-row"><span>' + esc(r[0]) + '</span><span>' + esc(r[1]) + '</span></div>'; }).join('') + '</div>';
  }

  function viewDone() {
    var d = S.done;
    return '<div class="ohs-done"><div class="ohs-h">' + _('Objednávka přijata', 'Order received') + '</div><h2>' + _('Děkujeme', 'Thank you') + (d.number ? ' · ' + esc(d.number) : '') + '</h2><p class="ohs-lead" style="font-size:16px">' +
      (d.pay === 'wallet' ? _('Uhrazeno z kreditu (' + kc(d.total) + '). Službu právě nasazujeme — průběh uvidíte v sekci Služby, potvrzení dorazí e-mailem.', 'Paid from credit (' + kc(d.total) + '). The service is being deployed — follow it under Services; a confirmation is on its way by e-mail.')
        : _('Zálohovou fakturu na ' + kc(d.total) + ' najdete v sekci Fakturace. Službu spustíme hned po připsání platby — rychlejší je zaplatit z kreditu.', 'The proforma for ' + kc(d.total) + ' is in Billing. The service starts as soon as the payment arrives — paying from credit is faster.')) +
      '</p><div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px"><button type="button" class="ohs-btn" style="width:auto;margin:0" data-a="goto" data-v="' + (d.pay === 'wallet' ? 'services' : 'billing') + '">' + (d.pay === 'wallet' ? _('Přejít na služby', 'Go to services') : _('Otevřít fakturaci', 'Open billing')) + '</button><button type="button" class="ohs-btn2" data-a="again">' + _('Objednat další službu', 'Order another service') + '</button><button type="button" class="ohs-btn2" data-a="close">' + _('Zavřít', 'Close') + '</button></div></div>';
  }

  function render() {
    if (!root) return;
    var steps = [_('1 · Služba', '1 · Service'), _('2 · Konfigurace', '2 · Configure'), _('3 · Shrnutí a platba', '3 · Review and pay')];
    var focus = document.activeElement && root.contains(document.activeElement) && document.activeElement.getAttribute('data-k') ? { k: document.activeElement.getAttribute('data-k'), s: document.activeElement.selectionStart } : null;
    var scroll = root.querySelector('.ohs-body') ? root.querySelector('.ohs-body').scrollTop : 0;
    var body = S.done ? viewDone() : '<div class="ohs-grid"><div>' + (S.step === 0 ? viewPick() : S.step === 1 ? viewConfigure() : viewReview()) + '</div>' + viewSummary() + '</div>';
    root.innerHTML = '<div class="ohs-top"><h1>' + _('Nová objednávka', 'New order') + '</h1><div class="ohs-steps">' + steps.map(function (s, i) { return '<span class="' + (S.done ? 'ok' : (i === S.step ? 'on' : (i < S.step ? 'ok' : ''))) + '">' + s + '</span>'; }).join('') + '</div><button type="button" class="ohs-x" data-a="close" aria-label="' + _('Zavřít', 'Close') + '">✕ ' + _('Zavřít', 'Close') + '</button></div><div class="ohs-body">' + body + '</div>' + (!S.done && S.step > 0 ? '<div class="ohs-mbar"><div><b>' + (S.quote ? kc(minor(S.quote.total)) : gross(estimate())) + '</b><div class="ohs-muted">' + (S.quote ? _('k úhradě s DPH', 'to pay incl. VAT') : _('odhad / měs. s DPH', 'estimate / mo incl. VAT')) + '</div></div>' + (S.step === 1 ? '<button type="button" class="ohs-btn" data-a="next"' + (missing().length ? ' disabled' : '') + '>' + _('Pokračovat →', 'Continue →') + '</button>' : '<button type="button" class="ohs-btn" data-a="tosum">' + _('K platbě ↓', 'To payment ↓') + '</button>') + '</div>' : '');
    root.querySelector('.ohs-body').scrollTop = scroll;
    if (focus) { var el = root.querySelector('[data-k="' + focus.k + '"]'); if (el && el.type !== 'checkbox' && el.type !== 'range') { el.focus(); try { el.setSelectionRange(focus.s, focus.s); } catch (e) { /* not a text input */ } } }
  }

  function onClick(e) {
    var t = e.target.closest ? e.target.closest('[data-a]') : null;
    if (!t || !root.contains(t) || t.disabled) return;
    e.preventDefault();
    var a = t.getAttribute('data-a'), v = t.getAttribute('data-v');
    var top = function () { var b = root.querySelector('.ohs-body'); if (b) b.scrollTop = 0; };
    if (a === 'close') return close();
    if (a === 'reloadpage') { location.reload(); return; }
    if (a === 'pick') { S = fresh(v); render(); top(); return; }
    if (a === 'back') { set({ step: Number(v), quoteErr: null }); top(); return; }
    if (a === 'next') { set({ step: 2, quote: null, consents: {} }); top(); runQuote(); return; }
    if (a === 'plan') return set({ plan: v }, true);
    if (a === 'period') return set({ period: v }, true);
    if (a === 'region') return set({ region: v }, true);
    if (a === 'image') return set({ image: v }, true);
    if (a === 'engine') return set({ engine: v }, true);
    if (a === 'domain') return set({ domain: v }, true);
    if (a.indexOf('optsel:') === 0) { var o = {}; o[a.slice(7)] = v; return set({ opts: Object.assign({}, S.opts, o) }, true); }
    if (a === 'gcat') return set({ game: Object.assign({}, S.game, { cat: v }) });
    if (a === 'gbrowse') return set({ game: Object.assign({}, S.game, { browse: true }) });
    if (a === 'ggroup') { var gc = D().game_config, grp = (gc.groups || []).filter(function (x) { return x.key === v; })[0]; return set({ game: Object.assign({}, S.game, { group: v, egg: grp ? grp.eggs[0] : null, opts: {}, version: null, env: {}, browse: false }) }, true); }
    if (a === 'gegg') return set({ game: Object.assign({}, S.game, { egg: v, opts: {}, version: null, env: {} }) }, true);
    if (a === 'gver') return set({ game: Object.assign({}, S.game, { version: v || null }) }, true);
    if (a === 'dsearch') return searchDomains();
    if (a === 'dpick') { var pt = tld(v); return set({ dpick: v, dyears: pt ? (pt.default_period || 1) : 1 }, true); }
    if (a === 'dyears') return set({ dyears: Number(v) }, true);
    if (a === 'order') return place();
    if (a === 'profile') return saveProfile();
    if (a === 'ptype') { S.profile = Object.assign({}, S.profile || {}, { type: v }); render(); return; }
    if (a === 'tosum') { var sum = root.querySelector('.ohs-sum'); if (sum) sum.scrollIntoView({ behavior: 'smooth' }); return; }
    if (a === 'again') { S = fresh(null); render(); return; }
    if (a === 'goto') { close(); if (cmpRef) cmpRef.setState(v === 'billing' ? { tab: 'billing' } : { tab: 'svcdesk' }); if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh(); setTimeout(function () { location.reload(); }, 400); }
  }
  function onInput(e) {
    var t = e.target, k = t.getAttribute('data-k'), kind = t.getAttribute('data-in');
    if (!kind || !root.contains(t)) return;
    if (kind === 'field') { var p = {}; p[k] = t.value; S = Object.assign({}, S, p); if (k === 'dq') return; if (e.type === 'change' || k === 'promo' || k === 'domain' || k === 'name') { if (e.type === 'change') { render(); scheduleQuote(); } } return; }
    if (kind === 'opt') { var o = {}; o[k] = Number(t.value); S = Object.assign({}, S, { opts: Object.assign({}, S.opts, o) }); if (e.type === 'change') { render(); scheduleQuote(); } else renderLight(t); return; }
    if (kind === 'gopt') { var go = {}; go[k] = Number(t.value); S = Object.assign({}, S, { game: Object.assign({}, S.game, { opts: Object.assign({}, gameValues(currentGame()), go) }) }); if (e.type === 'change') { render(); scheduleQuote(); } else renderLight(t); return; }
    if (kind === 'genv') { var ge = {}; ge[k] = String(t.value || '').trim(); S = Object.assign({}, S, { game: Object.assign({}, S.game, { env: Object.assign({}, S.game.env, ge) }) }); if (e.type === 'change') { render(); scheduleQuote(); } return; }
    if (kind === 'optbool' && e.type === 'change') { var ob = {}; ob[k] = t.checked; return set({ opts: Object.assign({}, S.opts, ob) }, true); }
    if (kind === 'addonplan' && e.type === 'change') { var ap = Object.assign({}, S.addonPlans); if (t.checked) ap[k] = t.getAttribute('data-v'); else delete ap[k]; return set({ addonPlans: ap }, true); }
    if (kind === 'pay' && e.type === 'change') return set({ pay: t.value });
    if (kind === 'profile') { S.profile = Object.assign({}, S.profile || {}); S.profile[k] = t.value; return; }
    if (kind === 'consent' && e.type === 'change') { var c = Object.assign({}, S.consents); c[k] = t.checked; return set({ consents: c, quoteErr: null }); }
  }
  function renderLight(t) { var b = t.parentNode && t.parentNode.querySelector('p b'); if (b) { var unit = (b.textContent.match(/\s\D+$/) || [''])[0]; b.textContent = t.value + unit; } }
  function onKey(e) {
    if (!root) return;
    if (e.key === 'Escape' && !S.placing) { close(); return; }
    if (e.key === 'Enter' && e.target && e.target.getAttribute && e.target.getAttribute('data-k') === 'dq') { e.preventDefault(); S.dq = e.target.value; searchDomains(); }
  }

  /* The panel data (window.ONHOST_PANEL) arrives with the page; when it is missing or failed (a slow or broken load) the
   * centre fetches it again instead of ever falling back to the prototype's wizard. */
  var loadingData = null;
  function ensureData() {
    if (D().catalog) return Promise.resolve(true);
    if (loadingData) return loadingData;
    loadingData = new Promise(function (resolve) {
      var s = document.createElement('script');
      s.src = '/surfaces/onhost-panel.js?t=' + Date.now();
      s.onload = function () { loadingData = null; resolve(!!D().catalog); };
      s.onerror = function () { loadingData = null; resolve(false); };
      document.head.appendChild(s);
    });
    return loadingData;
  }
  function mount() {
    if (root) return;
    if (!document.getElementById('ohs-css')) { var st = document.createElement('style'); st.id = 'ohs-css'; st.textContent = CSS; document.head.appendChild(st); }
    root = document.createElement('div'); root.className = 'ohs'; root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true');
    root.addEventListener('click', onClick); root.addEventListener('input', onInput); root.addEventListener('change', onInput);
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(root);
    document.documentElement.style.overflow = 'hidden';
  }
  function open(cmp, key) {
    cmpRef = cmp || cmpRef;
    if (!window.OnhostApi) return false;
    if (!D().catalog) { // the order centre opens at once and waits for the catalogue
      mount();
      root.innerHTML = '<div class="ohs-top"><h1>' + _('Nová objednávka', 'New order') + '</h1><button type="button" class="ohs-x" data-a="close">✕ ' + _('Zavřít', 'Close') + '</button></div><div class="ohs-body"><div class="ohs-done"><p class="ohs-lead">' + _('Načítám katalog služeb…', 'Loading the service catalogue…') + '</p></div></div>';
      S = fresh(null);
      ensureData().then(function (ok) {
        if (!root) return;
        if (ok) { open(cmpRef, key); return; }
        root.querySelector('.ohs-body').innerHTML = '<div class="ohs-done"><div class="ohs-h">' + _('Katalog se nepodařilo načíst', 'The catalogue could not be loaded') + '</div><p class="ohs-lead">' + _('Obnovte prosím stránku. Pokud potíže trvají, napište podpoře — objednávku za vás zadáme.', 'Please reload the page. If it persists, contact support and we will place the order for you.') + '</p><button type="button" class="ohs-btn2" data-a="reloadpage">' + _('Obnovit stránku', 'Reload the page') + '</button></div>';
      });
      return true;
    }
    if (key && key !== 'domain' && !product(key)) key = null;
    S = fresh(key || null);
    if (key === 'game' && D().game_config && (D().game_config.groups || []).length === 1) { var only = D().game_config.groups[0]; S.game = Object.assign({}, S.game, { group: only.key, egg: only.eggs[0] }); }
    mount();
    if (cmp && cmp.setState) cmp.setState({ modal: null, userOpen: false, curOpen: false, notifOpen: false });
    render();
    refreshCredit();
    return true;
  }
  function close() {
    clearTimeout(quoteTimer);
    if (root) { root.remove(); root = null; }
    document.removeEventListener('keydown', onKey, true);
    document.documentElement.style.overflow = '';
  }

  window.OnhostPanelShop = { open: open, close: close };
})();
