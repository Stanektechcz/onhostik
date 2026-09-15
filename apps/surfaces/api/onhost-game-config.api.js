/* ONhost public site — game server offer and configurator (audit §5v/§5w, docs/ui/data-seams.md #23).
 *
 * Step 1 — the game: tiles of every game the panel really offers (all Minecraft Java flavours are one "Minecraft Java
 * Edition" tile, Bedrock is its own), filtered by category or a search, each with its artwork and "Již od" price.
 * Step 2 — the server: the server type (Paper, Forge, BungeeCord …) where a game has more, quick size presets and
 * sliders for RAM, vCPU, NVMe, backups, ports and databases that never go below the game's floors, the version and
 * the inputs a game needs (the Steam token of CS2). The summary prices exactly like the quote: base plan +
 * (value − default) × unit price from `window.ONHOST_DATA.gameConfig(cs)`; the cart line carries the SKU with
 * `config: {egg, version, environment, options}`. */
(function () {
  if (window.OnhostGameConfig) return; // the prototype runtime executes helmet scripts twice
  var ACC = 'var(--acc,#ec3013)', FG = 'var(--fg,#201e1d)', LINE = 'color-mix(in srgb,var(--fg,#201e1d) 22%,transparent)';
  var TILE_ART = { // games without artwork get a drawn tile
    'minecraft-java': 'background:linear-gradient(180deg,#6aa84f 0 34%,#8b5a2b 34% 100%);',
    'minecraft-bedrock': 'background:linear-gradient(180deg,#3d9ab8 0 34%,#5b6770 34% 100%);',
    hytale: 'background:linear-gradient(135deg,#2b2f6b,#6a3fa0 60%,#d7a63a);'
  };
  var ART_TEXT = { 'minecraft-java': 'JAVA EDITION', 'minecraft-bedrock': 'BEDROCK EDITION', hytale: 'HYTALE' };
  function data(cs) { var D = window.ONHOST_DATA; return D && typeof D.gameConfig === 'function' ? D.gameConfig(cs) : null; }
  function chip(active) {
    return 'border:2px solid ' + (active ? ACC : LINE) + ';background:' + (active ? 'color-mix(in srgb,var(--acc,#ec3013) 10%,transparent)' : 'transparent') +
      ';color:' + FG + ';cursor:pointer;font-family:var(--font-heading);font-weight:800;font-size:13px;letter-spacing:.02em;padding:9px 14px;text-align:left;white-space:nowrap';
  }
  function card(active) {
    return 'display:flex;flex-direction:column;gap:3px;text-align:left;cursor:pointer;border:2px solid ' + (active ? ACC : LINE) + ';background:' + (active ? 'color-mix(in srgb,var(--acc,#ec3013) 8%,var(--bg,#f3f2f2))' : 'var(--bg,#f3f2f2)') +
      ';color:' + FG + ';padding:12px 14px;font-family:inherit;min-width:0';
  }
  // prices with VAT in whole crowns (the cart and the invoice carry the exact amount)
  function gross(cmp, v) { return cmp && typeof cmp.czk === 'function' ? cmp.czk(Math.round(v * 1.21) / 1.21) : (Math.round(v * 1.21).toLocaleString('cs-CZ') + ' Kč'); }
  function net(cmp, v) { return cmp && typeof cmp.mny === 'function' ? cmp.mny(v) : (Math.round(v).toLocaleString('cs-CZ') + ' Kč'); }
  function snap(o, v) {
    var step = Number(o.step) || 1, min = o.min != null ? Number(o.min) : 0, max = o.max != null ? Number(o.max) : Infinity;
    v = min + Math.ceil(Math.max(0, Number(v) - min) / step) * step;
    return Math.min(max, v);
  }
  function state(cmp) { return (cmp && cmp.state && cmp.state.gameCfg) || {}; }
  function update(cmp, patch) { cmp.setState({ gameCfg: Object.assign({}, state(cmp), patch) }); }
  function scrollTo(id) { setTimeout(function () { try { var el = document.getElementById(id); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* no DOM */ } }, 80); }
  function stop(e) { if (e && e.preventDefault) e.preventDefault(); }

  function floorOf(g, o) { return (g.min && g.min[o.key] != null) ? Number(g.min[o.key]) : (o['default'] != null ? Number(o['default']) : Number(o.min) || 0); }
  function values(g, d, st) {
    var chosen = (st.egg === g.key && st.opts) || {}, out = {};
    (d.options || []).forEach(function (o) { var f = floorOf(g, o); out[o.key] = snap(o, Math.max(f, chosen[o.key] != null ? Number(chosen[o.key]) : f)); });
    return out;
  }
  function priceOf(d, vals) {
    return (d.options || []).reduce(function (t, o) { return t + Math.max(0, (vals[o.key] || 0) - (o['default'] != null ? Number(o['default']) : 0)) * (Number(o.price) || 0); }, Number(d.base_price) || 0);
  }
  function presetsFor(g, d) { // three sizes a player understands, all above the game's floors
    var byKey = {}; (d.options || []).forEach(function (o) { byKey[o.key] = o; });
    var m = function (k) { return byKey[k] ? floorOf(g, byKey[k]) : 0; };
    var make = function (ram, vcpu, disk, backups, ports) {
      var v = {};
      (d.options || []).forEach(function (o) { v[o.key] = snap(o, m(o.key)); });
      if (byKey.ram_gb) v.ram_gb = snap(byKey.ram_gb, ram);
      if (byKey.vcpu) v.vcpu = snap(byKey.vcpu, vcpu);
      if (byKey.nvme_gb) v.nvme_gb = snap(byKey.nvme_gb, disk);
      if (byKey.backups) v.backups = snap(byKey.backups, backups);
      if (byKey.allocations) v.allocations = snap(byKey.allocations, ports);
      return v;
    };
    return [
      ['start', make(m('ram_gb'), m('vcpu'), m('nvme_gb'), m('backups'), m('allocations'))],
      ['community', make(Math.max(m('ram_gb') * 2, m('ram_gb') + 2), m('vcpu') + 1, m('nvme_gb') * 2, 3, m('allocations'))],
      ['pro', make(m('ram_gb') * 4, m('vcpu') + 3, m('nvme_gb') * 4, 7, Math.max(2, m('allocations')))]
    ];
  }
  function same(a, b) { return Object.keys(a).every(function (k) { return Number(a[k]) === Number(b[k]); }); }

  function view(cmp, cs) {
    var _ = function (a, b) { return cs ? a : b; };
    var d = data(cs), empty = { has: false, hasGame: false, tiles: [], tabs: [], sliders: [], rows: [], versions: [], inputs: [], variants: [], presets: [], perks: [] };
    if (!d || !(d.groups || []).length) return empty;
    var st = state(cmp), games = d.games || [], byEgg = {};
    games.forEach(function (x) { byEgg[x.key] = x; });
    var cat = st.cat || 'all', q = String(st.q || '').trim().toLowerCase();
    var group = (d.groups || []).filter(function (x) { return x.key === st.group || (st.egg && x.eggs.indexOf(st.egg) >= 0); })[0] || null;
    var g = group ? (byEgg[st.egg] && group.eggs.indexOf(st.egg) >= 0 ? byEgg[st.egg] : byEgg[group.eggs[0]]) : null;
    var catLabel = {}; (d.categories || []).forEach(function (c) { catLabel[c.key] = c.label; });
    var artStyle = function (x) { return 'position:relative;aspect-ratio:460/215;overflow:hidden;display:flex;align-items:flex-end;' + (x.art ? 'background:#1a1918;' : (TILE_ART[x.key] || 'background:linear-gradient(135deg,#1a1918,#4a4644);')); };

    var tiles = (d.groups || []).filter(function (x) {
      return (cat === 'all' || x.category === cat) && (!q || (x.label + ' ' + x.note).toLowerCase().indexOf(q) >= 0);
    }).map(function (x) {
      var active = !!(group && group.key === x.key), multi = x.eggs.length > 1;
      return {
        label: x.label, note: x.note, cat: catLabel[x.category] || '', from: gross(cmp, x.from), fromLabel: _('Již od', 'From'), per: _('/měs', '/mo'),
        art: x.art || '', hasArt: !!x.art, noArt: !x.art, artStyle: artStyle(x), artText: ART_TEXT[x.key] || x.label,
        badge: multi ? (x.eggs.length + _(' typů serveru', ' server types')) : '', hasBadge: multi,
        cta: active ? _('Vybráno ✓', 'Selected ✓') : _('Konfigurovat', 'Configure'),
        ctaStyle: 'font-family:var(--font-heading);font-weight:800;font-size:13px;padding:8px 12px;white-space:nowrap;' + (active ? 'background:' + ACC + ';color:#f3f2f2' : 'border:2px solid ' + FG + ';color:' + FG),
        style: 'display:flex;flex-direction:column;text-decoration:none;color:' + FG + ';background:var(--bg,#f3f2f2);border:2px solid ' + (active ? ACC : LINE) + ';min-width:0',
        on: function (e) { stop(e); update(cmp, { group: x.key, egg: x.eggs[0], opts: {}, version: null, env: {} }); scrollTo('game-config'); }
      };
    });
    var out = {
      has: true, hasGame: !!g, noGame: !g, goOffer: function (e) { stop(e); scrollTo('game-offer'); },
      kicker: _('Gamehosting', 'Game hosting'),
      title: _('Vyberte hru', 'Pick your game'),
      lead: _('Každá hra má svoje minimum a cenu. Klikněte na hru, zvolte typ serveru a velikost — cenu vidíte hned.', 'Every game has its own minimum and price. Click a game, pick the server type and size — the price updates instantly.'),
      tabs: [{ key: 'all', label: _('Všechny hry', 'All games') }].concat(d.categories || []).map(function (c) {
        var n = c.key === 'all' ? (d.groups || []).length : (d.groups || []).filter(function (x) { return x.category === c.key; }).length;
        return { label: c.label + ' · ' + n, style: chip(cat === c.key), on: function (e) { stop(e); update(cmp, { cat: c.key }); } };
      }),
      searchPh: _('Hledat hru…', 'Search games…'), q: st.q || '', onSearch: function (e) { update(cmp, { q: e.target.value }); },
      tiles: tiles, noTiles: tiles.length === 0, noTilesText: _('Takovou hru zatím nenabízíme. Napište nám, přidáme ji.', 'We do not offer that game yet. Tell us and we will add it.'),
      sliders: [], rows: [], versions: [], inputs: [], variants: [], presets: [], hasVariants: false, hasVersions: false, hasInputs: false,
      summary: _('Váš server', 'Your server'), unit: _('/ měsíc s DPH', '/ month incl. VAT'),
      note: _('Parametry změníte kdykoli v panelu, účtujeme měsíčně a bez závazku. Počet hráčů je orientační.', 'Change the parameters any time in the panel; billed monthly with no commitment. Player count is indicative.'),
      cta: _('Přidat do košíku', 'Add to cart'), change: _('← Změnit hru', '← Change game'),
      onChange: function (e) { stop(e); scrollTo('game-offer'); },
      perks: [_('Anti-DDoS ochrana v ceně', 'Anti-DDoS included'), _('Konzole, SFTP a plánované restarty', 'Console, SFTP and scheduled restarts'), _('Servery v Praze, podpora 24/7', 'Servers in Prague, 24/7 support')]
    };
    if (!g) { out.price = ''; out.net = ''; return out; }

    out.gameTitle = group.label; out.gameNote = group.note; out.gameArt = group.art || ''; out.gameHasArt = !!group.art; out.gameNoArt = !group.art; out.gameArtStyle = artStyle(group) + 'width:168px;flex:0 0 auto;';
    if (group.eggs.length > 1) {
      out.hasVariants = true;
      out.variants = group.eggs.map(function (k) {
        var x = byEgg[k]; if (!x) return null;
        return { name: x.variant || x.label, note: x.variant_note || x.note, from: _('od ', 'from ') + gross(cmp, x.from), style: card(x.key === g.key),
          on: function (e) { stop(e); update(cmp, { group: group.key, egg: x.key, opts: {}, version: null, env: {} }); } };
      }).filter(Boolean);
    }
    var vals = values(g, d, st), total = priceOf(d, vals), n = out.hasVariants ? 2 : 1;
    out.stepType = _('1 · Typ serveru', '1 · Server type');
    out.stepSize = n + ' · ' + _('Velikost serveru', 'Server size'); out.stepFine = (n + 1) + ' · ' + _('Doladit parametry', 'Fine-tune');
    var names = { start: [_('Pro pár kamarádů', 'A few friends'), _('minimum hry', 'game minimum')], community: [_('Komunita', 'Community'), _('nejčastější volba', 'most chosen')], pro: [_('Velký server', 'Big server'), _('mody a hodně hráčů', 'mods and many players')] };
    out.presets = presetsFor(g, d).map(function (p) {
      var v = p[1], slots = g.slot_mb ? Math.floor((v.ram_gb || 1) * 1024 / g.slot_mb) : null;
      return { name: names[p[0]][0], sub: v.ram_gb + ' GB RAM · ' + v.vcpu + ' vCPU · ' + v.nvme_gb + ' GB' + (slots ? ' · ≈ ' + slots + _(' hráčů', ' players') : ''), hint: names[p[0]][1], price: gross(cmp, priceOf(d, v)) + _(' /měs', ' /mo'),
        style: card(same(v, vals)), on: function (e) { stop(e); update(cmp, { group: group.key, egg: g.key, opts: v }); } };
    });
    (d.options || []).forEach(function (o) {
      var min = floorOf(g, o), unit = o.unit ? ' ' + o.unit : '', max = o.max != null ? o.max : 100;
      out.sliders.push({ label: o.label, desc: o.desc || '', value: vals[o.key] + unit, raw: vals[o.key], min: min, max: max, step: o.step || 1, minLabel: min + unit, maxLabel: max + unit,
        on: function (e) { var next = Object.assign({}, values(g, d, state(cmp))); next[o.key] = Number(e.target.value); update(cmp, { group: group.key, egg: g.key, opts: next }); } });
      var extra = Math.max(0, vals[o.key] - (o['default'] != null ? Number(o['default']) : 0)) * (Number(o.price) || 0);
      out.rows.push({ k: o.label, v: vals[o.key] + unit + (extra ? ' · + ' + net(cmp, extra) : '') });
    });
    out.rows.unshift({ k: _('Hra', 'Game'), v: group.label + (g.variant && group.eggs.length > 1 ? ' · ' + g.variant : '') });
    if (g.slot_mb) out.rows.splice(1, 0, { k: _('Hráči (orientačně)', 'Players (indicative)'), v: '≈ ' + Math.floor((vals.ram_gb || 1) * 1024 / g.slot_mb) });
    var version = st.egg === g.key ? st.version : null;
    if ((g.versions || []).length) {
      out.hasVersions = true; out.versionsLabel = _('Verze hry', 'Game version');
      out.versions = [{ key: null, label: _('Nejnovější', 'Latest') }].concat(g.versions.map(function (v) { return { key: v, label: v }; })).map(function (v) {
        return { label: v.label, style: chip((version || null) === v.key), on: function (e) { stop(e); update(cmp, { version: v.key }); } };
      });
      if (version) out.rows.splice(1, 0, { k: _('Verze', 'Version'), v: version });
    }
    var env = (st.egg === g.key && st.env) || {}, missing = [];
    (g.inputs || []).forEach(function (i) {
      var value = String(env[i.env] || '');
      var ok = value !== '' && (!i.pattern || new RegExp('^(?:' + i.pattern + ')$').test(value)) && (i.min == null || value.length >= i.min) && (i.max == null || value.length <= i.max);
      if (!ok) missing.push(i.label);
      out.inputs.push({ label: i.label, hint: i.hint || '', value: value, help: i.help_url || '', hasHelp: !!i.help_url, maxlength: i.max || 200,
        on: function (e) { var next = Object.assign({}, state(cmp).env || {}); next[i.env] = String(e.target.value || '').trim(); update(cmp, { group: group.key, egg: g.key, env: next }); } });
    });
    out.hasInputs = out.inputs.length > 0; out.inputsLabel = _('Údaje, které hra potřebuje', 'What the game needs');
    out.price = gross(cmp, total); out.net = _('bez DPH ', 'excl. VAT ') + net(cmp, total);
    out.blocked = missing.length ? _('Před objednáním doplňte: ', 'Fill in before ordering: ') + missing.join(', ') : '';
    out.ctaStyle = 'display:block;text-align:center;font-size:16px;padding:16px 22px;' + (missing.length ? 'opacity:.45;pointer-events:none' : '');
    out.buy = function (e) {
      stop(e);
      if (missing.length) return;
      var config = { egg: g.key, options: vals };
      if (version) config.version = version;
      if (Object.keys(env).length) config.environment = env;
      var name = (cs ? 'Gamehosting ' : 'Game hosting ') + group.label + (g.variant && group.eggs.length > 1 ? ' · ' + g.variant : '') + ' · ' + vals.ram_gb + ' GB / ' + vals.vcpu + ' vCPU / ' + vals.nvme_gb + ' GB';
      cmp.addToCart(name, Math.round(total * 100) / 100, { product_key: d.product_key, plan_key: d.plan_key, config: config });
    };
    return out;
  }

  window.OnhostGameConfig = {
    view: function (cmp, cs) { try { return view(cmp, cs); } catch (e) { return { has: false, hasGame: false, tiles: [], tabs: [], sliders: [], rows: [], versions: [], inputs: [], variants: [], presets: [], perks: [] }; } },
    /* a card elsewhere (the home page): open the game page with the game selected */
    select: function (cmp, group, egg) { update(cmp, { group: group, egg: egg, opts: {}, version: null, env: {} }); scrollTo('game-config'); },
    pickOn: function (cmp, egg) { return function (e) { stop(e); update(cmp, { egg: egg, opts: {}, version: null, env: {} }); scrollTo('game-config'); }; },
    labels: function (cs) { var d = data(cs); return d ? (d.groups || []).map(function (g) { return g.label; }) : []; }
  };
})();
