/* ONhost public site — cart rules seams (docs/ui/data-seams.md #24).
 *
 * The prototype's cart hard-codes a 10 % / 18 % discount for 12- and 24-month terms, four generic upsells, a fake
 * promo code and a fake domain search. On the API-backed site every number comes from the catalogue and the pricing
 * rules staff set in Nastavení systému → Slevy a doplňky (`window.ONHOST_DATA.pricing/addons/tlds`):
 *  - a longer term is not a discount in itself: the cart shows exactly the percentage staff approved (none by default),
 *  - domains are cart lines of their own (whole years, at least one, no term discount, a TLD discount only if set),
 *  - every cart line carries its own add-ons (priced options of the product, add-on products staff allow next to it),
 *  - promo codes are validated by the API, the availability search asks the registrars.
 * The checkout bridge turns the same state into the order (`OnhostCart.orderItems`). Loaded after onhost-data.js. */
(function () {
  'use strict';
  if (window.OnhostCart) return; // the prototype runtime executes helmet scripts twice
  var cmpRef = null, promoFetching = false;

  function A() { return window.OnhostApi; }
  function D() { return window.ONHOST_DATA || null; }
  function isCs() { return (document.documentElement.lang || 'cs').indexOf('en') !== 0; }
  function T() { var c = isCs(); return function (a, b) { return c ? a : b; }; }
  function section(name) { var d = D(); return d && typeof d[name] === 'function' ? (d[name](isCs()) || null) : null; }
  function pricing() { return section('pricing') || {}; }
  function addonsData() { return section('addons') || {}; }
  function tldList() { return section('tlds') || []; }
  function money(cmp, v) { return cmp && typeof cmp.mny === 'function' ? cmp.mny(v) : (Math.round(v).toLocaleString('cs-CZ') + ' Kč'); }
  function yearsWord(n, cs) { return cs ? (n === 1 ? 'rok' : (n < 5 ? 'roky' : 'let')) : (n === 1 ? 'year' : 'years'); }
  function chip(active) {
    return 'border:1px solid ' + (active ? 'var(--acc,#ec3013)' : 'color-mix(in srgb,var(--fg,#201e1d) 28%,transparent)') + ';background:' + (active ? 'color-mix(in srgb,var(--acc,#ec3013) 10%,transparent)' : 'transparent') +
      ';color:var(--fg,#201e1d);cursor:pointer;font-family:var(--font-heading);font-weight:800;font-size:11px;letter-spacing:.04em;text-transform:uppercase;padding:5px 9px';
  }

  /* ── identity of a cart line ──────────────────────────────────────────── */
  function meta(it) { return it && it.meta && typeof it.meta === 'object' ? it.meta : null; }
  function sku(it) {
    var m = meta(it);
    if (m && m.product_key) return m;
    var P = window.OnhostSvcPages;
    var s = P && P.sku ? P.sku(it.name, it.price) : null;
    if (s) return s;
    var d = D(), idx = d && d.skus ? d.skus(isCs()) : null;
    if (!idx) return null;
    var n = String(it.name || '').toLowerCase();
    return idx[n + '|' + Math.round(it.price || 0)] || idx[n] || null;
  }
  function isDomain(it) { var m = meta(it); return !!(m && m.product_key === 'domain'); }
  function familyOf(it) { var s = sku(it); if (!s) return null; return (pricing().product_families || {})[s.product_key] || null; }
  function years(it) { var m = meta(it); var y = m && m.config && m.config.period_years ? Number(m.config.period_years) : 1; return y > 0 ? y : 1; }
  function domainPct(it) { var m = meta(it); var tld = m && m.config && m.config.tld; var dd = pricing().domain_discounts || {}; return tld && dd[tld] ? (Number(dd[tld].register) || 0) : 0; }

  /* ── commitment discounts (percent staff approved, none by default) ─────── */
  function commitPct(family, months) {
    if (months <= 1) return 0;
    var c = pricing().commit || {};
    var fam = family && c.families ? c.families[family] : null;
    var v = fam && fam[String(months)] != null ? fam[String(months)] : (c['default'] ? c['default'][String(months)] : 0);
    return Number(v) || 0;
  }
  function commits(family) { return [1, 12, 24].map(function (m) { return [m, commitPct(family, m) / 100]; }); }
  function discount(it) { if (isDomain(it)) return 0; return commitPct(familyOf(it), Number(it.commit) || 1) / 100; }
  function commitOpts(cs, items) {
    var families = {};
    (items || []).forEach(function (it) { if (!isDomain(it)) { var f = familyOf(it); if (f) families[f] = 1; } });
    var keys = Object.keys(families), family = keys.length === 1 ? keys[0] : null;
    var labels = cs ? ['1 měsíc', '12 měsíců', '24 měsíců'] : ['1 month', '12 months', '24 months'];
    return [1, 12, 24].map(function (m, i) { return [m, labels[i], commitPct(family, m)]; });
  }

  /* ── add-ons of a line: priced options + add-on products ───────────────── */
  function optionDefs(it) { var s = sku(it); if (!s) return []; var a = addonsData()[s.product_key]; var fixed = (s.config && s.config.options) || {}; return a ? (a.options || []).filter(function (o) { return !(o.key in fixed); }) : []; }
  function productDefs(it) { var s = sku(it); if (!s) return []; var a = addonsData()[s.product_key]; return a ? (a.products || []) : []; }
  function optionQty(def, raw) {
    if (def.kind === 'addon') return raw ? 1 : 0;
    if (def.kind === 'select') { var c = (def.choices || []).filter(function (x) { return x.key === raw; })[0]; return c ? (Number(c.units) || 0) : 0; }
    var v = Number(raw);
    if (isNaN(v)) return 0;
    var min = def.min != null ? Number(def.min) : 0, max = def.max != null ? Number(def.max) : Infinity;
    v = Math.max(min, Math.min(max, v));
    return Math.max(0, v - (def['default'] != null ? Number(def['default']) : 0));
  }
  function planOf(p, key) { return (p.plans || []).filter(function (x) { return x.key === key; })[0] || null; }
  function planMonthly(pl) { return pl ? (pl.unit_year ? (Number(pl.price) || 0) / 12 : (Number(pl.price) || 0)) : 0; }
  function addonsOf(it) { // monthly net of the line's options and attached add-on products, per unit of the line
    var sum = 0, sel = (it && it.addons && it.addons.options) || {}, prods = (it && it.addons && it.addons.products) || {};
    optionDefs(it).forEach(function (d) { if (d.key in sel) sum += optionQty(d, sel[d.key]) * (Number(d.price) || 0); });
    productDefs(it).forEach(function (p) { if (prods[p.key]) sum += planMonthly(planOf(p, prods[p.key])); });
    return sum;
  }
  /* ── the term of a line: 1 month, or 12 / 24 months paid in advance at the yearly list price ─ */
  function months(it) { var m = Number(it && it.commit) || 1; return m === 12 || m === 24 ? m : 1; }
  function priceYear(it) { var s = sku(it); return s && s.price_year ? Number(s.price_year) || 0 : 0; }
  /* list price of one unit for the term chosen: the yearly price for 12 months (twice for 24), otherwise the monthly one */
  function periodPrice(it) { var m = months(it), p = Number(it.price) || 0, y = priceYear(it); return m >= 12 ? (y ? y * (m / 12) : p * m) : p; }
  function lineNet(it, d) {
    if (isDomain(it)) return (Number(it.price) || 0) * years(it) * (1 - domainPct(it) / 100);
    var q = quoteLine(it);
    if (q) return q.net;
    var qty = Number(it.qty) || 1;
    return (periodPrice(it) * (1 - (d || 0)) + addonsOf(it) * months(it)) * qty;
  }
  function addonsTotal(items) { return (items || []).reduce(function (a, it) { return a + addonsOf(it) * (isDomain(it) ? 1 : (Number(it.qty) || 1) * months(it)); }, 0); }
  function commitOff(items) { return (items || []).reduce(function (a, it) { var d = discount(it); return a + (d ? periodPrice(it) * (Number(it.qty) || 1) * d : 0) + (isDomain(it) ? (Number(it.price) || 0) * years(it) * domainPct(it) / 100 : 0); }, 0); }
  /* the drawer's total line names the term: monthly, a year or two years paid in advance; domains alone are one-off */
  function totalLabel(state, cs) {
    var items = (state && state.cartItems) || [], services = items.filter(function (it) { return !isDomain(it); });
    if (!services.length) return cs ? 'Celkem k platbě' : 'Total due';
    var m = commitOf(state);
    if (m === 12) return cs ? 'Celkem za rok' : 'Yearly total';
    if (m === 24) return cs ? 'Celkem za 2 roky' : 'Total for 2 years';
    return cs ? 'Celkem měsíčně' : 'Monthly total';
  }
  function termLabel(it, d, _) {
    var m = months(it), y = priceYear(it);
    var base = m === 12 ? _('platba na rok předem', 'a year paid in advance') : _('platba na 2 roky předem', 'two years paid in advance');
    return base + (y ? ' · ' + money(cmpRef, y) + _(' / rok', ' / yr') : '') + (d ? _(' · sleva ' + Math.round(d * 100) + ' %', ' · ' + Math.round(d * 100) + '% off') : '');
  }

  /* ── the server's quote: the amounts the invoice will carry ──────────────
   * The cart shows what the platform will charge, not an estimate: once the drawer or the checkout is open the module
   * mirrors the cart to PUT /cart, asks POST /cart/quote and, while the quote matches the cart, every total and line
   * comes from it (yearly list prices, options priced per term, promo and TLD discounts, VAT by the tax rules). Until
   * the quote arrives the local list-price estimate is shown. Guests keep their server cart through X-Cart-Token. */
  var quote = { sig: null, data: null, pending: null, failed: null, timer: null, map: {} };
  function commitOf(state) { var c = state && state.commit; return c === 'yearly' || Number(c) === 12 ? 12 : (Number(c) === 24 ? 24 : 1); }
  function cartToken() { try { return sessionStorage.getItem('onhost.cart.token') || null; } catch (e) { return null; } }
  function quoteSig(state) { var o = orderItems({ items: (state && state.cartItems) || [] }); return JSON.stringify({ i: o.items, c: commitOf(state), p: state && state.promoOk ? (state.promo || null) : null }); }
  function quoteWanted(state) { return !!(state && state.cartOpen) || /^#\/(kosik|checkout)/.test(String(location.hash || '')); }
  function refreshQuote(cmp) {
    var items = (cmp.state.cartItems || []);
    if (!A() || !items.length) { quote.sig = null; quote.data = null; quote.pending = null; return; }
    if (!quoteWanted(cmp.state)) return;
    var sig = quoteSig(cmp.state);
    if (sig === quote.sig || sig === quote.pending || sig === quote.failed) return;
    quote.pending = sig;
    clearTimeout(quote.timer);
    quote.timer = setTimeout(function () {
      if (quote.pending !== sig) return;
      var o = orderItems({ items: cmp.state.cartItems || [] });
      if (!o.items.length) { quote.pending = null; return; }
      var token = cartToken(), h = token ? { 'X-Cart-Token': token } : undefined;
      A().put('/cart', { items: o.items, commit_months: commitOf(cmp.state), currency: 'CZK', promo_code: cmp.state.promoOk ? (cmp.state.promo || null) : null }, h)
        .then(function (r) {
          var t = r && r.data && r.data.token;
          if (t) { try { sessionStorage.setItem('onhost.cart.token', t); } catch (e) {} }
          return A().post('/cart/quote', {}, t ? { 'X-Cart-Token': t } : h);
        })
        .then(function (r) {
          if (quote.pending !== sig) return;
          quote.sig = sig; quote.data = r.data || r; quote.map = o.map; quote.pending = null;
          cmp.setState({ quoteAt: Date.now() });
        })
        .catch(function () { if (quote.pending === sig) { quote.pending = null; quote.failed = sig; } });
    }, 600); // one quote per pause in the customer's clicking (audit §5d-3), the local estimate bridges the gap
  }
  function currentQuote(state) { return quote.data && quote.sig === quoteSig(state) ? quote.data : null; }
  /* the quote lines of one cart line: its service line and the add-on lines attached to it */
  function quoteLines(it) {
    var st = cmpRef && cmpRef.state, q = st ? currentQuote(st) : null, id = quote.map[it.id];
    if (!q || !id) return null;
    return (q.lines || []).filter(function (l) { return l.line_id === id || (l.config && l.config.parent_line_id === id); });
  }
  function quoteLine(it) { var ls = quoteLines(it); if (!ls || !ls.length) return null; var net = 0; ls.forEach(function (l) { net += Number(l.net) || 0; }); return { net: net / 100, lines: ls }; }
  /* cartMath() of the prototype: the server's totals while the quote matches the cart, otherwise null (local estimate) */
  function totals(cmp) {
    cmpRef = cmp;
    refreshQuote(cmp);
    var q = currentQuote(cmp.state);
    if (!q) return null;
    var addons = 0;
    (q.lines || []).forEach(function (l) {
      var c = l.config || {}, pb = Number(c.periods_billed) || 1, qty = Number(l.qty) || 1;
      if (c.parent_line_id) addons += (Number(l.unit_net) || 0) * qty;
      (c.options_priced || []).forEach(function (o) { addons += (Number(o.net) || 0) * pb * qty; });
    });
    var discount = Number(q.discount) || 0;
    return {
      count: (cmp.state.cartItems || []).reduce(function (a, x) { return a + (Number(x.qty) || 1); }, 0),
      net: ((Number(q.subtotal) || 0) - discount) / 100, promoOff: discount / 100, commitOff: 0,
      vat: (Number(q.tax) || 0) / 100, total: (Number(q.total) || 0) / 100, addonsOff: addons / 100,
      renewal: (Number(q.renewal_total) || 0) / 100, quoted: true
    };
  }
  function termSuffix(l, cs) {
    var pb = Number((l.config || {}).periods_billed) || 1;
    if (l.period !== 'year') return cs ? ' · 1 měsíc' : ' · 1 month';
    return cs ? (pb === 2 ? ' · 2 roky' : ' · 1 rok') : (pb === 2 ? ' · 2 years' : ' · 1 year');
  }
  function quoteRows(lines, rows, cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    lines.forEach(function (l) {
      var c = l.config || {}, qty = Number(l.qty) || 1, pb = Number(c.periods_billed) || 1, unit = (Number(l.unit_net) || 0) * qty, opts = 0;
      (c.options_priced || []).forEach(function (o) { opts += (Number(o.net) || 0) * pb * qty; });
      if (l.product_key === 'domain') {
        rows.push({ k: l.name, v: money(cmp, (Number(l.net) || 0) / 100) });
        rows.push({ k: _('  obnova po uplynutí', '  renewal afterwards'), v: money(cmp, (Number(l.renewal_net) || 0) / 100) + _(' / rok', ' / yr') });
        return;
      }
      var main = !c.parent_line_id;
      rows.push({ k: (main ? '' : '+ ') + l.name + (qty > 1 ? ' × ' + qty : '') + (main ? termSuffix(l, cs) : ''), v: (main ? '' : '+ ') + money(cmp, (unit - opts) / 100) });
      (c.options_priced || []).forEach(function (o) { rows.push({ k: '+ ' + o.label + (Number(o.qty) > 1 ? ' × ' + o.qty : ''), v: '+ ' + money(cmp, (Number(o.net) || 0) * pb * qty / 100) }); });
    });
  }

  /* ── promo codes: validated by the API, applied to the families they name ─ */
  function applyPromo(cmp) {
    cmpRef = cmp;
    var code = String(cmp.state.promo || '').trim();
    if (!code) { cmp.setState({ promoOk: false, promoInfo: null, promoError: '' }); return; }
    promoFetching = true;
    A().get('/catalog/promo?code=' + encodeURIComponent(code)).then(function (r) {
      var d = r.data || r; promoFetching = false;
      cmp.setState({ promoOk: true, promoInfo: d, promo: d.code, promoError: '' });
    }).catch(function (e) {
      promoFetching = false;
      cmp.setState({ promoOk: false, promoInfo: null, promoError: (e && e.body && e.body.message) || (isCs() ? 'Slevový kód neplatí.' : 'The promo code is not valid.') });
    });
  }
  function promoOff(state, items) {
    if (!state.promoOk) return 0;
    if (!state.promoInfo) { if (state.promo && cmpRef && !promoFetching) applyPromo(cmpRef); return 0; } // restored from storage: re-validate once
    var info = state.promoInfo, applies = info.applies_to || [], base = 0;
    (items || []).forEach(function (it) {
      var fam = isDomain(it) ? 'domain' : familyOf(it);
      if (applies.length && applies.indexOf(fam) < 0 && applies.indexOf('*') < 0) return;
      base += lineNet(it, discount(it));
    });
    if (info.kind === 'fixed') return Math.min(base, Number(info.value) || 0);
    return base * (Number(info.value) || 0) / 100;
  }
  function promoState(state, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    if (state.promoOk && state.promoInfo) {
      var i = state.promoInfo;
      return _('Kód ' + i.code + ' uplatněn — sleva ' + (i.kind === 'fixed' ? Math.round(i.value) + ' ' + (i.currency || 'Kč') : i.value + ' %'), 'Code ' + i.code + ' applied — ' + (i.kind === 'fixed' ? Math.round(i.value) + ' ' + (i.currency || 'CZK') : i.value + '%') + ' off') +
        ((i.applies_to || []).length ? _(' (jen na vybrané služby)', ' (selected services only)') : '');
    }
    if (state.promoError) return state.promoError;
    return (state.promo || '').trim() ? _('Kód ověříme po kliknutí na Použít.', 'The code is checked when you click Apply.') : '';
  }

  /* ── cart rows: domains ──────────────────────────────────────────────────── */
  function updateItem(cmp, id, fn) {
    cmp.setState(function (st) { return { cartItems: (st.cartItems || []).map(function (x) { return x.id === id ? fn(Object.assign({}, x)) : x; }) }; });
  }
  function cartRow(it, cmp, _) {
    if (!isDomain(it)) return null;
    cmpRef = cmp;
    var m = meta(it), y = years(it), pct = domainPct(it), cs = isCs();
    var line = lineNet(it, 0), renew = Number(m.renew) || Number(it.price) || 0;
    var periods = (m.periods && m.periods.length ? m.periods : [1, 2, 3, 5]).map(Number).filter(function (n) { return n >= 1; });
    var setYears = function (n) { updateItem(cmp, it.id, function (x) { var nm = Object.assign({}, x.meta, { config: Object.assign({}, x.meta.config, { period_years: n }) }); return Object.assign(x, { meta: nm }); }); };
    return {
      id: it.id, name: it.name,
      meta: _('registrace na ' + y + ' ' + yearsWord(y, true) + (pct ? ' · sleva ' + pct + ' %' : '') + ' · obnova ' + money(cmp, renew) + ' / rok', 'registration for ' + y + ' ' + yearsWord(y, false) + (pct ? ' · ' + pct + '% off' : '') + ' · renewal ' + money(cmp, renew) + ' / yr'),
      qty: '1', unit: money(cmp, it.price) + _(' / rok', ' / yr'),
      line: money(cmp, line), lineVat: _('s DPH ', 'incl. VAT ') + money(cmp, line * 1.21),
      discount: pct ? '− ' + pct + ' %' : '',
      discountStyle: pct ? 'font-size:11px;font-family:var(--font-heading);font-weight:800;color:var(--accInk,#ae1800)' : 'display:none',
      inc: function () {}, dec: function () {},
      remove: function () { cmp.setState(function (st) { var left = (st.cartItems || []).filter(function (x) { return x.id !== it.id; }); return { cartItems: left, cartOpen: left.length ? st.cartOpen : false }; }); },
      commits: periods.map(function (n) { return { label: n + ' ' + yearsWord(n, cs), style: chip(n === y), on: function () { setYears(n); } }; })
    };
  }

  /* ── checkout step 1: add-ons under every line ───────────────────────────── */
  function itemAddons(cmp) {
    cmpRef = cmp;
    var _ = T(), cs = isCs();
    return (cmp.state.cartItems || []).map(function (it) {
      if (isDomain(it)) {
        var renewal = Number((meta(it) || {}).renew) || Number(it.price) || 0;
        return { name: it.name, meta: _('registrace na ' + years(it) + ' ' + yearsWord(years(it), true) + ' · obnova ' + money(cmp, renewal) + ' / rok', 'registration for ' + years(it) + ' ' + yearsWord(years(it), false) + ' · renewal ' + money(cmp, renewal) + ' / yr'), none: true, noneLabel: _('K doméně se doplňky nevážou — DNS hosting a DNSSEC jsou v ceně.', 'Domains carry no add-ons — DNS hosting and DNSSEC are included.'), addons: [] };
      }
      var defs = optionDefs(it), prods = productDefs(it), s = sku(it);
      var sel = (it.addons && it.addons.options) || {}, selP = (it.addons && it.addons.products) || {};
      var update = function (fn) { updateItem(cmp, it.id, function (x) { var ad = { options: Object.assign({}, (x.addons && x.addons.options) || {}), products: Object.assign({}, (x.addons && x.addons.products) || {}) }; fn(ad); return Object.assign(x, { addons: ad }); }); };
      var rows = defs.map(function (d) {
        var raw = sel[d.key];
        var row = { key: d.key, name: d.label, desc: d.desc || '', isCheck: d.kind === 'addon', isRange: d.kind === 'slider', isChips: d.kind === 'select', choices: [] };
        if (d.kind === 'addon') {
          row.on = !!raw; row.priceLabel = '+ ' + money(cmp, d.price) + _(' / měs.', ' / mo');
          row.toggle = function () { update(function (ad) { if (ad.options[d.key]) delete ad.options[d.key]; else ad.options[d.key] = true; }); };
        } else if (d.kind === 'slider') {
          var v = raw != null ? Number(raw) : (d['default'] != null ? Number(d['default']) : Number(d.min || 0));
          row.raw = v; row.min = d.min != null ? d.min : 0; row.max = d.max != null ? d.max : 100; row.step = d.step || 1; row.value = v + (d.unit ? ' ' + d.unit : '');
          row.priceLabel = optionQty(d, v) ? '+ ' + money(cmp, optionQty(d, v) * d.price) + _(' / měs.', ' / mo') : money(cmp, d.price) + ' / ' + (d.unit || _('ks', 'pc')) + _(' / měs.', ' / mo');
          row.change = function (e) { var n = Number(e.target.value); update(function (ad) { if (optionQty(d, n) > 0) ad.options[d.key] = n; else delete ad.options[d.key]; }); };
        } else {
          var cur = raw || (((d.choices || [])[0] || {}).key);
          row.choices = (d.choices || []).map(function (c) { return { label: c.label + (c.units ? ' · + ' + money(cmp, c.units * d.price) : ''), style: chip(c.key === cur), on: function (e) { if (e && e.preventDefault) e.preventDefault(); update(function (ad) { if (c.units > 0) ad.options[d.key] = c.key; else delete ad.options[d.key]; }); } }; });
          row.priceLabel = optionQty(d, cur) ? '+ ' + money(cmp, optionQty(d, cur) * d.price) + _(' / měs.', ' / mo') : _('v ceně', 'included');
        }
        return row;
      });
      prods.forEach(function (p) {
        var chosen = selP[p.key], pl = chosen ? planOf(p, chosen) : null;
        rows.push({
          key: 'product:' + p.key, name: p.name, desc: p.desc || '', isCheck: false, isRange: false, isChips: true,
          choices: [{ label: _('bez', 'none'), style: chip(!chosen), on: function (e) { if (e && e.preventDefault) e.preventDefault(); update(function (ad) { delete ad.products[p.key]; }); } }].concat((p.plans || []).map(function (x) {
            return { label: x.name + ' · ' + money(cmp, x.price) + (x.unit_year ? _(' / rok', ' / yr') : _(' / měs.', ' / mo')), style: chip(chosen === x.key), on: function (e) { if (e && e.preventDefault) e.preventDefault(); update(function (ad) { ad.products[p.key] = x.key; }); } };
          })),
          priceLabel: pl ? '+ ' + money(cmp, pl.price) + (pl.unit_year ? _(' / rok', ' / yr') : _(' / měs.', ' / mo')) : ''
        });
      });
      var sum = addonsOf(it);
      var fixed = s && s.config && s.config.options ? Object.keys(s.config.options).length : 0;
      return {
        name: it.name + (it.qty > 1 ? ' × ' + it.qty : ''),
        meta: (fixed ? _('konfigurace z konfigurátoru · ', 'configured in the builder · ') : '') + (sum ? _('doplňky + ' + money(cmp, sum) + ' / měs.', 'add-ons + ' + money(cmp, sum) + ' / mo') : _('bez doplňků', 'no add-ons')),
        none: rows.length === 0, noneLabel: _('K této službě zatím nenabízíme doplňky.', 'No add-ons are offered for this service yet.'), addons: rows
      };
    });
  }

  /* ── cart drawer "Přidat k objednávce": add-on products of the lines in the cart (catalogue, not the prototype's fixed four) ─ */
  function upsells(cmp, _) {
    cmpRef = cmp;
    var out = [], seen = {};
    (cmp.state.cartItems || []).forEach(function (it) {
      if (isDomain(it)) return;
      var chosen = (it.addons && it.addons.products) || {};
      productDefs(it).forEach(function (p) {
        if (seen[p.key] || chosen[p.key]) return;
        var cheapest = (p.plans || []).slice().sort(function (a, b) { return a.price - b.price; })[0];
        if (!cheapest) return;
        seen[p.key] = 1;
        out.push({
          name: p.name + ' · ' + cheapest.name, desc: (p.desc || '') + ' ' + _('(k položce ' + it.name + ')', '(for ' + it.name + ')'),
          price: money(cmp, cheapest.price) + (cheapest.unit_year ? _(' / rok', ' / yr') : _(' / měs.', ' / mo')),
          on: function (e) { if (e && e.preventDefault) e.preventDefault(); updateItem(cmp, it.id, function (x) { var ad = { options: Object.assign({}, (x.addons && x.addons.options) || {}), products: Object.assign({}, (x.addons && x.addons.products) || {}) }; ad.products[p.key] = cheapest.key; return Object.assign(x, { addons: ad }); }); }
        });
      });
    });
    return out;
  }

  /* ── checkout summary rows ───────────────────────────────────────────────── */
  function summaryRows(cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; }, rows = [];
    cmpRef = cmp;
    (cmp.state.cartItems || []).forEach(function (it) {
      var ql = quoteLines(it);
      if (ql && ql.length) { quoteRows(ql, rows, cmp, cs); return; }
      if (isDomain(it)) {
        var renew = Number((meta(it) || {}).renew) || Number(it.price) || 0;
        rows.push({ k: it.name + ' (' + years(it) + ' ' + yearsWord(years(it), cs) + ')', v: money(cmp, (Number(it.price) || 0) * years(it)) });
        rows.push({ k: _('  obnova po uplynutí', '  renewal afterwards'), v: money(cmp, renew) + _(' / rok', ' / yr') });
        return;
      }
      var qty = Number(it.qty) || 1, m = months(it);
      rows.push({ k: it.name + (qty > 1 ? ' × ' + qty : '') + (m >= 12 ? (cs ? (m === 24 ? ' · 2 roky' : ' · 1 rok') : (m === 24 ? ' · 2 years' : ' · 1 year')) : (cs ? ' · 1 měsíc' : ' · 1 month')), v: money(cmp, periodPrice(it) * qty) });
      var sel = (it.addons && it.addons.options) || {}, prods = (it.addons && it.addons.products) || {};
      optionDefs(it).forEach(function (d) { if (!(d.key in sel)) return; var q = optionQty(d, sel[d.key]); if (q > 0) rows.push({ k: '+ ' + d.label + (d.kind === 'slider' ? ' ' + sel[d.key] + (d.unit ? ' ' + d.unit : '') : ''), v: '+ ' + money(cmp, q * d.price * qty * m) }); });
      productDefs(it).forEach(function (p) { var pl = prods[p.key] ? planOf(p, prods[p.key]) : null; if (pl) rows.push({ k: '+ ' + p.name + ' ' + pl.name, v: '+ ' + money(cmp, planMonthly(pl) * qty * m) }); });
    });
    return rows;
  }

  /* ── domain search on the home page: real availability from the registrars ─ */
  function searchDomains(raw, cmp) {
    var list = tldList();
    if (!list.length || !A()) return false;
    cmpRef = cmp;
    var _ = T(), cs = isCs();
    var names = list.slice(0, 8).map(function (t) { return raw + '.' + t.tld; });
    var muted = 'color-mix(in srgb,var(--fg,#201e1d) 30%,transparent)';
    cmp.setState({ results: names.map(function (n) { return { full: n, price: '…', state: _('ověřujeme', 'checking'), dot: muted, action: '' }; }) });
    A().post('/domains/check', { names: names, currency: 'CZK' }).then(function (r) {
      var rows = r.data || r || [];
      cmp.setState({ results: rows.map(function (d) {
        var free = d.available === true, tld = list.filter(function (t) { return t.tld === d.tld; })[0] || {};
        var price = (Number(d.price_register) || 0) / 100, pct = Number(tld.discount) || 0;
        return {
          full: d.unicode || d.fqdn || d.input, price: free ? money(cmp, price * (1 - pct / 100)) + _(' / rok', ' / yr') + (pct ? ' · −' + pct + ' %' : '') + _(' · obnova ', ' · renewal ') + money(cmp, (Number(d.price_renew) || 0) / 100) + _(' / rok', ' / yr') : (d.available === false ? '—' : '?'),
          state: free ? _('volná', 'available') : (d.reason === 'premium' ? _('prémiové jméno — cenu určuje registr, napište nám', 'premium name — the registry sets its price, write to us') : d.available === false ? _('obsazená', 'taken') : (d.reason === 'registrar_unavailable' ? _('ověření je dočasně nedostupné, zkuste to za chvíli', 'the check is temporarily unavailable, try again shortly') : (d.reason === 'domain_invalid' ? _('neplatný název', 'invalid name') : _('nelze ověřit', 'cannot check')))),
          dot: free ? 'var(--neon,#b8ff2e)' : (d.available === false ? 'var(--acc,#ec3013)' : muted),
          action: free ? _('Do košíku', 'Add to cart') : '',
          on: free ? function (e) { if (e && e.preventDefault) e.preventDefault(); cmp.addToCart(_('Doména ', 'Domain ') + (d.unicode || d.fqdn), price, { product_key: 'domain', config: { fqdn: d.fqdn, tld: d.tld, period_years: tld.default_period || 1, action: 'register' }, periods: d.periods || tld.periods || [1], renew: (Number(d.price_renew) || 0) / 100 }); } : function (e) { if (e && e.preventDefault) e.preventDefault(); }
        };
      }) });
    }).catch(function () {
      cmp.setState({ results: names.map(function (n) { return { full: n, price: '—', state: _('ověření se nezdařilo, zkuste to znovu', 'the check failed, try again'), dot: 'var(--acc,#ec3013)', action: '' }; }) });
    });
    return true;
  }

  /* ── confirmation: what the placed order really is (bank transfer awaiting payment, paid, gateway) ─ */
  function doneCopy(o, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    if (o && o.state === 'PENDING_PAYMENT' && o.bank_instructions) {
      return { kicker: _('Objednávka přijata · čeká na platbu', 'Order received · awaiting payment'), title: _('Zaplaťte převodem a spustíme to', 'Pay by bank transfer and we launch'), lead: _('Pokyny k platbě jsou níže a poslali jsme je i e-mailem se zálohovou fakturou. Služby spustíme ihned po připsání platby; převod v rámci ČR obvykle dorazí do druhého pracovního dne. Objednávku najdete i v klientském panelu.', 'The payment instructions are below and in the e-mail with the proforma invoice. We launch the services as soon as the payment arrives; a domestic transfer usually lands by the next business day. The order is in the client panel as well.') };
    }
    if (o && o.state === 'PENDING_PAYMENT') {
      return { kicker: _('Objednávka přijata · čeká na platbu', 'Order received · awaiting payment'), title: _('Dokončete platbu', 'Complete the payment'), lead: _('Platba u brány zatím neproběhla. Objednávku najdete v klientském panelu, kde ji můžete zaplatit znovu.', 'The gateway payment has not completed yet. The order is in the client panel, where you can pay it again.') };
    }
    return { kicker: _('Objednávka přijata', 'Order received'), title: _('Zaplaceno, služby se zřizují', 'Paid — provisioning'), lead: _('Průběh vidíte v klientském panelu; webhosting a domény bývají hotové do několika minut. Doklad jsme poslali e-mailem.', 'Progress shows in the client panel; web hosting and domains are usually ready within minutes. The document is in your inbox.') };
  }
  /* the summary column after the order was placed: the real order instead of the (now emptied) cart */
  function orderSummaryRows(o, cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; }, rows = [];
    rows.push({ k: _('Objednávka', 'Order'), v: o.number || '' });
    rows.push({ k: _('Stav', 'State'), v: o.state === 'PENDING_PAYMENT' ? _('čeká na platbu', 'awaiting payment') : (o.state === 'PAID' ? _('zaplaceno', 'paid') : String(o.state || '').toLowerCase()) });
    rows.push({ k: _('Způsob platby', 'Payment'), v: o.payment_mode === 'bank' ? _('bankovní převod', 'bank transfer') : (o.payment_mode === 'gateway' ? _('platební brána', 'payment gateway') : (o.payment_mode === 'wallet' ? _('kredit', 'credit') : (o.payment_mode || '—'))) });
    if (o.discount) rows.push({ k: _('Slevy', 'Discounts'), v: '− ' + money(cmp, Number(o.discount) / 100) });
    return rows;
  }
  function doneRows(o, cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; }, rows = [];
    if (!o) return rows;
    if (o.total != null) rows.push({ k: _('Celkem k platbě (s DPH)', 'Total due (incl. VAT)'), v: money(cmp, Number(o.total) / 100) + (o.currency && o.currency !== 'CZK' ? ' ' + o.currency : '') });
    var b = o.bank_instructions;
    if (b) {
      if (b.account_number) rows.push({ k: _('Číslo účtu', 'Account number'), v: b.account_number });
      if (b.iban) rows.push({ k: 'IBAN', v: b.iban + (b.bic ? ' · ' + b.bic : '') });
      if (b.variable_symbol) rows.push({ k: _('Variabilní symbol', 'Variable symbol'), v: b.variable_symbol });
      if (b.amount) rows.push({ k: _('Částka převodu', 'Transfer amount'), v: b.amount + ' ' + (b.currency || 'CZK') });
      if (b.message) rows.push({ k: _('Zpráva pro příjemce', 'Message for the recipient'), v: b.message });
    }
    return rows;
  }

  /* ── guest details typed in the checkout (step "Údaje"), kept for the order ─ */
  var guest = (function () { try { return JSON.parse(sessionStorage.getItem('onhost.guest') || '{}') || {}; } catch (e) { return {}; } })();
  function checkoutView() { return /^#\/(kosik|checkout)/.test(String(location.hash || '')); }
  function captureField(el) {
    if (!el || el.tagName !== 'INPUT') return;
    var ph = el.getAttribute('placeholder') || '';
    if (el.type === 'email') guest.email = el.value;
    else if (el.type === 'checkbox') { var label = el.closest('label'); var text = label ? (label.textContent || '') : ''; if (/firm|company/i.test(text)) guest.company = el.checked; }
    else if (ph === 'Jan Novák') guest.name = el.value;
    else if (ph === '12345678') guest.ico = el.value;
    else if (ph === 'CZ12345678') guest.dic = el.value;
    else if (ph === 'Nová s.r.o.') guest.company = el.value;
    else if (ph === 'Dlouhá 12') guest.street = el.value;
    else if (ph === 'Praha') guest.city = el.value;
    else if (ph === '110 00') guest.postal_code = el.value;
    else return;
    try { sessionStorage.setItem('onhost.guest', JSON.stringify(guest)); } catch (e) {}
  }
  document.addEventListener('input', function (e) { if (checkoutView()) captureField(e.target); }, true);
  document.addEventListener('change', function (e) { if (checkoutView()) captureField(e.target); }, true);

  /* ── what the checkout bridge sends: API items with line ids, options and add-on lines ─ */
  function orderItems(cart) {
    var items = [], unknown = [], map = {}, n = 0;
    (cart.items || []).forEach(function (it) {
      var s = sku(it);
      if (!s) { unknown.push(it.name); return; }
      var lineId = 'l' + (++n);
      map[it.id] = lineId;
      if (s.product_key === 'domain') { items.push({ line_id: lineId, product_key: 'domain', qty: 1, config: Object.assign({}, s.config || {}, { period_years: years(it) }) }); return; }
      var options = Object.assign({}, (s.config && s.config.options) || {}), sel = (it.addons && it.addons.options) || {};
      optionDefs(it).forEach(function (d) { if (d.key in sel && optionQty(d, sel[d.key]) > 0) options[d.key] = sel[d.key]; });
      var config = Object.assign({}, s.config || {});
      if (Object.keys(options).length) config.options = options; else delete config.options;
      items.push({ line_id: lineId, product_key: s.product_key, plan_key: s.plan_key, qty: it.qty || 1, config: config });
      var prods = (it.addons && it.addons.products) || {};
      productDefs(it).forEach(function (p) { if (prods[p.key]) items.push({ line_id: 'l' + (++n), product_key: p.key, plan_key: prods[p.key], qty: it.qty || 1, config: { parent_line_id: lineId } }); });
    });
    return { items: items, unknown: unknown, map: map };
  }
  function domainTlds(cart) { var out = {}; (cart.items || []).forEach(function (it) { var m = meta(it); if (m && m.product_key === 'domain' && m.config && m.config.tld) out[m.config.tld] = 1; }); return Object.keys(out); }

  window.OnhostCart = {
    commits: commits, discount: discount, lineNet: lineNet, addonsTotal: addonsTotal, commitOff: commitOff, commitOpts: commitOpts,
    totals: totals, termLabel: termLabel, totalLabel: totalLabel, months: months, periodPrice: periodPrice,
    cartRow: cartRow, itemAddons: itemAddons, summaryRows: summaryRows, upsells: upsells,
    applyPromo: applyPromo, promoOff: promoOff, promoState: promoState, doneCopy: doneCopy, doneRows: doneRows, orderSummaryRows: orderSummaryRows,
    searchDomains: searchDomains,
    orderItems: orderItems, domainTlds: domainTlds, isDomain: isDomain, sku: sku,
    guestDetails: function () { return Object.assign({}, guest); },
    /* signed-in customer: the form starts with the account's billing identity (guests get the last typed values) */
    prefill: function () {
      var u = (window.ONHOST && window.ONHOST.user) || null;
      if (!u) return {};
      var o = u.organization || {};
      var out = { email: u.email || '', name: u.name || '', ico: o.ico || '', dic: o.dic || o.vat_id || '', street: o.street || '', city: o.city || '', zip: o.postal_code || '' };
      if (o.type === 'company' || o.ico) out.company = o.name || '';
      return out;
    },
    /* what happens after the order, by payment method: a transfer starts nothing until the money arrives */
    eta: function (state, cs) {
      var p = String((state && state.pay) || 'card');
      if (p === 'bank') return cs ? 'Služby spustíme ihned po připsání platby; převod v rámci ČR obvykle dorazí do druhého pracovního dne.' : 'Services start as soon as the transfer arrives, usually the next business day within the Czech Republic.';
      var web = (state && state.cartItems || []).some(function (it) { var m = meta(it); return !m || m.product_key !== 'domain'; });
      return web ? (cs ? 'Webhosting je připravený obvykle do 90 sekund od zaplacení.' : 'Web hosting is usually ready within 90 seconds of payment.') : (cs ? 'Doménu zaregistrujeme ihned po zaplacení; registr ji zpravidla potvrdí do několika minut.' : 'The domain is registered right after payment; the registry usually confirms within minutes.');
    }
  };
})();
