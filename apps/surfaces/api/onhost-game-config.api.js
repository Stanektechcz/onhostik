/* ONhost public site — game server configurator (audit §5v, docs/ui/data-seams.md #23).
 *
 * The customer picks a game first (every template the game panel really offers, with its "from" price), then sizes
 * the server with sliders — RAM, vCPU, NVMe, backups, ports, databases. Each game lifts the sliders' minimum to its
 * floors (Minecraft Paper 2 GB RAM, CS2 2 vCPU …) and the slot count follows the RAM. Prices are the catalogue's
 * per-unit options of the `game` product (`window.ONHOST_DATA.gameConfig(cs)`), the same arithmetic the quote uses:
 * base plan + (value − default) × unit price. The cart line carries the SKU with `config: {egg, version, environment,
 * options}`, so the checkout never guesses. */
(function () {
  if (window.OnhostGameConfig) return; // the prototype runtime executes helmet scripts twice
  function data(cs) { var D = window.ONHOST_DATA; return D && typeof D.gameConfig === 'function' ? D.gameConfig(cs) : null; }
  function chip(active) {
    return 'border:2px solid ' + (active ? 'var(--acc,#ec3013)' : 'color-mix(in srgb,var(--fg,#201e1d) 30%,transparent)') + ';background:' + (active ? 'color-mix(in srgb,var(--acc,#ec3013) 10%,transparent)' : 'transparent') +
      ';color:var(--fg,#201e1d);cursor:pointer;font-family:var(--font-heading);font-weight:800;font-size:12px;letter-spacing:.04em;padding:8px 12px;text-align:left';
  }
  // prices with VAT are shown in whole crowns (the cart and the invoice carry the exact amount)
  function gross(cmp, v) { return cmp && typeof cmp.czk === 'function' ? cmp.czk(Math.round(v * 1.21) / 1.21) : (Math.round(v * 1.21).toLocaleString('cs-CZ') + ' Kč'); }
  function net(cmp, v) { return cmp && typeof cmp.mny === 'function' ? cmp.mny(v) : (Math.round(v).toLocaleString('cs-CZ') + ' Kč'); }
  function snap(o, v) {
    var step = Number(o.step) || 1, min = o.min != null ? Number(o.min) : 0, max = o.max != null ? Number(o.max) : Infinity;
    v = min + Math.ceil(Math.max(0, Number(v) - min) / step) * step;
    return Math.min(max, v);
  }
  function state(cmp) { return (cmp && cmp.state && cmp.state.gameCfg) || {}; }
  function update(cmp, patch) { cmp.setState({ gameCfg: Object.assign({}, state(cmp), patch) }); }
  function scrollTo() { try { var el = document.getElementById('game-config'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* no DOM */ } }

  /* the configuration of one game: every slider at least at the game's floor */
  function values(g, d, st) {
    var chosen = (st.egg === g.key && st.opts) || {}, out = {};
    (d.options || []).forEach(function (o) {
      var floor = (g.min && g.min[o.key] != null) ? Number(g.min[o.key]) : (o['default'] != null ? Number(o['default']) : Number(o.min) || 0);
      out[o.key] = snap(o, Math.max(floor, chosen[o.key] != null ? Number(chosen[o.key]) : floor));
    });
    return out;
  }
  function priceOf(d, vals) {
    return (d.options || []).reduce(function (t, o) { return t + Math.max(0, (vals[o.key] || 0) - (o['default'] != null ? Number(o['default']) : 0)) * (Number(o.price) || 0); }, Number(d.base_price) || 0);
  }

  function view(cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    var d = data(cs), empty = { has: false, hasGame: false, games: [], sliders: [], rows: [], versions: [], inputs: [] };
    if (!d || !(d.games || []).length) return empty;
    var st = state(cmp), g = (d.games || []).filter(function (x) { return x.key === st.egg; })[0] || null;
    var out = {
      has: true, hasGame: !!g, noGame: !g, anchor: 'game-config',
      title: _('Vyberte hru a nastavte si server', 'Pick a game and size your server'),
      lead: _('Nejdřív hra, potom výkon. Minimum hlídáme za vás podle toho, co hra opravdu potřebuje, cenu počítáme z ceníku parametrů.', 'The game first, then the power. We keep the minimum the game really needs; the price follows the parameter price list.'),
      gamesLabel: _('1. Hra', '1. Game'), paramsLabel: _('2. Parametry serveru', '2. Server parameters'),
      pickFirst: _('Vyberte hru — posuvníky se nastaví na její minimum.', 'Pick a game — the sliders start at its minimum.'),
      games: (d.games || []).map(function (x) {
        return { label: x.label + ' · ' + _('od ', 'from ') + gross(cmp, x.from), style: chip(g && g.key === x.key), on: function (e) { if (e && e.preventDefault) e.preventDefault(); update(cmp, { egg: x.key, opts: {}, version: null, env: {} }); } };
      }),
      sliders: [], rows: [], versions: [], inputs: [], hasVersions: false, hasInputs: false,
      summary: _('Souhrn konfigurace', 'Configuration summary'), unit: _('/ měsíc s DPH', '/ month incl. VAT'),
      note: _('Parametry změníte kdykoli v panelu, účtujeme měsíčně a bez závazku. Sloty jsou orientační podle RAM a hry.', 'Change the parameters any time in the panel; billed monthly with no commitment. Slots are indicative from the RAM and the game.'),
      cta: _('Objednat server', 'Order the server')
    };
    if (!g) { out.price = ''; out.net = ''; return out; }
    var vals = values(g, d, st), total = priceOf(d, vals);
    (d.options || []).forEach(function (o) {
      var min = g.min && g.min[o.key] != null ? Number(g.min[o.key]) : (Number(o.min) || 0), unit = o.unit ? ' ' + o.unit : '';
      out.sliders.push({ label: o.label, value: vals[o.key] + unit, raw: vals[o.key], min: min, max: o.max != null ? o.max : 100, step: o.step || 1, minLabel: min + unit, maxLabel: (o.max != null ? o.max : 100) + unit,
        on: function (e) { var next = Object.assign({}, values(g, d, state(cmp))); next[o.key] = Number(e.target.value); update(cmp, { egg: g.key, opts: next }); } });
      var extra = Math.max(0, vals[o.key] - (o['default'] != null ? Number(o['default']) : 0)) * (Number(o.price) || 0);
      out.rows.push({ k: o.label, v: vals[o.key] + unit + (extra ? ' · + ' + net(cmp, extra) : '') });
    });
    if (g.slot_mb) out.rows.push({ k: _('Sloty (orientačně)', 'Slots (indicative)'), v: '≈ ' + Math.floor((vals.ram_gb || 1) * 1024 / g.slot_mb) });
    out.rows.unshift({ k: _('Hra', 'Game'), v: g.label });
    var version = st.egg === g.key ? st.version : null;
    if ((g.versions || []).length) {
      out.hasVersions = true; out.versionsLabel = _('Verze', 'Version');
      out.versions = [{ key: null, label: _('Nejnovější', 'Latest') }].concat(g.versions.map(function (v) { return { key: v, label: v }; })).map(function (v) {
        return { label: v.label, style: chip((version || null) === v.key), on: function (e) { if (e && e.preventDefault) e.preventDefault(); update(cmp, { version: v.key }); } };
      });
    }
    var env = (st.egg === g.key && st.env) || {}, missing = [];
    (g.inputs || []).forEach(function (i) {
      var value = String(env[i.env] || '');
      var ok = value !== '' && (!i.pattern || new RegExp('^(?:' + i.pattern + ')$').test(value)) && (i.min == null || value.length >= i.min) && (i.max == null || value.length <= i.max);
      if (!ok) missing.push(i.label);
      out.inputs.push({ label: i.label, hint: i.hint || '', value: value, help: i.help_url || '', hasHelp: !!i.help_url, maxlength: i.max || 200,
        on: function (e) { var next = Object.assign({}, state(cmp).env || {}); next[i.env] = String(e.target.value || '').trim(); update(cmp, { egg: g.key, env: next }); } });
    });
    out.hasInputs = out.inputs.length > 0; out.inputsLabel = _('Údaje pro hru', 'Game settings');
    out.price = gross(cmp, total); out.net = _('bez DPH ', 'excl. VAT ') + net(cmp, total);
    out.blocked = missing.length ? _('Doplňte: ', 'Fill in: ') + missing.join(', ') : '';
    out.buy = function (e) {
      if (e && e.preventDefault) e.preventDefault();
      if (missing.length) return;
      var config = { egg: g.key, options: vals };
      if (version) config.version = version;
      if (Object.keys(env).length) config.environment = env;
      var name = (cs ? 'Gamehosting ' : 'Game hosting ') + g.label + ' · ' + vals.ram_gb + ' GB / ' + vals.vcpu + ' vCPU / ' + vals.nvme_gb + ' GB';
      cmp.addToCart(name, Math.round(total * 100) / 100, { product_key: d.product_key, plan_key: d.plan_key, config: config });
    };
    return out;
  }

  window.OnhostGameConfig = {
    view: function (cmp, cs) { try { return view(cmp, cs); } catch (e) { return { has: false, hasGame: false, games: [], sliders: [], rows: [], versions: [], inputs: [] }; } },
    /* a landing card's button: select the game in the configurator and scroll to it */
    pickOn: function (cmp, egg) { return function (e) { if (e && e.preventDefault) e.preventDefault(); update(cmp, { egg: egg, opts: {}, version: null, env: {} }); setTimeout(scrollTo, 30); }; },
    labels: function (cs) { var d = data(cs); return d ? (d.games || []).map(function (g) { return g.label; }) : []; }
  };
})();
