/* Onhost — příkazová paleta (⌘K) + dokovaný asistent (⌘J).
   Načítá ji onhost-shell.js, takže je na všech plochách bez zásahu do jejich kódu. */
(function () {
  if (window.OnhostCommand) return;

  var ACC = '#ec3013', INK = '#201e1d', BG = '#f3f2f2';
  var F = 'Archivo,system-ui,sans-serif';
  var MUT = 'rgba(32,30,29,.55)';

  function el(tag, style, text) {
    var n = document.createElement(tag);
    if (style) n.setAttribute('style', style);
    if (text != null) n.textContent = text;
    return n;
  }
  function norm(s) {
    return String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }
  /* Skóre shody: prefix > podřetězec > podsekvence. */
  function score(hay, q) {
    if (!q) return 1;
    var h = norm(hay), n = norm(q);
    if (!h) return 0;
    var i = h.indexOf(n);
    if (i === 0) return 100;
    if (i > 0) return 74 - Math.min(24, i);
    var hi = 0, sc = 0;
    for (var k = 0; k < n.length; k++) {
      var p = h.indexOf(n.charAt(k), hi);
      if (p < 0) return 0;
      sc += (p === hi ? 3 : 1);
      hi = p + 1;
    }
    return Math.min(58, sc * 2);
  }
  function btnCss(kind) {
    var base = 'padding:9px 12px;border:2px solid ' + INK + ';font:800 10px/1 ' + F + ';letter-spacing:.1em;text-transform:uppercase;cursor:pointer;text-decoration:none;display:inline-block';
    if (kind === 'primary') return base + ';background:' + ACC + ';color:#fff';
    return base + ';background:transparent;color:' + INK;
  }
  function kbd(t) {
    return el('span', 'padding:3px 6px;border:1px solid rgba(32,30,29,.3);font:800 9px/1 ' + F + ';letter-spacing:.08em;color:' + MUT, t);
  }

  function ready(fn) {
    if (window.OnhostSession && window.OnhostStore && document.body) return fn();
    setTimeout(function () { ready(fn); }, 60);
  }

  ready(function () {
    var S = window.OnhostStore, Ses = window.OnhostSession;
    var SURFACES = window.OnhostSurfaces || [];
    var here = (location.pathname.split('/').pop() || '').toLowerCase();
    var hereS = SURFACES.filter(function (s) { return s.h.toLowerCase() === here; })[0];
    var mac = /Mac|iP(hone|ad)/.test(navigator.platform || navigator.userAgent);
    var MOD = mac ? '⌘' : 'Ctrl+';

    /* ---------- toast ---------- */
    var toastN = null, toastT = null;
    function toast(text, tone) {
      if (!toastN) {
        toastN = el('div', 'position:fixed;left:50%;bottom:22px;transform:translate(-50%,14px);z-index:2147483400;border:2px solid ' + INK + ';background:#fff;padding:11px 15px;font:700 12px/1.3 ' + F + ';color:' + INK + ';max-width:min(440px,92vw);opacity:0;transition:opacity .16s,transform .16s;box-shadow:0 4px 0 rgba(32,30,29,.2)');
        document.body.appendChild(toastN);
      }
      toastN.textContent = text;
      toastN.style.borderColor = tone === 'bad' ? ACC : INK;
      toastN.style.opacity = '1';
      toastN.style.transform = 'translate(-50%,0)';
      clearTimeout(toastT);
      toastT = setTimeout(function () {
        toastN.style.opacity = '0';
        toastN.style.transform = 'translate(-50%,14px)';
      }, 2600);
    }

    /* ---------- rozsah dat podle role ---------- */
    function scoped(list, key) {
      var sc = Ses.scope();
      if (sc === null) return list;
      if (sc === false) return [];
      return list.filter(function (r) { return r[key || 'email'] === sc; });
    }
    function money(v) { return S.money(v); }

    /* =========================================================
       Příkazová paleta
       ========================================================= */
    var pal = el('div', 'position:fixed;inset:0;z-index:2147483200;display:none;font-family:' + F);
    var palScrim = el('div', 'position:absolute;inset:0;background:rgba(32,30,29,.46);opacity:0;transition:opacity .14s');
    var box = el('div', 'position:absolute;left:50%;top:8vh;transform:translate(-50%,-8px);width:min(680px,94vw);max-height:82vh;display:flex;flex-direction:column;border:2px solid ' + INK + ';background:' + BG + ';opacity:0;transition:opacity .14s,transform .14s;box-shadow:0 10px 0 rgba(32,30,29,.18)');
    pal.appendChild(palScrim); pal.appendChild(box);

    var inWrap = el('div', 'flex:0 0 auto;display:flex;align-items:center;gap:10px;border-bottom:2px solid ' + INK + ';padding:12px 14px;background:#fff');
    inWrap.appendChild(el('span', 'font:900 13px/1 ' + F + ';color:' + ACC, '›'));
    var input = el('input', 'flex:1 1 auto;min-width:0;border:0;outline:0;background:transparent;font:600 16px/1.2 ' + F + ';color:' + INK);
    input.placeholder = 'Hledej plochu, fakturu, ticket, incident nebo akci…';
    input.setAttribute('aria-label', 'Příkazová paleta');
    inWrap.appendChild(input);
    var ctxTag = el('span', 'flex:0 0 auto;font:800 9px/1 ' + F + ';letter-spacing:.14em;text-transform:uppercase;color:' + MUT);
    inWrap.appendChild(ctxTag);
    box.appendChild(inWrap);

    var results = el('div', 'flex:1 1 auto;overflow-y:auto;overscroll-behavior:contain');
    box.appendChild(results);

    var palFoot = el('div', 'flex:0 0 auto;border-top:2px solid ' + INK + ';padding:9px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font:500 11px/1.4 ' + F + ';color:' + MUT);
    [['↑↓', 'pohyb'], ['↵', 'provést'], ['esc', 'zavřít']].forEach(function (p) {
      palFoot.appendChild(kbd(p[0]));
      palFoot.appendChild(el('span', 'margin-right:6px', p[1]));
    });
    box.appendChild(palFoot);
    document.body.appendChild(pal);

    /* --- akce --- */
    function actions() {
      var out = [];
      var unpaid = scoped(S.invoices({ unpaid: true }));
      if (Ses.may('pay') && unpaid.length) {
        var oldest = unpaid[unpaid.length - 1];
        out.push({ t: 'Zaplatit ' + oldest.id + ' kartou', d: money(oldest.total) + ' · nejstarší neuhrazená', tag: 'akce',
          run: function () { S.payInvoice(oldest.id, 'karta'); toast(oldest.id + ' zaplacena — ' + money(oldest.total)); } });
      }
      if (Ses.may('dunning')) {
        out.push({ t: 'Spustit dávku upomínek', d: 'Upomínky po splatnosti, nad 30 dní pozastavení', tag: 'akce',
          run: function () { var r = S.dunningRun(); toast(S.pl(r.reminded, 'upomínka', 'upomínky', 'upomínek') + ' · ' + r.suspended + '× pozastaveno'); } });
      }
      if (Ses.may('mail')) {
        var q = S.counts().mailsQueued;
        out.push({ t: 'Odeslat maily ve frontě', d: q ? S.pl(q, 'zpráva', 'zprávy', 'zpráv') + ' čeká' : 'fronta je prázdná', tag: 'akce',
          run: function () { var n = S.sendAllQueued(); toast(n ? 'Odesláno ' + S.pl(n, 'zpráva', 'zprávy', 'zpráv') : 'Fronta je prázdná'); } });
      }
      if (Ses.may('incident')) {
        var open = S.incidents({ open: true });
        if (open.length) {
          var i = open[0];
          var next = (S.flows.incident[i.state] || {}).next || [];
          if (next.length) {
            out.push({ t: 'Posunout ' + i.id + ' → ' + S.flows.incident[next[0]].label, d: i.title, tag: 'akce',
              run: function () { S.setIncidentState(i.id, next[0]); toast(i.id + ' → ' + S.flows.incident[next[0]].label); } });
          }
        } else {
          out.push({ t: 'Otevřít nový incident · P2', d: 'Zaeviduje incident a vyrozumí stavovou stránku', tag: 'akce',
            run: function () {
              var i = S.createIncident({ title: 'Zvýšená chybovost okrajové vrstvy', svc: 'Platforma', sev: 'p2', impact: 'Část požadavků končí chybou 502.' });
              toast(i.id + ' otevřen'); setTimeout(function () { location.href = 'Onhost-admin.dc.html#/incidenty'; }, 500);
            } });
        }
      }
      out.push({ t: 'Označit notifikace přečtené', d: S.pl(S.unread(), 'nepřečtená', 'nepřečtené', 'nepřečtených'), tag: 'akce',
        run: function () { S.markRead(); toast('Notifikace označeny'); } });
      if (Ses.may('seed')) {
        out.push({ t: 'Vygenerovat zátěžová data · 500 záznamů', d: 'Objednávky, tickety a faktury pro test výkonu', tag: 'akce',
          run: function () { var m = S.seedVolume(500); toast(m.orders + ' objednávek, ' + m.tickets + ' ticketů za ' + m.ms + ' ms'); } });
        out.push({ t: 'Reset provozních dat', d: 'Vrátí store na výchozí seed', tag: 'akce',
          run: function () { S.reset(); toast('Data resetována'); } });
      }
      (Ses.roles || []).forEach(function (r) {
        if (r.id === Ses.role().id) return;
        out.push({ t: 'Přepnout roli · ' + r.label, d: r.d, tag: 'role',
          run: function () { window.__ohGateOff = false; Ses.setRole(r.id); toast('Role: ' + r.label); if (window.OnhostGate) window.OnhostGate(); } });
      });
      out.push({ t: 'Otevřít asistenta', d: 'Chat nad živými provozními daty', tag: 'akce', run: function () { openAsk(true); } });
      return out;
    }

    /* --- záznamy --- */
    function records(q) {
      if (norm(q).length < 2) return [];
      var out = [];
      function push(list, mk) { list.forEach(function (r) { out.push(mk(r)); }); }
      push(scoped(S.invoices()).slice(0, 400), function (i) {
        return { t: i.id + ' · ' + (i.name || i.email), d: money(i.total) + ' · ' + i.state.replace(/_/g, ' '), tag: 'faktura', hay: i.id + ' ' + i.name + ' ' + i.email + ' faktura', rec: { type: 'invoice', id: i.id } };
      });
      push(scoped(S.tickets()).slice(0, 400), function (t) {
        return { t: t.id + ' · ' + t.subject, d: (t.name || t.email) + ' · ' + (S.flows.ticket[t.state] || {}).label, tag: 'ticket', hay: t.id + ' ' + t.subject + ' ' + t.name + ' ' + t.email + ' ticket', rec: { type: 'ticket', id: t.id } };
      });
      push(scoped(S.orders()).slice(0, 400), function (o) {
        return { t: o.id + ' · ' + (o.name || o.email), d: money(o.total) + ' · ' + (S.flows.order[o.state] || {}).label, tag: 'objednávka', hay: o.id + ' ' + o.name + ' ' + o.email + ' objednavka', rec: { type: 'order', id: o.id } };
      });
      if (Ses.scope() === null) {
        push(S.incidents(), function (i) {
          return { t: i.id + ' · ' + i.title, d: i.svc + ' · ' + i.sev.toUpperCase() + ' · ' + (S.flows.incident[i.state] || {}).label, tag: 'incident', hay: i.id + ' ' + i.title + ' ' + i.svc + ' incident', rec: { type: 'incident', id: i.id } };
        });
      }
      return out;
    }

    function surfaceItems() {
      return SURFACES.filter(function (s) { return Ses.can(s.h); }).map(function (s) {
        return { t: s.t, d: s.d, tag: 'plocha', hay: s.t + ' ' + s.d + ' ' + s.g, href: s.h, here: s.h.toLowerCase() === here };
      });
    }

    var items = [], sel = 0, peek = null;

    function build(q) {
      var pool = surfaceItems().concat(actions().map(function (a) { a.hay = a.t + ' ' + a.d; return a; })).concat(records(q));
      var ORD = ['plocha', 'akce', 'role', 'faktura', 'ticket', 'objednávka', 'incident'];
      /* Dotaz, který vypadá jako číslo dokladu, řadí záznamy před plochy. */
      if (/\d/.test(q) || /^(fa|tk|oh|inc|dk)/i.test(String(q || '').trim())) {
        ORD = ['faktura', 'ticket', 'objednávka', 'incident', 'plocha', 'akce', 'role'];
      }
      var ranked = pool.map(function (it) {
        var s = Math.max(score(it.t, q), score(it.hay || '', q) * 0.92);
        return { it: it, s: s };
      }).filter(function (r) { return r.s > 0; })
        .sort(function (a, b) {
          var ga = ORD.indexOf(a.it.tag), gb = ORD.indexOf(b.it.tag);
          if (ga !== gb) return ga - gb;
          return b.s - a.s;
        });
      return ranked.slice(0, 40).map(function (r) { return r.it; });
    }

    function row(it, i) {
      var on = i === sel;
      var a = el(it.href && !it.here ? 'a' : 'div', [
        'display:grid;grid-template-columns:1fr auto;gap:3px 12px;align-items:center;text-decoration:none;color:' + INK,
        'padding:10px 14px;border-bottom:1px solid rgba(32,30,29,.1);cursor:pointer',
        on ? 'background:rgba(236,48,19,.1);box-shadow:inset 4px 0 0 ' + ACC : 'background:transparent'
      ].join(';'));
      if (it.href && !it.here) a.href = it.href;
      var t = el('div', 'min-width:0');
      t.appendChild(el('div', 'font:700 14px/1.25 ' + F + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap', it.t));
      t.appendChild(el('div', 'margin-top:2px;font:500 12px/1.3 ' + F + ';color:' + MUT + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap', it.here ? 'jsi tady' : it.d));
      a.appendChild(t);
      a.appendChild(el('div', 'font:800 9px/1 ' + F + ';letter-spacing:.14em;text-transform:uppercase;color:' + (on ? ACC : 'rgba(32,30,29,.4)'), it.tag));
      a.onmouseenter = function () { sel = i; paint(true); };
      a.onclick = function (e) { if (!it.href || it.here) { e.preventDefault(); } run(it); };
      return a;
    }

    function paint(keepScroll) {
      results.textContent = '';
      if (peek) { results.appendChild(peekCard(peek)); return; }
      if (!items.length) {
        results.appendChild(el('div', 'padding:26px 14px;font:500 13px/1.5 ' + F + ';color:' + MUT, 'Nic neodpovídá. Zkus číslo dokladu, jméno klienta nebo „upomínky“.'));
        return;
      }
      var last = null;
      items.forEach(function (it, i) {
        if (it.tag !== last) {
          last = it.tag;
          results.appendChild(el('div', 'padding:10px 14px 5px;font:800 9px/1 ' + F + ';letter-spacing:.18em;text-transform:uppercase;color:rgba(32,30,29,.42);border-top:1px solid rgba(32,30,29,.12);background:rgba(32,30,29,.03)', it.tag));
        }
        results.appendChild(row(it, i));
      });
      if (!keepScroll) results.scrollTop = 0;
    }

    /* --- náhled záznamu s akcemi --- */
    function peekCard(rec) {
      var wrap = el('div', 'padding:16px 14px;display:grid;gap:12px');
      var back = el('button', 'justify-self:start;padding:6px 9px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 9px/1 ' + F + ';letter-spacing:.12em;text-transform:uppercase;cursor:pointer', '← zpět');
      back.onclick = function () { peek = null; paint(); input.focus(); };
      wrap.appendChild(back);

      var facts = [], title = '', sub = '', acts = [], link = null;
      if (rec.type === 'invoice') {
        var i = S.invoice(rec.id); if (!i) { peek = null; return wrap; }
        title = i.id; sub = (i.name || i.email) + ' · ' + (i.credit ? 'opravný doklad' : 'faktura');
        facts = [['Celkem', money(i.total)], ['Základ', money(i.net)], ['DPH', money(i.vat)],
          ['Stav', i.state.replace(/_/g, ' ')], ['Vystaveno', new Date(i.issued).toLocaleDateString('cs-CZ')],
          ['Splatnost', new Date(i.due).toLocaleDateString('cs-CZ')]];
        link = 'Onhost-admin.dc.html#/doklady';
        if (Ses.may('pay') && i.state !== 'zaplacena' && i.state !== 'stornovana') {
          acts.push(['Zaplatit kartou', 'primary', function () { S.payInvoice(i.id, 'karta'); toast(i.id + ' zaplacena'); peek = null; refresh(); }]);
        }
        if (Ses.may('dunning') && i.state === 'po_splatnosti') {
          acts.push(['Poslat upomínku', '', function () { S.remindInvoice(i.id); toast('Upomínka k ' + i.id + ' ve frontě'); refresh(); }]);
        }
        if (Ses.may('dunning') && i.state !== 'stornovana' && !i.credit) {
          acts.push(['Opravný doklad', '', function () { var cn = S.creditNote(i.id, 'Storno na žádost'); toast('Vystaven ' + cn.id); peek = null; refresh(); }]);
        }
      } else if (rec.type === 'ticket') {
        var t = S.ticket(rec.id); if (!t) { peek = null; return wrap; }
        title = t.id; sub = t.subject;
        facts = [['Zákazník', t.name || t.email], ['Priorita', t.prio], ['Stav', (S.flows.ticket[t.state] || {}).label],
          ['Zpráv', String((t.msgs || []).length)], ['Téma', S.topicOf ? (S.topics().filter(function (x) { return x.id === S.topicOf(t); })[0] || {}).label || '—' : '—'],
          ['Založen', new Date(t.at).toLocaleString('cs-CZ')]];
        link = 'Onhost-admin.dc.html#/fronta';
        ((S.flows.ticket[t.state] || {}).next || []).forEach(function (nx) {
          if (!Ses.may('ticket')) return;
          acts.push([S.flows.ticket[nx].label, nx === 'vyreseny' ? 'primary' : '', function () { S.setTicketState(t.id, nx); toast(t.id + ' → ' + S.flows.ticket[nx].label); refresh(); }]);
        });
      } else if (rec.type === 'order') {
        var o = S.order(rec.id); if (!o) { peek = null; return wrap; }
        title = o.id; sub = (o.name || o.email) + ' · ' + money(o.total);
        facts = [['Stav', (S.flows.order[o.state] || {}).label], ['Položek', String((o.items || []).length)],
          ['Fakturace', o.commit === 12 ? 'ročně' : 'měsíčně'], ['Zdroj', o.src || 'web'],
          ['Vytvořeno', new Date(o.at).toLocaleString('cs-CZ')]];
        link = 'Onhost-admin.dc.html';
        ((S.flows.order[o.state] || {}).next || []).forEach(function (nx) {
          if (!Ses.may('order')) return;
          acts.push([S.flows.order[nx].label, nx === 'aktivni' ? 'primary' : '', function () { S.setOrderState(o.id, nx, 'paleta'); toast(o.id + ' → ' + S.flows.order[nx].label); refresh(); }]);
        });
      } else if (rec.type === 'incident') {
        var inc = S.incident(rec.id); if (!inc) { peek = null; return wrap; }
        title = inc.id; sub = inc.title;
        facts = [['Služba', inc.svc], ['Závažnost', inc.sev.toUpperCase()], ['Stav', (S.flows.incident[inc.state] || {}).label],
          ['Otevřen', new Date(inc.at).toLocaleString('cs-CZ')], ['Kroků', String((inc.hist || []).length)]];
        link = 'Onhost-admin.dc.html#/incidenty';
        ((S.flows.incident[inc.state] || {}).next || []).forEach(function (nx) {
          if (!Ses.may('incident')) return;
          acts.push([S.flows.incident[nx].label, nx === 'vyreseno' ? 'primary' : '', function () { S.setIncidentState(inc.id, nx); toast(inc.id + ' → ' + S.flows.incident[nx].label); refresh(); }]);
        });
        if (Ses.may('order') && inc.state === 'vyreseno') {
          acts.push(['Vystavit SLA kredity', '', function () { var m = S.slaCredit(inc.id); toast(m.length ? S.pl(m.length, 'kredit', 'kredity', 'kreditů') + ' vystaveno' : 'Žádné dotčené objednávky'); refresh(); }]);
        }
      }

      wrap.appendChild(el('div', 'font:900 clamp(20px,4vw,26px)/1.05 ' + F + ';letter-spacing:-.02em', title));
      wrap.appendChild(el('div', 'font:600 13px/1.35 ' + F + ';color:' + MUT, sub));
      var grid = el('div', 'display:grid;grid-template-columns:repeat(auto-fit,minmax(min(130px,100%),1fr));gap:10px 14px;border-top:2px solid ' + INK + ';border-bottom:1px solid rgba(32,30,29,.16);padding:12px 0');
      facts.forEach(function (f) {
        var c = el('div', 'display:grid;gap:2px;min-width:0');
        c.appendChild(el('div', 'font:800 9px/1 ' + F + ';letter-spacing:.14em;text-transform:uppercase;color:rgba(32,30,29,.45)', f[0]));
        c.appendChild(el('div', 'font:700 13px/1.25 ' + F + ';overflow:hidden;text-overflow:ellipsis', String(f[1] == null ? '—' : f[1])));
        grid.appendChild(c);
      });
      wrap.appendChild(grid);
      var row2 = el('div', 'display:flex;flex-wrap:wrap;gap:8px');
      acts.forEach(function (a) {
        var b = el('button', btnCss(a[1]), a[0]);
        b.onclick = a[2];
        row2.appendChild(b);
      });
      if (!acts.length) row2.appendChild(el('div', 'font:500 12px/1.4 ' + F + ';color:' + MUT, 'Role ' + Ses.role().label + ' zde nemá žádnou akci — jen čtení.'));
      if (link && Ses.can(link)) {
        var go = el('a', btnCss(''), 'Otevřít plochu →');
        go.href = link; row2.appendChild(go);
      }
      wrap.appendChild(row2);
      return wrap;
    }

    function refresh() { items = build(input.value); paint(true); }

    function run(it) {
      if (it.rec) { peek = it.rec; paint(); return; }
      if (it.run) { setPalOpen(false); it.run(); return; }
      if (it.href && !it.here) { location.href = it.href; return; }
      setPalOpen(false);
    }

    var palOpen = false;
    function setPalOpen(v) {
      palOpen = v;
      if (v) {
        peek = null;
        ctxTag.textContent = Ses.role().label;
        input.value = '';
        items = build('');
        sel = 0;
        paint();
        pal.style.display = 'block';
        setTimeout(function () {
          if (!palOpen) return;
          palScrim.style.opacity = '1';
          box.style.opacity = '1';
          box.style.transform = 'translate(-50%,0)';
        }, 16);
        setTimeout(function () { input.focus(); input.select(); }, 30);
      } else {
        palScrim.style.opacity = '0';
        box.style.opacity = '0';
        box.style.transform = 'translate(-50%,-8px)';
        setTimeout(function () { if (!palOpen) pal.style.display = 'none'; }, 150);
      }
    }
    palScrim.onclick = function () { setPalOpen(false); };
    input.oninput = function () { peek = null; sel = 0; items = build(input.value); paint(); };
    input.onkeydown = function (e) {
      if (e.key === 'ArrowDown' || (e.key === 'Tab' && !e.shiftKey)) { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); paint(true); scrollSel(); }
      else if (e.key === 'ArrowUp' || (e.key === 'Tab' && e.shiftKey)) { e.preventDefault(); sel = Math.max(0, sel - 1); paint(true); scrollSel(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (peek) return; if (items[sel]) run(items[sel]); }
      else if (e.key === 'Escape') { e.preventDefault(); if (peek) { peek = null; paint(); } else setPalOpen(false); }
    };
    function scrollSel() {
      var rows = results.querySelectorAll('[style*="inset 4px 0 0"]');
      var n = rows[0];
      if (!n) return;
      var rt = n.offsetTop, rb = rt + n.offsetHeight;
      if (rt < results.scrollTop) results.scrollTop = rt - 8;
      else if (rb > results.scrollTop + results.clientHeight) results.scrollTop = rb - results.clientHeight + 8;
    }

    /* =========================================================
       Dokovaný asistent
       ========================================================= */
    var CHAT_K = 'onhost.chat';
    function loadChat() {
      try { var r = JSON.parse(localStorage.getItem(CHAT_K) || '[]'); return Array.isArray(r) ? r.slice(-24) : []; }
      catch (e) { return []; }
    }
    function saveChat(l) { try { localStorage.setItem(CHAT_K, JSON.stringify(l.slice(-24))); } catch (e) {} }
    var chat = loadChat();

    var dock = el('button', [
      'position:fixed;right:14px;bottom:14px;z-index:2147483001;display:flex;align-items:center;gap:8px',
      'padding:0 13px;height:38px;border:2px solid ' + INK + ';background:#fff;color:' + INK,
      'font:800 11px/1 ' + F + ';letter-spacing:.12em;text-transform:uppercase;cursor:pointer;box-shadow:0 3px 0 rgba(32,30,29,.22)'
    ].join(';'));
    dock.title = 'Asistent (' + MOD + 'J)';
    dock.appendChild(el('span', 'width:9px;height:9px;background:' + ACC + ';flex:0 0 auto'));
    dock.appendChild(el('span', null, 'Asistent'));
    document.body.appendChild(dock);

    var ask = el('div', 'position:fixed;inset:0;z-index:2147483100;display:none;font-family:' + F);
    var askScrim = el('div', 'position:absolute;inset:0;background:rgba(32,30,29,.38);opacity:0;transition:opacity .16s');
    var askP = el('div', [
      'position:absolute;right:0;top:0;bottom:0;width:min(420px,100vw)',
      'background:' + BG + ';border-left:2px solid ' + INK + ';display:flex;flex-direction:column',
      'transform:translateX(102%);transition:transform .22s cubic-bezier(.2,.7,.2,1)'
    ].join(';'));
    ask.appendChild(askScrim); ask.appendChild(askP);

    var aHead = el('div', 'flex:0 0 auto;border-bottom:2px solid ' + INK + ';padding:16px 18px 13px;display:flex;align-items:flex-start;gap:12px');
    var aT = el('div', 'min-width:0');
    aT.appendChild(el('div', 'font:900 18px/1.05 ' + F + ';letter-spacing:-.01em', 'Asistent'));
    var aCtx = el('div', 'margin-top:5px;font:500 11px/1.4 ' + F + ';color:' + MUT);
    aT.appendChild(aCtx);
    aHead.appendChild(aT);
    var aClose = el('button', 'margin-left:auto;flex:0 0 auto;width:30px;height:30px;border:2px solid ' + INK + ';background:transparent;color:' + INK + ';font:800 14px/1 ' + F + ';cursor:pointer', '×');
    aHead.appendChild(aClose);
    askP.appendChild(aHead);

    var stream = el('div', 'flex:1 1 auto;overflow-y:auto;overscroll-behavior:contain;padding:14px 18px;display:flex;flex-direction:column;gap:12px');
    askP.appendChild(stream);

    var chips = el('div', 'flex:0 0 auto;padding:0 18px 10px;display:flex;flex-wrap:wrap;gap:6px');
    askP.appendChild(chips);

    var aForm = el('form', 'flex:0 0 auto;border-top:2px solid ' + INK + ';padding:12px 18px;display:flex;gap:8px;background:#fff');
    var aIn = el('input', 'flex:1 1 auto;min-width:0;border:2px solid ' + INK + ';background:' + BG + ';padding:10px 11px;font:600 13px/1.2 ' + F + ';color:' + INK + ';outline:0');
    aIn.placeholder = 'Zeptej se na cokoli z provozu…';
    aIn.setAttribute('aria-label', 'Dotaz na asistenta');
    var aSend = el('button', btnCss('primary'), 'Poslat');
    aSend.type = 'submit';
    aForm.appendChild(aIn); aForm.appendChild(aSend);
    askP.appendChild(aForm);
    document.body.appendChild(ask);

    function bubble(m) {
      var mine = m.who === 'me';
      var w = el('div', 'display:flex;' + (mine ? 'justify-content:flex-end' : 'justify-content:flex-start'));
      var b = el('div', [
        'max-width:86%;padding:11px 13px;border:2px solid ' + INK,
        mine ? 'background:' + ACC + ';color:#fff' : 'background:#fff;color:' + INK,
        'font:500 13px/1.5 ' + F + ';text-wrap:pretty'
      ].join(';'));
      b.textContent = m.text;
      w.appendChild(b);
      var box2 = el('div', 'display:grid;gap:8px');
      box2.appendChild(w);
      if (m.facts && m.facts.length) {
        var g = el('div', 'display:grid;gap:6px;border:2px solid ' + INK + ';background:#fff;padding:11px 12px');
        m.facts.forEach(function (f) {
          var r = el('div', 'display:flex;gap:10px;align-items:baseline');
          r.appendChild(el('div', 'font:800 9px/1.3 ' + F + ';letter-spacing:.12em;text-transform:uppercase;color:rgba(32,30,29,.5);flex:1 1 auto', f.k));
          r.appendChild(el('div', 'font:700 12px/1.3 ' + F + ';text-align:right', String(f.v)));
          g.appendChild(r);
        });
        box2.appendChild(g);
      }
      if (m.actions && m.actions.length) {
        var ar = el('div', 'display:flex;flex-wrap:wrap;gap:6px');
        m.actions.forEach(function (a) {
          if (a.kind === 'pay' && !Ses.may('pay')) return;
          var b2 = el(a.kind === 'link' ? 'a' : 'button', btnCss(a.kind === 'pay' ? 'primary' : ''), a.label);
          if (a.kind === 'link') { b2.href = a.href; }
          else b2.onclick = function () { doAction(a, m.q); };
          ar.appendChild(b2);
        });
        box2.appendChild(ar);
      }
      return box2;
    }

    function doAction(a, q) {
      if (a.kind === 'pay') {
        var i = S.payInvoice(a.ref, 'karta');
        say('bot', i ? a.ref + ' je zaplacená — ' + money(i.total) + '. Potvrzení jsem poslal na ' + i.email + '.' : 'Faktura už zaplacená není k platbě.');
      } else if (a.kind === 'ticket') {
        var u = Ses.user();
        var t = S.createTicket({ email: (u && u.email) || 'demo@onhost.cz', name: u && u.name, subject: (q || 'Dotaz z asistenta').slice(0, 70), text: q || 'Předáno asistentem.' });
        say('bot', 'Založil jsem ticket ' + t.id + ' a přiložil celý kontext. Podpora se ozve do 30 minut.');
      }
    }

    function say(who, text, extra) {
      var m = Object.assign({ who: who, text: text, at: Date.now() }, extra || {});
      chat.push(m);
      saveChat(chat);
      stream.appendChild(bubble(m));
      stream.scrollTop = stream.scrollHeight;
      return m;
    }

    function suggestions() {
      var out = ['Kolik mám neuhrazeno?', 'Běží nějaký incident?', 'Stav mých objednávek'];
      if (Ses.may('dunning')) out = ['Jak jsme na inkasu?', 'Kolik je po splatnosti?', 'Běží nějaký incident?'];
      if (Ses.may('incident')) out = ['Běží nějaký incident?', 'Co je nejčastější téma ticketů?', 'Stav dostupnosti'];
      return out;
    }

    function renderChips() {
      chips.textContent = '';
      suggestions().forEach(function (s) {
        var b = el('button', 'padding:6px 9px;border:1px solid rgba(32,30,29,.3);background:transparent;color:' + INK + ';font:600 11px/1.2 ' + F + ';cursor:pointer', s);
        b.onmouseenter = function () { b.style.background = 'rgba(236,48,19,.08)'; b.style.borderColor = ACC; };
        b.onmouseleave = function () { b.style.background = 'transparent'; b.style.borderColor = 'rgba(32,30,29,.3)'; };
        b.onclick = function () { aIn.value = s; submit(); };
        chips.appendChild(b);
      });
    }

    function submit() {
      var q = String(aIn.value || '').trim();
      if (!q) return;
      aIn.value = '';
      say('me', q);
      var sc = Ses.scope();
      var r = S.bot(q, sc === false ? 'nikdo@nikde' : sc || null);
      setTimeout(function () {
        say('bot', r.text, { facts: r.facts, actions: r.actions, q: q, topic: r.label });
      }, 180);
    }
    aForm.onsubmit = function (e) { e.preventDefault(); submit(); };

    var askOpen = false;
    function openAsk(v) {
      askOpen = v;
      if (v) {
        aCtx.textContent = (hereS ? hereS.t : 'Onhost') + ' · role ' + Ses.role().label;
        stream.textContent = '';
        if (!chat.length) {
          say('bot', 'Ptej se česky na cokoli z provozu — faktury, incidenty, tickety, objednávky. Odpovídám z živých dat a rovnou nabídnu akci.');
        } else chat.forEach(function (m) { stream.appendChild(bubble(m)); });
        renderChips();
        ask.style.display = 'block';
        setTimeout(function () { if (askOpen) { askScrim.style.opacity = '1'; askP.style.transform = 'none'; } }, 16);
        setTimeout(function () { aIn.focus(); stream.scrollTop = stream.scrollHeight; }, 40);
      } else {
        askScrim.style.opacity = '0';
        askP.style.transform = 'translateX(102%)';
        setTimeout(function () { if (!askOpen) ask.style.display = 'none'; }, 230);
      }
    }
    dock.onclick = function () { openAsk(!askOpen); };
    aClose.onclick = function () { openAsk(false); };
    askScrim.onclick = function () { openAsk(false); };

    /* ---------- klávesy ---------- */
    document.addEventListener('keydown', function (e) {
      var meta = e.metaKey || e.ctrlKey;
      var k = String(e.key || '').toLowerCase();
      if (meta && k === 'k') { e.preventDefault(); if (askOpen) openAsk(false); setPalOpen(!palOpen); return; }
      if (meta && k === 'j') { e.preventDefault(); if (palOpen) setPalOpen(false); openAsk(!askOpen); return; }
      if (k === 'escape') { if (palOpen) setPalOpen(false); else if (askOpen) openAsk(false); }
    });

    /* Živá data: dokud je paleta otevřená, drží se čerstvá. */
    S.on(function () { if (palOpen) refresh(); });
    Ses.on(function () {
      if (palOpen) { ctxTag.textContent = Ses.role().label; refresh(); }
      if (askOpen) { aCtx.textContent = (hereS ? hereS.t : 'Onhost') + ' · role ' + Ses.role().label; renderChips(); }
    });

    /* Úzké displeje: paleta i asistent jdou na plnou plochu. */
    function fit() {
      var narrow = innerWidth < 560;
      box.style.top = narrow ? '0' : '8vh';
      box.style.maxHeight = narrow ? '100vh' : '82vh';
      box.style.width = narrow ? '100vw' : 'min(680px,94vw)';
      box.style.height = narrow ? '100vh' : 'auto';
      dock.style.padding = innerWidth < 420 ? '0 10px' : '0 13px';
    }
    fit();
    addEventListener('resize', fit);

    window.OnhostCommand = {
      palette: setPalOpen,
      assistant: openAsk,
      toast: toast
    };
  });
})();
