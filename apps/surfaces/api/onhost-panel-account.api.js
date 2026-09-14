/* onhost-panel-account.api.js — the account area of the customer panel on the control plane (data seam #27).
 *
 * API keys (/v1/tokens), profile, password and two-factor (/v1/me, /v1/me/password, /v1/me/totp/*), team
 * (/v1/organizations/{id} members, invitations, roles), billing identity (PATCH /v1/organizations/{id}), webhooks
 * (/v1/webhooks) and sessions. Every view is returned in the prototype's generic view shape
 * (crumb/title/stats/form/cols/rows/side/wide/advice) so the surface stays byte-identical; the renderer swaps the
 * narrated builders for these when window.ONHOST_PANEL is present. Helpers (stat/pill/bar/dot/rowStyle/match)
 * come from the prototype's render scope. */
(function () {
  'use strict';
  if (window.OnhostPanelAccount) return;
  var S = { tokens: null, org: null, webhooks: null, discord: null, totp: null, lastToken: null, recovery: null, pw: false, prefilled: false, busy: {} };
  var SCOPES = { 'services:read': ['čtení služeb', 'read services'], 'services:power': ['start a restart služeb', 'power services'], 'invoices:read': ['čtení faktur', 'read invoices'], 'tickets:write': ['zakládání tiketů', 'write tickets'], 'dns:write': ['zápis DNS', 'write DNS'], 'domains:read': ['čtení domén', 'read domains'], 'wallet:read': ['čtení kreditu', 'read wallet'] };
  var ROLES = [['owner', 'vlastník', 'owner'], ['org_admin', 'administrátor', 'administrator'], ['billing_admin', 'fakturace', 'billing'], ['domain_manager', 'správce domén', 'domain manager'], ['dns_manager', 'správce DNS', 'DNS manager'], ['developer', 'vývojář', 'developer'], ['cloud_operator', 'správce serverů', 'cloud operator'], ['game_operator', 'správce herních serverů', 'game operator'], ['viewer', 'jen čtení', 'viewer']];

  function A() { return window.OnhostApi; }
  function me() { return (window.ONHOST && window.ONHOST.user) || null; }
  function orgId() { var u = me(); return u && u.organization ? u.organization.id : null; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function rerender(cmp) { try { cmp.setState({ accountAt: Date.now() }); } catch (e) {} }
  function flash(cmp, t, b) { if (cmp && typeof cmp.flash === 'function') cmp.flash(t, b); }
  function fail(cmp, _, e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); }
  function load(cmp, key, path, map) {
    if (S[key] !== null || S.busy[key] || !A()) return;
    S.busy[key] = true;
    A().get(path).then(function (r) { S[key] = map ? map(r) : (r.data || r); }).catch(function () { S[key] = map ? map({ data: [] }) : []; }).then(function () { delete S.busy[key]; rerender(cmp); });
  }
  function reload(cmp, key) { S[key] = null; rerender(cmp); }
  function when(iso, cs) { if (!iso) return null; var d = new Date(iso); return d.toLocaleDateString(cs ? 'cs-CZ' : 'en-GB') + ' ' + d.toLocaleTimeString(cs ? 'cs-CZ' : 'en-GB', { hour: '2-digit', minute: '2-digit' }); }
  function day(iso, cs) { if (!iso) return '—'; return new Date(iso).toLocaleDateString(cs ? 'cs-CZ' : 'en-GB'); }
  function roleLabel(key, cs) { var r = ROLES.filter(function (x) { return x[0] === key; })[0]; return r ? (cs ? r[1] : r[2]) : key; }
  function roleKey(label) { var r = ROLES.filter(function (x) { return x[1] === label || x[2] === label || x[0] === label; })[0]; return r ? r[0] : label; }
  function mfaOn() { var u = me(); return !!(u && u.mfa && (u.mfa === true || u.mfa.totp)); }
  function setMfa(on) { var u = me(); if (u) u.mfa = on; }
  function goSecurity(cmp) { cmp.setState({ tab: 'settings', sub: 'security', selected: null, query: '' }); }

  /* ── API keys and webhooks ─────────────────────────────────────────────────────────────── */
  /* Discord: link the account for /onhost slash commands and confirmation buttons; a Discord channel webhook receives the same events as any webhook, as embeds. */
  function discordRows(cmp, _, cs) {
    load(cmp, 'discord', '/integrations/discord');
    var d = S.discord;
    if (d === null || d === undefined) return [{ title: 'Discord', meta: _('načítám…', 'loading…'), value: '', kind: 'off' }];
    if (!d.configured) return [{ title: 'Discord', meta: _('ovládání služeb z Discordu zapneme, jakmile je aplikace nastavená na platformě', 'service control from Discord is enabled once the application is configured on the platform'), value: _('brzy', 'soon'), kind: 'off' }];
    var rows = (d.links || []).map(function (l) {
      return { title: 'Discord · @' + (l.discord_username || l.discord_user_id), meta: _('propojeno ', 'linked ') + (l.linked_at ? day(l.linked_at, cs) : '') + ' · ' + (l.commands || 0) + _(' příkazů', ' commands') + ' · /onhost services, status, backup, restart, deploy, ask', value: _('odpojit', 'unlink'), kind: 'ok',
        on: function () { if (window.confirm(_('Odpojit Discord účet @' + (l.discord_username || '') + '?', 'Unlink the Discord account @' + (l.discord_username || '') + '?'))) A().del('/integrations/discord/links/' + encodeURIComponent(l.id)).then(function () { reload(cmp, 'discord'); }).catch(function (e) { fail(cmp, _, e); }); } };
    });
    rows.push({ title: _('Propojit Discord účet', 'Link a Discord account'), meta: _('vygenerujeme kód; na Discordu zadejte /onhost link KÓD (bot musí být na serveru: ', 'we generate a code; in Discord run /onhost link CODE (the bot must be on the server: ') + (d.invite_url || '') + ')', value: _('kód', 'code'), kind: 'ok',
      on: function () {
        A().post('/integrations/discord/link-code', {}, A().key()).then(function (r) {
          var c = r.data || r;
          S.lastToken = { name: 'Discord', token: '/onhost link ' + c.code, expires: c.expires_at };
          flash(cmp, _('Kód pro Discord: ', 'Discord code: ') + c.code, _('Platí 15 minut. Na Discordu zadejte /onhost link ' + c.code, 'Valid 15 minutes. In Discord run /onhost link ' + c.code));
          rerender(cmp);
        }).catch(function (e) { fail(cmp, _, e); });
      } });
    rows.push({ title: _('Discord notifikace', 'Discord notifications'), meta: _('vložte URL webhooku kanálu (Nastavení kanálu → Integrace → Webhooky); události přijdou jako karty', 'paste a channel webhook URL (Channel settings → Integrations → Webhooks); events arrive as embeds'), value: '+', kind: 'ok',
      on: function () {
        var url = window.prompt(_('URL Discord webhooku kanálu:', 'Discord channel webhook URL:'), 'https://discord.com/api/webhooks/');
        if (!url || url.indexOf('https://discord.com/api/webhooks/') !== 0 && url.indexOf('https://discordapp.com/api/webhooks/') !== 0) return;
        A().post('/webhooks', { url: url.trim(), events: ['*'] }, A().key()).then(function () { flash(cmp, _('Discord notifikace zapnuty', 'Discord notifications on'), _('Výpadky, deploye, faktury a tikety přijdou do kanálu.', 'Outages, deployments, invoices and tickets arrive in the channel.')); reload(cmp, 'webhooks'); }).catch(function (e) { fail(cmp, _, e); });
      } });
    return rows;
  }

  function apiView(cmp, _, H) {
    load(cmp, 'tokens', '/tokens');
    load(cmp, 'webhooks', '/webhooks');
    var cs = isCs(cmp), s = cmp.state, tokens = (S.tokens || []).filter(function (t) { return !t.revoked_at; }), hooks = (S.webhooks || []).filter(function (h) { return h.state !== 'disabled'; });
    var presets = [
      [_('jen čtení', 'read only'), ['services:read', 'invoices:read', 'domains:read', 'wallet:read']],
      [_('provoz služeb', 'operate services'), ['services:read', 'services:power', 'domains:read', 'dns:write', 'tickets:write']],
      [_('všechny rozsahy', 'all scopes'), Object.keys(SCOPES)]
    ];
    var failing = hooks.filter(function (h) { return (h.failures || 0) > 0; }).length;
    return {
      crumb: _('Vývoj', 'Development'), title: _('API klíče a webhooky', 'API keys and webhooks'),
      stats: [
        H.stat(_('Aktivní klíče', 'Active keys'), String(tokens.length), '', 2, Math.min(100, tokens.length * 20), 14, 'ok'),
        H.stat(_('Webhooky', 'Webhooks'), String(hooks.length), failing ? failing + _(' selhává', ' failing') : (hooks.length ? _('doručeno', 'delivered') : ''), 10, Math.min(100, hooks.length * 25), 16, failing ? 'warn' : 'ok'),
        H.stat(_('Dokumentace', 'Documentation'), 'OpenAPI 3.1', '/dokumentace', 18, 100, 10, 'ok'),
        H.stat(_('Limit volání', 'Rate limit'), '120 / min', _('na klíč', 'per key'), 26, 40, 8, 'ok')
      ],
      form: {
        title: _('Nový API klíč', 'New API key'), note: _('rozsah nastavte co nejužší · klíč dědí práva vašeho účtu v této organizaci', 'scope it as narrowly as you can · the key inherits your rights in this organization'),
        fields: [
          { key: 'keyName', label: _('Název klíče', 'Key name'), ph: 'deploy-bot', kind: 'text' },
          { key: 'keyScope', label: _('Rozsah', 'Scope'), kind: 'select', options: presets.map(function (p) { return p[0]; }) },
          { key: 'keyDays', label: _('Platnost (dní, max. 365)', 'Validity (days, max. 365)'), ph: '365', kind: 'text' }
        ],
        cta: _('Vytvořit klíč', 'Create key'),
        hint: _('Klíč uvidíte jen jednou, hned po vytvoření. Posílá se v hlavičce Authorization: Bearer.', 'The key is shown once, right after creation. Send it in the Authorization: Bearer header.'),
        submit: function () {
          var name = String(s.keyName || '').trim();
          if (!name) { flash(cmp, _('Chybí název', 'Name missing'), _('Pojmenujte klíč, ať víte, kdo ho používá.', 'Name the key so you know who uses it.')); return; }
          var preset = presets.filter(function (p) { return p[0] === s.keyScope; })[0] || presets[0];
          var days = parseInt(s.keyDays, 10);
          A().post('/tokens', { name: name, scopes: preset[1], expires_in_days: days > 0 ? Math.min(365, days) : 365 }, A().key()).then(function (r) {
            var d = r.data || r;
            S.lastToken = { name: d.name || name, token: d.token, expires: d.expires_at };
            cmp.setState({ keyName: '' });
            flash(cmp, _('Klíč vytvořen', 'Key created'), _('Zkopírujte si ho z rámečku nad tabulkou — podruhé se nezobrazí.', 'Copy it from the box above the table — it will not be shown again.'));
            reload(cmp, 'tokens');
          }).catch(function (e) { fail(cmp, _, e); });
        }
      },
      tableTitle: _('Klíče', 'Keys'), tableNote: _('rozsah práv, platnost a poslední použití', 'scope, validity and last use'),
      cols: [_('Klíč', 'Key'), _('Rozsah', 'Scope'), _('Stav', 'State'), _('Naposledy použit', 'Last used'), ''],
      rows: tokens.filter(function (t) { return H.match(t.name); }).map(function (t) {
        var expired = t.expires_at && new Date(t.expires_at) < new Date();
        return {
          name: t.name, sub: (t.scopes || []).map(function (k) { return SCOPES[k] ? (cs ? SCOPES[k][0] : SCOPES[k][1]) : k; }).join(', ') || '—',
          c2: (t.scopes || []).join(', '),
          state: expired ? _('vypršel', 'expired') : _('aktivní', 'active'), stateStyle: H.pill(expired ? 'off' : 'ok'),
          barStyle: H.bar(expired ? 5 : 100, expired ? 'off' : 'ok'), metric: (t.last_used_at ? when(t.last_used_at, cs) : _('nepoužit', 'unused')) + (t.expires_at ? ' · ' + _('platí do ', 'valid until ') + day(t.expires_at, cs) : ''), rowStyle: H.rowStyle,
          action: _('Zrušit', 'Revoke'), actionCls: 'btn btn-secondary',
          onAction: function () {
            if (!window.confirm(_('Zrušit klíč ' + t.name + '? Přestane platit okamžitě.', 'Revoke key ' + t.name + '? It stops working immediately.'))) return;
            A().del('/tokens/' + encodeURIComponent(t.id)).then(function () { flash(cmp, _('Klíč zrušen', 'Key revoked'), t.name + _(' · přestal platit okamžitě.', ' · invalidated immediately.')); reload(cmp, 'tokens'); }).catch(function (e) { fail(cmp, _, e); });
          }
        };
      }),
      filters: [], readOnly: true,
      wide: S.lastToken ? {
        real: true, title: _('Nový klíč ', 'New key ') + S.lastToken.name + _(' — zkopírujte si ho teď', ' — copy it now'), note: _('zobrazí se jen jednou · po obnovení stránky zmizí', 'shown once · gone after a page reload'),
        rows: [{ title: S.lastToken.token, meta: 'Authorization: Bearer ' + S.lastToken.token, aux: S.lastToken.expires ? _('platí do ', 'valid until ') + day(S.lastToken.expires, cs) : '', value: '', dot: H.dot('ok') }]
      } : null,
      side: {
        title: _('Discord a webhooky', 'Discord and webhooks'),
        rows: discordRows(cmp, _, cs).concat(hooks.length ? hooks.map(function (h) {
          return { title: h.url, meta: ((h.events || ['*']).join(', ')) + (h.last_delivered_at ? ' · ' + _('naposledy ', 'last ') + when(h.last_delivered_at, cs) : ''), value: (h.failures || 0) > 0 ? h.failures + ' ×' : (h.state === 'paused' ? _('pozastaven', 'paused') : _('aktivní', 'active')), kind: (h.failures || 0) > 0 ? 'warn' : 'ok',
            on: function () { if (window.confirm(_('Odebrat webhook ' + h.url + '?', 'Remove webhook ' + h.url + '?'))) A().del('/webhooks/' + encodeURIComponent(h.id)).then(function () { reload(cmp, 'webhooks'); }).catch(function (e) { fail(cmp, _, e); }); } };
        }) : [{ title: _('Zatím žádný webhook', 'No webhook yet'), meta: _('podepsané události (objednávky, služby, faktury, domény, tikety) s opakováním a historií doručení', 'signed events (orders, services, invoices, domains, tickets) with retries and a delivery history'), value: '—', kind: 'off' }])
      },
      advice: {
        title: _('Přidat webhook', 'Add a webhook'), lead: _('Události o objednávkách, službách, fakturách, doménách a tiketech posíláme podepsané na vaši HTTPS adresu. Doručení opakujeme a jeho historii uvidíte zde.', 'Events about orders, services, invoices, domains and tickets are delivered signed to your HTTPS endpoint, with retries and a visible delivery history.'), cta: _('Přidat webhook →', 'Add a webhook →'),
        on: function () {
          var url = window.prompt(_('URL webhooku (musí začínat https://):', 'Webhook URL (must start with https://):'), 'https://');
          if (!url || url === 'https://') return;
          A().post('/webhooks', { url: url.trim(), events: ['*'] }, A().key()).then(function (r) {
            var d = r.data || r;
            flash(cmp, _('Webhook přidán', 'Webhook added'), d.secret ? _('Podpisový klíč (zobrazí se jen jednou): ', 'Signing secret (shown once): ') + d.secret : url);
            if (d.secret) S.lastToken = { name: 'webhook', token: d.secret, expires: null };
            reload(cmp, 'webhooks');
          }).catch(function (e) { fail(cmp, _, e); });
        }
      }
    };
  }

  /* ── Security: two-factor, password, sessions ────────────────────────────────────────── */
  function securityView(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state, u = me() || {}, on = mfaOn();
    var enrolling = S.totp && !S.totp.disable, disabling = S.totp && S.totp.disable;
    var form;
    if (enrolling || disabling) {
      form = {
        title: enrolling ? _('Potvrďte kód z autentikátoru', 'Confirm the code from your authenticator') : _('Vypnutí dvoufázového ověření', 'Turn two-factor off'),
        note: enrolling ? _('naskenujte tajný klíč níže do Google Authenticator, Aegis nebo 1Password a opište šestimístný kód', 'add the secret below to Google Authenticator, Aegis or 1Password and type the six-digit code') : _('zadejte aktuální kód z aplikace nebo záložní kód', 'enter the current code from the app or a recovery code'),
        fields: [{ key: 'totpCode', label: _('Kód', 'Code'), ph: '123456', kind: 'text', autocomplete: 'one-time-code' }],
        cta: enrolling ? _('Zapnout 2FA', 'Turn 2FA on') : _('Vypnout 2FA', 'Turn 2FA off'),
        hint: enrolling ? _('Po potvrzení dostanete deset záložních kódů — uložte je mimo telefon.', 'After confirming you get ten recovery codes — keep them away from the phone.') : '',
        submit: function () {
          var code = String(s.totpCode || '').trim();
          if (!code) { flash(cmp, _('Chybí kód', 'Code missing'), ''); return; }
          var req = enrolling ? A().post('/me/totp/confirm', { code: code }, A().key()) : A().post('/me/totp/disable', { code: code }, A().key());
          req.then(function (r) {
            var d = r.data || r;
            if (enrolling) { S.recovery = d.recovery_codes || []; setMfa(true); flash(cmp, _('Dvoufázové ověření zapnuto', 'Two-factor enabled'), _('Záložní kódy jsou v pravém sloupci — zobrazí se jen jednou.', 'Recovery codes are in the right column — shown once.')); }
            else { setMfa(false); S.recovery = null; flash(cmp, _('Dvoufázové ověření vypnuto', 'Two-factor disabled'), _('Doporučujeme ho zase zapnout.', 'We recommend turning it back on.')); }
            S.totp = null; cmp.setState({ totpCode: '' });
          }).catch(function (e) { fail(cmp, _, e); });
        }
      };
    } else {
      form = {
        title: _('Změna hesla', 'Change password'), note: _('alespoň 12 znaků, písmena i číslice · ostatní relace odhlásíme', 'at least 12 characters with letters and digits · other sessions are signed out'),
        fields: [
          { key: 'pwCurrent', label: _('Současné heslo', 'Current password'), ph: '••••••••', kind: 'password', autocomplete: 'current-password' },
          { key: 'pwNew', label: _('Nové heslo', 'New password'), ph: _('12+ znaků', '12+ characters'), kind: 'password', autocomplete: 'new-password' },
          { key: 'pwNew2', label: _('Nové heslo znovu', 'New password again'), ph: '', kind: 'password', autocomplete: 'new-password' }
        ],
        cta: _('Změnit heslo', 'Change password'),
        hint: _('Heslo nikdy neposíláme e-mailem; po změně se odhlásí ostatní zařízení.', 'We never e-mail passwords; other devices are signed out after the change.'),
        submit: function () {
          var cur = String(s.pwCurrent || ''), nw = String(s.pwNew || '');
          if (nw.length < 12 || !/[0-9]/.test(nw) || !/[a-zA-Z]/.test(nw)) { flash(cmp, _('Slabé heslo', 'Weak password'), _('Alespoň 12 znaků, písmena i číslice.', 'At least 12 characters with letters and digits.')); return; }
          if (nw !== String(s.pwNew2 || '')) { flash(cmp, _('Hesla se neshodují', 'Passwords differ'), ''); return; }
          A().post('/me/password', { current_password: cur, password: nw }, A().key()).then(function () {
            cmp.setState({ pwCurrent: '', pwNew: '', pwNew2: '' });
            flash(cmp, _('Heslo změněno', 'Password changed'), _('Ostatní relace jsme odhlásili.', 'Other sessions were signed out.'));
          }).catch(function (e) { fail(cmp, _, e); });
        }
      };
    }
    var rows = [
      [_('Dvoufázové ověření (TOTP)', 'Two-factor (TOTP)'), _('váš účet · autentikátor', 'your account · authenticator app'), on, on ? _('zapnuto', 'on') : _('doporučujeme zapnout', 'recommended'), on ? _('Vypnout', 'Turn off') : _('Zapnout', 'Turn on'), function () {
        if (on) { S.totp = { disable: true }; rerender(cmp); return; }
        A().post('/me/totp/enroll', {}, A().key()).then(function (r) { S.totp = r.data || r; rerender(cmp); }).catch(function (e) { fail(cmp, _, e); });
      }],
      [_('Heslo', 'Password'), _('přihlášení e-mailem a heslem', 'e-mail and password sign-in'), true, _('nastaveno', 'set'), _('Změnit', 'Change'), function () { S.totp = null; rerender(cmp); }],
      [_('Ověřený e-mail', 'Verified e-mail'), u.email || '', !!u.email_verified || true, u.email || '', _('Změnit přes podporu', 'Change via support'), function () { flash(cmp, _('Změna e-mailu', 'E-mail change'), _('Přihlašovací e-mail měníme po ověření identity — napište prosím podpoře.', 'The sign-in e-mail is changed after identity verification — please write to support.')); }]
    ];
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Zabezpečení a 2FA', 'Security and 2FA'),
      stats: [
        H.stat(_('Dvoufázové ověření', 'Two-factor'), on ? _('zapnuto', 'on') : _('vypnuto', 'off'), on ? '✓' : _('doporučeno', 'recommended'), 3, on ? 100 : 30, 8, on ? 'ok' : 'warn'),
        H.stat(_('Přihlášení', 'Sign-in'), _('heslo', 'password') + (on ? ' + TOTP' : ''), '', 11, on ? 90 : 50, 10, 'ok'),
        H.stat(_('Účet od', 'Customer since'), u.since ? day(u.since, cs) : '—', '', 19, 40, 10, 'ok'),
        H.stat(_('Aktivní relace', 'Active session'), _('tato', 'this one'), '', 27, 20, 6, 'ok')
      ],
      form: form,
      tableTitle: _('Zabezpečení účtu', 'Account security'), tableNote: _('co je zapnuté a co doporučujeme', 'what is on and what we recommend'),
      cols: [_('Nastavení', 'Setting'), _('Rozsah', 'Scope'), _('Stav', 'State'), _('Poznámka', 'Note'), ''],
      rows: rows.filter(function (r) { return H.match(r[0]); }).map(function (r) {
        return { name: r[0], sub: r[1], c2: r[1], state: r[2] ? _('zapnuto', 'on') : _('vypnuto', 'off'), stateStyle: H.pill(r[2] ? 'ok' : 'warn'), barStyle: H.bar(r[2] ? 100 : 20, r[2] ? 'ok' : 'warn'), metric: r[3], rowStyle: H.rowStyle, action: r[4], actionCls: r[2] ? 'btn btn-secondary' : 'btn btn-primary', onAction: r[5] };
      }),
      filters: [], readOnly: true,
      wide: enrolling ? {
        real: true, title: _('Tajný klíč pro autentikátor', 'Authenticator secret'), note: _('zadejte ručně, nebo vložte odkaz otpauth do aplikace', 'type it in, or paste the otpauth link into the app'),
        rows: [
          { title: S.totp.secret || '', meta: _('tajný klíč · zadejte ručně (time-based, 6 číslic, 30 s)', 'secret · manual entry (time-based, 6 digits, 30 s)'), aux: '', value: '', dot: H.dot('ok') },
          { title: S.totp.otpauth || '', meta: _('otpauth odkaz pro aplikaci', 'otpauth link for the app'), aux: '', value: '', dot: H.dot('ok') }
        ]
      } : null,
      side: S.recovery && S.recovery.length ? {
        title: _('Záložní kódy — uložte si je', 'Recovery codes — save them'),
        rows: S.recovery.map(function (c) { return { title: c, meta: _('jednorázový', 'single use'), value: '', kind: 'ok' }; })
      } : {
        title: _('Relace a zařízení', 'Sessions and devices'),
        rows: [{ title: (navigator.userAgent.match(/(Firefox|Edg|Chrome|Safari)\/[\d.]+/) || ['prohlížeč'])[0], meta: _('tato relace · odhlášení v nabídce účtu vpravo nahoře', 'this session · sign out from the account menu top right'), value: _('aktivní', 'active'), kind: 'ok' }]
      },
      advice: on ? null : { title: _('Zapněte dvoufázové ověření', 'Turn on two-factor'), lead: _('Kód z autentikátoru chrání účet, i kdyby heslo uniklo. Zabere to minutu.', 'A code from an authenticator protects the account even if the password leaks. It takes a minute.'), cta: _('Zapnout 2FA →', 'Turn on 2FA →'), on: rows[0][5] }
    };
  }

  /* ── Account: profile and billing identity ──────────────────────────────────────────── */
  function accountView(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state, u = me() || {}, o = (u.organization) || {};
    if (orgId()) load(cmp, 'org', '/organizations/' + encodeURIComponent(orgId()));
    var org = S.org || null, b = (org && org.billing) || {};
    if (org && !S.prefilled) {
      S.prefilled = true;
      setTimeout(function () { cmp.setState({ billName: org.name || '', billIco: b.ico || '', billVat: b.vat_id || b.dic || '', billStreet: b.street || '', billCity: b.city || '', billZip: b.postal_code || '', billMail: b.email || '' }); }, 0);
    }
    var kpi = (window.ONHOST_PANEL && window.ONHOST_PANEL.kpis) || {};
    var money = function (n) { return cmp && cmp.money ? cmp.money(n || 0) : String(n || 0); };
    if (S.rewards === undefined && orgId()) { S.rewards = null; load(cmp, 'rewards', '/account/rewards'); } // loyalty programme (points, level, badges)
    var rw = S.rewards && S.rewards.level ? S.rewards : null;
    // the ecosystem rows (audit §5j): invites, missions, the status page and the green footprint — each read from its own endpoint once
    if (S.referral === undefined && orgId()) { S.referral = null; load(cmp, 'referral', '/account/referral'); }
    if (S.missions === undefined && orgId()) { S.missions = null; load(cmp, 'missions', '/account/missions'); }
    if (S.statusPage === undefined && orgId()) { S.statusPage = null; load(cmp, 'statusPage', '/account/status-page'); }
    if (S.green === undefined && orgId()) { S.green = null; load(cmp, 'green', '/account/green'); }
    var rf = S.referral || null, ms = S.missions || null, sp = S.statusPage || null, gr = S.green || null;
    var profileRows = [
      [_('Doporučte nás', 'Refer us'), rf && rf.code ? rf.code : (rf ? _('kód ještě nemáte', 'no code yet') : _('načítám…', 'loading…')), rf && rf.counts ? (rf.counts.rewarded + _(' odměněných · ', ' rewarded · ') + rf.counts.pending + _(' čeká', ' pending')) : _('body a kredit pro obě strany po první platbě', 'points and credit for both sides after the first payment'), 'ok', rf && rf.code ? _('Zkopírovat odkaz', 'Copy the link') : _('Získat kód', 'Get a code'), function () {
        if (!orgId()) return;
        if (rf && rf.code) { try { navigator.clipboard.writeText(rf.link); } catch (e) { /* clipboard unavailable */ } flash(cmp, _('Odkaz k doporučení', 'Invite link'), rf.link + '\n' + _('Odměna: ', 'Reward: ') + (rf.reward ? (rf.reward.referrer_points + ' b. + ' + (rf.reward.referrer_credit ? (rf.reward.referrer_credit.minor / 100) + ' ' + rf.reward.referrer_credit.currency : '')) : '')); return; }
        A().post('/account/referral/code', {}, A().key()).then(function (r) { S.referral = r.data || r; rerender(cmp); flash(cmp, _('Kód vytvořen', 'Code created'), (S.referral && S.referral.link) || ''); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
      }],
      [_('Mise měsíce', 'Missions of the month'), ms ? (ms.done + ' / ' + ms.total + (ms.streak ? _(' · série ', ' · streak ') + ms.streak.months + ' / ' + ms.streak.target : '')) : _('načítám…', 'loading…'), ms && ms.missions ? ms.missions.filter(function (m) { return !m.done; }).map(function (m) { return m.title; }).slice(0, 2).join(' · ') || _('vše splněno', 'all done') : '', ms && ms.done === ms.total ? 'ok' : 'warn', _('Vyhodnotit', 'Evaluate'), function () {
        if (!orgId()) return;
        A().post('/account/missions/evaluate', {}, A().key()).then(function (r) { var d = r.data || r; S.missions = d.summary || S.missions; rerender(cmp); var lines = ((d.summary && d.summary.missions) || []).map(function (m) { return (m.done ? '✓ ' : '· ') + m.title + ' (' + m.points + ' b.)' + (m.done ? '' : ' — ' + m.hint); }); flash(cmp, (d.awarded && d.awarded.length) ? _('Body připsány za: ', 'Points for: ') + d.awarded.join(', ') : _('Mise měsíce', 'Missions of the month'), lines.join('\n')); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
      }],
      [_('Vlastní stránka stavu', 'Own status page'), sp ? (sp.settings && sp.settings.enabled ? _('zapnuta', 'on') : _('vypnuta', 'off')) : _('načítám…', 'loading…'), sp ? sp.url : '', sp && sp.settings && sp.settings.enabled ? 'ok' : 'off', sp && sp.settings && sp.settings.enabled ? _('Vypnout', 'Switch off') : _('Zapnout', 'Switch on'), function () {
        if (!orgId() || !sp) return;
        var on = !(sp.settings && sp.settings.enabled);
        var title = on ? window.prompt(_('Název stránky (např. jméno vaší firmy):', 'Page title (e.g. your company name):'), (sp.settings && sp.settings.title) || (org && org.name) || '') : null;
        if (on && title === null) return;
        A().patch('/organizations/' + encodeURIComponent(orgId()), { status_page: on ? { enabled: true, title: title || undefined } : { enabled: false } }).then(function () { S.statusPage = undefined; rerender(cmp); flash(cmp, on ? _('Stránka stavu zapnuta', 'Status page on') : _('Stránka stavu vypnuta', 'Status page off'), on ? sp.url + '\n' + _('Odznak: ', 'Badge: ') + sp.badge_url + '\n' + (sp.cname_hint || '') : ''); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
      }],
      // the customer's own status host (audit §5k-3): set the host, verify its CNAME, the edge issues the certificate on demand
      [_('Stavová stránka na vlastní doméně', 'Status page on your own domain'), sp && sp.settings ? (sp.settings.domain ? sp.settings.domain + (sp.settings.domain_verified_at ? _(' · ověřeno', ' · verified') : _(' · čeká na ověření CNAME', ' · awaiting CNAME')) : _('nenastaveno', 'not set')) : _('načítám…', 'loading…'), sp ? _('CNAME na ', 'CNAME to ') + (sp.expected_cname || '') : '', sp && sp.settings && sp.settings.domain_verified_at ? 'ok' : 'off', sp && sp.settings && sp.settings.domain && !sp.settings.domain_verified_at ? _('Ověřit', 'Verify') : _('Nastavit', 'Set'), function () {
        if (!orgId() || !sp) return;
        if (sp.settings && sp.settings.domain && !sp.settings.domain_verified_at) {
          A().post('/account/status-page/verify', {}, A().key()).then(function (r) { var d = r.data || r; S.statusPage = undefined; rerender(cmp); flash(cmp, d.verified ? _('Doména ověřena', 'Domain verified') : _('CNAME zatím nesedí', 'CNAME not there yet'), d.verified ? (d.url || '') : _('Nalezeno: ', 'Found: ') + ((d.found || []).join(', ') || '—') + '\n' + _('Očekáváno: ', 'Expected: ') + (d.expected_cname || '')); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
          return;
        }
        var domain = window.prompt(_('Hostname stránky stavu (např. status.vase-domena.cz); prázdné = zrušit:', 'Status page host name (e.g. status.your-domain.cz); empty = remove:'), (sp.settings && sp.settings.domain) || '');
        if (domain === null) return;
        A().patch('/organizations/' + encodeURIComponent(orgId()), { status_page: { domain: domain.trim() } }).then(function () { S.statusPage = undefined; rerender(cmp); flash(cmp, domain.trim() ? _('Doména uložena', 'Domain saved') : _('Doména odebrána', 'Domain removed'), domain.trim() ? _('Nastavte CNAME ', 'Set a CNAME ') + domain.trim() + ' → ' + (sp.expected_cname || '') + _(' a klikněte na Ověřit.', ' and click Verify.') : ''); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
      }],
      [_('Uhlíková stopa', 'Carbon footprint'), gr ? (gr.kwh + ' kWh · ' + Math.round(gr.gco2 / 100) / 10 + ' kg CO₂e / ' + _('měsíc', 'month')) : _('načítám…', 'loading…'), gr ? gr.renewable_pct + _(' % z obnovitelných zdrojů · odhad', ' % renewable · estimate') : '', gr && gr.renewable_pct >= 90 ? 'ok' : 'warn', _('Odznak', 'Badge'), function () {
        if (!gr) return;
        flash(cmp, _('Zelený hosting', 'Green hosting'), (gr.statement || '') + '\n' + _('Odznak na váš web: ', 'Badge for your site: ') + gr.badge_url + '\n' + (gr.rows || []).slice(0, 6).map(function (r) { return r.label + ': ' + r.kwh + ' kWh (' + r.source + ')'; }).join('\n'));
      }],
      [_('Věrnostní program', 'Loyalty programme'), rw ? (rw.level.name + ' · ' + rw.points + ' ' + _('b.', 'pts')) : _('načítám…', 'loading…'), rw ? (rw.next ? _('další úroveň ' + rw.next.name + ' za ' + rw.next.missing + ' b.', 'next level ' + rw.next.name + ' in ' + rw.next.missing + ' pts') : _('nejvyšší úroveň', 'top level')) : '', 'ok', _('Zobrazit', 'Show'), function () {
        if (!rw) return;
        var badges = (rw.badges || []).map(function (b) { return b.name; }).join(', ') || _('zatím žádný odznak', 'no badge yet');
        var history = (rw.history || []).slice(0, 8).map(function (h) { return (h.points > 0 ? '+' : '') + h.points + ' · ' + (h.note || h.rule); }).join('\n');
        flash(cmp, _('Věrnostní program', 'Loyalty programme'), _('Odznaky: ', 'Badges: ') + badges + (history ? '\n' + history : '') + '\n' + _('Body za zaplacené objednávky, včasné platby, 2FA, zálohy a monitoring; úrovně přinášejí promo kredit.', 'Points for paid orders, on-time payments, 2FA, backups and monitoring; levels bring promo credit.'));
      }],
      [_('Jméno', 'Name'), u.name || '—', _('zobrazuje se týmu i podpoře', 'shown to your team and to support'), 'ok', _('Upravit', 'Edit'), function () { var n = window.prompt(_('Jméno:', 'Name:'), u.name || ''); if (!n || !n.trim()) return; A().patch('/me', { name: n.trim() }).then(function () { u.name = n.trim(); flash(cmp, _('Jméno uloženo', 'Name saved'), n.trim()); rerender(cmp); }).catch(function (e) { fail(cmp, _, e); }); }],
      [_('Přihlašovací e-mail', 'Sign-in e-mail'), u.email || '—', _('ověřený · doklady a upozornění', 'verified · documents and alerts'), 'ok', _('Změnit přes podporu', 'Change via support'), function () { flash(cmp, _('Změna e-mailu', 'E-mail change'), _('Přihlašovací e-mail měníme po ověření identity — napište prosím podpoře.', 'The sign-in e-mail is changed after identity verification — please write to support.')); }],
      [_('Heslo a 2FA', 'Password and 2FA'), mfaOn() ? _('heslo + autentikátor', 'password + authenticator') : _('jen heslo', 'password only'), mfaOn() ? _('v pořádku', 'in order') : _('doporučujeme zapnout 2FA', 'we recommend 2FA'), mfaOn() ? 'ok' : 'warn', _('Zabezpečení', 'Security'), function () { goSecurity(cmp); }],
      // digest tuning (audit §5f-5): weekly → monthly → off, previewed from the API; the frequency lives on the organization
      [_('Přehled e-mailem', 'E-mail summary'), (function () { var fq = (org && org.settings && org.settings.digest && org.settings.digest.frequency) || (S.digest && S.digest.frequency) || 'weekly'; return { weekly: _('týdně', 'weekly'), monthly: _('měsíčně', 'monthly'), off: _('vypnuto', 'off') }[fq] || fq; })(), _('obnovy, kredit, zálohy a monitoring v jednom e-mailu', 'renewals, credit, backups and monitoring in one e-mail'), 'ok', _('Změnit', 'Change'), function () {
        if (!orgId()) return;
        var fq = (org && org.settings && org.settings.digest && org.settings.digest.frequency) || 'weekly';
        var next = fq === 'weekly' ? 'monthly' : (fq === 'monthly' ? 'off' : 'weekly');
        var label = { weekly: _('týdně', 'weekly'), monthly: _('měsíčně', 'monthly'), off: _('vypnout', 'off') }[next];
        if (!window.confirm(_('Přepnout přehled e-mailem na: ' + label + '?', 'Switch the e-mail summary to: ' + label + '?'))) return;
        A().patch('/organizations/' + encodeURIComponent(orgId()), { digest_frequency: next }).then(function () { if (org) { org.settings = org.settings || {}; org.settings.digest = { frequency: next }; } flash(cmp, _('Přehled nastaven', 'Summary set'), label); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
      }],
      [_('Náhled přehledu', 'Summary preview'), _('co by přišlo dnes', 'what would arrive today'), _('sestaveno z toho, co o účtu víme teď', 'assembled from what we know about the account now'), 'ok', _('Zobrazit', 'Show'), function () {
        if (!orgId()) return;
        A().get('/organizations/' + encodeURIComponent(orgId()) + '/digest').then(function (r) { var d = (r.data || r); var p = d.preview; flash(cmp, p ? p.title : _('Přehled', 'Summary'), p ? (p.text || p.body || '').slice(0, 900) : _('Zatím není co shrnout — přehled má smysl s první službou.', 'Nothing to summarise yet — the summary starts with the first service.')); }).catch(function (e) { fail(cmp, _, e); });
      }],
      [_('Jazyk panelu', 'Panel language'), (u.locale || 'cs') === 'en' ? 'English' : 'Čeština', _('faktury, e-maily i podpora', 'invoices, e-mails and support'), 'ok', _('Přepnout', 'Switch'), function () { var next = (u.locale || 'cs') === 'en' ? 'cs' : 'en'; A().patch('/me', { locale: next }).then(function () { u.locale = next; cmp.setState({ lang: next }); flash(cmp, _('Jazyk přepnut', 'Language switched'), next === 'cs' ? 'Čeština' : 'English'); }).catch(function (e) { fail(cmp, _, e); }); }],
      [_('Zákaznický profil', 'Customer profile'), (org && org.customer_class === 'b2b') || o.ico ? _('firma (B2B)', 'company (B2B)') : _('spotřebitel (B2C)', 'consumer (B2C)'), (b.ico || o.ico) ? 'IČO ' + (b.ico || o.ico) : _('bez IČO', 'no company id'), 'ok', _('Fakturační údaje', 'Billing details'), function () { flash(cmp, _('Fakturační údaje', 'Billing details'), _('Upravte je ve formuláři níže.', 'Edit them in the form below.')); }]
    ];
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Nastavení účtu', 'Account settings'),
      stats: [
        H.stat(_('Zákazníkem od', 'Customer since'), u.since ? day(u.since, cs) : '—', '', 3, 40, 10, 'ok'),
        H.stat(_('Organizace', 'Organization'), o.name || '—', roleLabel(u.member_role || 'owner', cs), 11, 90, 6, 'ok'),
        H.stat(_('Kredit', 'Credit'), money(kpi.credit), '', 19, 60, 10, 'ok'),
        H.stat(_('Dvoufázové ověření', 'Two-factor'), mfaOn() ? _('zapnuto', 'on') : _('vypnuto', 'off'), '', 27, mfaOn() ? 100 : 30, 8, mfaOn() ? 'ok' : 'warn')
      ],
      form: {
        title: _('Fakturační údaje', 'Billing details'), note: _('na dokladech od příštího vystavení · IČO a DIČ určují režim DPH', 'on documents from the next issue · company and VAT ids set the VAT regime'),
        fields: [
          { key: 'billName', label: _('Firma nebo jméno', 'Company or name'), ph: 'Nová s.r.o.', kind: 'text' },
          { key: 'billIco', label: 'IČO', ph: '12345678', kind: 'text' },
          { key: 'billVat', label: 'DIČ', ph: 'CZ12345678', kind: 'text' },
          { key: 'billMail', label: _('E-mail pro doklady', 'E-mail for documents'), ph: 'fakturace@firma.cz', kind: 'email', autocomplete: 'email' },
          { key: 'billStreet', label: _('Ulice a číslo', 'Street and number'), ph: 'Dlouhá 12', kind: 'text' },
          { key: 'billCity', label: _('Město', 'City'), ph: 'Praha', kind: 'text' },
          { key: 'billZip', label: _('PSČ', 'Postcode'), ph: '110 00', kind: 'text' }
        ],
        cta: _('Uložit fakturační údaje', 'Save billing details'),
        hint: _('Změna se zapíše do auditu účtu.', 'The change is written to the account audit trail.'),
        submit: function () {
          if (!orgId()) return;
          var body = { name: String(s.billName || '').trim(), billing_email: String(s.billMail || '').trim() || undefined, ico: String(s.billIco || '').trim() || null, vat_id: String(s.billVat || '').trim() || null, street: String(s.billStreet || '').trim() || null, city: String(s.billCity || '').trim() || null, postal_code: String(s.billZip || '').trim() || null };
          if (!body.name) { flash(cmp, _('Chybí název', 'Name missing'), ''); return; }
          A().patch('/organizations/' + encodeURIComponent(orgId()), body).then(function () { flash(cmp, _('Fakturační údaje uloženy', 'Billing details saved'), body.name); S.prefilled = false; reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
        }
      },
      tableTitle: _('Profil a přihlašování', 'Profile and sign-in'), tableNote: _('vše, co patří k účtu a osobě', 'everything tied to the account and the person'),
      cols: [_('Údaj', 'Detail'), _('Hodnota', 'Value'), _('Stav', 'State'), _('Poznámka', 'Note'), ''],
      rows: profileRows.filter(function (r) { return H.match(r[0]) || H.match(r[1]); }).map(function (r) {
        return { name: r[0], sub: r[2], c2: r[1], state: r[3] === 'ok' ? _('v pořádku', 'in order') : _('doporučeno', 'recommended'), stateStyle: H.pill(r[3]), barStyle: H.bar(r[3] === 'ok' ? 100 : 45, r[3]), metric: r[2], rowStyle: H.rowStyle, action: r[4], actionCls: r[3] === 'ok' ? 'btn btn-secondary' : 'btn btn-primary', onAction: r[5] };
      }),
      filters: [], readOnly: true,
      wide: {
        real: true, title: _('Platby a doklady', 'Payments and documents'), note: _('co používáme pro doklady', 'what we use for documents'),
        rows: [
          { title: _('Kredit', 'Credit'), meta: _('čerpá se první, dobijete převodem nebo kartou', 'drawn first; top up by transfer or card'), aux: '', value: money(kpi.credit), dot: H.dot('ok') },
          { title: _('Bankovní převod', 'Bank transfer'), meta: _('každý doklad má variabilní symbol · spárování automaticky', 'every document has a payment reference · matched automatically'), aux: 'VS', value: _('dostupný', 'available'), dot: H.dot('ok') },
          { title: _('Platební karta', 'Payment card'), meta: _('přes platební bránu · Visa, Mastercard, Apple Pay, Google Pay', 'through the payment gateway · Visa, Mastercard, Apple Pay, Google Pay'), aux: '', value: _('dostupná', 'available'), dot: H.dot('ok') },
          { title: _('Daňové doklady', 'Tax documents'), meta: _('PDF a ISDOC/UBL v sekci Fakturace a e-mailem', 'PDF and ISDOC/UBL in Billing and by e-mail'), aux: '', value: _('zapnuto', 'on'), dot: H.dot('ok') }
        ]
      },
      side: {
        title: _('Kontakty účtu', 'Account contacts'),
        rows: [
          { title: u.name || u.email || '—', meta: _('vlastník účtu · ', 'account owner · ') + (u.email || ''), value: _('primární', 'primary'), kind: 'ok' },
          { title: _('Fakturační e-mail', 'Invoice e-mail'), meta: b.email || o.billing_email || u.email || '—', value: '✓', kind: 'ok' },
          { title: _('Sídlo', 'Address'), meta: [b.street, b.city, b.postal_code].filter(Boolean).join(', ') || _('doplňte ve formuláři', 'add it in the form'), value: b.street ? '✓' : _('doplnit', 'add'), kind: b.street ? 'ok' : 'warn' }
        ]
      },
      advice: (b.street ? { title: _('Zapněte dvoufázové ověření', 'Turn on two-factor'), lead: _('Kód z autentikátoru ochrání účet, i kdyby heslo uniklo.', 'A code from an authenticator protects the account even if the password leaks.'), cta: _('Zabezpečení →', 'Security →'), on: function () { goSecurity(cmp); } } : { title: _('Doplňte fakturační údaje', 'Complete your billing details'), lead: _('Bez adresy a IČO vystavíme doklad na jméno účtu; s DIČ určíme správný režim DPH.', 'Without an address and company id documents carry the account name; a VAT id sets the right VAT regime.'), cta: _('Vyplnit formulář ↑', 'Fill in the form ↑'), on: function () { flash(cmp, _('Fakturační údaje', 'Billing details'), _('Formulář je nad tabulkou.', 'The form is above the table.')); } })
    };
  }

  /* ── Sessions and devices ───────────────────────────────────────────────────────────── */
  function sessionsView(cmp, _, H) {
    load(cmp, 'tokens', '/tokens');
    var cs = isCs(cmp), tokens = (S.tokens || []).filter(function (t) { return !t.revoked_at; });
    var ua = (navigator.userAgent.match(/(Firefox|Edg|Chrome|Safari)\/[\d.]+/) || ['prohlížeč'])[0];
    var rows = [{ name: ua, sub: _('tato relace', 'this session'), c2: _('aktuální zařízení', 'this device'), state: _('aktivní', 'active'), stateStyle: H.pill('ok'), barStyle: H.bar(100, 'ok'), metric: _('právě teď', 'right now'), rowStyle: H.rowStyle, action: _('Odhlásit', 'Sign out'), actionCls: 'btn btn-secondary', onAction: function () { if (window.OnhostSession) window.OnhostSession.signOut(); } }]
      .concat(tokens.map(function (t) { return { name: t.name, sub: _('API klíč', 'API key'), c2: (t.scopes || []).join(', '), state: _('klíč', 'key'), stateStyle: H.pill('off'), barStyle: H.bar(55, 'ok'), metric: t.last_used_at ? when(t.last_used_at, cs) : _('nepoužit', 'unused'), rowStyle: H.rowStyle, action: _('Zrušit', 'Revoke'), actionCls: 'btn btn-secondary', onAction: function () { if (window.confirm(_('Zrušit klíč ' + t.name + '?', 'Revoke key ' + t.name + '?'))) A().del('/tokens/' + encodeURIComponent(t.id)).then(function () { reload(cmp, 'tokens'); }).catch(function (e) { fail(cmp, _, e); }); } }; }));
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Relace a zařízení', 'Sessions and devices'),
      stats: [H.stat(_('Aktivní relace', 'Active sessions'), '1', _('tato', 'this one'), 5, 30, 12, 'ok'), H.stat(_('API klíče', 'API keys'), String(tokens.length), '', 13, Math.min(100, tokens.length * 20), 10, 'ok'), H.stat(_('Dvoufázové ověření', 'Two-factor'), mfaOn() ? _('zapnuto', 'on') : _('vypnuto', 'off'), '', 21, mfaOn() ? 100 : 30, 8, mfaOn() ? 'ok' : 'warn'), H.stat(_('Ochrana', 'Protection'), _('8 pokusů → 15 min', '8 attempts → 15 min'), _('zámek přihlášení', 'sign-in lock'), 29, 60, 6, 'ok')],
      tableTitle: _('Aktivní relace a klíče', 'Active sessions and keys'), tableNote: _('odhlášení je okamžité · po změně hesla se ostatní relace odhlásí samy', 'sign-out is immediate · a password change signs the others out'),
      cols: [_('Zařízení', 'Device'), _('Rozsah', 'Scope'), _('Stav', 'State'), _('Aktivita', 'Activity'), ''],
      rows: rows, filters: [], readOnly: true,
      side: { title: _('Doporučení', 'Recommendation'), rows: [{ title: _('Změna hesla odhlásí ostatní zařízení', 'A password change signs out other devices'), meta: _('Nastavení → Zabezpečení a 2FA', 'Settings → Security and 2FA'), value: '', kind: 'ok' }, { title: _('Zapnuté 2FA', 'Two-factor on'), meta: mfaOn() ? _('chrání každé nové přihlášení', 'protects every new sign-in') : _('doporučujeme zapnout', 'we recommend turning it on'), value: mfaOn() ? '✓' : '—', kind: mfaOn() ? 'ok' : 'warn' }] },
      advice: mfaOn() ? { title: _('Změna hesla odhlásí ostatní zařízení', 'A password change signs out other devices'), lead: _('Pokud máte podezření na cizí přihlášení, změňte heslo v Zabezpečení — všechny ostatní relace skončí.', 'If you suspect a foreign sign-in, change the password under Security — every other session ends.'), cta: _('Zabezpečení →', 'Security →'), on: function () { goSecurity(cmp); } } : { title: _('Zapněte dvoufázové ověření', 'Turn on two-factor'), lead: _('Každé nové přihlášení pak potvrdí kód z autentikátoru.', 'Every new sign-in is then confirmed by a code from the authenticator.'), cta: _('Zapnout 2FA →', 'Turn on 2FA →'), on: function () { goSecurity(cmp); } }
    };
  }

  /* ── Team and roles ─────────────────────────────────────────────────────────────────── */
  function teamView(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state, u = me() || {};
    if (orgId()) load(cmp, 'org', '/organizations/' + encodeURIComponent(orgId()));
    var org = S.org || null, members = (org && org.members) || [];
    var canManage = (u.member_role || 'owner') === 'owner' || u.member_role === 'org_admin';
    var invites = (org && org.invitations) || [];
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Tým a práva', 'Team and roles'),
      stats: [H.stat(_('Členů', 'Members'), String(members.length), '', 3, Math.min(100, members.length * 20), 12, 'ok'), H.stat(_('Vlastníků', 'Owners'), String(members.filter(function (m) { return m.role === 'owner'; }).length), '', 11, 40, 8, 'ok'), H.stat(_('Vaše role', 'Your role'), roleLabel(u.member_role || 'owner', cs), '', 19, 100, 6, 'ok'), H.stat(_('Rolí k dispozici', 'Roles available'), String(ROLES.length), '', 27, 60, 6, 'ok')],
      form: canManage ? {
        title: _('Pozvat člena', 'Invite a member'), note: _('pozvánka platí 7 dní · role určuje rozsah, ne důvěru', 'the invitation is valid 7 days · a role grants scope, not trust'),
        fields: [{ key: 'invEmail', label: 'E-mail', ph: 'kolega@firma.cz', kind: 'text' }, { key: 'invRole', label: _('Role', 'Role'), kind: 'select', options: ROLES.filter(function (r) { return r[0] !== 'owner'; }).map(function (r) { return cs ? r[1] : r[2]; }) }],
        cta: _('Poslat pozvánku', 'Send invitation'), hint: _('Pozvaný dostane e-mail s odkazem; do přijetí nemá k účtu přístup.', 'The invitee gets an e-mail link; no access until it is accepted.'),
        submit: function () {
          var email = String(s.invEmail || '').trim(), role = roleKey(s.invRole || (cs ? 'jen čtení' : 'viewer'));
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { flash(cmp, _('Neplatný e-mail', 'Invalid e-mail'), ''); return; }
          A().post('/organizations/' + encodeURIComponent(orgId()) + '/invitations', { email: email, role: role }, A().key()).then(function () { cmp.setState({ invEmail: '' }); flash(cmp, _('Pozvánka odeslána', 'Invitation sent'), email + ' · ' + roleLabel(role, cs)); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
        }
      } : null,
      tableTitle: _('Členové', 'Members'), tableNote: _('role a stav přístupu', 'role and access state'),
      cols: [_('Člen', 'Member'), _('Role', 'Role'), _('Stav', 'State'), _('Od', 'Since'), ''],
      rows: members.filter(function (m) { return H.match(m.name) || H.match(m.email); }).map(function (m) {
        var self = m.user_id === u.id, owner = m.role === 'owner';
        return {
          name: (m.name || m.email || '—') + (self ? _(' (vy)', ' (you)') : ''), sub: m.email || '', c2: roleLabel(m.role, cs),
          state: m.state === 'active' ? _('aktivní', 'active') : (m.state || '—'), stateStyle: H.pill(m.state === 'active' ? 'ok' : 'warn'), barStyle: H.bar(owner ? 100 : 60, 'ok'), metric: day(m.joined_at, cs), rowStyle: H.rowStyle,
          action: canManage && !self && !owner ? _('Změnit roli', 'Change role') : (canManage && !self && owner ? '' : ''), actionCls: 'btn btn-secondary',
          onAction: function () {
            if (!canManage || self || owner) return;
            var pick = window.prompt(_('Nová role (', 'New role (') + ROLES.map(function (r) { return r[0]; }).join(', ') + _(') nebo "odebrat":', ') or "remove":'), m.role);
            if (!pick) return;
            var req = /^(odebrat|remove)$/i.test(pick.trim()) ? A().del('/organizations/' + encodeURIComponent(orgId()) + '/members/' + encodeURIComponent(m.user_id)) : A().patch('/organizations/' + encodeURIComponent(orgId()) + '/members/' + encodeURIComponent(m.user_id), { role: roleKey(pick.trim()) });
            req.then(function () { flash(cmp, _('Přístup upraven', 'Access updated'), m.email || ''); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
          }
        };
      }).concat(invites.filter(function (i) { return H.match(i.email); }).map(function (i) {
        return {
          name: i.email, sub: _('pozvánka · platí do ', 'invitation · valid until ') + day(i.expires_at, cs), c2: roleLabel(i.role, cs),
          state: _('čeká na přijetí', 'awaiting acceptance'), stateStyle: H.pill('warn'), barStyle: H.bar(35, 'warn'), metric: day(i.created_at, cs), rowStyle: H.rowStyle,
          action: canManage ? _('Zrušit pozvánku', 'Cancel invitation') : '', actionCls: 'btn btn-secondary',
          onAction: function () {
            if (!canManage || !window.confirm(_('Zrušit pozvánku pro ' + i.email + '? Odkaz z e-mailu přestane platit.', 'Cancel the invitation for ' + i.email + '? The link in the e-mail stops working.'))) return;
            A().del('/organizations/' + encodeURIComponent(orgId()) + '/invitations/' + encodeURIComponent(i.id)).then(function () { flash(cmp, _('Pozvánka zrušena', 'Invitation cancelled'), i.email); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
          }
        };
      })),
      filters: [], readOnly: true,
      side: { title: _('Role', 'Roles'), rows: ROLES.map(function (r) { return { title: cs ? r[1] : r[2], meta: r[0], value: '', kind: 'ok' }; }) },
      advice: { title: _('Pozvěte účetní jen na fakturaci', 'Invite your accountant to billing only'), lead: _('Role „fakturace“ vidí doklady, kredit a platby — nikdy servery ani data. Přístup lze kdykoli odebrat.', 'The billing role sees documents, credit and payments — never servers or data. Access can be removed any time.'), cta: _('Pozvat ↑', 'Invite ↑'), on: function () { cmp.setState({ invRole: isCs(cmp) ? 'fakturace' : 'billing' }); flash(cmp, _('Pozvánka', 'Invitation'), _('Doplňte e-mail do formuláře nad tabulkou.', 'Add the e-mail to the form above the table.')); } }
    };
  }

  window.OnhostPanelAccount = { apiView: apiView, securityView: securityView, accountView: accountView, sessionsView: sessionsView, teamView: teamView, reload: reload, roles: ROLES, scopes: SCOPES };
})();
