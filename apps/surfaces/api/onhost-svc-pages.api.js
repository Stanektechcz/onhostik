/* ONhost public site — product page seams (docs/ui/data-seams.md #23).
 *
 * The prototype's service pages (`onhost-svc-*.js`) carry narrated plans and comparison tables. On the API-backed
 * site the plans, prices, specs and the comparison table of every page that has a catalogue product behind it come
 * from `window.ONHOST_DATA.pages(cs)` (generated from the catalogue), so what the visitor sees is exactly what the
 * cart sells; static copy (features, benchmarks, FAQ, migration, reviews) stays as authored. Each overlaid plan
 * remembers its SKU so the checkout never has to guess from a display name.
 *
 * `extra(cmp, slug, cs)` builds the blocks the prototype pages lack — the complete parameter listing, the add-ons
 * a plan can carry (priced from the catalogue options) and the configurator ("Tarif na míru": every parameter is a
 * priced option, the price follows the unit prices staff set) — for the service page and the web hosting landing. */
(function () {
  if (window.OnhostSvcPages) return; // the prototype runtime executes helmet scripts twice
  var registry = {};
  function overlay(cs) { var D = window.ONHOST_DATA; return D && typeof D.pages === 'function' ? (D.pages(cs) || {}) : {}; }
  function remember(crumb, plan) {
    if (!plan.sku) return;
    var name = String(crumb || '') + ' · ' + String(plan.name || '');
    registry[name.toLowerCase() + '|' + Math.round(plan.price || 0)] = plan.sku;
    registry[name.toLowerCase()] = plan.sku;
  }
  function apply(pages, ov) {
    Object.keys(ov).forEach(function (slug) {
      var page = pages[slug], o = ov[slug];
      if (!page || !o) return;
      if (o.withdrawn) { // an offer the platform does not make (e.g. the school/student programmes, owner decision 21): a short notice instead of the advert
        page.kicker = o.kicker || ''; page.title = o.title || page.title; page.lead = o.lead || '';
        page.kpis = []; page.chips = []; page.plans = []; page.plansTitle = o.kicker || page.plansTitle; page.plansNote = o.lead || '';
        page.feats = []; page.tech = []; page.bench = []; page.benchNote = ''; page.cases = []; page.faq = []; page.config = false; page.roi = false;
        delete page.cmp; delete page.hourly;
        return;
      }
      if (o.unavailable) { // the product exists but is not on sale: no prices, no order buttons, a contact request instead
        page.kicker = o.kicker || page.kicker;
        page.plansNote = o.note || page.plansNote;
        page.plans = (page.plans || []).map(function (p) { return Object.assign({}, p, { price: 0, priceLabel: o.label || '—', priceNote: o.note || '', ctaLabel: o.cta || 'Nezávazně poptat', sku: null }); });
        delete page.hourly;
        return;
      }
      if (o.plans && o.plans.length) {
        var head = o.keep_first ? (page.plans || []).slice(0, o.keep_first) : [];
        var tail = o.keep_last ? (page.plans || []).slice(-o.keep_last) : [];
        page.plans = head.concat(o.plans.map(function (p) { return Object.assign({}, p); })).concat(tail);
        page.plans.forEach(function (p) { remember(page.crumb, p); });
        if (!o.hourly) delete page.hourly; else page.hourly = o.hourly;
      }
      if (o.cmp && o.cmp.rows && o.cmp.rows.length) page.cmp = { cols: o.cmp.cols, rows: o.cmp.rows };
      if (o.cmpTitle) page.cmpTitle = o.cmpTitle;
      if (o.kicker) page.kicker = o.kicker;
      if (o.lead) page.lead = o.lead; // a lead that promised more than the plans sell (eshop, decision 7)
      if (o.chips && o.chips.length) page.chips = o.chips; // game pages: the templates the panel really offers
      if (o.kpi_games && page.kpis && page.kpis.length) page.kpis = [o.kpi_games].concat(page.kpis.slice(1));
    });
    return pages;
  }

  /* ── prices only from the catalogue (owner decision R14, audit 2026-10 P1-6) ───────────────────────────────────────
   * After the overlay, a plan may show a price only when a catalogue SKU stands behind it. A narrated plan with a price and no
   * SKU (gpu, inference, colocation, git, ci, the solution pages, managed, storage, enterprise …) says "Připravujeme": no price,
   * no order button (price 0 → the prototype's contact action), and the page loses what would still price it — the hourly
   * rates, the prototype configurator (it adds made-up prices to the cart), the ROI calculator without a catalogue base and the
   * amounts written into its copy. A page the platform marks `coming` (e.g. the free migration) is treated so as a whole. */
  var AMOUNT = /\d[\d\s .,]*\s?(Kč|CZK|EUR|€|zł|PLN|USD|GBP|£)|(€|\$|£)\s?\d/;
  function priced(v, strict) {
    if (typeof v !== 'string') return false;
    var m = v.match(AMOUNT);
    return !!m && (strict || /[1-9]/.test(m[0])); // "0 Kč" (free) is no price claim unless the whole page is not on offer
  }
  function pricedDeep(v, strict) {
    if (typeof v === 'string') return priced(v, strict);
    if (Array.isArray(v)) return v.some(function (x) { return pricedDeep(x, strict); });
    if (v && typeof v === 'object') return Object.keys(v).some(function (k) { return pricedDeep(v[k], strict); });
    return false;
  }
  function scrubText(v, strict) { // drops the " · " segments that name an amount
    if (!priced(v, strict)) return v;
    return String(v).split(' · ').filter(function (seg) { return !priced(seg, strict); }).join(' · ');
  }
  function keepRows(list, strict) { return Array.isArray(list) ? list.filter(function (r) { return !pricedDeep(r, strict); }) : list; }
  function comingCopy(cs, o) {
    var _ = function (a, b) { return cs ? a : b; };
    return {
      label: (o && o.label) || _('Připravujeme', 'Coming soon'),
      note: (o && o.note) || _('Tuto službu zatím neprodáváme. Napište nám a dáme vám vědět, jakmile bude v nabídce.', 'This service is not sold yet. Get in touch and we will tell you when it is available.'),
      cta: (o && o.cta) || _('Napsat nám', 'Get in touch'),
      kicker: (o && o.kicker) || _('Připravujeme', 'In preparation')
    };
  }
  function comingPlan(p, copy) {
    var out = Object.assign({}, p, { price: 0, priceLabel: copy.label, priceNote: '', ctaLabel: copy.cta, sku: null });
    delete out.goto; delete out.unitYear;
    return out;
  }
  function honest(pages, ov, cs) {
    Object.keys(pages).forEach(function (slug) {
      var page = pages[slug], o = ov[slug] || null;
      if (!page || (o && o.withdrawn) || page.docs) return;
      var plans = page.plans || [];
      var whole = !!(o && o.coming);
      var copy = comingCopy(cs, whole ? o : null), converted = 0;
      page.plans = plans.map(function (p) {
        if (whole || (Number(p.price) > 0 && !p.sku)) { converted++; return comingPlan(p, copy); }
        return p;
      });
      var product = !!o || converted > 0;
      if (!product) return; // company pages (about, careers, partners …) sell nothing: their numbers stay as authored
      var fromCatalogue = !!(o && o.plans && o.plans.length); // the domains page prices its catalogue TLDs in labels, not in `price`
      var strict = whole || !!(o && o.unavailable) || (!fromCatalogue && !page.plans.some(function (p) { return Number(p.price) > 0; }));
      if (converted && !fromCatalogue) { page.plansNote = copy.note; if (strict) page.kicker = copy.kicker; }
      page.config = false; // the prototype configurator prices with its own unit prices; the catalogue configurator is extra().builder
      if (strict) { page.roi = false; delete page.hourly; }
      if (!(o && o.cmp && o.cmp.rows && o.cmp.rows.length) && page.cmp) page.cmp = { cols: page.cmp.cols, rows: keepRows(page.cmp.rows, strict) };
      page.kpis = keepRows(page.kpis, strict);
      page.faq = keepRows(page.faq, strict);
      if (!(o && o.chips && o.chips.length)) page.chips = keepRows(page.chips, strict);
      if (page.panels) page.panels = page.panels.map(function (x) { return [x[0], scrubText(x[1], strict), keepRows(x[2], strict)]; });
      if (page.sla && page.sla.rows) page.sla = Object.assign({}, page.sla, { rows: keepRows(page.sla.rows, strict) });
      if (page.bench) page.bench = page.bench.map(function (b) { return Object.assign({}, b, { note: scrubText(b.note, strict) }); });
      if (page.cases) page.cases = page.cases.map(function (c) { return c && typeof c === 'object' && !Array.isArray(c) ? Object.assign({}, c, { m: scrubText(c.m, strict) }) : c; });
      if (page.feats) page.feats = page.feats.map(function (f) { return Array.isArray(f) ? f.map(function (t) { return scrubText(t, strict); }) : f; });
      page.kicker = scrubText(page.kicker, strict); page.lead = scrubText(page.lead, strict); page.title = scrubText(page.title, strict);
    });
    return pages;
  }
  /* the VAT rate of the tax rules (ONHOST_DATA.vat, audit P1-6); the statutory rate only when the platform has no active rule set */
  function vatRate() { var D = window.ONHOST_DATA, v = D && typeof D.vat === 'function' ? D.vat() : null; return typeof v === 'number' ? v : 0.21; }

  /* ── extra blocks ──────────────────────────────────────────────────────── */
  function money(cmp, v) { return cmp && typeof cmp.mny === 'function' ? cmp.mny(v) : (Math.round(v).toLocaleString('cs-CZ') + ' Kč'); }
  function chip(active) {
    return 'border:2px solid ' + (active ? 'var(--acc,#ec3013)' : 'color-mix(in srgb,var(--fg,#201e1d) 30%,transparent)') + ';background:' + (active ? 'color-mix(in srgb,var(--acc,#ec3013) 10%,transparent)' : 'transparent') +
      ';color:var(--fg,#201e1d);cursor:pointer;font-family:var(--font-heading);font-weight:800;font-size:12px;letter-spacing:.04em;padding:8px 12px;text-align:left';
  }
  function qtyOf(o, v) {
    if (o.kind === 'addon') return v ? 1 : 0;
    if (o.kind === 'select') { var c = (o.choices || []).filter(function (x) { return x.key === v; })[0]; return c ? (Number(c.units) || 0) : 0; }
    var n = Number(v); if (isNaN(n)) return 0;
    var min = o.min != null ? Number(o.min) : 0, max = o.max != null ? Number(o.max) : Infinity;
    n = Math.max(min, Math.min(max, n));
    return Math.max(0, n - (o['default'] != null ? Number(o['default']) : 0));
  }
  function unitLabel(cmp, o, _) {
    if (o.kind === 'slider') return '+ ' + money(cmp, o.price) + ' / ' + (o.unit || _('ks', 'pc')) + _(' / měs.', ' / mo');
    if (o.kind === 'select') { var max = (o.choices || []).reduce(function (m, c) { return Math.max(m, Number(c.units) || 0); }, 0); return max ? _('až + ', 'up to + ') + money(cmp, max * o.price) + _(' / měs.', ' / mo') : _('v ceně', 'included'); }
    return '+ ' + money(cmp, o.price) + _(' / měs.', ' / mo');
  }
  function builder(cmp, b, cs) {
    var _ = function (a, b2) { return cs ? a : b2; };
    var st = (cmp.state && cmp.state.svcBuild) || {};
    var val = function (o) { if (o.key in st) return st[o.key]; return o.kind === 'addon' ? false : (o.kind === 'select' ? (((o.choices || [])[0] || {}).key) : (o['default'] != null ? o['default'] : (o.min || 0))); };
    var set = function (key, v) { var next = Object.assign({}, (cmp.state && cmp.state.svcBuild) || {}); next[key] = v; cmp.setState({ svcBuild: next }); };
    var total = Number(b.base_price) || 0, rows = [], options = {}, sliders = [], toggles = [], selects = [], summary = [];
    (b.options || []).forEach(function (o) {
      var v = val(o), q = qtyOf(o, v), add = q * (Number(o.price) || 0);
      total += add;
      if (o.kind === 'slider') {
        var unit = o.unit ? ' ' + o.unit : '';
        sliders.push({ label: o.label, value: v + unit, raw: v, min: o.min != null ? o.min : 0, max: o.max != null ? o.max : 100, step: o.step || 1, minLabel: (o.min != null ? o.min : 0) + unit, maxLabel: (o.max != null ? o.max : 100) + unit, on: function (e) { set(o.key, Number(e.target.value)); } });
        rows.push({ k: o.label, v: v + unit + (add ? ' · + ' + money(cmp, add) : '') });
        if (q > 0) options[o.key] = v;
        summary.push(v + unit.replace(/^ /, ' '));
      } else if (o.kind === 'select') {
        var cur = (o.choices || []).filter(function (c) { return c.key === v; })[0];
        selects.push({ label: o.label, desc: o.desc || '', choices: (o.choices || []).map(function (c) { return { label: c.label + (c.units ? ' · + ' + money(cmp, c.units * o.price) : ''), style: chip(c.key === v), on: function (e) { if (e && e.preventDefault) e.preventDefault(); set(o.key, c.key); } }; }) });
        rows.push({ k: o.label, v: (cur ? cur.label : '—') + (add ? ' · + ' + money(cmp, add) : '') });
        if (q > 0) options[o.key] = v;
      } else {
        toggles.push({ label: o.label, desc: o.desc || '', price: '+ ' + money(cmp, o.price) + _(' / měs.', ' / mo'), style: chip(!!v), on: function (e) { if (e && e.preventDefault) e.preventDefault(); set(o.key, !v); } });
        if (v) { rows.push({ k: o.label, v: '+ ' + money(cmp, add) }); options[o.key] = true; }
      }
    });
    var name = (b.name || _('Webhosting na míru', 'Custom web hosting')) + ' · ' + summary.slice(0, 3).join(' / ');
    return {
      title: _('Poskládejte si tarif na míru', 'Build your own plan'), lead: b.lead || '',
      summary: _('Souhrn konfigurace', 'Configuration summary'), baseLabel: _('Základ tarifu', 'Plan base'), basePrice: money(cmp, Number(b.base_price) || 0),
      sliders: sliders, hasToggles: toggles.length > 0, toggles: toggles, togglesLabel: _('Volitelné funkce', 'Optional features'), hasSelects: selects.length > 0, selects: selects, rows: rows,
      price: money(cmp, total), unit: _('/ měsíc', '/ month'), vat: _('s DPH ', 'incl. VAT ') + money(cmp, total * (1 + vatRate())),
      note: _('Cena vychází z ceníku parametrů a přepočítává se okamžitě. Konfiguraci změníte kdykoli v panelu, účtujeme měsíčně a bez závazku.', 'The price follows the parameter price list and updates instantly. Change the configuration any time in the panel; billed monthly with no commitment.'),
      cta: _('Objednat tarif na míru', 'Order the custom plan'),
      buy: function (e) { if (e && e.preventDefault) e.preventDefault(); cmp.addToCart(name, Math.round(total * 100) / 100, { product_key: b.product_key, plan_key: b.plan_key, config: { options: options } }); }
    };
  }
  function extra(cmp, slug, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    var ov = overlay(cs)[slug] || {}, s = (cmp && cmp.state) || {};
    var out = { hasCmp: false, hasDetails: false, hasAddons: false, hasBuilder: false, cmp: { cols: [] }, cmpRows: [], detailCols: [], detailRows: [], addons: [], builder: { sliders: [], toggles: [], selects: [], rows: [] } };
    if (ov.cmp && ov.cmp.rows && ov.cmp.rows.length) {
      out.hasCmp = true; out.cmpTitle = ov.cmpTitle || _('Srovnání tarifů řádek po řádku', 'Plans compared, row by row'); out.cmpFirst = _('Parametr', 'Parameter');
      out.cmp = { cols: ov.cmp.cols }; out.cmpRows = ov.cmp.rows.map(function (r) { return { label: r[0], cells: r.slice(1) }; });
    }
    if (ov.details && ov.details.rows && ov.details.rows.length) {
      var open = !!s.svcDetails;
      out.hasDetails = true; out.detailsOpen = open; out.detailsFirst = _('Parametr', 'Parameter');
      out.detailsTitle = _('Kompletní konfigurace tarifů', 'Complete plan configuration');
      out.detailsLead = _('Každý parametr, který tarif má — limity, verze PHP, zálohy, podpora. Nic není schované v hvězdičce.', 'Every parameter a plan has — limits, PHP versions, backups, support. Nothing hidden behind an asterisk.');
      out.detailsToggle = open ? _('Skrýt kompletní konfiguraci', 'Hide the complete configuration') : _('Zobrazit kompletní konfiguraci (' + ov.details.rows.length + ' parametrů)', 'Show the complete configuration (' + ov.details.rows.length + ' parameters)');
      out.detailsOn = function (e) { if (e && e.preventDefault) e.preventDefault(); cmp.setState({ svcDetails: !open }); };
      out.detailCols = ov.details.cols; out.detailRows = ov.details.rows.map(function (r) { return { label: r[0], cells: r.slice(1) }; });
    }
    if (ov.addons && ((ov.addons.options || []).length || (ov.addons.products || []).length)) {
      out.hasAddons = true;
      out.addonsTitle = _('Příplatkové služby k tarifu', 'Add-ons for the plan');
      out.addonsLead = _('Doplňky vybíráte v košíku u každé položky zvlášť. Cena se přičte k měsíční platbě tarifu a zrušit je můžete kdykoli v klientském panelu.', 'Add-ons are picked in the cart per item. The price is added to the monthly plan price and you can cancel them any time in the client panel.');
      out.addons = (ov.addons.options || []).map(function (o) { return { name: o.label, desc: o.desc || '', price: unitLabel(cmp, o, _) }; })
        .concat((ov.addons.products || []).map(function (p) { var cheapest = (p.plans || []).slice().sort(function (a, b) { return a.price - b.price; })[0]; return { name: p.name, desc: p.desc || '', price: cheapest ? _('od ', 'from ') + money(cmp, cheapest.price) + (cheapest.unit_year ? _(' / rok', ' / yr') : _(' / měs.', ' / mo')) : '' }; }));
    }
    if (ov.builder && ov.builder.options && ov.builder.options.length && cmp) {
      out.hasBuilder = true; out.builder = builder(cmp, ov.builder, cs);
    }
    return out;
  }

  window.OnhostSvcPages = {
    /* wraps a prototype page module (`webPages(cs)` …) so its pages carry catalogue plans */
    wrap: function (fn) { return function (cs) { var pages = fn(cs) || {}; var ov = overlay(cs); try { apply(pages, ov); } catch (e) { /* the overlay failed: the honesty pass below still runs */ } try { return honest(pages, ov, cs); } catch (e) { return pages; } }; },
    /* SKU of a cart line added from a product page (`crumb · plan`), used by the checkout bridge */
    sku: function (name, price) { var k = String(name || '').toLowerCase(); return registry[k + '|' + Math.round(price || 0)] || registry[k] || null; },
    /* comparison, complete parameters, add-ons and the configurator of one product page */
    extra: function (cmp, slug, cs) { try { return extra(cmp, slug, cs); } catch (e) { return { hasCmp: false, hasDetails: false, hasAddons: false, hasBuilder: false }; } }
  };
})();
