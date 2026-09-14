/* onhost-panel-pages.api.js — the remaining panel areas on real data (data seam #37).
 *
 * The prototype narrates notifications and audit, maintenance windows, costs, personal data, monitoring and backups
 * with invented figures. Outside demo mode these views read the organization's own data: the audit trail and the
 * notification feed, the calendar's maintenance windows and the public status, the wallet forecast with spend per
 * project and the subscriptions, the data requests (export / deletion / switching), the uptime monitors and the backups
 * of every service. Views keep the prototype's generic shape (crumb/title/stats/form/cols/rows/side/advice). */
(function () {
  'use strict';
  if (window.OnhostPanelPages) return;
  var S = { busy: {} };

  function A() { return window.OnhostApi; }
  function me() { return (window.ONHOST && window.ONHOST.user) || null; }
  function orgId() { var u = me(); return u && u.organization ? u.organization.id : null; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function rerender(cmp) { try { cmp.setState({ pagesAt: Date.now() }); } catch (e) {} }
  function flash(cmp, t, b) { if (cmp && typeof cmp.flash === 'function') cmp.flash(t, b); }
  function fail(cmp, _, e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); }
  function load(cmp, key, path, map) {
    if (S[key] !== undefined || S.busy[key] || !A()) return;
    S.busy[key] = true;
    A().get(path).then(function (r) { S[key] = map ? map(r) : r; }).catch(function () { S[key] = map ? map({ data: [] }) : { data: [] }; }).then(function () { delete S.busy[key]; rerender(cmp); });
  }
  function reload(cmp, keys) { keys.forEach(function (k) { delete S[k]; }); rerender(cmp); }
  function when(iso, cs) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString(cs ? 'cs-CZ' : 'en-GB') + ' ' + d.toLocaleTimeString(cs ? 'cs-CZ' : 'en-GB', { hour: '2-digit', minute: '2-digit' }); }
  function day(iso, cs) { if (!iso) return '—'; return new Date(iso).toLocaleDateString(cs ? 'cs-CZ' : 'en-GB'); }
  function money(m, cs) { if (m == null) return '—'; var n = typeof m === 'number' ? m : Number(m.decimal != null ? m.decimal : (m.minor || 0) / 100); return n.toLocaleString(cs ? 'cs-CZ' : 'en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + ((m && m.currency) || ''); }
  function bytes(n) { if (n == null) return '—'; var u = ['B', 'kB', 'MB', 'GB', 'TB'], i = 0, v = Number(n); while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; } return (i ? v.toFixed(1) : v) + ' ' + u[i]; }
  function openService(cmp, id) { return function () { if (!id) return; cmp.setState({ tab: 'svcdesk', svcId: id, svcTab: null, svcGo: null, selected: null, query: '' }); }; }
  function empty(cmp, _, H, crumb, title, back) {
    return { crumb: crumb, title: title, stats: [], cols: [], rows: [], filters: [], readOnly: true, side: { title: '', rows: [{ title: _('← Zpět na přehled', '← Back to the overview'), meta: '', value: '', kind: 'off', on: function () { cmp.setState({ tab: 'overview' }); } }] } };
  }

  /* ── notifications and audit ──────────────────────────────────────────── */
  function audit(cmp, _, H) {
    var cs = isCs(cmp);
    if (orgId()) load(cmp, 'audit', '/organizations/' + encodeURIComponent(orgId()) + '/audit?limit=60');
    load(cmp, 'notifs', '/notifications?limit=12');
    load(cmp, 'unread', '/notifications?unread=1&limit=1');
    var events = (S.audit && S.audit.data) || [], total = (S.audit && S.audit.total) || events.length, notifs = (S.notifs && S.notifs.data) || [], unread = (S.unread && S.unread.total) || 0;
    var last = events[0] ? events[0].at : null;
    var actors = {};
    events.forEach(function (e) { var k = (e.actor && e.actor.type) || '?'; actors[k] = (actors[k] || 0) + 1; });
    return {
      crumb: _('Účet', 'Account'), title: _('Oznámení a audit', 'Notifications and audit'),
      stats: [
        H.stat(_('Záznamů v auditu', 'Audit entries'), String(total), _('nic se nemaže, včetně našich zásahů', 'nothing is deleted, our own actions included'), 5, Math.min(100, total), 12, 'ok'),
        H.stat(_('Nepřečtená oznámení', 'Unread notifications'), String(unread), '', 9, Math.min(100, unread * 20), 10, unread ? 'warn' : 'ok'),
        H.stat(_('Poslední zásah', 'Last action'), last ? when(last, cs) : '—', events[0] ? events[0].action : '', 13, last ? 90 : 5, 8, 'ok'),
        H.stat(_('Aktéři', 'Actors'), Object.keys(actors).map(function (k) { return k + ' ' + actors[k]; }).join(' · ') || '—', _('user = vy a váš tým · system = automatika', 'user = you and your team · system = automation'), 17, 60, 8, 'ok')
      ],
      tableTitle: _('Audit', 'Audit trail'), tableNote: _('kdo co udělal, s výsledkem a IP adresou · export přes API', 'who did what, with the result and the IP address · export through the API'),
      cols: [_('Akce', 'Action'), _('Objekt', 'Resource'), _('Výsledek', 'Result'), _('Kdy', 'When'), ''],
      rows: events.filter(function (e) { return H.match(e.action) || H.match((e.resource || []).join(' ')) || H.match(e.actor && e.actor.id); }).map(function (e) {
        var ok = e.result === 'succeeded';
        return {
          name: e.action, sub: ((e.actor && e.actor.type) || '') + (e.actor && e.actor.id ? ' · ' + e.actor.id : '') + (e.ip ? ' · ' + e.ip : '') + (e.reason ? ' · ' + e.reason : ''), c2: (e.resource || []).filter(Boolean).join(' '),
          state: ok ? _('provedeno', 'done') : (e.result || '—'), stateStyle: H.pill(ok ? 'ok' : 'warn'), barStyle: H.bar(ok ? 100 : 40, ok ? 'ok' : 'warn'), metric: when(e.at, cs), rowStyle: H.rowStyle,
          action: e.detail && Object.keys(e.detail).length ? _('Detail', 'Detail') : '', actionCls: 'btn btn-secondary',
          onAction: function () { window.alert(JSON.stringify(e.detail || {}, null, 2)); }
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Oznámení', 'Notifications'),
        rows: (notifs.length ? notifs.map(function (n) {
          return { title: n.title, meta: (n.body || '') + (n.created_at || n.at ? ' · ' + when(n.created_at || n.at, cs) : ''), value: n.read_at ? '' : '●', kind: n.read_at ? 'off' : (n.severity === 'hot' ? 'warn' : 'ok'), on: function () {
            if (n.read_at) return;
            A().post('/notifications/read', { ids: [n.id] }, A().key()).then(function () { reload(cmp, ['notifs', 'unread']); }).catch(function (e) { fail(cmp, _, e); });
          } };
        }) : [{ title: _('Zatím žádné oznámení', 'No notifications yet'), meta: _('objednávky, platby, služby, domény a tikety se hlásí sem i e-mailem', 'orders, payments, services, domains and tickets report here and by e-mail'), value: '', kind: 'off' }]).concat([
          { title: _('Nastavení oznámení', 'Notification settings'), meta: _('co chodí e-mailem a co jen do panelu', 'what goes by e-mail and what stays in the panel'), value: '→', kind: 'ok', on: function () { cmp.setState({ tab: 'settings', sub: 'account', selected: null }); } }
        ])
      },
      advice: { title: _('Audit je důkaz, ne výkaz', 'The audit trail is evidence, not a report'), lead: _('Každý zásah v účtu — váš, vašeho týmu, automatiky i náš — má řádek s výsledkem, IP adresou a důvodem. Přes API (/v1/organizations/{id}/audit) ho stáhnete do SIEM nebo účetnictví.', 'Every action in the account — yours, your team\'s, automation\'s and ours — has a row with the result, the IP address and the reason. The API (/v1/organizations/{id}/audit) exports it to a SIEM or bookkeeping.'), cta: '', on: function () {} }
    };
  }

  /* ── maintenance windows ──────────────────────────────────────────────── */
  function windows(cmp, _, H) {
    var cs = isCs(cmp);
    load(cmp, 'calendar', '/calendar?days=120');
    load(cmp, 'status', '/status');
    var events = ((S.calendar && S.calendar.data) || []).filter(function (e) { return e.kind === 'maintenance'; });
    var status = (S.status && (S.status.data || S.status)) || {}, components = Array.isArray(status.components) ? status.components : (Array.isArray(status) ? status : []);
    var next = events[0] || null;
    return {
      crumb: _('Účet', 'Account'), title: _('Servisní okna', 'Maintenance windows'),
      stats: [
        H.stat(_('Plánované údržby', 'Planned maintenance'), String(events.length), _('příštích 120 dní', 'next 120 days'), 4, Math.min(100, events.length * 25), 12, events.length ? 'warn' : 'ok'),
        H.stat(_('Nejbližší', 'Next'), next ? when(next.starts_at, cs) : '—', next ? next.title.replace(/^Údržba: /, '') : _('nic v plánu', 'nothing planned'), 8, next ? 80 : 5, 10, 'ok'),
        H.stat(_('Stav infrastruktury', 'Infrastructure status'), status.overall ? String(status.overall) : (components.length ? _('viz komponenty', 'see components') : '—'), '', 12, 100, 8, status.overall && status.overall !== 'operational' ? 'warn' : 'ok'),
        H.stat(_('Kalendář', 'Calendar'), 'ICS', _('údržby, obnovy a splatnosti ve vašem kalendáři', 'maintenance, renewals and due dates in your calendar'), 16, 100, 8, 'ok')
      ],
      tableTitle: _('Údržby, které se týkají vašich služeb', 'Maintenance touching your services'), tableNote: _('z plánu provozu · údržba se nepočítá do SLA, výpadek mimo ni ano', 'from the operations plan · maintenance is excluded from the SLA, an outage outside it is not'),
      cols: [_('Údržba', 'Maintenance'), _('Dopad', 'Impact'), _('Stav', 'State'), _('Kdy', 'When'), ''],
      rows: events.filter(function (e) { return H.match(e.title) || H.match(e.description); }).map(function (e) {
        return {
          name: e.title.replace(/^Údržba: /, ''), sub: (e.components || []).join(', ') + (e.number ? ' · ' + e.number : ''), c2: e.description || '',
          state: _('plánováno', 'planned'), stateStyle: H.pill('warn'), barStyle: H.bar(60, 'warn'), metric: when(e.starts_at, cs) + (e.ends_at ? ' – ' + new Date(e.ends_at).toLocaleTimeString(cs ? 'cs-CZ' : 'en-GB', { hour: '2-digit', minute: '2-digit' }) : ''), rowStyle: H.rowStyle,
          action: '', actionCls: 'btn btn-secondary', onAction: function () {}
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Komponenty', 'Components'),
        rows: (components.length ? components.map(function (c) { var st = String(c.state || c.status || 'operational'); return { title: c.name || c.label || c.key, meta: c.uptime != null ? _('dostupnost ', 'uptime ') + c.uptime + ' %' : st, value: st === 'operational' ? 'OK' : '!', kind: st === 'operational' ? 'ok' : 'warn' }; }) : [{ title: _('Stavová stránka', 'Status page'), meta: _('incidenty a odstávky veřejně', 'incidents and maintenance, public'), value: '→', kind: 'ok', on: function () { window.open('/stav', '_blank', 'noopener'); } }]).concat([
          { title: _('Odebírat kalendář (ICS)', 'Subscribe to the calendar (ICS)'), meta: _('údržby, obnovy, expirace a splatnosti', 'maintenance, renewals, expiries and due dates'), value: '→', kind: 'ok', on: function () { A().get('/calendar/feed').then(function (r) { var url = r && r.data && r.data.url; if (url) window.open(url, '_blank', 'noopener'); }).catch(function (e) { fail(cmp, _, e); }); } }
        ])
      },
      advice: { title: _('Údržba je ohlášená předem', 'Maintenance is announced ahead'), lead: _('Plánovanou údržbu oznamujeme v panelu, e-mailem a na stavové stránce; okno volíme mimo špičku vašich služeb. Neplánovaný výpadek mimo okno se počítá do SLA a kompenzace vzniká sama.', 'Planned maintenance is announced in the panel, by e-mail and on the status page; the window avoids your services\' peak. An unplanned outage outside a window counts against the SLA and the credit arises on its own.'), cta: '', on: function () {} }
    };
  }

  /* ── costs and forecast ───────────────────────────────────────────────── */
  function costs(cmp, _, H) {
    var cs = isCs(cmp);
    load(cmp, 'wallet', '/wallet');
    load(cmp, 'subs', '/subscriptions?limit=60');
    if (orgId()) load(cmp, 'projects', '/organizations/' + encodeURIComponent(orgId()) + '/projects');
    var w = (S.wallet && S.wallet.data) || {}, f = w.forecast || {}, subs = (S.subs && S.subs.data) || [], projects = (S.projects && S.projects.data) || [], spend = (S.projects && S.projects.spend) || {};
    var runway = f.days == null ? _('víc než 120 dní', 'more than 120 days') : f.days + _(' dní', ' days');
    return {
      crumb: _('Účet', 'Account'), title: _('Náklady a předpověď', 'Costs and forecast'),
      stats: [
        H.stat(_('Měsíční spotřeba', 'Monthly burn'), money(f.monthly_burn, cs), _('obnovy vč. DPH', 'renewals incl. VAT'), 6, 60, 12, 'ok'),
        H.stat(_('Kredit vystačí', 'Credit lasts'), runway, f.depletes_at ? _('do ', 'until ') + day(f.depletes_at, cs) : (f.auto_topup ? _('automatické dobíjení zapnuto', 'automatic top-up on') : ''), 10, f.days == null ? 100 : Math.min(100, f.days), 10, f.days != null && f.days <= 14 ? 'warn' : 'ok'),
        H.stat(_('Obnovy do 30 dní', 'Renewals within 30 days'), money(f.renewals_30d, cs), f.shortfall_30d && f.shortfall_30d.minor > 0 ? _('chybí ', 'short by ') + money(f.shortfall_30d, cs) : _('kryto kreditem', 'covered by credit'), 14, 50, 8, f.shortfall_30d && f.shortfall_30d.minor > 0 ? 'warn' : 'ok'),
        H.stat(_('Předplatných', 'Subscriptions'), String(f.subscriptions != null ? f.subscriptions : subs.length), f.next_renewal ? _('nejbližší ', 'next ') + day(f.next_renewal.at || f.next_renewal.next_renewal_at, cs) : '', 18, Math.min(100, subs.length * 10), 8, 'ok')
      ],
      tableTitle: _('Předplatná a obnovy', 'Subscriptions and renewals'), tableNote: _('co se kdy obnoví a za kolik · vypnutí obnovy je v detailu služby', 'what renews when and for how much · turn renewal off in the service detail'),
      cols: [_('Služba', 'Service'), _('Období', 'Period'), _('Stav', 'State'), _('Obnova', 'Renewal'), ''],
      rows: subs.filter(function (s) { return H.match(s.label || s.service || s.service_id || ''); }).map(function (s) {
        var active = s.state === 'active';
        return {
          name: s.label || s.service || s.hostname || s.service_id || s.id, sub: (s.product_key || '') + (s.auto_renew === false ? _(' · automatická obnova vypnutá', ' · automatic renewal off') : ''), c2: s.period === 'year' ? _('ročně', 'yearly') : _('měsíčně', 'monthly'),
          state: active ? _('aktivní', 'active') : (s.state || '—'), stateStyle: H.pill(active ? 'ok' : 'warn'), barStyle: H.bar(active ? 100 : 30, active ? 'ok' : 'warn'), metric: money(s.amount, cs) + (s.next_renewal_at ? ' · ' + day(s.next_renewal_at, cs) : ''), rowStyle: H.rowStyle,
          action: s.service_id ? _('Otevřít službu', 'Open service') : '', actionCls: 'btn btn-secondary', onAction: openService(cmp, s.service_id)
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Útrata podle projektů', 'Spend by project'),
        rows: projects.map(function (p) { return { title: p.name, meta: String(p.services || 0) + _(' služeb · ', ' services · ') + (p.share || 0) + ' %', value: money(p.monthly, cs), kind: p.state === 'archived' ? 'off' : 'ok', on: function () { cmp.setState({ tab: 'projects', projSel: p.id, query: '' }); } }; })
          .concat(spend.unassigned ? [{ title: _('Nezařazeno', 'Unassigned'), meta: String(spend.unassigned.services || 0) + _(' služeb', ' services'), value: money(spend.unassigned.monthly, cs), kind: spend.unassigned.services ? 'warn' : 'off' }] : [])
          .concat([{ title: _('Dobít kredit', 'Top up credit'), meta: _('převodem nebo kartou · z kreditu se obnovy platí ihned', 'by transfer or card · renewals draw on credit at once'), value: '→', kind: 'ok', on: function () { if (window.OnhostPanelOverview && window.OnhostPanelOverview.topUp) window.OnhostPanelOverview.topUp(cmp); else cmp.setState({ tab: 'billing' }); } }])
      },
      advice: f.days != null && f.days <= 14 ? {
        title: _('Kredit dojde za ', 'The credit runs out in ') + f.days + _(' dní', ' days'), lead: _('Podle nadcházejících obnov kredit nepokryje další období. Dobijte ho, nebo zapněte automatické dobíjení, ať služby běží bez přerušení.', 'The upcoming renewals outrun the credit. Top it up or turn on automatic top-up so the services keep running.'),
        cta: _('Dobít kredit', 'Top up credit'), on: function () { if (window.OnhostPanelOverview && window.OnhostPanelOverview.topUp) window.OnhostPanelOverview.topUp(cmp); else cmp.setState({ tab: 'billing' }); }
      } : {
        title: _('Předpověď počítá s DPH', 'The forecast includes VAT'), lead: _('Obnovy se počítají hrubě podle vašich daňových údajů; roční plány v plné výši v měsíci obnovy. Když se kredit blíží konci, dáme vědět 14 dní předem e-mailem i tady.', 'Renewals are computed gross from your tax profile; yearly plans in full in the month they renew. When the credit nears its end you hear 14 days ahead, by e-mail and here.'),
        cta: '', on: function () {}
      }
    };
  }

  /* ── personal data and leaving ────────────────────────────────────────── */
  function privacy(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state;
    load(cmp, 'requests', '/data-requests?limit=30');
    var requests = (S.requests && S.requests.data) || [];
    var KINDS = [['export', _('export dat', 'data export')], ['switching', _('přenos k jinému poskytovateli', 'switching provider')], ['deletion', _('smazání účtu a dat', 'deletion of the account and data')]];
    var open = requests.filter(function (r) { return r.state !== 'completed' && r.state !== 'done' && r.state !== 'rejected' && r.state !== 'cancelled'; }).length;
    return {
      crumb: _('Účet', 'Account'), title: _('Osobní údaje a odchod', 'Personal data and leaving'),
      stats: [
        H.stat(_('Žádosti', 'Requests'), String(requests.length), open ? open + _(' otevřené', ' open') : '', 5, Math.min(100, requests.length * 20), 12, open ? 'warn' : 'ok'),
        H.stat(_('Export dat', 'Data export'), _('do 30 dnů', 'within 30 days'), _('strojově čitelný balík', 'a machine-readable bundle'), 9, 100, 8, 'ok'),
        H.stat(_('Přenos', 'Switching'), _('zdarma', 'free'), _('data i konfigurace k novému poskytovateli', 'data and configuration to a new provider'), 13, 100, 8, 'ok'),
        H.stat(_('Smazání', 'Deletion'), _('po ukončení služeb', 'after the services end'), _('doklady zůstávají ze zákona 10 let', 'invoices stay 10 years by law'), 17, 100, 8, 'ok')
      ],
      form: {
        title: _('Nová žádost', 'New request'), note: _('žádost se zapíše do auditu a vyřídí ji člověk · smazání blokují aktivní služby a neuhrazené doklady', 'the request goes to the audit trail and a person handles it · deletion is blocked by active services and unpaid documents'),
        fields: [{ key: 'drKind', label: _('Druh', 'Kind'), kind: 'select', options: KINDS.map(function (k) { return k[1]; }) }, { key: 'drReason', label: _('Poznámka (volitelné)', 'Note (optional)'), ph: _('např. ukončujeme projekt', 'e.g. the project ends'), kind: 'text' }],
        cta: _('Odeslat žádost', 'Send request'), hint: _('Potvrzení a stav sledujete zde i e-mailem.', 'Confirmation and progress show here and by e-mail.'),
        submit: function () {
          var kind = (KINDS.filter(function (k) { return k[1] === s.drKind; })[0] || KINDS[0])[0];
          if (kind === 'deletion' && !window.confirm(_('Opravdu požádat o smazání účtu a všech dat? Po vyřízení je to nevratné.', 'Really request deletion of the account and all data? Once processed it cannot be undone.'))) return;
          A().post('/data-requests', { kind: kind, reason: String(s.drReason || '').trim() || null }, A().key()).then(function () { cmp.setState({ drReason: '' }); flash(cmp, _('Žádost přijata', 'Request received'), KINDS.filter(function (k) { return k[0] === kind; })[0][1]); reload(cmp, ['requests']); }).catch(function (e) { fail(cmp, _, e); });
        }
      },
      tableTitle: _('Žádosti', 'Requests'), tableNote: _('export si stáhnete zde, jakmile je připraven', 'download the export here once it is ready'),
      cols: [_('Žádost', 'Request'), _('Druh', 'Kind'), _('Stav', 'State'), _('Podáno', 'Filed'), ''],
      rows: requests.map(function (r) {
        var kind = (KINDS.filter(function (k) { return k[0] === r.kind; })[0] || [r.kind, r.kind])[1];
        var done = r.state === 'completed' || r.state === 'done' || r.state === 'ready';
        return {
          name: kind, sub: r.reason || r.note || '', c2: r.kind,
          state: r.state || '—', stateStyle: H.pill(done ? 'ok' : (r.state === 'rejected' ? 'off' : 'warn')), barStyle: H.bar(done ? 100 : 40, done ? 'ok' : 'warn'), metric: day(r.requested_at || r.created_at, cs) + (r.due_at ? ' · ' + _('do ', 'by ') + day(r.due_at, cs) : ''), rowStyle: H.rowStyle,
          action: r.kind === 'export' && (r.download_url || done) ? _('Stáhnout', 'Download') : '', actionCls: 'btn btn-secondary',
          onAction: function () { window.open(r.download_url || ((A().base || '/v1') + '/data-requests/' + encodeURIComponent(r.id) + '/download'), '_blank', 'noopener'); }
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Co o vás držíme', 'What we hold about you'),
        rows: [
          { title: _('Fakturační údaje', 'Billing identity'), meta: _('jméno, adresa, IČO/DIČ · doklady ze zákona 10 let', 'name, address, IDs · invoices 10 years by law'), value: '', kind: 'ok' },
          { title: _('Přihlášení a relace', 'Sign-ins and sessions'), meta: _('e-mail, heslo (hash), 2FA, IP a zařízení relací', 'e-mail, password hash, 2FA, session IP and device'), value: '', kind: 'ok' },
          { title: _('Audit', 'Audit trail'), meta: _('kdo co udělal · nemaže se', 'who did what · never deleted'), value: '', kind: 'ok' },
          { title: _('Data služeb', 'Service data'), meta: _('weby, databáze, pošta, zálohy · mažou se s ukončením služby', 'sites, databases, mail, backups · deleted with the service'), value: '', kind: 'ok' },
          { title: _('Připojené účty', 'Connected accounts'), meta: _('Discord, registrátoři · hesla šifrovaná, kdykoli odpojíte', 'Discord, registrars · encrypted secrets, disconnect any time'), value: '', kind: 'ok' }
        ]
      },
      advice: { title: _('Odchod bez překážek', 'Leaving without obstacles'), lead: _('Export dat i přenos k jinému poskytovateli jsou zdarma a bez lhůty. Smazání vyřídíme po ukončení služeb a uhrazení dokladů; doklady zůstávají jen tam, kde to ukládá zákon.', 'Data export and switching to another provider are free and without a notice period. Deletion follows once services end and documents are paid; invoices stay only where the law requires.'), cta: '', on: function () {} }
    };
  }

  /* ── monitoring ───────────────────────────────────────────────────────── */
  function monitoring(cmp, _, H) {
    var cs = isCs(cmp);
    load(cmp, 'monitors', '/monitors?limit=100');
    var rows = (S.monitors && S.monitors.data) || [];
    var down = rows.filter(function (m) { return m.state === 'down'; }), up = rows.filter(function (m) { return m.state === 'up'; });
    var avg = rows.filter(function (m) { return m.last_ms != null; }).reduce(function (a, m) { return a + m.last_ms; }, 0) / Math.max(1, rows.filter(function (m) { return m.last_ms != null; }).length);
    return {
      crumb: _('Provoz', 'Operations'), title: _('Monitoring', 'Monitoring'),
      stats: [
        H.stat(_('Kontroly', 'Checks'), String(rows.length), _('každých 5 minut z více míst', 'every 5 minutes from several places'), 5, Math.min(100, rows.length * 10), 12, 'ok'),
        H.stat(_('Neodpovídá', 'Down'), String(down.length), down[0] ? down[0].url : '', 9, down.length ? 90 : 5, 10, down.length ? 'warn' : 'ok'),
        H.stat(_('V pořádku', 'Up'), String(up.length), '', 13, rows.length ? Math.round(up.length / rows.length * 100) : 0, 8, 'ok'),
        H.stat(_('Odezva', 'Response'), rows.length ? Math.round(avg) + ' ms' : '—', _('průměr posledních kontrol', 'average of the latest checks'), 17, Math.min(100, avg / 20), 8, avg > 1500 ? 'warn' : 'ok')
      ],
      tableTitle: _('Sledované adresy', 'Monitored addresses'), tableNote: _('stav, poslední kontrola a odezva · nastavení v nástrojích služby', 'state, last check and response · settings in the service tools'),
      cols: [_('Adresa', 'Address'), _('Služba', 'Service'), _('Stav', 'State'), _('Poslední kontrola', 'Last check'), ''],
      rows: rows.filter(function (m) { return H.match(m.url) || H.match(m.service && m.service.label); }).map(function (m) {
        var st = m.state === 'up' ? _('běží', 'up') : m.state === 'down' ? _('neodpovídá', 'down') : m.state === 'paused' || !m.enabled ? _('pozastaveno', 'paused') : _('čeká na kontrolu', 'pending');
        return {
          name: m.url, sub: (m.keyword ? _('hledá ', 'expects ') + '„' + m.keyword + '“ · ' : '') + _('očekává HTTP ', 'expects HTTP ') + m.expected_status + (m.last_error ? ' · ' + m.last_error : ''), c2: (m.service && m.service.label) || m.service_id,
          state: st, stateStyle: H.pill(m.state === 'up' ? 'ok' : m.state === 'down' ? 'warn' : 'off'), barStyle: H.bar(m.state === 'up' ? 100 : m.state === 'down' ? 15 : 50, m.state === 'down' ? 'warn' : 'ok'),
          metric: (m.last_checked_at ? when(m.last_checked_at, cs) : '—') + (m.last_ms != null ? ' · ' + m.last_ms + ' ms' : '') + (m.consecutive_failures ? ' · ' + m.consecutive_failures + '×' : ''), rowStyle: H.rowStyle,
          action: _('Otevřít službu', 'Open service'), actionCls: 'btn btn-secondary', onAction: openService(cmp, m.service_id)
        };
      }),
      filters: [], readOnly: true,
      side: { title: _('Výpadky', 'Outages'), rows: down.length ? down.map(function (m) { return { title: m.url, meta: (m.last_error || '') + (m.last_checked_at ? ' · ' + when(m.last_checked_at, cs) : ''), value: m.consecutive_failures + '×', kind: 'warn', on: openService(cmp, m.service_id) }; }) : [{ title: _('Žádný výpadek', 'No outage'), meta: rows.length ? _('všechny sledované adresy odpovídají', 'every monitored address answers') : _('monitoring zapnete v nástrojích webu', 'turn monitoring on in the site tools'), value: '', kind: 'ok' }] },
      advice: { title: _('Výpadek hlásíme hned', 'Outages are reported at once'), lead: _('Když adresa přestane odpovídat na dvou kontrolách po sobě, přijde oznámení do panelu, e-mailem, na Discord i webhook — a znovu, když web naběhne, s délkou výpadku.', 'When an address fails two checks in a row you hear in the panel, by e-mail, on Discord and by webhook — and again when it is back, with the outage length.'), cta: '', on: function () {} }
    };
  }

  /* ── backups ──────────────────────────────────────────────────────────── */
  function backups(cmp, _, H) {
    var cs = isCs(cmp);
    load(cmp, 'backups', '/backups?limit=100');
    var rows = (S.backups && S.backups.data) || [];
    var ok = rows.filter(function (b) { return b.state === 'completed' || b.state === 'ok' || b.state === 'succeeded'; });
    var size = rows.reduce(function (a, b) { return a + (b.size_bytes || 0); }, 0);
    var byService = {};
    rows.forEach(function (b) { var k = b.service_id; if (!byService[k] || (b.finished_at || '') > (byService[k].finished_at || '')) byService[k] = b; });
    var latest = Object.keys(byService).map(function (k) { return byService[k]; });
    return {
      crumb: _('Provoz', 'Operations'), title: _('Zálohy', 'Backups'),
      stats: [
        H.stat(_('Zálohy', 'Backups'), String(rows.length), ok.length + _(' dokončených', ' completed'), 5, Math.min(100, rows.length * 4), 12, 'ok'),
        H.stat(_('Služeb se zálohou', 'Services with a backup'), String(latest.length), '', 9, Math.min(100, latest.length * 20), 10, 'ok'),
        H.stat(_('Objem', 'Volume'), bytes(size), _('offsite, šifrované', 'off-site, encrypted'), 13, 60, 8, 'ok'),
        H.stat(_('Poslední', 'Latest'), rows[0] ? when(rows[0].finished_at || rows[0].started_at, cs) : '—', rows[0] && rows[0].service ? rows[0].service.label : '', 17, rows[0] ? 90 : 5, 8, 'ok')
      ],
      tableTitle: _('Poslední zálohy', 'Latest backups'), tableNote: _('obnovu a stažení najdete v nástrojích služby', 'restore and download live in the service tools'),
      cols: [_('Služba', 'Service'), _('Druh', 'Kind'), _('Stav', 'State'), _('Dokončeno', 'Finished'), ''],
      rows: rows.filter(function (b) { return H.match(b.service && b.service.label) || H.match(b.kind); }).map(function (b) {
        var good = b.state === 'completed' || b.state === 'ok' || b.state === 'succeeded';
        return {
          name: (b.service && b.service.label) || b.service_id, sub: bytes(b.size_bytes) + (b.verify_status ? ' · ' + _('ověření ', 'verify ') + b.verify_status : '') + (b.protected ? ' · ' + _('chráněná', 'protected') : ''), c2: b.kind,
          state: b.state, stateStyle: H.pill(good ? 'ok' : (b.state === 'failed' ? 'warn' : 'off')), barStyle: H.bar(good ? 100 : 40, good ? 'ok' : 'warn'), metric: when(b.finished_at || b.started_at, cs), rowStyle: H.rowStyle,
          action: _('Otevřít službu', 'Open service'), actionCls: 'btn btn-secondary', onAction: openService(cmp, b.service_id)
        };
      }),
      filters: [], readOnly: true,
      side: { title: _('Poslední záloha každé služby', 'Every service\'s latest backup'), rows: latest.length ? latest.map(function (b) { return { title: (b.service && b.service.label) || b.service_id, meta: when(b.finished_at || b.started_at, cs) + ' · ' + bytes(b.size_bytes), value: b.state, kind: (b.state === 'completed' || b.state === 'ok') ? 'ok' : 'warn', on: openService(cmp, b.service_id) }; }) : [{ title: _('Zatím žádná záloha', 'No backup yet'), meta: _('automatické zálohy běží každou noc; ruční zálohu spustíte v nástrojích služby', 'automatic backups run every night; start a manual one in the service tools'), value: '', kind: 'off' }] },
      advice: { title: _('Záloha, kterou jsme neobnovili, není záloha', 'A backup we never restored is not a backup'), lead: _('Zálohy ověřujeme obnovou na zkoušku a výsledek vidíte u každé z nich. Obnovu spustíte sami v nástrojích služby — do původního místa nebo vedle něj.', 'Backups are verified by a trial restore and the result shows on each. Restore yourself in the service tools — in place or next to it.'), cta: '', on: function () {} }
    };
  }

  window.OnhostPanelPages = { audit: audit, windows: windows, costs: costs, privacy: privacy, monitoring: monitoring, backups: backups, reload: function (cmp) { S = { busy: {} }; rerender(cmp); } };
})();
