/* onhost-panel-registrars.api.js — connected registrar accounts in the customer panel (data seam #36).
 *
 * Bring your own WEDOS API: the customer connects the account (login + API password, stored server-side in the secret
 * store), the platform mirrors its domains and zones (/v1/registrar-connections), warns before expiries, watches the
 * credit and pairs a domain with a web hosting plan (/v1/domains/{id}/pair) — DNS rows and the certificate follow.
 * Views use the prototype's generic view shape so the surface stays byte-identical; the API password field is the
 * only `password` kind the renderer adds to the generic form. */
(function () {
  'use strict';
  if (window.OnhostPanelRegistrars) return;
  var S = { list: null, detail: null, detailId: null, history: null, busy: {} };

  function A() { return window.OnhostApi; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function rerender(cmp) { try { cmp.setState({ registrarsAt: Date.now() }); } catch (e) {} }
  function flash(cmp, t, b) { if (cmp && typeof cmp.flash === 'function') cmp.flash(t, b); }
  function fail(cmp, _, e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); }
  function when(iso, cs) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString(cs ? 'cs-CZ' : 'en-GB') + ' ' + d.toLocaleTimeString(cs ? 'cs-CZ' : 'en-GB', { hour: '2-digit', minute: '2-digit' }); }
  function day(iso, cs) { if (!iso) return '—'; return new Date(iso).toLocaleDateString(cs ? 'cs-CZ' : 'en-GB'); }
  function credit(c) { return c && c.balance != null ? String(c.balance) + ' ' + (c.currency || '') : '—'; }
  function stateLabel(st, _) { return st === 'active' ? _('aktivní', 'active') : st === 'error' ? _('chyba', 'error') : st === 'disabled' ? _('odpojeno', 'disconnected') : _('připojuji', 'connecting'); }
  function stateKind(st) { return st === 'active' ? 'ok' : st === 'error' ? 'warn' : 'off'; }

  function loadList(cmp) {
    if (S.list !== null || S.busy.list || !A()) return;
    S.busy.list = true;
    A().get('/registrar-connections').then(function (r) { S.list = r.data || []; }).catch(function () { S.list = []; }).then(function () { delete S.busy.list; rerender(cmp); });
  }
  function loadDetail(cmp, id) {
    if (S.detailId === id && (S.detail !== null || S.busy.detail)) return;
    S.detailId = id; S.detail = null; S.history = null; S.busy.detail = true;
    A().get('/registrar-connections/' + encodeURIComponent(id)).then(function (r) { S.detail = r.data || r; }).catch(function () { S.detail = null; }).then(function () { delete S.busy.detail; rerender(cmp); });
  }
  function reload(cmp) { S.list = null; S.detail = null; S.detailId = null; S.history = null; rerender(cmp); }
  function back(cmp) { return function () { cmp.setState({ rconSel: null, query: '' }); }; }
  function busy(cmp, _, key, promise, okTitle, okBody) {
    if (S.busy[key]) return;
    S.busy[key] = true; rerender(cmp);
    promise.then(function (r) { flash(cmp, okTitle, typeof okBody === 'function' ? okBody(r) : (okBody || '')); reload(cmp); }).catch(function (e) { fail(cmp, _, e); }).then(function () { delete S.busy[key]; rerender(cmp); });
  }
  function summary(run, _) {
    var s = run.summary || {};
    if (run.kind === 'sync') return _('importováno ', 'imported ') + (s.imported || 0) + ' · ' + _('změněno ', 'changed ') + (s.updated || 0) + ' · ' + _('zóny ', 'zones ') + (s.zones || 0) + ' · ' + _('upozornění ', 'notices ') + (s.notices || 0);
    if (run.kind === 'probe') return (s.domains != null ? s.domains + _(' domén · ', ' domains · ') : '') + (s.credit || '');
    return Object.keys(s).map(function (k) { return k + ' ' + s[k]; }).join(' · ');
  }

  /* ── list: connections + the connect form ───────────────────────────────── */
  function listView(cmp, _, H, cs, s) {
    loadList(cmp);
    var rows = S.list || [], live = rows.filter(function (c) { return c.state !== 'disabled'; });
    var domains = live.reduce(function (n, c) { return n + (c.domains || 0); }, 0), zones = live.reduce(function (n, c) { return n + (c.zones || 0); }, 0);
    var last = live.map(function (c) { return c.last_synced_at; }).filter(Boolean).sort().pop();
    var errors = live.filter(function (c) { return c.state === 'error'; }).length;
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Připojené registrátory', 'Connected registrars'),
      stats: [
        H.stat(_('Účty', 'Accounts'), String(live.length), errors ? errors + _(' s chybou', ' failing') : '', 5, Math.min(100, live.length * 34), 10, errors ? 'warn' : 'ok'),
        H.stat(_('Zrcadlené domény', 'Mirrored domains'), String(domains), '', 9, Math.min(100, domains * 5), 12, 'ok'),
        H.stat(_('Zóny u registrátora', 'Zones at the registrar'), String(zones), _('spravované z panelu', 'managed from the panel'), 13, Math.min(100, zones * 8), 14, 'ok'),
        H.stat(_('Poslední synchronizace', 'Last sync'), last ? when(last, cs) : '—', _('každou hodinu', 'every hour'), 17, last ? 90 : 5, 8, last ? 'ok' : 'off')
      ],
      form: {
        title: _('Připojit účet WEDOS', 'Connect a WEDOS account'),
        note: _('WAPI login je e-mail účtu · API heslo nastavíte v administraci WEDOS (Zákazník → WAPI) a povolíte tam adresu platformy', 'the WAPI login is the account e-mail · set the API password in the WEDOS administration (Customer → WAPI) and allow the platform address there'),
        fields: [
          { key: 'rcLogin', label: _('WAPI login (e-mail)', 'WAPI login (e-mail)'), ph: 'ucet@firma.cz', kind: 'text' },
          { key: 'rcPassword', label: _('API heslo', 'API password'), ph: '••••••••', kind: 'password' },
          { key: 'rcLabel', label: _('Označení (volitelné)', 'Label (optional)'), ph: _('Firemní účet', 'Company account'), kind: 'text' }
        ],
        cta: _('Připojit', 'Connect'),
        hint: _('Heslo uložíme šifrované, do prohlížeče se už nevrátí. Připojení vyžaduje ověření heslem k účtu ONhost.', 'The password is stored encrypted and never returns to the browser. Connecting asks for your ONhost password again.'),
        submit: function () {
          var login = String(s.rcLogin || '').trim(), password = String(s.rcPassword || '');
          if (!login || !password) { flash(cmp, _('Chybí přihlašovací údaje', 'Credentials missing'), _('Vyplňte WAPI login i API heslo.', 'Enter the WAPI login and the API password.')); return; }
          busy(cmp, _, 'connect', A().post('/registrar-connections', { provider: 'wedos', login: login, password: password, label: String(s.rcLabel || '').trim() || null }, A().key()).then(function (r) { cmp.setState({ rcPassword: '', rcLogin: '', rcLabel: '' }); return r; }),
            _('Účet připojen', 'Account connected'), function (r) { var c = (r && r.connection) || {}; return (c.stats && c.stats.domains != null ? c.stats.domains + _(' domén zrcadleno · ', ' domains mirrored · ') : '') + _('synchronizace běží každou hodinu', 'the account syncs every hour'); });
        }
      },
      tableTitle: _('Účty', 'Accounts'), tableNote: _('stav, domény, kredit a poslední synchronizace', 'state, domains, credit and last sync'),
      cols: [_('Účet', 'Account'), _('Domény', 'Domains'), _('Stav', 'State'), _('Kredit', 'Credit'), ''],
      rows: rows.filter(function (c) { return H.match(c.label) || H.match(c.login); }).map(function (c) {
        return {
          name: c.label, sub: c.login + (c.last_synced_at ? ' · ' + _('sync ', 'synced ') + when(c.last_synced_at, cs) : ''), c2: String(c.domains || 0) + _(' domén', ' domains') + (c.zones ? ' · ' + c.zones + _(' zón', ' zones') : ''),
          state: stateLabel(c.state, _), stateStyle: H.pill(stateKind(c.state)), barStyle: H.bar(c.state === 'active' ? 100 : 30, stateKind(c.state)), metric: credit(c.credit), rowStyle: H.rowStyle,
          action: c.state === 'disabled' ? '' : _('Otevřít', 'Open'), actionCls: 'btn btn-secondary', onAction: function () { if (c.state !== 'disabled') cmp.setState({ rconSel: c.id, query: '' }); }
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Co se děje automaticky', 'What runs on its own'),
        rows: [
          { title: _('Zrcadlení domén', 'Domain mirroring'), meta: _('každou hodinu: nové, změněné i zmizelé domény', 'every hour: new, changed and vanished domains'), value: '', kind: 'ok' },
          { title: _('DNS zóny', 'DNS zones'), meta: _('zóny vedené u registrátora spravujete z panelu', 'zones hosted at the registrar are managed from the panel'), value: '', kind: 'ok' },
          { title: _('Upozornění na expirace', 'Expiry notices'), meta: _('30, 14, 7, 3 a 1 den předem, e-mailem i v panelu', '30, 14, 7, 3 and 1 day ahead, by e-mail and here'), value: '', kind: 'ok' },
          { title: _('Hlídání kreditu', 'Credit watch'), meta: _('upozorníme, když kredit u registrátora klesne pod limit', 'a notice when the registrar credit drops under the limit'), value: '', kind: 'ok' },
          { title: _('Párování s hostingem', 'Pairing with hosting'), meta: _('doména → web: alias na serveru, DNS záznamy, certifikát', 'domain → site: server alias, DNS rows, certificate'), value: '', kind: 'ok' }
        ]
      },
      advice: {
        title: _('Odkud vzít API heslo', 'Where the API password comes from'),
        lead: _('V administraci WEDOS otevřete Zákazník → WAPI, nastavte heslo pro WAPI a povolte IP adresu platformy. Login je e-mail účtu; číslo zákazníka je volitelné.', 'In the WEDOS administration open Customer → WAPI, set the WAPI password and allow the platform IP address. The login is the account e-mail; the customer number is optional.'),
        cta: '', on: function () {}
      }
    };
  }

  /* ── detail: domains, pairing, settings, history ────────────────────────── */
  function detailView(cmp, _, H, cs, s, id) {
    loadList(cmp); loadDetail(cmp, id);
    var c = S.detail;
    if (!c) {
      return { crumb: _('Registrátoři', 'Registrars'), title: _('Načítám účet…', 'Loading the account…'), stats: [], cols: [], rows: [], filters: [], readOnly: true, side: { title: _('Účty', 'Accounts'), rows: [{ title: _('← Zpět na účty', '← Back to accounts'), meta: '', value: '', kind: 'off', on: back(cmp) }] } };
    }
    var domains = c.domains || [], services = c.services || [], runs = c.runs || [], settings = c.settings || {};
    var path = '/registrar-connections/' + encodeURIComponent(c.id);
    var paired = domains.filter(function (d) { return d.paired_service_id; }).length;
    var expiring = domains.filter(function (d) { return d.days_to_expiry != null && d.days_to_expiry <= 30; }).length;
    var serviceOf = function (sid) { return services.filter(function (x) { return x.id === sid; })[0]; };
    var defaultService = settings.pair_service_id && serviceOf(settings.pair_service_id) ? serviceOf(settings.pair_service_id) : null;
    function pickService() {
      if (!services.length) { flash(cmp, _('Žádný webhosting', 'No web hosting'), _('Nejdřív si objednejte webhosting; poté k němu doménu spárujete.', 'Order a web hosting plan first; then pair the domain with it.')); return null; }
      if (services.length === 1) return services[0];
      var text = services.map(function (x, i) { return (i + 1) + ') ' + (x.label || x.hostname); }).join('\n');
      var pick = window.prompt(_('Ke kterému webu doménu spárovat? Zadejte číslo:\n', 'Which site should the domain pair with? Enter the number:\n') + text, defaultService ? String(services.indexOf(defaultService) + 1) : '1');
      if (pick === null) return null;
      return services[parseInt(pick, 10) - 1] || null;
    }
    function pair(d) {
      var svc = pickService(); if (!svc) return;
      busy(cmp, _, 'pair:' + d.id, A().post('/domains/' + encodeURIComponent(d.id) + '/pair', { service_id: svc.id }, A().key()), _('Doména spárována', 'Domain paired'), function (r) {
        var p = (r && r.pairing) || {};
        return d.fqdn + ' → ' + (svc.label || svc.hostname) + (p.dns === 'synced' ? _(' · DNS záznamy nastaveny, certifikát vystavíme po rozšíření', ' · DNS rows set, the certificate follows once propagated') : _(' · nastavte A záznamy @ a www na ', ' · set the A records for @ and www to ') + ((p.records || [])[0] || {}).content);
      });
    }
    function unpair(d) {
      if (!window.confirm(_('Odpojit ' + d.fqdn + ' od webu? Alias na serveru i DNS záznamy z párování zmizí.', 'Unpair ' + d.fqdn + '? The server alias and the DNS rows the pairing added go away.'))) return;
      busy(cmp, _, 'unpair:' + d.id, A().post('/domains/' + encodeURIComponent(d.id) + '/unpair', {}, A().key()), _('Doména odpojena', 'Domain unpaired'), d.fqdn);
    }
    return {
      crumb: _('Registrátoři', 'Registrars'), title: c.label,
      stats: [
        H.stat(_('Domény', 'Domains'), String(domains.length), expiring ? expiring + _(' expiruje do 30 dní', ' expiring within 30 days') : '', 6, Math.min(100, domains.length * 5), 12, expiring ? 'warn' : 'ok'),
        H.stat(_('Spárováno s hostingem', 'Paired with hosting'), String(paired), '', 10, domains.length ? Math.round(paired / domains.length * 100) : 0, 10, 'ok'),
        H.stat(_('Kredit u registrátora', 'Registrar credit'), credit(c.credit), c.credit && c.credit.at ? when(c.credit.at, cs) : '', 14, 60, 14, c.credit && settings.credit_threshold_minor && c.credit.minor < settings.credit_threshold_minor ? 'warn' : 'ok'),
        H.stat(_('Stav', 'State'), stateLabel(c.state, _), c.last_synced_at ? _('sync ', 'synced ') + when(c.last_synced_at, cs) : '', 18, c.state === 'active' ? 100 : 20, 8, stateKind(c.state))
      ],
      form: {
        title: _('Nastavení účtu', 'Account settings'), note: _('co má platforma dělat sama · limit kreditu je v haléřích měny registrátora', 'what the platform does on its own · the credit limit is in minor units of the registrar currency'),
        fields: [
          { key: 'rcSetLabel', label: _('Označení', 'Label'), ph: c.label, kind: 'text' },
          { key: 'rcSetSync', label: _('Automatická synchronizace', 'Automatic sync'), kind: 'select', options: [_('zapnuto', 'on'), _('vypnuto', 'off')] },
          { key: 'rcSetNotices', label: _('Upozornění na expirace', 'Expiry notices'), kind: 'select', options: [_('zapnuto', 'on'), _('vypnuto', 'off')] },
          { key: 'rcSetThreshold', label: _('Limit kreditu (Kč)', 'Credit limit'), ph: String((settings.credit_threshold_minor || 0) / 100), kind: 'text' },
          { key: 'rcSetService', label: _('Výchozí web pro párování', 'Default site for pairing'), kind: 'select', options: [_('— žádný —', '— none —')].concat(services.map(function (x) { return x.label || x.hostname; })) }
        ],
        cta: _('Uložit nastavení', 'Save settings'), hint: _('Prázdné pole ponechá současnou hodnotu.', 'An empty field keeps the current value.'),
        submit: function () {
          var body = {};
          if (String(s.rcSetLabel || '').trim()) body.label = String(s.rcSetLabel).trim();
          if (s.rcSetSync) body.auto_sync = s.rcSetSync === _('zapnuto', 'on');
          if (s.rcSetNotices) body.notices = s.rcSetNotices === _('zapnuto', 'on');
          if (String(s.rcSetThreshold || '').trim() !== '') body.credit_threshold_minor = Math.round(parseFloat(String(s.rcSetThreshold).replace(',', '.')) * 100) || 0;
          if (s.rcSetService) { var svc = services.filter(function (x) { return (x.label || x.hostname) === s.rcSetService; })[0]; body.pair_service_id = svc ? svc.id : null; }
          busy(cmp, _, 'settings', A().patch(path, body), _('Nastavení uloženo', 'Settings saved'), c.label);
        }
      },
      tableTitle: _('Domény v účtu', 'Domains in the account'), tableNote: _('expirace, kde běží DNS a s jakým webem je doména spárovaná', 'expiry, where DNS runs and which site the domain is paired with'),
      cols: [_('Doména', 'Domain'), _('DNS', 'DNS'), _('Stav', 'State'), _('Web', 'Site'), ''],
      rows: domains.filter(function (d) { return H.match(d.fqdn) || H.match(d.unicode || ''); }).map(function (d) {
        var svc = d.paired_service_id ? serviceOf(d.paired_service_id) : null, missing = !!d.missing_since, soon = d.days_to_expiry != null && d.days_to_expiry <= 30;
        var state = missing ? _('už není v účtu', 'no longer in the account') : d.state === 'ACTIVE' ? (soon ? _('expiruje za ' + d.days_to_expiry + ' dní', 'expires in ' + d.days_to_expiry + ' days') : _('aktivní', 'active')) : d.state === 'EXPIRED' || d.state === 'GRACE' ? _('expirovaná', 'expired') : String(d.state || '').toLowerCase();
        return {
          name: d.unicode || d.fqdn, sub: _('expirace ', 'expires ') + day(d.expires_at, cs) + (d.registrar_account ? ' · ' + d.registrar_account : ''),
          c2: d.dns_provider === 'connected' ? _('u registrátora · z panelu', 'at the registrar · from the panel') : d.dns_provider === 'onhost' ? _('ONhost DNS', 'ONhost DNS') : _('jinde', 'elsewhere'),
          state: state, stateStyle: H.pill(missing || d.state === 'EXPIRED' ? 'off' : soon ? 'warn' : 'ok'), barStyle: H.bar(d.days_to_expiry != null ? Math.max(3, Math.min(100, d.days_to_expiry / 3.65)) : 50, soon ? 'warn' : 'ok'),
          metric: svc ? (svc.label || svc.hostname) : (d.paired_service_id ? d.paired_service_id : '—'), rowStyle: H.rowStyle,
          action: missing ? '' : d.paired_service_id ? _('Odpojit od webu', 'Unpair') : _('Spárovat s hostingem', 'Pair with hosting'), actionCls: 'btn btn-secondary',
          onAction: function () { if (missing) return; if (d.paired_service_id) unpair(d); else pair(d); }
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Akce a historie', 'Actions and history'),
        rows: [
          { title: _('← Zpět na účty', '← Back to accounts'), meta: '', value: '', kind: 'off', on: back(cmp) },
          { title: _('Synchronizovat teď', 'Sync now'), meta: _('domény, zóny, upozornění, kredit', 'domains, zones, notices, credit'), value: S.busy.sync ? '…' : '↻', kind: 'ok', on: function () { busy(cmp, _, 'sync', A().post(path + '/sync', {}, A().key()), _('Synchronizováno', 'Synchronised'), function (r) { return summary({ kind: 'sync', summary: (r && r.summary) || {} }, _); }); } },
          { title: _('Ověřit připojení', 'Check the connection'), meta: _('přihlášení, počet domén a kredit', 'login, domain count and credit'), value: S.busy.probe ? '…' : '✓', kind: 'ok', on: function () { busy(cmp, _, 'probe', A().post(path + '/probe', {}, A().key()), _('Účet odpovídá', 'The account answers'), function (r) { var x = (r && r.result) || {}; return (x.domains != null ? x.domains + _(' domén · ', ' domains · ') : '') + credit(x.credit); }); } },
          { title: _('Odpojit účet', 'Disconnect the account'), meta: _('smaže uložené heslo a zrcadlené domény; u registrátora se nic nezmění', 'drops the stored password and the mirrored domains; nothing changes at the registrar'), value: '×', kind: 'warn', on: function () {
            if (!window.confirm(_('Odpojit účet ' + c.label + '? Uložené heslo a zrcadlené domény zmizí z panelu.', 'Disconnect ' + c.label + '? The stored password and the mirrored domains leave the panel.'))) return;
            busy(cmp, _, 'disconnect', A().del(path), _('Účet odpojen', 'Account disconnected'), c.label);
            cmp.setState({ rconSel: null });
          } }
        ].concat(runs.slice(0, 8).map(function (r) {
          return { title: (r.kind === 'sync' ? _('Synchronizace', 'Sync') : r.kind === 'probe' ? _('Ověření', 'Check') : r.kind) + ' · ' + when(r.at, cs), meta: r.ok ? summary(r, _) : (r.error || _('selhalo', 'failed')), value: r.ok ? 'OK' : '!', kind: r.ok ? 'ok' : 'warn' };
        }))
      },
      advice: c.last_error ? {
        title: _('Poslední chyba', 'Last error'), lead: c.last_error, cta: _('Zkusit znovu', 'Try again'),
        on: function () { busy(cmp, _, 'probe', A().post(path + '/probe', {}, A().key()), _('Účet odpovídá', 'The account answers'), ''); }
      } : {
        title: _('Párování domény s webhostingem', 'Pairing a domain with web hosting'),
        lead: _('„Spárovat s hostingem“ přidá doménu i www na server, nastaví A záznamy v zóně u registrátora (pokud ji spravujeme) a certifikát vystaví, jakmile jméno míří na server. Vlastní DNS jinde? Ukážeme záznamy k nastavení.', '“Pair with hosting” adds the domain and www to the server, sets the A rows in the zone at the registrar (when we manage it) and issues the certificate once the name points at the server. DNS elsewhere? We show the rows to set.'),
        cta: '', on: function () {}
      }
    };
  }

  window.OnhostPanelRegistrars = {
    view: function (cmp, _, H) { var cs = isCs(cmp), s = cmp.state; return s.rconSel ? detailView(cmp, _, H, cs, s, s.rconSel) : listView(cmp, _, H, cs, s); },
    reload: reload
  };
})();
