/* onhost-domains.js — domény, DNS zóny a kredit registrátora (WEDOS WAPI).
 *
 * Zóna se v WAPI mění dvoufázově: dns-row-add/-update/-delete jen připraví změnu,
 * publikuje ji až dns-domain-commit. Tenhle modul se tak chová taky — nepublikované
 * změny leží ve `pending` a plocha o nich musí říkat pravdu.
 *
 * window.OnhostDomains = { domains, domain, zone, pending, addRow, updateRow,
 *   deleteRow, commit, discard, credit, kpis, nssets, on }
 */
(function () {
  'use strict';
  if (window.OnhostDomains) return;

  var seed = 6612093;
  function rnd() { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; }
  function int(a, b) { return a + Math.floor(rnd() * (b - a + 1)); }
  function pick(a) { return a[Math.floor(rnd() * a.length) % a.length]; }
  function day(offset) {
    var d = new Date(Date.now() + offset * 86400000);
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear();
  }

  var OWNERS = ['Alza Studio s.r.o.', 'Hraj.gg', 'Bistro Kotelna', 'MŠ Nová Ves', 'Tomáš Bervid',
    'Statutární město Brno', 'Wave Digital', 'Klinika Podolí', 'GameHost CZ', 'Petr Doležal'];
  var NAMES = ['alzastudio.cz', 'hraj.gg', 'bistrokotelna.cz', 'msnovaves.cz', 'bervid.eu',
    'brno-zakazky.cz', 'wavedigital.cz', 'klinikapodoli.cz', 'gamehost.cz', 'dolezal-it.cz',
    'kotelna-shop.cz', 'hraj-mc.eu', 'wave-cdn.com', 'podoli-lab.cz', 'novaves-skola.cz',
    'alza-studio.com', 'bervid.dev', 'gamehost.gg', 'brno-tendry.cz', 'onhost-demo.cz'];
  var NSSETS = [
    { id: 'NSSET:ONHOST-1', ns: ['ns.onhost.cz', 'ns2.onhost.cz', 'ns3.onhost.eu'], note: 'Výchozí pro služby u nás' },
    { id: 'NSSET:WEDOS', ns: ['ns.wedos.net', 'ns.wedos.eu', 'ns.wedos.cz'], note: 'Domény bez hostingu' },
    { id: 'vlastní', ns: ['—'], note: 'NS zadané zákazníkem' }
  ];

  var DOMAINS = NAMES.map(function (n, i) {
    var exp = int(-8, 300);
    var auto = rnd() < 0.78;
    return {
      name: n,
      owner: OWNERS[i % OWNERS.length],
      tld: n.split('.').pop(),
      status: exp < 0 ? 'po_expiraci' : (rnd() < 0.06 ? 'transfer' : 'aktivni'),
      expDays: exp,
      expires: day(exp),
      autoRenew: auto,
      nsset: pick(NSSETS).id,
      dnssec: rnd() < 0.35,
      zoneAt: rnd() < 0.85 ? 'wedos' : 'ispconfig',
      svc: rnd() < 0.7 ? 'S-' + (1000 + i * 7) : null,
      priceCzk: n.indexOf('.cz') > 0 ? 179 : (n.indexOf('.eu') > 0 ? 229 : 319),
      wapiId: 7000 + i
    };
  });

  var TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA', 'NS'];
  function rowFor(name, i) {
    var t = i === 0 ? 'A' : (i === 1 ? 'AAAA' : (i === 2 ? 'MX' : (i === 3 ? 'TXT' : pick(TYPES))));
    var data = t === 'A' ? '89.187.' + int(140, 190) + '.' + int(2, 250)
      : t === 'AAAA' ? '2a02:4b8:' + int(10, 99) + '::' + int(2, 99)
        : t === 'MX' ? 'mail.' + name
          : t === 'TXT' ? 'v=spf1 include:_spf.onhost.cz -all'
            : t === 'CNAME' ? name
              : t === 'CAA' ? '0 issue "letsencrypt.org"'
                : t === 'SRV' ? '10 0 25565 game.' + name
                  : 'ns.onhost.cz';
    return {
      id: 'R' + (100000 + i * 13 + name.length),
      host: t === 'MX' ? '' : (i === 1 ? '' : pick(['', 'www', 'mail', 'api', 'game', '_dmarc'])),
      type: t,
      data: data,
      ttl: pick([300, 1800, 3600, 14400]),
      prio: t === 'MX' ? pick([10, 20]) : (t === 'SRV' ? 10 : null),
      changed: null
    };
  }

  var ZONES = {};
  DOMAINS.forEach(function (d) {
    var n = int(5, 11), rows = [];
    for (var i = 0; i < n; i++) rows.push(rowFor(d.name, i));
    ZONES[d.name] = { rows: rows, pending: [], committedAt: int(1, 400), serial: 2026090100 + int(1, 40) };
  });
  /* dvě zóny mají nepublikované změny — bez toho není vidět, že commit existuje */
  ZONES[DOMAINS[1].name].pending = [
    { op: 'add', row: { id: 'Rnew1', host: 'game2', type: 'A', data: '89.187.164.31', ttl: 300, prio: null }, who: 'jan.k@onhost.cz', why: 'nová alokace herního serveru' },
    { op: 'update', row: { id: ZONES[DOMAINS[1].name].rows[0].id, host: '', type: 'A', data: '89.187.164.30', ttl: 300, prio: null }, who: 'jan.k@onhost.cz', why: 'migrace na nový uzel' }
  ];
  ZONES[DOMAINS[6].name].pending = [
    { op: 'delete', row: ZONES[DOMAINS[6].name].rows[3], who: 'vera.p@onhost.cz', why: 'starý SPF po migraci mailu' }
  ];

  var CREDIT = { balance: 41280, currency: 'CZK', low: 15000, renewals30: 27, renewals30Czk: 6431, lastSync: 'před 3 h' };

  var subs = [];
  function emit() { subs.slice().forEach(function (f) { try { f(); } catch (e) {} }); }
  function audit(what, detail) {
    if (window.OnhostStore && window.OnhostStore.audit) {
      try { window.OnhostStore.audit('dns', what, detail); } catch (e) {}
    }
  }
  function z(name) { return ZONES[name] || (ZONES[name] = { rows: [], pending: [], committedAt: 0, serial: 2026090100 }); }

  window.OnhostDomains = {
    domains: function (f) {
      f = f || {};
      return DOMAINS.filter(function (d) {
        if (f.q) {
          var q = String(f.q).toLowerCase();
          if (d.name.indexOf(q) < 0 && d.owner.toLowerCase().indexOf(q) < 0) return false;
        }
        if (f.status && d.status !== f.status) return false;
        if (f.expiring && d.expDays > 30) return false;
        if (f.pending && !z(d.name).pending.length) return false;
        return true;
      });
    },
    domain: function (name) { return DOMAINS.filter(function (d) { return d.name === name; })[0] || DOMAINS[0]; },
    zone: function (name) { return z(name).rows.slice(); },
    zoneMeta: function (name) { var Z = z(name); return { serial: Z.serial, committedAt: Z.committedAt, pending: Z.pending.length }; },
    pending: function (name) { return z(name).pending.slice(); },
    addRow: function (name, row, who, why) {
      z(name).pending.push({ op: 'add', row: Object.assign({ id: 'Rnew' + Date.now() }, row), who: who || 'admin', why: why || '' });
      audit('dns.row.add', name + ' · ' + row.type + ' ' + (row.host || '@') + ' → ' + row.data);
      emit(); return true;
    },
    updateRow: function (name, row, who, why) {
      z(name).pending.push({ op: 'update', row: row, who: who || 'admin', why: why || '' });
      audit('dns.row.update', name + ' · ' + row.id);
      emit(); return true;
    },
    deleteRow: function (name, row, who, why) {
      z(name).pending.push({ op: 'delete', row: row, who: who || 'admin', why: why || '' });
      audit('dns.row.delete', name + ' · ' + row.type + ' ' + (row.host || '@'));
      emit(); return true;
    },
    discard: function (name) {
      var n = z(name).pending.length;
      z(name).pending = [];
      audit('dns.discard', name + ' · zahozeno ' + n + ' změn');
      emit(); return n;
    },
    /** Publikace zóny = dns-domain-commit. Do té doby změna neexistuje. */
    commit: function (name, who) {
      var Z = z(name);
      if (!Z.pending.length) return { ok: false, msg: 'není co publikovat' };
      Z.pending.forEach(function (p) {
        if (p.op === 'add') Z.rows.push(Object.assign({}, p.row, { changed: 'nový' }));
        else if (p.op === 'update') {
          Z.rows = Z.rows.map(function (r) { return r.id === p.row.id ? Object.assign({}, r, p.row, { changed: 'změněný' }) : r; });
        } else Z.rows = Z.rows.filter(function (r) { return r.id !== p.row.id; });
      });
      var n = Z.pending.length;
      Z.pending = []; Z.committedAt = 0; Z.serial += 1;
      audit('dns.commit', name + ' · publikováno ' + n + ' změn · serial ' + Z.serial + ' · ' + (who || 'admin'));
      emit();
      return { ok: true, msg: 'publikováno ' + n + ' změn, serial ' + Z.serial, serial: Z.serial };
    },
    credit: function () { return Object.assign({}, CREDIT); },
    nssets: function () { return NSSETS.slice(); },
    kpis: function () {
      var exp30 = DOMAINS.filter(function (d) { return d.expDays >= 0 && d.expDays <= 30; }).length;
      var over = DOMAINS.filter(function (d) { return d.expDays < 0; }).length;
      var pend = DOMAINS.filter(function (d) { return z(d.name).pending.length; }).length;
      var noAuto = DOMAINS.filter(function (d) { return !d.autoRenew; }).length;
      return { total: DOMAINS.length, exp30: exp30, over: over, pending: pend, noAuto: noAuto, credit: CREDIT.balance };
    },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; }
  };
})();
