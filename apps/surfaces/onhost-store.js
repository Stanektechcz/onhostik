/* Onhost operační store — sdílená datová vrstva mezi plochami.
   Objednávky, tickety, incidenty, notifikace, odchozí maily, audit log.
   Persistence: IndexedDB 'onhost' (plný snapshot) + localStorage 'onhost.ops'
   jako synchronní cache pro první vykreslení. Cross-tab: storage event na stamp.
   API: window.OnhostStore  (viz docs-backend-handoff.md, sekce Provozní store) */
(function () {
  if (window.OnhostStore) return;

  var KEY = 'onhost.ops';
  var STAMP = 'onhost.ops.stamp';
  var VER = 4;
  var IDB_NAME = 'onhost', IDB_STORE = 'ops', IDB_KEY = 'snapshot';

  /* ---------- stavové automaty ---------- */

  var ORDER_FLOW = {
    nova:        { label: 'Nová',          next: ['zaplaceno', 'zruseno'],   tone: '#8a6d00', internal: true },
    zaplaceno:   { label: 'Zaplaceno',     next: ['provisioning', 'zruseno'], tone: '#0f5f3a' },
    provisioning:{ label: 'Provisioning',  next: ['aktivni'],                tone: '#1a4c8f' },
    aktivni:     { label: 'Aktivní',       next: ['pozastaveno', 'zruseno'], tone: '#0f5f3a' },
    pozastaveno: { label: 'Pozastaveno',   next: ['aktivni', 'zruseno'],     tone: '#8f1c0a' },
    zruseno:     { label: 'Zrušeno',       next: [],                         tone: '#6b6663' }
  };
  var TICKET_FLOW = {
    otevreny:  { label: 'Otevřený',  next: ['ceka', 'vyreseny'], tone: '#8f1c0a' },
    ceka:      { label: 'Čeká na zákazníka', next: ['otevreny', 'vyreseny'], tone: '#8a6d00' },
    vyreseny:  { label: 'Vyřešený',  next: ['otevreny'],         tone: '#0f5f3a' }
  };
  var INC_FLOW = {
    vysetrovani:   { label: 'Vyšetřujeme',  next: ['identifikovano', 'vyreseno'], tone: '#8f1c0a' },
    identifikovano:{ label: 'Identifikováno', next: ['monitoring', 'vyreseno'],   tone: '#8a6d00' },
    monitoring:    { label: 'Monitorujeme', next: ['vyreseno'],                   tone: '#1a4c8f' },
    vyreseno:      { label: 'Vyřešeno',     next: [],                             tone: '#0f5f3a' }
  };
  var INV_FLOW = {
    vystavena:     { label: 'Vystavena',      next: ['zaplacena', 'po_splatnosti', 'stornovana'], tone: '#1a4c8f' },
    po_splatnosti: { label: 'Po splatnosti',  next: ['zaplacena', 'stornovana'],  tone: '#8f1c0a' },
    zaplacena:     { label: 'Zaplacena',      next: ['stornovana'],               tone: '#0f5f3a' },
    stornovana:    { label: 'Stornována',     next: [],                           tone: '#6b6663' }
  };
  var DAY = 86400000;

  /* ---------- persistence ---------- */

  function now() { return Date.now(); }
  function load() {
    try {
      var raw = localStorage.getItem(KEY);
      if (!raw) return null;
      var d = JSON.parse(raw);
      if (!d || d.ver !== VER) return null;
      return repair(d);
    } catch (e) { return null; }
  }
  /* Starší data mohou nést duplicitní čísla dokladů z dřívější verze
     počítadla — přečíslujeme je, jinak lookup podle ID vrací cizí záznam. */
  function repair(d) {
    var seen = {}, max = d.iseq || 0, y = new Date().getFullYear();
    (d.invoices || []).forEach(function (v) {
      var m = /-(\d+)$/.exec(String(v.id || ''));
      if (m && Number(m[1]) > max) max = Number(m[1]);
    });
    (d.invoices || []).forEach(function (v) {
      if (!seen[v.id]) { seen[v.id] = 1; return; }
      var pref = String(v.id || 'FV').slice(0, 2), nid;
      do { max++; nid = pref + '-' + y + '-' + String(max).padStart(4, '0'); } while (seen[nid]);
      v.id = nid; seen[nid] = 1;
    });
    d.iseq = max;
    return d;
  }
  var db = null, subs = [];

  /* ---------- IndexedDB: plný snapshot mimo 5MB kvótu localStorage ----------
     localStorage drží jen zkrácenou kopii (hot set) kvůli synchronnímu bootu;
     zdrojem pravdy je IDB. Když IDB není k dispozici, degradujeme na chování
     s localStorage samotným — plochy o rozdílu nevědí. */
  var idbP = null, idbOK = null, lastStamp = 0;
  function openIDB() {
    if (idbP) return idbP;
    idbP = new Promise(function (res) {
      var req;
      try { req = indexedDB.open(IDB_NAME, 1); } catch (e) { idbOK = false; return res(null); }
      req.onupgradeneeded = function () {
        var d = req.result;
        if (!d.objectStoreNames.contains(IDB_STORE)) d.createObjectStore(IDB_STORE);
      };
      req.onsuccess = function () { idbOK = true; res(req.result); };
      req.onerror = function () { idbOK = false; res(null); };
      req.onblocked = function () { idbOK = false; res(null); };
    });
    return idbP;
  }
  function idbPut(data) {
    return openIDB().then(function (d) {
      if (!d) return false;
      return new Promise(function (res) {
        var tx;
        try { tx = d.transaction(IDB_STORE, 'readwrite'); } catch (e) { return res(false); }
        tx.objectStore(IDB_STORE).put(data, IDB_KEY);
        tx.oncomplete = function () { res(true); };
        tx.onerror = tx.onabort = function () { res(false); };
      });
    });
  }
  function idbGet() {
    return openIDB().then(function (d) {
      if (!d) return null;
      return new Promise(function (res) {
        var tx, r;
        try { tx = d.transaction(IDB_STORE, 'readonly'); r = tx.objectStore(IDB_STORE).get(IDB_KEY); }
        catch (e) { return res(null); }
        r.onsuccess = function () { res(r.result || null); };
        r.onerror = function () { res(null); };
      });
    });
  }
  /* Zkrácená kopie pro localStorage — nikdy nemutuje db, jen serializovaný výřez.
     part:true říká bootu, že plná data mají přijít z IDB. */
  function hotSet() {
    var cut = function (l, n) { return (l || []).slice(0, n); };
    var full = (db.orders || []).length <= 300 && (db.tickets || []).length <= 300 && (db.invoices || []).length <= 400;
    return { ver: VER, seq: db.seq, iseq: db.iseq, at: now(), part: !full,
      orders: cut(db.orders, 300), tickets: cut(db.tickets, 300), invoices: cut(db.invoices, 400),
      incidents: cut(db.incidents, 60), notifs: cut(db.notifs, 40), mails: cut(db.mails, 40), log: cut(db.log, 60) };
  }

  /* Zápis do localStorage je coalescovaný: mutace jen označí stav špinavým,
     serializace proběhne jednou v idle okně. Emit pro UI jde v microtasku,
     takže dávka (dunningRun, seedVolume) překreslí plochy jedenkrát. */
  var dirty = false, wPending = false, ePending = false;
  var idle = window.requestIdleCallback ? function (f) { window.requestIdleCallback(f, { timeout: 400 }); }
                                        : function (f) { setTimeout(f, 60); };
  /* Poslední záchrana, když ani hot set neprojde kvótou: zúžíme výřez, ne db. */
  function narrow(snap) {
    snap.part = true;
    snap.orders = (snap.orders || []).slice(0, 60);
    snap.tickets = (snap.tickets || []).slice(0, 60);
    snap.invoices = (snap.invoices || []).slice(0, 60);
    snap.log = (snap.log || []).slice(0, 20);
    return snap;
  }
  function flush() {
    wPending = false;
    if (!dirty) return;
    dirty = false;
    var stamp = now();
    if (stamp <= lastStamp) stamp = lastStamp + 1;
    lastStamp = stamp;
    db.at = stamp;
    idbPut(db);
    var snap = hotSet();
    snap.at = stamp;
    try { localStorage.setItem(KEY, JSON.stringify(snap)); }
    catch (e) {
      try { localStorage.setItem(KEY, JSON.stringify(narrow(snap))); } catch (e2) {}
    }
    try { localStorage.setItem(STAMP, String(stamp)); } catch (e3) {}
  }
  function save() {
    dirty = true; if (!bootPhase) touched = true; IDX = null;
    if (!wPending) { wPending = true; idle(flush); }
    if (!ePending) { ePending = true; Promise.resolve().then(function () { ePending = false; emit(); }); }
  }
  window.addEventListener('pagehide', flush);
  window.addEventListener('beforeunload', flush);
  function emit() { subs.slice().forEach(function (f) { try { f(API); } catch (e) {} }); }

  /* Indexy podle id — u tisíců záznamů je lookup O(1) místo filtru přes celé pole. */
  var IDX = null;
  function idx() {
    if (IDX) return IDX;
    IDX = { o: {}, t: {}, i: {}, v: {} };
    (db.orders || []).forEach(function (o) { IDX.o[o.id] = o; });
    (db.tickets || []).forEach(function (t) { IDX.t[t.id] = t; });
    (db.incidents || []).forEach(function (i) { IDX.i[i.id] = i; });
    (db.invoices || []).forEach(function (v) { IDX.v[v.id] = v; });
    return IDX;
  }

  /* Čísla dokladů: counter může zůstat pozadu za existujícími záznamy
     (seed, zátěžová data, starší localStorage), proto vždy dohledáme maximum. */
  function taken(list, id) {
    for (var i = 0; i < (list || []).length; i++) if (list[i].id === id) return true;
    return false;
  }
  function tailOf(id) {
    var m = /-(\d+)$/.exec(String(id || ''));
    return m ? Number(m[1]) : 0;
  }

  function seq(prefix) {
    var pool = (db.orders || []).concat(db.tickets || []);
    var max = db.seq || 0, id;
    pool.forEach(function (r) { var t = tailOf(r.id) - 1000; if (t > max) max = t; });
    do { max++; id = prefix + '-' + new Date().getFullYear() + '-' + String(1000 + max); } while (taken(pool, id));
    db.seq = max;
    return id;
  }
  function h(n) { return now() - n * 3600000; }

  function seed() {
    var d = { ver: VER, seq: 40, iseq: 60, orders: [], tickets: [], incidents: [], notifs: [], mails: [], invoices: [], log: [] };
    d.orders = [
      { id: 'OH-2026-1012', email: 'provoz@studio-kvark.cz', name: 'Studio Kvark', state: 'aktivni',
        items: [{ sku: 'vps-pro', name: 'VPS Pro 8/16', qty: 2, unitNet: 1290 }], commit: 12, total: 3121,
        at: h(760), hist: [['nova', h(760)], ['zaplaceno', h(758)], ['provisioning', h(757)], ['aktivni', h(756)]] },
      { id: 'OH-2026-1024', email: 'it@medisys.cz', name: 'Medisys', state: 'provisioning',
        items: [{ sku: 'dedicated-epyc', name: 'Dedikovaný EPYC 32c', qty: 1, unitNet: 12400 }], commit: 24, total: 15004,
        at: h(29), hist: [['nova', h(29)], ['zaplaceno', h(26)], ['provisioning', h(4)]] },
      { id: 'OH-2026-1031', email: 'dev@hraj.gg', name: 'Hraj.gg', state: 'zaplaceno',
        items: [{ sku: 'game-mc', name: 'Game hosting Minecraft 16 GB', qty: 3, unitNet: 690 }], commit: 1, total: 2504,
        at: h(9), hist: [['nova', h(9)], ['zaplaceno', h(7)]] },
      { id: 'OH-2026-1036', email: 'nakup@obec-brezina.cz', name: 'Obec Březina', state: 'nova',
        items: [{ sku: 'web-business', name: 'Webhosting Business', qty: 1, unitNet: 390 }, { sku: 'ssl-ev', name: 'SSL EV', qty: 1, unitNet: 2400 }], commit: 12, total: 3376,
        at: h(2), hist: [['nova', h(2)]] }
    ];
    d.tickets = [
      { id: 'TK-2026-1004', email: 'it@medisys.cz', name: 'Medisys', subject: 'Migrace databáze na nový EPYC',
        prio: 'vysoka', state: 'otevreny', at: h(6), svc: 'OH-2026-1024',
        msgs: [{ from: 'zakaznik', at: h(6), text: 'Potřebujeme naplánovat okno pro migraci PostgreSQL, ideálně v noci ze soboty na nedělI.' },
               { from: 'podpora', at: h(5), text: 'Rezervovali jsme okno So 02:00–05:00. Potvrdíte?' }] },
      { id: 'TK-2026-1007', email: 'dev@hraj.gg', name: 'Hraj.gg', subject: 'Vyšší latence na uzlu PRG-3',
        prio: 'stredni', state: 'ceka', at: h(20), svc: 'OH-2026-1031',
        msgs: [{ from: 'zakaznik', at: h(20), text: 'Hráči hlásí 90+ ms, dřív to bylo 18 ms.' },
               { from: 'podpora', at: h(19), text: 'Vidíme retransmise na jednom uplinku. Pošlete prosím traceroute z klienta.' }] },
      { id: 'TK-2026-1009', email: 'provoz@studio-kvark.cz', name: 'Studio Kvark', subject: 'Faktura na jiné IČO',
        prio: 'nizka', state: 'vyreseny', at: h(70), svc: 'OH-2026-1012',
        msgs: [{ from: 'zakaznik', at: h(70), text: 'Prosíme přefakturovat na novou entitu.' },
               { from: 'podpora', at: h(69), text: 'Hotovo, opravný doklad je v panelu.' }] }
    ];
    d.incidents = [
      { id: 'INC-2026-0031', title: 'Zvýšená latence uplinku PRG-3', svc: 'Síť Praha', sev: 'p2',
        state: 'monitoring', at: h(5), impact: '~4 % provozu v PRG, latence +70 ms',
        hist: [['vysetrovani', h(5), 'Alarm z monitoringu, retransmise na uplinku B.'],
               ['identifikovano', h(4), 'Vadný optický modul, přesměrováno na uplink A.'],
               ['monitoring', h(2), 'Modul vyměněn, latence zpět na 19 ms, sledujeme.']] },
      { id: 'INC-2026-0029', title: 'Výpadek panelu — chyba deploye', svc: 'Klientský panel', sev: 'p1',
        state: 'vyreseno', at: h(96), impact: 'Panel nedostupný 11 minut',
        hist: [['vysetrovani', h(96), 'HTTP 502 po nasazení 2026.8.3.'],
               ['identifikovano', h(96), 'Chybná migrace session tabulky.'],
               ['vyreseno', h(95), 'Rollback na 2026.8.2, panel v provozu.']] }
    ];
    d.log = [
      { at: h(2), who: 'web', text: 'Objednávka OH-2026-1036 vytvořena z e-shopu' },
      { at: h(4), who: 'admin', text: 'OH-2026-1024 → Provisioning' },
      { at: h(2), who: 'noc', text: 'INC-2026-0031 → Monitorujeme' }
    ];
    d.notifs = [
      { id: 'N1', aud: 'internal', at: h(2), kind: 'order', ref: 'OH-2026-1036', title: 'Nová objednávka Obec Březina', body: '3 376 Kč · čeká na platbu', read: false, surface: 'Onhost-admin.dc.html' },
      { id: 'N2', aud: 'internal', at: h(2), kind: 'incident', ref: 'INC-2026-0031', title: 'INC-2026-0031 → Monitorujeme', body: 'Latence PRG-3 zpět na 19 ms', read: false, surface: 'Onhost-admin.dc.html#/incidenty' },
      { id: 'N3', aud: 'customer', at: h(4), kind: 'order', ref: 'OH-2026-1024', title: 'Server se instaluje', body: 'Dedikovaný EPYC 32c — odhad 40 minut', read: false, surface: 'Onhost-app.dc.html' }
    ];
    d.mails = [
      { id: 'M1', tpl: 'welcome', to: 'nakup@obec-brezina.cz', subject: 'Vítejte v Onhost', state: 'sent', at: h(2), vars: { cislo: 'OH-2026-1036' } },
      { id: 'M2', tpl: 'incident', to: 'dev@hraj.gg', subject: 'Incident INC-2026-0031 — monitorujeme', state: 'sent', at: h(2), vars: { incident: 'INC-2026-0031' } }
    ];
    d.invoices = [
      { id: 'FV-2026-0061', order: 'OH-2026-1012', email: 'provoz@studio-kvark.cz', name: 'Studio Kvark',
        rows: [{ name: 'VPS Pro 8/16', qty: 2, unitNet: 1290 }], net: 2580, vat: 542, total: 3122,
        state: 'zaplacena', issued: h(760), due: h(760) + 14 * DAY, paid: h(758), method: 'karta', period: '12 měsíců' },
      { id: 'FV-2026-0062', order: 'OH-2026-1024', email: 'it@medisys.cz', name: 'Medisys',
        rows: [{ name: 'Dedikovaný EPYC 32c', qty: 1, unitNet: 12400 }], net: 12400, vat: 2604, total: 15004,
        state: 'zaplacena', issued: h(29), due: h(29) + 14 * DAY, paid: h(26), method: 'převod', period: '24 měsíců' },
      { id: 'FV-2026-0063', order: 'OH-2026-1031', email: 'dev@hraj.gg', name: 'Hraj.gg',
        rows: [{ name: 'Game hosting Minecraft 16 GB', qty: 3, unitNet: 690 }], net: 2070, vat: 435, total: 2505,
        state: 'zaplacena', issued: h(9), due: h(9) + 14 * DAY, paid: h(7), method: 'karta', period: 'měsíčně' },
      { id: 'FV-2026-0064', order: 'OH-2026-1036', email: 'nakup@obec-brezina.cz', name: 'Obec Březina',
        rows: [{ name: 'Webhosting Business', qty: 1, unitNet: 390 }, { name: 'SSL EV', qty: 1, unitNet: 2400 }],
        net: 2790, vat: 586, total: 3376,
        state: 'vystavena', issued: h(2), due: h(2) + 14 * DAY, paid: null, method: null, period: '12 měsíců' },
      { id: 'FV-2026-0058', order: 'OH-2026-1012', email: 'provoz@studio-kvark.cz', name: 'Studio Kvark',
        rows: [{ name: 'Doplňkové zálohování 500 GB', qty: 1, unitNet: 890 }], net: 890, vat: 187, total: 1077,
        state: 'po_splatnosti', issued: now() - 32 * DAY, due: now() - 18 * DAY, paid: null, method: null, period: 'měsíčně' }
    ];
    return d;
  }

  var cached = load();
  db = cached || seed();
  if (!db.invoices) db.invoices = [];
  var booted = db.at || 0, touched = false, bootPhase = true;
  if (!cached || sweepDue()) save();

  /* Hydratace z IDB: cache byla zkrácená nebo starší → dotáhneme plný snapshot.
     Pokud mezitím něco zapsalo do db, hydrataci zahodíme — čerstvá mutace má přednost. */
  var hydrated = idbGet().then(function (snap) {
    if (!snap || snap.ver !== VER) { if (idbOK && !touched) idbPut(db); return false; }
    if (touched || (snap.at || 0) < booted) return false;
    db = repair(snap);
    if (!db.invoices) db.invoices = [];
    IDX = null; TCACHE = {};
    if (sweepDue()) save();
    emit();
    return true;
  });
  bootPhase = false;

  /* ---------- interní zápisy ---------- */

  function log(who, text) {
    db.log.unshift({ at: now(), who: who, text: text });
    db.log = db.log.slice(0, 120);
  }
  function notify(aud, kind, ref, title, body, surface) {
    db.notifs.unshift({ id: 'N' + now() + Math.floor(Math.random() * 99), aud: aud, at: now(),
      kind: kind, ref: ref, title: title, body: body, read: false, surface: surface || null });
    db.notifs = db.notifs.slice(0, 60);
  }
  function queue(tpl, to, subject, vars, ref) {
    db.mails.unshift({ id: 'M' + now() + Math.floor(Math.random() * 99), tpl: tpl, to: to,
      subject: subject, state: 'queued', at: now(), vars: vars || {}, ref: ref || null });
    db.mails = db.mails.slice(0, 60);
  }
  function money(n) { return new Intl.NumberFormat('cs-CZ').format(Math.round(n)) + ' Kč'; }
  function iseq(prefix) {
    var list = db.invoices || [], max = db.iseq || 0, id;
    list.forEach(function (v) { var t = tailOf(v.id); if (t > max) max = t; });
    do { max++; id = prefix + '-' + new Date().getFullYear() + '-' + String(max).padStart(4, '0'); } while (taken(list, id));
    db.iseq = max;
    return id;
  }
  function commitLabel(c) { return c === 1 ? 'měsíčně' : c + ' měsíců'; }
  /* Faktura z objednávky: netto ze položek, DPH 21 %, splatnost 14 dní. */
  function mkInvoice(o) {
    var net = (o.items || []).reduce(function (s, i) { return s + (i.unitNet || 0) * (i.qty || 1); }, 0);
    var vat = Math.round(net * 0.21);
    var inv = { id: iseq('FV'), order: o.id, email: o.email, name: o.name,
      rows: (o.items || []).map(function (i) { return { name: i.name, qty: i.qty || 1, unitNet: i.unitNet || 0 }; }),
      net: net, vat: vat, total: net + vat, state: 'vystavena', issued: now(), due: now() + 14 * DAY,
      paid: null, method: null, period: commitLabel(o.commit || 1) };
    db.invoices.unshift(inv);
    log('fakturace', 'Faktura ' + inv.id + ' vystavena k ' + o.id + ' · ' + money(inv.total));
    notify('customer', 'invoice', inv.id, 'Nová faktura ' + inv.id, money(inv.total) + ' · splatnost 14 dní', 'Onhost-app.dc.html');
    return inv;
  }
  /* Sweep splatnosti — vystavené faktury po termínu přepadnou do po_splatnosti. */
  function sweepDue() {
    var n = 0;
    (db.invoices || []).forEach(function (i) {
      if (i.state === 'vystavena' && i.due < now()) { i.state = 'po_splatnosti'; n++; }
    });
    return n;
  }

  /* ---------- efekty přechodů objednávky ---------- */

  var ORDER_FX = {
    zaplaceno: function (o) {
      notify('customer', 'order', o.id, 'Platba přijata', o.id + ' · ' + money(o.total), 'Onhost-app.dc.html');
      var inv = db.invoices.filter(function (i) { return i.order === o.id && i.state !== 'zaplacena' && i.state !== 'stornovana'; })[0];
      if (inv) { inv.state = 'zaplacena'; inv.paid = now(); inv.method = inv.method || 'převod'; }
      queue('invoice', o.email, 'Daňový doklad k ' + o.id, { cislo: (inv && inv.id) || o.id, castka: money(o.total) }, o.id);
    },
    provisioning: function (o) {
      notify('customer', 'order', o.id, 'Služba se instaluje', (o.items[0] && o.items[0].name) || o.id, 'Onhost-app.dc.html');
      notify('internal', 'order', o.id, 'Provisioning ' + o.id, 'Přiřaďte uzel a IP', 'Onhost-admin.dc.html');
    },
    aktivni: function (o) {
      notify('customer', 'order', o.id, 'Služba je aktivní', (o.items[0] && o.items[0].name) || o.id, 'Onhost-app.dc.html');
      queue('ready', o.email, 'Vaše služba je připravena', { cislo: o.id, sluzba: (o.items[0] && o.items[0].name) || '' }, o.id);
    },
    pozastaveno: function (o) {
      notify('customer', 'order', o.id, 'Služba pozastavena', o.id + ' — neuhrazená faktura', 'Onhost-app.dc.html');
      queue('dunning', o.email, 'Upomínka — služba pozastavena', { cislo: o.id }, o.id);
    },
    zruseno: function (o) {
      notify('internal', 'order', o.id, o.id + ' zrušeno', o.name, 'Onhost-admin.dc.html');
    }
  };

  /* ---------- témata ticketů, shlukování a asistent ---------- */

  /* Klíčová slova bez diakritiky — text ticketu i dotaz asistenta se normalizuje. */
  var TOPICS = [
    { id: 'dostupnost', label: 'Výpadky a dostupnost', kw: ['vypadek', 'nedostupn', '502', '503', 'timeout', 'pada', 'spadl', 'restart', 'nejede', 'nefunguje'] },
    { id: 'latence',    label: 'Latence a síť',        kw: ['latence', 'ping', 'ms', 'traceroute', 'retransmis', 'uplink', 'paket', 'sit', 'route', 'ztrat'] },
    { id: 'vykon',      label: 'Výkon a kapacita',     kw: ['cpu', 'ram', 'pomal', 'vytiz', 'iops', 'disk', 'tick', 'kapacit', 'load', 'zpomal'] },
    { id: 'fakturace',  label: 'Fakturace a doklady',  kw: ['faktur', 'ico', 'doklad', 'dph', 'platb', 'upominka', 'storno', 'cena', 'uhrad', 'zaplat', 'neuhraz', 'nedoplat', 'dluz'] },
    { id: 'zalohy',     label: 'Zálohy a obnova',      kw: ['zaloh', 'obnov', 'snapshot', 'restore', 'smazal', 'ztratil'] },
    { id: 'pristup',    label: 'Přístupy a bezpečnost', kw: ['heslo', 'pristup', 'ssh', '2fa', 'klic', 'firewall', 'port', 'certifik', 'ssl', 'tls', 'prihlas'] },
    { id: 'dns',        label: 'DNS a domény',         kw: ['dns', 'domen', 'zaznam', 'mx', 'nameserver', 'ttl', 'zona', 'presmerov'] },
    { id: 'mail',       label: 'E-mail a doručitelnost', kw: ['mail', 'spf', 'dkim', 'dmarc', 'spam', 'schrank', 'dorucit', 'posta'] },
    { id: 'migrace',    label: 'Migrace a přenos',     kw: ['migrac', 'prenos', 'presun', 'postgres', 'databaz', 'import', 'okno', 'stehov'] },
    { id: 'objednavka', label: 'Objednávky a služby',  kw: ['objednav', 'provisioning', 'aktivac', 'navys', 'upgrade', 'zrus', 'tarif', 'sluzb'] }
  ];
  function norm(s) {
    var v = String(s || '').toLowerCase();
    try { return v.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (e) { return v; }
  }
  function labelOf(id) {
    for (var i = 0; i < TOPICS.length; i++) if (TOPICS[i].id === id) return TOPICS[i].label;
    return 'Ostatní';
  }
  function scoreText(txt) {
    var best = null, bs = 0;
    TOPICS.forEach(function (t) {
      var s = 0;
      t.kw.forEach(function (k) { if (txt.indexOf(k) >= 0) s += (k.length > 4 ? 2 : 1); });
      if (s > bs) { bs = s; best = t.id; }
    });
    return { id: bs ? best : 'ostatni', score: bs };
  }
  var TCACHE = {};
  function topicOf(t) {
    if (!t) return 'ostatni';
    if (t.topic) return t.topic;
    var key = t.id + ':' + ((t.msgs && t.msgs.length) || 0);
    if (TCACHE[key]) return TCACHE[key];
    var txt = norm(t.subject) + ' ' + norm((t.msgs || []).map(function (m) { return m.text; }).join(' '));
    var r = scoreText(txt).id;
    TCACHE[key] = r;
    return r;
  }

  /* Odpovědi asistenta: fakta se čtou ze store, ne z textu. */
  var KB = {
    dostupnost: { a: 'Nedostupnost řešíme jako incident, ne jako ticket: na stavové stránce je vždy vidět, co je rozbité a kdy čekáme opravu. Pokud výpadek na vaší službě nevidíme my, potřebujeme čas a IP, ze které to nešlo.', href: 'Onhost.dc.html#/stav', hrefLabel: 'Stav platformy' },
    latence:    { a: 'Latenci měříme z pěti bodů v ČR a z Frankfurtu. Když vám roste jen z jedné sítě, je to obvykle peering u vašeho operátora — pošlete traceroute z klienta a přiložíme svůj z uzlu.', href: 'Onhost-admin.dc.html#/incidenty', hrefLabel: 'Dohled sítě' },
    vykon:      { a: 'Nejdřív se podíváme, co bere čas: u webů to v devíti z deseti případů není CPU, ale dotaz do databáze bez indexu. Navýšení tarifu doporučíme jen tehdy, když je opravdu vytížený hardware.', href: 'Onhost-app.dc.html', hrefLabel: 'Metriky služby' },
    fakturace:  { a: 'Faktury a opravné doklady jsou v panelu ke stažení do PDF, splatnost je 14 dní a službu nepozastavíme dřív než 30 dní po splatnosti. Změnu IČO uděláme opravným dokladem, ne přepsáním faktury.', href: 'Onhost-app.dc.html', hrefLabel: 'Faktury v panelu' },
    zalohy:     { a: 'Zálohy držíme 30 dní a obnovujeme na úrovni jednoho souboru nebo jedné databáze — nemusíte vracet celý server. Obnova jednoho souboru trvá desítky sekund.', href: 'Onhost.dc.html#/dokumentace', hrefLabel: 'Dokumentace záloh' },
    pristup:    { a: 'Přístupy dáváme jmenovitě, nikdy sdíleným heslem, a FTP nemáme schválně. Certifikáty obnovujeme automaticky 30 dní před expirací.', href: 'Onhost.dc.html#/dokumentace', hrefLabel: 'Přístupy a klíče' },
    dns:        { a: 'Změny v zóně se propíší do minuty, ale svět je uvidí až po vypršení TTL — před stěhováním proto TTL stahujeme na 300 sekund. Zónu jde importovat i exportovat.', href: 'Onhost.dc.html#/dokumentace', hrefLabel: 'DNS v dokumentaci' },
    mail:       { a: 'Doručitelnost stojí na SPF, DKIM a DMARC — bez nich pošta končí ve spamu bez ohledu na server. V panelu je vidět, který ze tří záznamů chybí.', href: 'Onhost-admin.dc.html#/maily', hrefLabel: 'Doručitelnost' },
    migrace:    { a: 'Migrace jde vždy v okně, které potvrdíte, a starý stroj necháme běžet ještě 7 dní. Přenos dat a přepnutí DNS jsou dva oddělené kroky, aby se dalo vrátit.', href: 'Onhost.dc.html#/dokumentace', hrefLabel: 'Postup migrace' },
    objednavka: { a: 'Objednávka jde přes stavy nová → zaplaceno → provisioning → aktivní; každý krok vidíte v panelu i v e-mailu. Navýšení tarifu je bez odstávky, snížení až od dalšího období.', href: 'Onhost-app.dc.html', hrefLabel: 'Moje služby' },
    ostatni:    { a: 'Tohle si netroufnu odpovědět z paměti — přepošlu to na podporu s celým kontextem, ať se vás nikdo neptá dvakrát. Odpovídáme do 30 minut, u priority vysoká do 15.', href: 'Onhost.dc.html#/dokumentace', hrefLabel: 'Dokumentace' }
  };
  function answer(text, email, api) {
    var q = norm(text);
    var sc = scoreText(q);
    var topic = sc.id;
    /* Slabá shoda: doplňkové intenty, ať se běžná otázka nedostane do „ostatní“. */
    if (sc.score < 2) {
      if (/faktur|neuhraz|nedoplat|platb|kolik.*(kc|korun|zaplat|dluz)|uhrad|dph|ico/.test(q)) topic = 'fakturace';
      else if (/incident|vypadek|nejede|nefunguje|50[23]|nedostupn/.test(q)) topic = 'dostupnost';
      else if (/zaloh|obnov|smazal|snapshot/.test(q)) topic = 'zalohy';
      else if (/objednav|sluzb|tarif|upgrade|navys/.test(q)) topic = 'objednavka';
      else if (/latenc|ping|pomal/.test(q)) topic = 'latence';
    }
    var kb = KB[topic] || KB.ostatni;
    var facts = [], actions = [];

    /* Fakta z živých dat — podle role a přihlášeného e-mailu. */
    var myInv = api.invoices(email ? { email: email, unpaid: true } : { unpaid: true });
    var myOrders = api.orders(email ? { email: email } : null);
    var myTickets = api.tickets(email ? { email: email } : null).filter(function (t) { return t.state !== 'vyreseny'; });
    var openInc = api.incidents({ open: true });

    if (topic === 'fakturace' || /faktur|platb|uhrad|dluh|neuhraz|kolik/.test(q)) {
      if (myInv.length) {
        facts.push({ k: 'Neuhrazeno', v: money(myInv.reduce(function (s, i) { return s + i.total; }, 0)) });
        facts.push({ k: 'Nejstarší doklad', v: myInv[myInv.length - 1].id });
        actions.push({ id: 'pay', label: 'Zaplatit ' + myInv[0].id + ' kartou', kind: 'pay', ref: myInv[0].id });
      } else facts.push({ k: 'Neuhrazené faktury', v: 'žádné' });
    }
    if (topic === 'objednavka' || /objednav|sluzb|aktiv/.test(q)) {
      var act = myOrders.filter(function (o) { return o.state === 'aktivni'; }).length;
      facts.push({ k: 'Objednávky', v: API.pl(myOrders.length, 'záznam', 'záznamy', 'záznamů') + ' · ' + act + '× aktivní' });
      if (myOrders[0]) facts.push({ k: 'Poslední', v: myOrders[0].id + ' · ' + ORDER_FLOW[myOrders[0].state].label });
    }
    if (topic === 'dostupnost' || /vypadek|incident|nejede|nefunguje/.test(q)) {
      if (openInc.length) {
        facts.push({ k: 'Otevřený incident', v: openInc[0].id + ' · ' + INC_FLOW[openInc[0].state].label });
        facts.push({ k: 'Dotčeno', v: openInc[0].svc });
        actions.push({ id: 'inc', label: 'Otevřít ' + openInc[0].id, kind: 'link', href: 'Onhost.dc.html#/stav' });
      } else facts.push({ k: 'Incidenty', v: 'žádný otevřený — platforma běží' });
    }
    if (myTickets.length) facts.push({ k: 'Vaše otevřené tickety', v: myTickets.map(function (t) { return t.id; }).slice(0, 3).join(', ') });

    actions.push({ id: 'doc', label: kb.hrefLabel, kind: 'link', href: kb.href });
    actions.push({ id: 'esc', label: 'Předat na podporu jako ticket', kind: 'ticket' });

    return {
      topic: topic, label: labelOf(topic), confident: topic !== 'ostatni',
      text: kb.a, facts: facts, actions: actions
    };
  }

  /* ---------- generátor zátěžových dat ---------- */

  var VN = ['Studio Kvark', 'Medisys', 'Hraj.gg', 'Obec Březina', 'Datalog', 'Nordis', 'Pekárna Vlček', 'Kolektiv 9',
    'Tiskárna Rous', 'Vinaři Mikulov', 'Fotbal Zlín', 'Skladiště CZ', 'Auto Hejl', 'Klinika Vita', 'Škola Podolí',
    'Radio Beat FM', 'Kavárna Zrno', 'Bikepark Jih', 'Aerotech', 'Statik Novák'];
  var VD = ['kvark.cz', 'medisys.cz', 'hraj.gg', 'obec-brezina.cz', 'datalog.cz', 'nordis.eu', 'vlcek.cz', 'k9.cz',
    'rous.cz', 'vinari.cz', 'fczlin.cz', 'sklad.cz', 'autohejl.cz', 'vita.cz', 'zspodoli.cz', 'beatfm.cz',
    'zrno.coffee', 'bikepark.cz', 'aerotech.cz', 'statik-novak.cz'];
  var VSKU = [
    { sku: 'web-business', name: 'Webhosting Business', unitNet: 390 },
    { sku: 'vps-pro', name: 'VPS Pro 8/16', unitNet: 1290 },
    { sku: 'vps-max', name: 'VPS Max 16/64', unitNet: 3190 },
    { sku: 'dedicated-epyc', name: 'Dedikovaný EPYC 32c', unitNet: 12400 },
    { sku: 'game-mc', name: 'Game hosting Minecraft 16 GB', unitNet: 690 },
    { sku: 'mail-pro', name: 'Poštovní server Pro', unitNet: 490 },
    { sku: 'bucket-s3', name: 'Objektové úložiště 2 TB', unitNet: 840 },
    { sku: 'ssl-ev', name: 'SSL EV', unitNet: 2400 }
  ];
  var VSUBJ = [
    ['Web hlásí 503 po nasazení', 'Po nasazení nové verze web vrací 503, rollback jsme zkoušeli.', 'dostupnost'],
    ['Vyšší latence na uzlu PRG-3', 'Hráči hlásí 90+ ms, dřív to bylo 18 ms. Traceroute posílám.', 'latence'],
    ['Databáze zpomalila po importu', 'Import 40 GB dat a od té doby jsou dotazy 4× pomalejší.', 'vykon'],
    ['Faktura na jiné IČO', 'Fakturu potřebujeme přepsat na novou firmu, IČO máme nové.', 'fakturace'],
    ['Obnova jednoho souboru', 'Kolega přepsal konfiguraci, potřebujeme verzi ze včerejška.', 'zalohy'],
    ['Certifikát vypršel', 'Prohlížeč hlásí neplatný certifikát na subdoméně shop.', 'pristup'],
    ['Nepropsané DNS záznamy', 'Změnili jsme MX a po dvou hodinách je pošta stále jinde.', 'dns'],
    ['Pošta padá do spamu', 'Odchozí zprávy končí v Gmailu ve spamu, DMARC hlásí fail.', 'mail'],
    ['Naplánovat migraci PostgreSQL', 'Potřebujeme okno pro migraci, ideálně v noci ze soboty na nedělí.', 'migrace'],
    ['Navýšení tarifu bez odstávky', 'Potřebujeme více RAM ještě před kampaní, jde to bez restartu?', 'objednavka'],
    ['Nedostupnost e-shopu ráno', 'Mezi 7:10 a 7:40 nešel e-shop, ztratili jsme objednávky.', 'dostupnost'],
    ['Pomalé zápisy na disk', 'IOPS na datovém disku spadlo na pětinu, aplikace čeká.', 'vykon'],
    ['Upomínka k zaplacené faktuře', 'Přišla upomínka, ale platbu jsme poslali už minulý týden.', 'fakturace'],
    ['Zapomenuté heslo do panelu', 'Kolega odešel, potřebujeme převzít přístup na nový e-mail.', 'pristup'],
    ['Přesun domény k vám', 'Chceme přenést doménu i zónu, jak dlouho to potrvá?', 'dns']
  ];
  function pick(a, i) { return a[i % a.length]; }
  /* Deterministický LCG — rozložení témat a stavů nesmí vyjít na přesné tercie modula. */
  function lcg(seed) {
    var s = seed || 20260831;
    return function (max) { s = (s * 1103515245 + 12345) & 0x7fffffff; return Math.floor(s / 0x7fffffff * max); };
  }
  function volume(n) {
    var R = lcg(Date.now() & 0x7fffff);
    var orders = Math.max(0, Math.round(n));
    var tickets = Math.round(orders * 1.4);
    var made = { orders: 0, tickets: 0, invoices: 0 };
    var oStates = ['aktivni', 'aktivni', 'aktivni', 'aktivni', 'provisioning', 'zaplaceno', 'nova', 'pozastaveno', 'zruseno'];
    var tStates = ['otevreny', 'ceka', 'vyreseny', 'vyreseny', 'vyreseny'];
    var prios = ['nizka', 'stredni', 'stredni', 'vysoka'];
    var newOrders = [], newTickets = [], newInv = [];
    /* Vodočty nad existujícími záznamy — dávka nesmí vyrobenými čísly narazit do seedu. */
    var oMax = db.seq || 0, vMax = db.iseq || 0;
    (db.orders || []).concat(db.tickets || []).forEach(function (r) { var t = tailOf(r.id) - 1000; if (t > oMax) oMax = t; });
    (db.invoices || []).forEach(function (v) { var t = tailOf(v.id); if (t > vMax) vMax = t; });
    for (var i = 0; i < orders; i++) {
      var ni = (i * 7) % VN.length;
      var name = pick(VN, ni), dom = pick(VD, ni);
      var email = (i % 3 === 0 ? 'it@' : i % 3 === 1 ? 'provoz@' : 'fakturace@') + dom;
      var sku = pick(VSKU, R(VSKU.length * 4));
      var qty = 1 + R(3);
      var net = sku.unitNet * qty;
      var st = oStates[R(oStates.length)];
      var ageH = 2 + R(900);
      db.seq = (db.seq || 0) + 1;
      var oid = 'OH-2026-' + String(2000 + db.seq);
      var o = { id: oid, email: email, name: name, state: st,
        items: [{ sku: sku.sku, name: sku.name, qty: qty, unitNet: sku.unitNet }],
        commit: [1, 12, 24][i % 3], total: Math.round(net * 1.21), at: h(ageH),
        hist: [['nova', h(ageH)], [st, h(Math.max(1, ageH - 3))]], src: 'seed' };
      newOrders.push(o);
      made.orders++;
      vMax++;
      var paid = ['aktivni', 'provisioning', 'zaplaceno'].indexOf(st) >= 0;
      var overdue = st === 'pozastaveno';
      var issued = h(ageH);
      newInv.push({ id: 'FV-2026-' + String(vMax).padStart(4, '0'), order: oid, email: email, name: name,
        rows: [{ name: sku.name, qty: qty, unitNet: sku.unitNet }], net: net, vat: Math.round(net * 0.21),
        total: Math.round(net * 1.21), state: paid ? 'zaplacena' : overdue ? 'po_splatnosti' : 'vystavena',
        issued: issued, due: issued + 14 * DAY, paid: paid ? issued + 2 * 3600000 : null,
        method: paid ? (i % 2 ? 'karta' : 'převod') : null, period: commitLabel([1, 12, 24][i % 3]) });
      made.invoices++;
    }
    for (var j = 0; j < tickets; j++) {
      var s = VSUBJ[R(VSUBJ.length)];
      var nj = R(VN.length);
      var tname = pick(VN, nj), tdom = pick(VD, nj);
      var temail = (j % 2 ? 'it@' : 'provoz@') + tdom;
      var tst = tStates[R(tStates.length)];
      var tage = 1 + R(700);
      db.seq = (db.seq || 0) + 1;
      var msgs = [{ from: 'zakaznik', at: h(tage), text: s[1] }];
      if (tst !== 'otevreny') msgs.push({ from: 'podpora', at: h(Math.max(1, tage - 2)), text: 'Díváme se na to, ozveme se s nálezem.' });
      newTickets.push({ id: 'TK-2026-' + String(2000 + db.seq), email: temail, name: tname, subject: s[0],
        prio: prios[R(prios.length)], state: tst, at: h(tage), svc: null, topic: s[2], msgs: msgs });
      made.tickets++;
    }
    db.orders = newOrders.concat(db.orders);
    db.tickets = newTickets.concat(db.tickets);
    db.invoices = newInv.concat(db.invoices);
    db.iseq = vMax;
    IDX = null; TCACHE = {};
    return made;
  }

  /* ---------- veřejné API ---------- */

  var API = {
    KEY: KEY,
    flows: { order: ORDER_FLOW, ticket: TICKET_FLOW, incident: INC_FLOW, invoice: INV_FLOW },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; },
    all: function () { return db; },
    money: money,
    /* České skloňování počtů: 1 / 2–4 / ostatní včetně nuly. Jedno místo pro všechny plochy. */
    pl: function (n, one, few, many) { return n + ' ' + (n === 1 ? one : (n >= 2 && n <= 4) ? few : many); },

    /* --- objednávky --- */
    orders: function (filter) {
      var l = db.orders.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.email) l = l.filter(function (o) { return o.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (o) { return o.state === filter.state; });
      return l;
    },
    order: function (id) { return idx().o[id] || null; },
    createOrder: function (p) {
      var id = p.id || seq('OH');
      var o = { id: id, email: p.email || '', name: p.name || '', state: 'nova',
        items: p.items || [], commit: p.commit || 1, total: p.total || 0, at: now(),
        hist: [['nova', now()]], src: p.src || 'web' };
      db.orders.unshift(o);
      log(p.src || 'web', 'Objednávka ' + id + ' vytvořena');
      notify('internal', 'order', id, 'Nová objednávka ' + (o.name || o.email), money(o.total) + ' · čeká na platbu', 'Onhost-admin.dc.html');
      notify('customer', 'order', id, 'Objednávka přijata', id + ' · ' + money(o.total), 'Onhost-app.dc.html');
      queue('welcome', o.email, 'Vítejte v Onhost', { cislo: id, jmeno: o.name, castka: money(o.total) }, id);
      mkInvoice(o);
      save();
      return o;
    },
    setOrderState: function (id, to, who) {
      var o = this.order(id); if (!o) return null;
      var f = ORDER_FLOW[o.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      o.state = to; o.hist.push([to, now()]);
      log(who || 'admin', id + ' → ' + ORDER_FLOW[to].label);
      if (ORDER_FX[to]) ORDER_FX[to](o);
      save();
      return o;
    },

    /* --- tickety --- */
    tickets: function (filter) {
      var l = db.tickets.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.email) l = l.filter(function (t) { return t.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (t) { return t.state === filter.state; });
      return l;
    },
    ticket: function (id) { return idx().t[id] || null; },
    createTicket: function (p) {
      var id = seq('TK');
      var t = { id: id, email: p.email || '', name: p.name || '', subject: p.subject || 'Bez předmětu',
        prio: p.prio || 'stredni', state: 'otevreny', at: now(), svc: p.svc || null,
        msgs: [{ from: 'zakaznik', at: now(), text: p.text || '' }] };
      db.tickets.unshift(t);
      log('panel', 'Ticket ' + id + ' — ' + t.subject);
      notify('internal', 'ticket', id, 'Nový ticket · ' + (t.name || t.email), t.subject, 'Onhost-admin.dc.html');
      queue('ticket-ack', t.email, 'Přijali jsme váš požadavek ' + id, { cislo: id, predmet: t.subject }, id);
      save();
      return t;
    },
    replyTicket: function (id, from, text) {
      var t = this.ticket(id); if (!t || !String(text || '').trim()) return null;
      t.msgs.push({ from: from, at: now(), text: String(text).trim() });
      if (from === 'podpora') {
        t.state = 'ceka';
        notify('customer', 'ticket', id, 'Odpověď podpory', t.subject, 'Onhost-app.dc.html');
        queue('ticket-reply', t.email, 'Re: ' + t.subject, { cislo: id }, id);
      } else {
        t.state = 'otevreny';
        notify('internal', 'ticket', id, 'Reakce zákazníka · ' + (t.name || t.email), t.subject, 'Onhost-admin.dc.html');
      }
      log(from === 'podpora' ? 'podpora' : 'panel', id + ' — nová zpráva');
      save();
      return t;
    },
    setTicketState: function (id, to, who) {
      var t = this.ticket(id); if (!t) return null;
      var f = TICKET_FLOW[t.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      t.state = to;
      log(who || 'podpora', id + ' → ' + TICKET_FLOW[to].label);
      if (to === 'vyreseny') {
        notify('customer', 'ticket', id, 'Požadavek vyřešen', t.subject, 'Onhost-app.dc.html');
        queue('ticket-solved', t.email, 'Vyřešeno: ' + t.subject, { cislo: id }, id);
      }
      save();
      return t;
    },

    /* --- incidenty --- */
    incidents: function (filter) {
      var l = db.incidents.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.open) l = l.filter(function (i) { return i.state !== 'vyreseno'; });
      return l;
    },
    incident: function (id) { return idx().i[id] || null; },
    createIncident: function (p) {
      db.seq = (db.seq || 0) + 1;
      var id = p.id || ('INC-' + new Date().getFullYear() + '-' + String(db.seq).padStart(4, '0'));
      var i = { id: id, title: p.title || 'Incident', svc: p.svc || 'Platforma', sev: p.sev || 'p3',
        state: 'vysetrovani', at: now(), impact: p.impact || '',
        hist: [['vysetrovani', now(), p.note || 'Incident otevřen.']] };
      db.incidents.unshift(i);
      log('noc', 'Incident ' + id + ' otevřen — ' + i.title);
      notify('internal', 'incident', id, id + ' otevřen', i.title, 'Onhost-admin.dc.html#/incidenty');
      notify('customer', 'incident', id, 'Probíhá incident', i.title, 'Onhost.dc.html#/stav');
      save();
      return i;
    },
    setIncidentState: function (id, to, note) {
      var i = this.incident(id); if (!i) return null;
      var f = INC_FLOW[i.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      i.state = to; i.hist.push([to, now(), note || INC_FLOW[to].label + '.']);
      log('noc', id + ' → ' + INC_FLOW[to].label);
      notify('customer', 'incident', id, id + ' — ' + INC_FLOW[to].label, note || i.title, 'Onhost.dc.html#/stav');
      notify('internal', 'incident', id, id + ' → ' + INC_FLOW[to].label, i.svc, 'Onhost-admin.dc.html#/incidenty');
      if (to === 'vyreseno') queue('incident-resolved', 'dotceni@onhost.cz', 'Vyřešeno: ' + i.title, { incident: id }, id);
      save();
      return i;
    },

    /* --- fakturace --- */
    invoices: function (filter) {
      var l = (db.invoices || []).slice().sort(function (a, b) { return b.issued - a.issued; });
      if (filter && filter.email) l = l.filter(function (i) { return i.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (i) { return i.state === filter.state; });
      if (filter && filter.order) l = l.filter(function (i) { return i.order === filter.order; });
      if (filter && filter.unpaid) l = l.filter(function (i) { return i.state === 'vystavena' || i.state === 'po_splatnosti'; });
      return l;
    },
    invoice: function (id) { return idx().v[id] || null; },
    issueInvoice: function (orderId) {
      var o = this.order(orderId); if (!o) return null;
      var inv = mkInvoice(o); save(); return inv;
    },
    payInvoice: function (id, method) {
      var i = this.invoice(id); if (!i || i.state === 'zaplacena' || i.state === 'stornovana') return null;
      i.state = 'zaplacena'; i.paid = now(); i.method = method || 'karta';
      log('fakturace', i.id + ' zaplacena · ' + money(i.total) + ' (' + i.method + ')');
      notify('internal', 'invoice', i.id, 'Platba ' + i.id, (i.name || i.email) + ' · ' + money(i.total), 'Onhost-admin.dc.html#/doklady');
      notify('customer', 'invoice', i.id, 'Platba přijata', i.id + ' · ' + money(i.total), 'Onhost-app.dc.html');
      queue('receipt', i.email, 'Potvrzení platby ' + i.id, { cislo: i.id, castka: money(i.total) }, i.id);
      var o = i.order && this.order(i.order);
      if (o && o.state === 'nova') this.setOrderState(o.id, 'zaplaceno', 'fakturace');
      if (o && o.state === 'pozastaveno') this.setOrderState(o.id, 'aktivni', 'fakturace');
      save();
      return i;
    },
    remindInvoice: function (id) {
      var i = this.invoice(id); if (!i || i.state === 'zaplacena' || i.state === 'stornovana') return null;
      var days = Math.max(0, Math.round((now() - i.due) / DAY));
      queue('dunning', i.email, 'Upomínka k faktuře ' + i.id, { cislo: i.id, castka: money(i.total), dnu: String(days) }, i.id);
      log('fakturace', 'Upomínka k ' + i.id + ' (' + days + ' dní po splatnosti)');
      notify('customer', 'invoice', i.id, 'Upomínka k ' + i.id, money(i.total) + ' · ' + days + ' dní po splatnosti', 'Onhost-app.dc.html');
      save();
      return i;
    },
    creditNote: function (id, reason) {
      var i = this.invoice(id); if (!i || i.state === 'stornovana') return null;
      i.state = 'stornovana';
      var cn = { id: iseq('DK'), order: i.order, email: i.email, name: i.name, credit: true, reason: reason || 'Opravný doklad',
        rows: i.rows.map(function (r) { return { name: r.name, qty: r.qty, unitNet: -r.unitNet }; }),
        net: -i.net, vat: -i.vat, total: -i.total, state: 'zaplacena', issued: now(), due: now(), paid: now(), method: 'zápočet', period: i.period, src: i.id };
      db.invoices.unshift(cn);
      log('fakturace', 'Opravný doklad ' + cn.id + ' k ' + i.id + ' · ' + (reason || ''));
      notify('customer', 'invoice', cn.id, 'Opravný doklad ' + cn.id, money(-cn.total) + ' k ' + i.id, 'Onhost-app.dc.html');
      queue('credit-note', i.email, 'Opravný doklad ' + cn.id, { cislo: cn.id, original: i.id, castka: money(-cn.total) }, cn.id);
      save();
      return cn;
    },
    /* SLA kredit z vyřešeného incidentu — procento z měsíční ceny dotčených objednávek. */
    slaCredit: function (incidentId, pct) {
      var i = this.incident(incidentId); if (!i) return null;
      var p = pct || (i.sev === 'p1' ? 25 : i.sev === 'p2' ? 10 : 5);
      var made = [];
      this.orders({ state: 'aktivni' }).concat(this.orders({ state: 'provisioning' })).forEach(function (o) {
        var base = (o.items || []).reduce(function (s, it) { return s + (it.unitNet || 0) * (it.qty || 1); }, 0);
        var amount = Math.round(base * p / 100);
        if (amount <= 0) return;
        var vat = Math.round(amount * 0.21);
        var cn = { id: iseq('DK'), order: o.id, email: o.email, name: o.name, credit: true,
          reason: 'SLA kredit ' + p + ' % · ' + incidentId,
          rows: [{ name: 'SLA kredit ' + p + ' % (' + incidentId + ')', qty: 1, unitNet: -amount }],
          net: -amount, vat: -vat, total: -(amount + vat), state: 'zaplacena',
          issued: now(), due: now(), paid: now(), method: 'zápočet', period: 'jednorázově', sla: incidentId };
        db.invoices.unshift(cn);
        made.push(cn);
        notify('customer', 'invoice', cn.id, 'SLA kredit ' + p + ' %', money(amount + vat) + ' za ' + incidentId, 'Onhost-app.dc.html');
        queue('sla-credit', o.email, 'SLA kredit k ' + incidentId, { cislo: cn.id, incident: incidentId, castka: money(amount + vat), procent: String(p) }, cn.id);
      });
      if (made.length) log('fakturace', 'SLA kredity k ' + incidentId + ': ' + made.length + ' dokladů, ' + p + ' %');
      save();
      return made;
    },
    /* Dávka: upomínky ke všem po splatnosti, nad 30 dní pozastavení služby. */
    dunningRun: function () {
      var self = this, res = { reminded: 0, suspended: 0 };
      sweepDue();
      this.invoices({ state: 'po_splatnosti' }).forEach(function (i) {
        self.remindInvoice(i.id); res.reminded++;
        var days = Math.round((now() - i.due) / DAY);
        var o = i.order && self.order(i.order);
        if (days >= 30 && o && o.state === 'aktivni') { self.setOrderState(o.id, 'pozastaveno', 'fakturace'); res.suspended++; }
      });
      log('fakturace', 'Dávka upomínek: ' + res.reminded + ' upomínek, ' + res.suspended + '× pozastaveno');
      save();
      return res;
    },
    /* Výnosy — MRR z aktivních objednávek, obrat a pohledávky za 30 dní. */
    revenue: function () {
      var mrr = 0;
      db.orders.forEach(function (o) {
        if (['aktivni', 'provisioning'].indexOf(o.state) < 0) return;
        mrr += (o.items || []).reduce(function (s, i) { return s + (i.unitNet || 0) * (i.qty || 1); }, 0);
      });
      var since = now() - 30 * DAY, inv = db.invoices || [];
      var sum = function (l) { return l.reduce(function (s, i) { return s + i.total; }, 0); };
      return {
        mrr: mrr,
        arr: mrr * 12,
        issued30: sum(inv.filter(function (i) { return i.issued >= since && !i.credit; })),
        paid30: sum(inv.filter(function (i) { return i.state === 'zaplacena' && i.paid >= since; })),
        credits30: -sum(inv.filter(function (i) { return i.credit && i.issued >= since; })) || 0,
        unpaid: sum(inv.filter(function (i) { return i.state === 'vystavena'; })),
        overdue: sum(inv.filter(function (i) { return i.state === 'po_splatnosti'; })),
        avgTicket: (function () {
          var p = inv.filter(function (i) { return !i.credit; });
          return p.length ? Math.round(sum(p) / p.length) : 0;
        })()
      };
    },

    /* --- notifikace --- */
    notifs: function (aud) {
      return db.notifs.filter(function (n) { return !aud || n.aud === aud; })
        .slice().sort(function (a, b) { return b.at - a.at; });
    },
    unread: function (aud) { return this.notifs(aud).filter(function (n) { return !n.read; }).length; },
    markRead: function (aud, id) {
      db.notifs.forEach(function (n) {
        if (id ? n.id === id : (!aud || n.aud === aud)) n.read = true;
      });
      save();
    },

    /* --- maily --- */
    mails: function () { return db.mails.slice().sort(function (a, b) { return b.at - a.at; }); },
    queueMail: function (tpl, to, subject, vars, ref) { queue(tpl, to, subject, vars, ref); save(); },
    sendMail: function (id) {
      var m = db.mails.filter(function (x) { return x.id === id; })[0];
      if (!m) return null;
      m.state = 'sent'; m.at = now();
      log('maily', 'Odesláno „' + m.subject + '“ → ' + m.to);
      save();
      return m;
    },
    sendAllQueued: function () {
      var n = 0;
      db.mails.forEach(function (m) { if (m.state === 'queued') { m.state = 'sent'; m.at = now(); n++; } });
      if (n) { log('maily', 'Dávkově odesláno ' + n + ' zpráv'); save(); }
      return n;
    },

    /* --- log & reset --- */
    log: function () { return db.log.slice(); },
    /* Zápis z panelu (aaPanel, ISPConfig, Proxmox, Pterodactyl) do provozního logu.
       Panel si vede vlastní audit; tohle je druhá kopie na druhém místě, aby se
       daly porovnat. */
    audit: function (who, text) {
      if (!text) return null;
      log(who || 'panel', String(text));
      save();
      return true;
    },
    counts: function () {
      return {
        ordersOpen: db.orders.filter(function (o) { return ['nova', 'zaplaceno', 'provisioning'].indexOf(o.state) >= 0; }).length,
        ticketsOpen: db.tickets.filter(function (t) { return t.state !== 'vyreseny'; }).length,
        incOpen: db.incidents.filter(function (i) { return i.state !== 'vyreseno'; }).length,
        mailsQueued: db.mails.filter(function (m) { return m.state === 'queued'; }).length,
        invUnpaid: (db.invoices || []).filter(function (i) { return i.state === 'vystavena'; }).length,
        invOverdue: (db.invoices || []).filter(function (i) { return i.state === 'po_splatnosti'; }).length,
        unreadInternal: this.unread('internal'),
        unreadCustomer: this.unread('customer')
      };
    },
    reset: function () {
      db = seed(); touched = true; save(); flush();
      try { localStorage.removeItem(KEY); } catch (e) {}
      try { localStorage.setItem(KEY, JSON.stringify(hotSet())); } catch (e) {}
    },

    /* --- shlukování ticketů podle tématu --- */
    topics: function () { return TOPICS.map(function (t) { return { id: t.id, label: t.label }; }); },
    topicOf: function (t) { return topicOf(t); },
    /* Shluky: téma × počet, otevřené, věk, priorita, nejčastější zákazník. */
    clusters: function () {
      var by = {};
      (db.tickets || []).forEach(function (t) {
        var k = topicOf(t);
        var c = by[k] || (by[k] = { id: k, label: labelOf(k), count: 0, open: 0, waiting: 0, solved: 0,
          high: 0, ageSum: 0, ids: [], who: {}, last: 0 });
        c.count++;
        if (t.state === 'otevreny') c.open++;
        else if (t.state === 'ceka') c.waiting++;
        else c.solved++;
        if (t.prio === 'vysoka') c.high++;
        c.ageSum += (now() - t.at) / 3600000;
        if (c.ids.length < 60) c.ids.push(t.id);
        var w = t.name || t.email || '—';
        c.who[w] = (c.who[w] || 0) + 1;
        if (t.at > c.last) c.last = t.at;
      });
      return Object.keys(by).map(function (k) {
        var c = by[k];
        var top = Object.keys(c.who).sort(function (a, b) { return c.who[b] - c.who[a]; })[0] || '—';
        c.avgAge = Math.round(c.ageSum / Math.max(1, c.count));
        c.topCustomer = top;
        c.topCustomerCount = c.who[top] || 0;
        c.share = Math.round(c.count / Math.max(1, (db.tickets || []).length) * 100);
        delete c.who; delete c.ageSum;
        return c;
      }).sort(function (a, b) { return (b.open - a.open) || (b.count - a.count); });
    },
    ticketsByTopic: function (topic) {
      return this.tickets().filter(function (t) { return topicOf(t) === topic; });
    },

    /* --- asistent: odpovědi nad živými daty --- */
    bot: function (text, email) {
      return answer(String(text || ''), email || null, this);
    },

    /* --- zátěžová data --- */
    seedVolume: function (n) {
      var t0 = (window.performance && performance.now()) || Date.now();
      var made = volume(n || 200);
      log('sys', 'Vygenerována zátěžová data: ' + made.orders + ' objednávek, ' + made.tickets + ' ticketů, ' + made.invoices + ' faktur');
      save(); flush();
      made.ms = Math.round(((window.performance && performance.now()) || Date.now()) - t0);
      var s = this.size();
      made.bytes = s.cacheBytes; made.backend = s.backend;
      made.records = s.orders + s.tickets + s.invoices;
      return made;
    },
    size: function () {
      var b = 0; try { b = (localStorage.getItem(KEY) || '').length; } catch (e) {}
      return { bytes: b, cacheBytes: b, backend: idbOK === false ? 'localStorage' : 'IndexedDB',
        orders: (db.orders || []).length, tickets: (db.tickets || []).length,
        invoices: (db.invoices || []).length, incidents: (db.incidents || []).length, log: (db.log || []).length };
    },
    /* Plochy, které kreslí velké tabulky, si počkají na plná data z IDB;
       do té doby mají hot set z cache, takže se nekreslí prázdno. */
    ready: function (fn) { return fn ? hydrated.then(function () { try { fn(API); } catch (e) {} }) : hydrated; },
    flush: flush
  };

  /* Cross-tab: druhá karta zapsala → dotáhneme plný snapshot z IDB, ne zkrácenou
     cache (ta by ostatním kartám umazala data pod rukama).
     Vlastní zápis poznáme podle stampu a ignorujeme ho — jinak se při několika
     otevřených rámech emit vrací dokola a plochy se překreslují bez konce. */
  window.addEventListener('storage', function (e) {
    if (e.key !== STAMP && e.key !== KEY) return;
    var ext = 0;
    try { ext = Number(localStorage.getItem(STAMP) || 0); } catch (e2) {}
    if (!ext || ext === lastStamp || ext <= (db.at || 0)) return;
    idbGet().then(function (snap) {
      if (snap && snap.ver === VER && (snap.at || 0) > (db.at || 0)) {
        db = repair(snap); IDX = null; TCACHE = {}; emit(); return;
      }
      var d = load();
      if (d && !d.part && (d.at || 0) > (db.at || 0)) { db = d; IDX = null; TCACHE = {}; emit(); }
    });
  });

  window.OnhostStore = API;
})();
