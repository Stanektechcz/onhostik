/* Onhost shell — sdílená session mezi plochami + přepínač produktů.
   Načítá se v <helmet> každé plochy; sám se připojí do document.body. */
(function () {
  if (window.OnhostSession) return;

  /* Vnořený režim: plocha běží jako modul uvnitř sjednocené stránky (?embed=1).
     Tam nemá co dělat přepínač, gate ani paléta — chrome patří hostiteli. */
  var EMBED = /[?&]embed=1/.test(location.search);
  window.OnhostEmbedded = EMBED;

  /* Doplňkové skripty: plocha stačí, když načte shell — zbytek si dotáhne sám. */
  var BASE = '';
  try {
    var me = document.currentScript && document.currentScript.src;
    BASE = me ? me.replace(/[^/]*$/, '') : '';
  } catch (e) {}
  function need(src) {
    try {
      var s = document.createElement('script');
      s.src = BASE + src;
      (document.head || document.documentElement).appendChild(s);
    } catch (e) {}
  }
  if (!window.OnhostStore) need('onhost-store.js');
  if (!EMBED) need('onhost-command.js');

  var K = { session: 'onhost.session', cart: 'onhost.cart', hand: 'onhost.handoff', seen: 'onhost.seen', role: 'onhost.role' };

  function read(key, fallback) {
    try { var r = localStorage.getItem(key); return r ? JSON.parse(r) : fallback; }
    catch (e) { return fallback; }
  }
  function write(key, val) {
    try { if (val == null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(val)); }
    catch (e) {}
  }

  var subs = [];
  var Session = {
    keys: K,
    user: function () { return read(K.session, null); },
    signIn: function (email, name) {
      var u = { email: email, name: name || String(email).split('@')[0], since: Date.now() };
      write(K.session, u); emit(); return u;
    },
    signOut: function () { write(K.session, null); emit(); },
    cart: function () { return read(K.cart, null); },
    cartCount: function () {
      var c = this.cart(); if (!c || !c.items) return 0;
      return c.items.reduce(function (n, i) { return n + (i.qty || 1); }, 0);
    },
    /* Předání kontextu mezi plochami: např. web → panel, panel → admin. */
    handoff: function (payload) {
      if (payload === undefined) return read(K.hand, null);
      write(K.hand, payload ? Object.assign({ at: Date.now() }, payload) : null); emit();
    },
    takeHandoff: function () { var h = read(K.hand, null); if (h) write(K.hand, null); return h; },
    seen: function (id) {
      var s = read(K.seen, {});
      if (id === undefined) return s;
      s[id] = Date.now(); write(K.seen, s); emit(); return s;
    },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; },

    /* --- role a přístupy --- */
    roles: null, /* doplněno níž */
    role: function () {
      var id = read(K.role, 'admin');
      return ROLES.filter(function (r) { return r.id === id; })[0] || ROLES[0];
    },
    setRole: function (id) { write(K.role, id); emit(); return this.role(); },
    /* Má aktuální role přístup na plochu? */
    can: function (href) {
      var r = this.role();
      if (r.all) return true;
      var h = String(href || '').toLowerCase();
      return PUBLIC.indexOf(h) >= 0 || (r.s || []).indexOf(h) >= 0;
    },
    /* Smí role provést akci? pay | dunning | mail | incident | order | ticket | seed */
    may: function (act) {
      var r = this.role();
      return !!r.all || (r.acts || []).indexOf(act) >= 0;
    },
    /* Rozsah dat: null = vše, string = jen záznamy tohoto e-mailu, false = nic. */
    scope: function () {
      var r = this.role();
      if (!r.own) return null;
      var u = this.user();
      return u ? u.email : false;
    },
    rolesFor: function (href) {
      var self = this, cur = read(K.role, 'admin');
      return ROLES.filter(function (r) {
        if (r.id === cur) return false;
        var h = String(href || '').toLowerCase();
        return r.all || PUBLIC.indexOf(h) >= 0 || (r.s || []).indexOf(h) >= 0;
      });
    }
  };

  /* Plochy dostupné každé roli — marketing, dokumentace a stavová stránka. */
  var PUBLIC = ['onhost.dc.html', 'onhost-widgets.dc.html'];
  var ROLES = [
    { id: 'admin', label: 'Admin', d: 'Všechny plochy a všechny akce', all: true,
      acts: ['pay', 'dunning', 'mail', 'incident', 'order', 'ticket', 'seed'] },
    { id: 'klient', label: 'Klient', d: 'Jen vlastní služby, faktury a tickety', own: true,
      s: ['onhost-app.dc.html', 'onhost-mobil.dc.html'], acts: ['pay', 'ticket'] },
    { id: 'partner', label: 'Partner', d: 'Provize, klienti pod partnerem, whitelabel', own: true,
      s: ['onhost-partner.dc.html', 'onhost-app.dc.html', 'onhost-mobil.dc.html'], acts: ['ticket'] },
    { id: 'noc', label: 'NOC', d: 'Dohled, incidenty, postmortem',
      s: ['onhost-admin.dc.html', 'onhost-mobil.dc.html'],
      acts: ['incident', 'ticket'] },
    { id: 'fakturace', label: 'Fakturace', d: 'Faktury, upomínky, výnosy',
      s: ['onhost-admin.dc.html'],
      acts: ['pay', 'dunning', 'mail'] }
  ];
  Session.roles = ROLES;
  window.OnhostRoles = ROLES;
  window.OnhostPublic = PUBLIC;
  function emit() { subs.slice().forEach(function (f) { try { f(Session); } catch (e) {} }); }
  /* Store si o svých změnách informuje sám (OnhostStore.on). Kdyby shell emitoval
     i na jeho klíče, každý zápis by překreslil všechny plochy ve všech rámech. */
  window.addEventListener('storage', function (e) {
    if (!e.key || e.key.indexOf('onhost.') !== 0) return;
    if (e.key.indexOf('onhost.ops') === 0) return;
    emit();
  });
  window.OnhostSession = Session;

  /* Pět produkčních ploch prototypu. Každá odpovídá jedné oblasti Laravel
     aplikace; UI knihovna je nástroj pro vývojáře, ne produkt. */
  var SURFACES = [
    { g: 'Produkt', h: 'Onhost.dc.html', t: 'Prezentační web', d: 'Nabídka, ceník, katalog, obsah, stav služeb, košík', k: 'W' },
    { g: 'Produkt', h: 'Onhost-app.dc.html', t: 'Klientská sekce', d: 'Služby, panely, domény, zálohy, doklady, tickety, asistent', k: 'P' },
    { g: 'Produkt', h: 'Onhost-admin.dc.html', t: 'Administrátorská sekce', d: 'Role, provoz, fakturace, NOC, integrace, reporty, maily', k: 'N' },
    { g: 'Produkt', h: 'Onhost-partner.dc.html', t: 'Partnerský portál', d: 'Provize, klienti, výplaty, whitelabel, materiály', k: 'R' },
    { g: 'Produkt', h: 'Onhost-mobil.dc.html', t: 'Mobilní aplikace', d: 'Výstrahy, metriky, konzole, doklady', k: 'M' },
    { g: 'Nástroje', h: 'Onhost-widgets.dc.html', t: 'UI knihovna', d: 'Komponenty a stavy jednoho vizuálního systému' }
  ];
  window.OnhostSurfaces = SURFACES;

  var here = (location.pathname.split('/').pop() || '').toLowerCase();

  function el(tag, style, text) {
    var n = document.createElement(tag);
    if (style) n.setAttribute('style', style);
    if (text != null) n.textContent = text;
    return n;
  }

  var ACC = '#ec3013', INK = '#201e1d', BG = '#f3f2f2';

  function OnhostSwitcher() {
    var host = el('div', 'position:fixed;inset:auto 0 0 0;z-index:2147483000;pointer-events:none;font-family:Archivo,system-ui,sans-serif');
    var open = false;

    var btn = el('button', [
      'pointer-events:auto;position:fixed;left:14px;bottom:14px;z-index:2147483001;width:34px;height:34px',
      'display:flex;align-items:center;justify-content:center;gap:0;border:2px solid ' + INK, 'background:' + ACC + ';color:#fff',
      'font:800 11px/1 Archivo,system-ui,sans-serif;cursor:pointer;border-radius:0;box-shadow:0 3px 0 rgba(32,30,29,.22)'
    ].join(';'));
    var dot = el('span', 'width:10px;height:10px;background:#fff;flex:0 0 auto');
    btn.appendChild(dot);
    var badge = el('span', 'display:none;position:absolute;left:100%;bottom:100%;transform:translate(-45%,45%);min-width:16px;height:16px;padding:0 4px;border:1px solid ' + INK + ';background:#fff;color:' + ACC + ';font:800 10px/14px Archivo,system-ui,sans-serif;text-align:center');
    btn.appendChild(badge);
    btn.title = 'Plochy Onhost — přepínač (Alt+O)';

    /* Placement: každá plocha má vlastní fixní chrome, takže kotvu vybíráme
       podle toho, kde launcher nepřekrývá nic klikatelného. */
    var ANCHORS = [
      { name:'bl', css:{ left:'14px', right:'auto', top:'auto', bottom:'14px', transform:'none' } },
      { name:'br', css:{ left:'auto', right:'14px', top:'auto', bottom:'14px', transform:'none' } },
      { name:'bl2', css:{ left:'14px', right:'auto', top:'auto', bottom:'62px', transform:'none' } },
      { name:'br2', css:{ left:'auto', right:'14px', top:'auto', bottom:'62px', transform:'none' } },
      { name:'bc', css:{ left:'50%', right:'auto', top:'auto', bottom:'12px', transform:'translateX(-50%)' } },
      /* Svislé kotvy: když je spodní hrana obsazená (rail, lišta tabů, vstup konzole),
         volno bývá u svislých hran — tam se přesuneme, než abychom sedli na tlačítko. */
      { name:'rmid', css:{ left:'auto', right:'14px', top:'50%', bottom:'auto', transform:'translateY(-50%)' } },
      { name:'rup', css:{ left:'auto', right:'14px', top:'28%', bottom:'auto', transform:'none' } },
      { name:'rlow', css:{ left:'auto', right:'14px', top:'72%', bottom:'auto', transform:'none' } },
      { name:'lmid', css:{ left:'14px', right:'auto', top:'50%', bottom:'auto', transform:'translateY(-50%)' } }
    ];
    function applyAnchor(a) {
      Object.keys(a.css).forEach(function (k) { btn.style[k] = a.css[k]; });
    }
    function interactiveAt(x, y) {
      var n = document.elementFromPoint(x, y);
      while (n && n !== document.body && n !== document.documentElement) {
        var tag = n.tagName;
        if (tag === 'BUTTON' || tag === 'A' || tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'LABEL') return tag;
        if (n.getAttribute('role') === 'button') return 'role:' + tag;
        var cur = '';
        try { cur = getComputedStyle(n).cursor; } catch (e) {}
        if (cur === 'pointer') return 'pointer:' + tag + '.' + String(n.className || '').slice(0, 12);
        n = n.parentElement;
      }
      return false;
    }
    function clean(a, trace) {
      applyAnchor(a);
      var r = btn.getBoundingClientRect();
      if (r.left < 0 || r.top < 0 || r.right > innerWidth || r.bottom > innerHeight) {
        if (trace) trace.push(a.name + ':offscreen ' + [r.left, r.top, r.right, r.bottom].map(Math.round).join(','));
        return false;
      }
      btn.style.visibility = 'hidden';
      var pts = [[r.left + 3, r.top + 3], [r.right - 3, r.top + 3], [r.left + 3, r.bottom - 3], [r.right - 3, r.bottom - 3], [r.left + r.width / 2, r.top + r.height / 2]];
      var why = null;
      var hit = pts.some(function (p) { var w = interactiveAt(p[0], p[1]); if (w) why = Math.round(p[0]) + ',' + Math.round(p[1]) + '=' + w; return !!w; });
      btn.style.visibility = '';
      if (trace) trace.push(a.name + (hit ? ':hit ' + why : ':free'));
      return !hit;
    }
    var placeT = null;
    function place() {
      if (open) return;
      var trace = [];
      window.__ohPlaceRuns = (window.__ohPlaceRuns || 0) + 1;
      window.__ohPlaceLast = Date.now();
      for (var i = 0; i < ANCHORS.length; i++) {
        var ok = clean(ANCHORS[i], trace);
        if (ok) { window.__ohPlace = trace; return; }
      }
      window.__ohPlace = trace;
      applyAnchor(ANCHORS[5]);
    }
    function schedulePlace(delay) { clearTimeout(placeT); placeT = setTimeout(place, delay || 60); }
    window.addEventListener('resize', function () { schedulePlace(160); });
    window.addEventListener('scroll', function () { schedulePlace(150); }, { passive: true });
    /* Spouštěče přepočtu: vykreslení, mutace DOMu (s max-wait, aby je debounce
       nemohl odkládat donekonečna) a emit ze storu, který dotéká z IndexedDB. */
    var lastRun = 0;
    var placeNow = function () { lastRun = Date.now(); place(); };
    [300, 900, 1800].forEach(function (ms) { setTimeout(placeNow, ms); });
    try {
      var mo = new MutationObserver(function () {
        if (Date.now() - lastRun > 800) placeNow(); else schedulePlace(250);
      });
      mo.observe(document.body, { childList: true, subtree: true });
    } catch (e) {}
    (function hookStore() {
      if (!window.OnhostStore || !window.OnhostStore.on) return setTimeout(hookStore, 200);
      window.OnhostStore.on(function () { setTimeout(placeNow, 260); });
    })();
    /* Poslední změna geometrie nemusí být mutací DOMu (dopad webfontu, dorovnání
       výšek mřížky), a `resize` na okně se přitom nespustí — proto pozorujeme
       velikost body a čekáme i na fonty. */
    try {
      var ro = new ResizeObserver(function () { schedulePlace(200); });
      ro.observe(document.body);
    } catch (e) {}
    try { if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { setTimeout(placeNow, 120); }); } catch (e) {}
    setTimeout(placeNow, 5000);
    /* Rozhodující trigger: než se uživatel spouštěče dotkne. Verdikt tak nikdy
       není starší než pohyb myši v dolní části okna, kde launcher sedí. */
    var pmT = 0;
    window.addEventListener('pointermove', function (ev) {
      if (ev.clientY < innerHeight * 0.6) return;
      if (Date.now() - pmT < 400) return;
      pmT = Date.now();
      placeNow();
    }, { passive: true });
    btn.addEventListener('pointerenter', function () { placeNow(); });

    var scrim = el('div', 'pointer-events:auto;position:fixed;inset:0;background:rgba(32,30,29,.42);opacity:0;transition:opacity .18s;display:none');
    var panel = el('div', [
      'pointer-events:auto;position:fixed;right:0;top:0;bottom:0;width:min(430px,100vw)',
      'background:' + BG + ';color:' + INK + ';border-left:2px solid ' + INK,
      'transform:translateX(102%);transition:transform .24s cubic-bezier(.2,.7,.2,1)',
      'display:flex;flex-direction:column;overflow:hidden'
    ].join(';'));

    var head = el('div', 'flex:0 0 auto;border-bottom:2px solid ' + INK + ';padding:18px 20px 14px;display:flex;align-items:flex-start;gap:14px');
    var htxt = el('div', 'min-width:0');
    htxt.appendChild(el('div', 'font:900 19px/1.05 Archivo,system-ui,sans-serif;letter-spacing:-.01em', 'Plochy Onhost'));
    htxt.appendChild(el('div', 'margin-top:6px;font:500 12px/1.4 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.6)', 'Čtyři sjednocené plochy a archiv původních'));
    head.appendChild(htxt);
    var close = el('button', 'margin-left:auto;flex:0 0 auto;width:32px;height:32px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 15px/1 Archivo,system-ui,sans-serif;cursor:pointer', '×');
    head.appendChild(close);
    panel.appendChild(head);

    var roleBox = el('div', 'flex:0 0 auto;border-bottom:1px solid rgba(32,30,29,.18);padding:14px 20px;display:grid;gap:9px');
    panel.appendChild(roleBox);

    var ses = el('div', 'flex:0 0 auto;border-bottom:1px solid rgba(32,30,29,.18);padding:14px 20px;display:grid;gap:10px');
    panel.appendChild(ses);

    var ops = el('div', 'flex:0 0 auto;border-bottom:1px solid rgba(32,30,29,.18);padding:14px 20px;display:grid;gap:10px');
    panel.appendChild(ops);

    var body = el('div', 'flex:1 1 auto;overflow-y:auto');
    panel.appendChild(body);

    var foot = el('div', 'flex:0 0 auto;border-top:2px solid ' + INK + ';padding:12px 20px;font:500 11px/1.5 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.55)');
    foot.textContent = 'Alt+O přepínač · ⌘K příkazy · ⌘J asistent · Esc zavře';
    panel.appendChild(foot);

    function pill(label, value, tone) {
      var w = el('div', 'display:flex;align-items:baseline;gap:8px;min-width:0');
      w.appendChild(el('span', 'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.5);flex:0 0 auto', label));
      w.appendChild(el('span', 'font:700 13px/1.3 Archivo,system-ui,sans-serif;color:' + (tone || INK) + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap', value));
      return w;
    }

    function renderRoles() {
      roleBox.textContent = '';
      var cur = Session.role();
      var hd = el('div', 'display:flex;align-items:baseline;gap:8px;flex-wrap:wrap');
      hd.appendChild(el('span', 'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.5)', 'Role'));
      var n = SURFACES.filter(function (s) { return Session.can(s.h); }).length;
      hd.appendChild(el('span', 'margin-left:auto;font:700 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:rgba(32,30,29,.45)', n + ' / ' + SURFACES.length + ' ploch'));
      roleBox.appendChild(hd);
      var row = el('div', 'display:flex;flex-wrap:wrap;gap:6px');
      Session.roles.forEach(function (r) {
        var on = r.id === cur.id;
        var b = el('button', [
          'padding:7px 10px;border:2px solid ' + INK + ';cursor:pointer',
          'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase',
          on ? 'background:' + ACC + ';color:#fff' : 'background:transparent;color:' + INK
        ].join(';'), r.label);
        b.onclick = function () { window.__ohGateOff = false; Session.setRole(r.id); renderRoles(); renderList(); renderGate(); };
        row.appendChild(b);
      });
      roleBox.appendChild(row);
      roleBox.appendChild(el('div', 'font:500 11px/1.4 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.6)', cur.d));
    }

    function renderSession() {
      ses.textContent = '';
      var u = Session.user(), c = Session.cart(), n = Session.cartCount();
      ses.appendChild(pill('Účet', u ? u.email : 'nepřihlášen', u ? INK : 'rgba(32,30,29,.5)'));
      var row = el('div', 'display:flex;gap:18px;flex-wrap:wrap');
      row.appendChild(pill('Košík', n ? n + (n === 1 ? ' položka' : n < 5 ? ' položky' : ' položek') : 'prázdný', n ? ACC : 'rgba(32,30,29,.5)'));
      row.appendChild(pill('Fakturace', c && c.commit ? (c.commit === 'yearly' ? 'ročně' : 'měsíčně') : '—'));
      ses.appendChild(row);
      var acts = el('div', 'display:flex;gap:8px;flex-wrap:wrap;margin-top:2px');
      if (n) {
        var toCart = el('a', 'text-decoration:none;padding:8px 12px;border:2px solid ' + INK + ';background:' + ACC + ';color:#fff;font:800 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase', 'Dokončit objednávku');
        toCart.href = 'Onhost.dc.html#/kosik'; acts.appendChild(toCart);
      }
      if (u) {
        var out = el('button', 'padding:8px 12px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;cursor:pointer', 'Odhlásit');
        out.onclick = function () { Session.signOut(); };
        acts.appendChild(out);
      } else {
        var inn = el('a', 'text-decoration:none;padding:8px 12px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase', 'Přihlásit se');
        inn.href = 'Onhost.dc.html#/prihlaseni'; acts.appendChild(inn);
      }
      ses.appendChild(acts);
      var un = window.OnhostStore ? window.OnhostStore.notifs().filter(function (x) { return !x.read; }).length : 0;
      var show = n || un;
      badge.style.display = show ? 'block' : 'none';
      badge.style.color = n ? ACC : INK;
      badge.textContent = String(n || un);
    }

    function metric(label, value, tone, href) {
      var w = el(href ? 'a' : 'div', 'display:grid;gap:3px;text-decoration:none;color:inherit;min-width:0');
      if (href) w.href = href;
      w.appendChild(el('div', 'font:900 20px/1 Archivo,system-ui,sans-serif;color:' + (tone || INK), String(value)));
      w.appendChild(el('div', 'font:700 9px/1.2 Archivo,system-ui,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:rgba(32,30,29,.5)', label));
      return w;
    }

    function renderOps() {
      var S = window.OnhostStore;
      ops.textContent = '';
      if (!S) { ops.appendChild(pill('Provoz', 'store se načítá…', 'rgba(32,30,29,.5)')); return; }
      var c = S.counts();
      var hd = el('div', 'display:flex;align-items:baseline;gap:8px');
      hd.appendChild(el('span', 'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.5)', 'Provoz'));
      var link = el('a', 'margin-left:auto;text-decoration:none;font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:' + ACC, 'Provozní centrum →');
      link.href = 'Onhost-admin.dc.html#/fronta';
      hd.appendChild(link);
      ops.appendChild(hd);
      var grid = el('div', 'display:grid;grid-template-columns:repeat(4,1fr);gap:12px');
      grid.appendChild(metric('Objednávky', c.ordersOpen, c.ordersOpen ? ACC : INK, 'Onhost-admin.dc.html#/objednavky'));
      grid.appendChild(metric('Tickety', c.ticketsOpen, c.ticketsOpen ? ACC : INK, 'Onhost-admin.dc.html#/fronta'));
      grid.appendChild(metric('Incidenty', c.incOpen, c.incOpen ? ACC : INK, 'Onhost-admin.dc.html#/incidenty'));
      grid.appendChild(metric('Maily ve frontě', c.mailsQueued, c.mailsQueued ? ACC : INK, 'Onhost-admin.dc.html#/maily'));
      grid.appendChild(metric('Faktury k platbě', c.invUnpaid || 0, (c.invUnpaid ? ACC : INK), 'Onhost-admin.dc.html#/doklady'));
      grid.appendChild(metric('Po splatnosti', c.invOverdue || 0, (c.invOverdue ? ACC : INK), 'Onhost-admin.dc.html#/doklady'));
      ops.appendChild(grid);

      var un = S.notifs().filter(function (x) { return !x.read; }).slice(0, 3);
      if (un.length) {
        var list = el('div', 'display:grid;gap:6px;margin-top:2px');
        un.forEach(function (nf) {
          var a = el(nf.surface ? 'a' : 'div', 'display:grid;gap:2px;text-decoration:none;padding:8px 10px;border-left:3px solid ' + ACC + ';background:rgba(236,48,19,.07);color:' + INK);
          if (nf.surface) a.href = nf.surface;
          a.appendChild(el('div', 'font:700 12px/1.25 Archivo,system-ui,sans-serif', nf.title));
          a.appendChild(el('div', 'font:500 11px/1.3 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.6)', (nf.aud === 'internal' ? 'interní · ' : 'zákazník · ') + (nf.body || '')));
          list.appendChild(a);
        });
        ops.appendChild(list);
        var mr = el('button', 'justify-self:start;padding:6px 10px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;cursor:pointer', 'Označit vše přečtené');
        mr.onclick = function () { S.markRead(); renderOps(); };
        ops.appendChild(mr);
      }
      var tot = S.notifs().filter(function (x) { return !x.read; }).length;
      if (tot && !Session.cartCount()) { badge.style.display = 'block'; badge.textContent = String(tot); }
    }

    function renderList() {
      body.textContent = '';
      var groups = [];
      SURFACES.forEach(function (s) {
        var g = groups.filter(function (x) { return x.name === s.g; })[0];
        if (!g) { g = { name: s.g, items: [] }; groups.push(g); }
        g.items.push(s);
      });
      groups.forEach(function (g) {
        var gh = el('div', 'padding:12px 20px 6px;font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:rgba(32,30,29,.45);border-top:1px solid rgba(32,30,29,.14)', g.name);
        body.appendChild(gh);
        g.items.forEach(function (s) {
          var active = s.h.toLowerCase() === here;
          var allowed = Session.can(s.h);
          var a = el(allowed ? 'a' : 'div', [
            'display:grid;grid-template-columns:1fr auto;gap:4px 12px;align-items:center;text-decoration:none',
            'padding:11px 20px;border-bottom:1px solid rgba(32,30,29,.1);color:' + INK,
            active ? 'background:rgba(236,48,19,.1);box-shadow:inset 4px 0 0 ' + ACC : 'background:transparent',
            allowed ? '' : 'opacity:.42'
          ].join(';'));
          if (allowed) a.href = s.h;
          if (active) { a.removeAttribute('href'); a.style.cursor = 'default'; }
          if (allowed && !active) {
            a.onmouseenter = function () { a.style.background = 'rgba(32,30,29,.06)'; };
            a.onmouseleave = function () { a.style.background = 'transparent'; };
          }
          var t = el('div', 'min-width:0');
          t.appendChild(el('div', 'font:700 14px/1.2 Archivo,system-ui,sans-serif', s.t));
          t.appendChild(el('div', 'margin-top:3px;font:500 12px/1.35 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.55)', s.d));
          a.appendChild(t);
          var tag = active ? 'zde' : !allowed ? 'bez přístupu' : (s.k ? 'Alt+' + s.k : '');
          a.appendChild(el('div', 'text-align:right;font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:' + (active ? ACC : 'rgba(32,30,29,.4)'), tag));
          body.appendChild(a);
        });
      });
    }

    /* Reálné gatování: plocha mimo roli se nezobrazí, dokud roli nepřepneš. */
    var gate = null;
    function renderGate() {
      var blocked = !Session.can(here) && !window.__ohGateOff;
      if (!blocked) { if (gate) gate.style.display = 'none'; return; }
      var cur = Session.role();
      var s = SURFACES.filter(function (x) { return x.h.toLowerCase() === here; })[0];
      if (!gate) {
        gate = el('div', 'position:fixed;inset:0;z-index:2147482900;background:' + BG + ';display:flex;align-items:center;justify-content:center;padding:24px;font-family:Archivo,system-ui,sans-serif');
        document.body.appendChild(gate);
      }
      gate.style.display = 'flex';
      gate.textContent = '';
      var card = el('div', 'width:min(520px,100%);border:2px solid ' + INK + ';background:#fff;padding:26px 24px 22px;display:grid;gap:14px');
      card.appendChild(el('div', 'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:' + ACC, 'Přístup odepřen'));
      card.appendChild(el('div', 'font:900 clamp(22px,5vw,30px)/1.05 Archivo,system-ui,sans-serif;letter-spacing:-.02em;color:' + INK, (s ? s.t : 'Tato plocha') + ' není v roli ' + cur.label + ' dostupná'));
      card.appendChild(el('div', 'font:500 13px/1.5 Archivo,system-ui,sans-serif;color:rgba(32,30,29,.62)', cur.label + ' — ' + cur.d.toLowerCase() + '. Plochu otevřeš přepnutím role.'));
      var opts = Session.rolesFor(here);
      if (opts.length) {
        card.appendChild(el('div', 'font:800 10px/1 Archivo,system-ui,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:rgba(32,30,29,.5);margin-top:2px', 'Přepnout na roli'));
        var row = el('div', 'display:flex;flex-wrap:wrap;gap:8px');
        opts.forEach(function (r) {
          var b = el('button', 'padding:10px 13px;border:2px solid ' + INK + ';background:' + ACC + ';color:#fff;font:800 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;cursor:pointer', r.label);
          b.onclick = function () { Session.setRole(r.id); renderGate(); renderRoles(); renderList(); };
          row.appendChild(b);
        });
        card.appendChild(row);
      }
      var alt = SURFACES.filter(function (x) { return Session.can(x.h) && x.h.toLowerCase() !== here; })[0];
      var foot2 = el('div', 'display:flex;flex-wrap:wrap;gap:8px;border-top:2px solid ' + INK + ';padding-top:14px;margin-top:4px');
      if (alt) {
        var go = el('a', 'text-decoration:none;padding:10px 13px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase', alt.t + ' →');
        go.href = alt.h; foot2.appendChild(go);
      }
      var ig = el('button', 'padding:10px 13px;border:0;background:transparent;color:rgba(32,30,29,.5);font:700 11px/1 Archivo,system-ui,sans-serif;letter-spacing:.06em;cursor:pointer;text-decoration:underline', 'Zobrazit i tak (demo)');
      ig.onclick = function () { window.__ohGateOff = true; renderGate(); };
      foot2.appendChild(ig);
      card.appendChild(foot2);
      gate.appendChild(card);
    }
    window.OnhostGate = renderGate;

    function setOpen(v) {
      open = v;
      if (v) {
        renderRoles(); renderSession(); renderOps(); renderList();
        scrim.style.display = 'block';
        setTimeout(function () { if (open) { scrim.style.opacity = '1'; panel.style.transform = 'none'; } }, 16);
      } else {
        scrim.style.opacity = '0';
        panel.style.transform = 'translateX(102%)';
        setTimeout(function () { if (!open) scrim.style.display = 'none'; }, 240);
      }
    }

    btn.onclick = function () { setOpen(!open); };
    close.onclick = function () { setOpen(false); };
    scrim.onclick = function () { setOpen(false); };
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && open) { setOpen(false); return; }
      if (!e.altKey || e.ctrlKey || e.metaKey) return;
      var k = String(e.key || '').toUpperCase();
      if (k === 'O' && !e.shiftKey) { e.preventDefault(); setOpen(!open); return; }
      var hit = SURFACES.filter(function (s) { return s.k === k; })[0];
      if (hit && hit.h.toLowerCase() !== here && Session.can(hit.h)) { e.preventDefault(); location.href = hit.h; }
    });
    Session.on(function () { renderSession(); renderGate(); if (open) { renderRoles(); renderOps(); renderList(); } });
    function hookStore() {
      if (!window.OnhostStore) return setTimeout(hookStore, 120);
      window.OnhostStore.on(function () { if (open) renderOps(); renderSession(); });
      renderSession();
    }
    hookStore();

    host.appendChild(scrim); host.appendChild(panel);
    document.body.appendChild(host); document.body.appendChild(btn);
    renderSession(); renderGate();
    schedulePlace(120);
    [900, 2000, 4000].forEach(function (d) { setTimeout(place, d); });
    window.addEventListener('load', function () { setTimeout(place, 300); });
    document.addEventListener('scroll', function () { schedulePlace(240); }, true);
  }

  function boot() { if (EMBED) return; if (!document.body) return setTimeout(boot, 30); OnhostSwitcher(); }
  boot();
})();
