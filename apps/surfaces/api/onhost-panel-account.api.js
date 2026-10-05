/* onhost-panel-account.api.js — the account area of the customer panel on the control plane (data seam #27).
 *
 * API keys (/v1/tokens), profile, password and two-factor (/v1/me, /v1/me/password, /v1/me/totp/*), team
 * (/v1/organizations/{id} members, invitations, roles), billing identity (PATCH /v1/organizations/{id}), webhooks
 * (/v1/webhooks) and sessions (/v1/me/sessions, TASK-0070). Every view is returned in the prototype's generic view shape
 * (crumb/title/stats/form/cols/rows/side/wide/advice) so the surface stays byte-identical; the renderer swaps the
 * narrated builders for these when window.ONHOST_PANEL is present. Helpers (stat/pill/bar/dot/rowStyle/match)
 * come from the prototype's render scope. */
(function () {
  'use strict';
  /* a count with its noun in the right Czech form (1 den, 2 dny, 5 dní) — G8 item 7 */
  function cn(n, cs, en, lang) { var I = window.OnhostI18n; return I ? I.cn(n, cs, en, lang) : n + ' ' + (lang === 'en' ? (n === 1 ? en[0] : en[1]) : (n === 1 ? cs[0] : (n >= 2 && n <= 4 ? cs[1] : cs[2]))); }
  function cq(_, n, cs, en) { return _(cn(n, cs, en, 'cs'), cn(n, cs, en, 'en')); }
  if (window.OnhostPanelAccount) return;
  var S = { tokens: null, sessions: null, org: null, webhooks: null, discord: null, totp: null, lastToken: null, recovery: null, pw: false, prefilled: false, busy: {} };
  var SCOPES = { 'services:read': ['čtení služeb', 'read services'], 'services:power': ['start a restart služeb', 'power services'], 'invoices:read': ['čtení faktur', 'read invoices'], 'tickets:write': ['zakládání tiketů', 'write tickets'], 'dns:write': ['zápis DNS (včetně čtení)', 'write DNS (reading included)'], 'dns:read': ['čtení DNS', 'read DNS'], 'domains:read': ['čtení domén', 'read domains'], 'wallet:read': ['čtení kreditu', 'read wallet'], 'services:console': ['konzole, terminál a příkazy', 'consoles, terminal and commands'] };
  /* A console is neither a read nor a restart (C13-H2c): no preset carries it, the customer ticks it on purpose. */
  var EXPLICIT_ONLY = ['services:console'];
  /* Every customer preset of RoleCatalog (TASK-0035, IF-17: the list showed nine of fourteen). `partner` is commission only —
     it never had reseller powers, whatever its old name said. Guest and partner are organization-only: projects are not offered them. */
  var ROLES = [['owner', 'vlastník', 'owner'], ['org_admin', 'administrátor', 'administrator'], ['billing_admin', 'fakturace', 'billing'], ['domain_manager', 'správce domén', 'domain manager'], ['dns_manager', 'správce DNS', 'DNS manager'], ['developer', 'vývojář', 'developer'], ['cloud_operator', 'správce serverů', 'cloud operator'], ['game_operator', 'správce herních serverů', 'game operator'], ['mail_manager', 'správce pošty', 'mail manager'], ['security_auditor', 'bezpečnostní auditor', 'security auditor'], ['support_contact', 'kontakt pro podporu', 'support contact'], ['viewer', 'jen čtení', 'viewer'], ['guest', 'host (jen sdílené služby)', 'guest (shared services only)'], ['partner', 'partner (jen provize)', 'partner (commission only)']];
  var ORG_ONLY = ['guest', 'partner'];

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
  /* Czech plurals through the shared helper of the session bridge (TASK-0070): csForms [1, 2–4, 5+], enForms [1, other] */
  function plural(n, csForms, enForms, cmp) {
    var en = !isCs(cmp), I = window.OnhostI18n;
    if (I) return I.plural(n, en ? enForms : csForms, en ? 'en' : 'cs');
    return en ? (n === 1 ? enForms[0] : enForms[1]) : (n === 1 ? csForms[0] : (n >= 2 && n <= 4 ? csForms[1] : csForms[2]));
  }
  function orgList() { var u = me(); return (u && Array.isArray(u.organizations)) ? u.organizations : []; }

  /* The e-mail address is not verified yet (R5): POST /v1/me/email/verification sends the mail again (once a minute, five an hour). */
  function resendVerification(cmp, _) {
    if (S.busy.verify) return;
    S.busy.verify = true;
    A().post('/me/email/verification', {}, A().key()).then(function () {
      flash(cmp, _('Ověřovací e-mail odeslán', 'Verification e-mail sent'), _('Zkontrolujte schránku ' + ((me() || {}).email || '') + ' a klikněte na odkaz. Další e-mail můžete poslat za minutu.', 'Check the mailbox ' + ((me() || {}).email || '') + ' and click the link. You can ask for another one in a minute.'));
    }).catch(function (e) { fail(cmp, _, e); }).then(function () { delete S.busy.verify; });
  }

  /* ── Service accounts (TASK-0079 API, G8 item 1): non-human identities of the organization, each with a role and its own tokens.
   *    Owner only — anybody else is refused by the server (403), so the section says so instead of listing. Every write is a bus
   *    command that asks for a fresh step-up (the session bridge's step-up dialog answers it); a token's plain text is in the create /
   *    issue answer only and goes to the same copy-once box as an API key (S.lastToken). */
  function scopePresets(_) {
    return [
      [_('jen čtení', 'read only'), ['services:read', 'invoices:read', 'domains:read', 'wallet:read']],
      [_('provoz služeb', 'operate services'), ['services:read', 'services:power', 'domains:read', 'dns:write', 'tickets:write']],
      [_('všechny rozsahy', 'all scopes'), Object.keys(SCOPES).filter(function (k) { return EXPLICIT_ONLY.indexOf(k) < 0; })]
    ];
  }
  function saLoad(cmp) {
    if (S.svcAccounts !== undefined && S.svcAccounts !== null) return;
    if (S.busy.svcAccounts || !A()) return;
    S.busy.svcAccounts = true;
    A().get('/service-accounts').then(function (r) { S.svcAccounts = { list: r.data || [], roles: r.roles || [] }; })
      .catch(function (e) { S.svcAccounts = { list: [], roles: [], denied: !!(e && (e.status === 403 || e.status === 404)), error: (e && e.message) || '' }; })
      .then(function () { delete S.busy.svcAccounts; rerender(cmp); });
  }
  function saDone(cmp, _, r, title, body) {
    var d = r || {};
    if (d.token) S.lastToken = { name: d.name || title, token: d.token, expires: d.expires_at || null };
    S.svcAccounts = null; rerender(cmp);
    flash(cmp, title, body);
  }
  function saCreate(cmp, _) {
    var roles = (S.svcAccounts && S.svcAccounts.roles) || [], presets = scopePresets(_);
    return window.OnhostDialog.form({
      title: _('Nový servisní účet', 'New service account'),
      lead: _('Servisní účet je identita pro skript nebo integraci, ne člověk. Má jednu roli v organizaci a vlastní klíče; zrušením klíče nebo účtu přestane platit okamžitě.', 'A service account is an identity for a script or an integration, not a person. It has one role in the organization and its own keys; revoking a key or the account stops it at once.'),
      confirm: _('Vytvořit účet', 'Create account'),
      fields: [
        { key: 'name', label: _('Název', 'Name'), required: true, placeholder: 'ci-deploy' },
        { key: 'role', label: _('Role', 'Role'), type: 'select', value: roles.indexOf('viewer') >= 0 ? 'viewer' : roles[0], options: roles.map(function (k) { return [k, roleLabel(k, isCs(cmp))]; }) },
        { key: 'scope', label: _('Rozsah prvního klíče', 'Scope of the first key'), type: 'select', options: presets.map(function (p) { return p[0]; }) },
        { key: 'days', label: _('Platnost klíče (dní, max. 365)', 'Key validity (days, max. 365)'), value: '365' }
      ]
    }).then(function (v) {
      if (!v) return null;
      var preset = presets.filter(function (p) { return p[0] === v.scope; })[0] || presets[0], days = parseInt(v.days, 10);
      return A().post('/service-accounts', { name: v.name.trim(), role: v.role, scopes: preset[1], expires_in_days: days > 0 ? Math.min(365, days) : 365 }, A().key()).then(function (r) {
        saDone(cmp, _, r, _('Servisní účet vytvořen', 'Service account created'), _('Klíč zkopírujte z rámečku nad tabulkou — podruhé se nezobrazí.', 'Copy the key from the box above the table — it is not shown again.'));
      });
    }).catch(function (e) { fail(cmp, _, e); });
  }
  function saManage(cmp, _, acc) {
    var live = (acc.tokens || []).filter(function (t) { return !t.revoked_at; }), presets = scopePresets(_);
    return window.OnhostDialog.form({
      title: _('Servisní účet ', 'Service account ') + acc.name,
      lead: _('Aktivní klíče: ' + live.length + '. Vyberte, co se má stát.', 'Active keys: ' + live.length + '. Choose what to do.'),
      confirm: _('Provést', 'Do it'),
      fields: [
        { key: 'op', label: _('Akce', 'Action'), type: 'select', options: [['issue', _('Vydat nový klíč', 'Issue a new key')], ['revoke', _('Zrušit vybraný klíč', 'Revoke the selected key')], ['delete', _('Odstranit celý účet i s klíči', 'Remove the whole account with its keys')]] },
        { key: 'scope', label: _('Rozsah nového klíče', 'Scope of a new key'), type: 'select', options: presets.map(function (p) { return p[0]; }) },
        { key: 'name', label: _('Název nového klíče', 'Name of a new key'), value: acc.name + '-' + new Date().toISOString().slice(0, 10) },
        { key: 'token', label: _('Klíč ke zrušení', 'Key to revoke'), type: 'select', options: live.map(function (t) { return [t.id, t.name + (t.expires_at ? ' · ' + day(t.expires_at, isCs(cmp)) : '')]; }) }
      ]
    }).then(function (v) {
      if (!v) return null;
      var base = '/service-accounts/' + encodeURIComponent(acc.id);
      if (v.op === 'issue') {
        var preset = presets.filter(function (p) { return p[0] === v.scope; })[0] || presets[0];
        if (!v.name.trim()) return null;
        return A().post(base + '/tokens', { name: v.name.trim(), scopes: preset[1], expires_in_days: 365 }, A().key()).then(function (r) { saDone(cmp, _, r, _('Klíč vydán', 'Key issued'), acc.name); });
      }
      if (v.op === 'revoke') {
        if (!v.token) return null;
        return A().del(base + '/tokens/' + encodeURIComponent(v.token)).then(function () { saDone(cmp, _, null, _('Klíč zrušen', 'Key revoked'), _('Přestal platit okamžitě.', 'It stopped working at once.')); });
      }
      return window.OnhostDialog.confirm(_('Odstranit servisní účet ' + acc.name + '?', 'Remove the service account ' + acc.name + '?'), _('Všechny jeho klíče přestanou platit okamžitě a účet se odebere z organizace.', 'All its keys stop working at once and the account leaves the organization.'), { danger: true, confirm: _('Odstranit', 'Remove') }).then(function (yes) {
        if (!yes) return null;
        return A().del(base).then(function () { saDone(cmp, _, null, _('Servisní účet odstraněn', 'Service account removed'), acc.name); });
      });
    }).catch(function (e) { fail(cmp, _, e); });
  }
  function serviceAccountRows(cmp, _, cs) {
    saLoad(cmp);
    var sa = S.svcAccounts;
    if (!sa) return [{ title: _('Servisní účty', 'Service accounts'), meta: _('načítám…', 'loading…'), value: '', kind: 'off' }];
    if (sa.denied) return [{ title: _('Servisní účty', 'Service accounts'), meta: _('spravuje je jen vlastník organizace', 'only the owner of the organization manages them'), value: '—', kind: 'off' }];
    var rows = (sa.list || []).map(function (a) {
      var live = (a.tokens || []).filter(function (t) { return !t.revoked_at; }).length;
      return { title: _('Servisní účet · ', 'Service account · ') + a.name, meta: roleLabel(a.role, cs) + ' · ' + live + ' ' + plural(live, ['aktivní klíč', 'aktivní klíče', 'aktivních klíčů'], ['active key', 'active keys'], cmp) + (a.created_at ? ' · ' + day(a.created_at, cs) : ''), value: _('spravovat', 'manage'), kind: a.state === 'active' || !a.state ? 'ok' : 'warn', on: function () { saManage(cmp, _, a); } };
    });
    rows.push({ title: _('Nový servisní účet', 'New service account'), meta: _('identita pro skript nebo integraci s vlastní rolí a klíči · jen vlastník, vyžaduje čerstvé ověření', 'an identity for a script or integration with its own role and keys · owner only, needs a fresh confirmation'), value: '+', kind: 'ok', on: function () { saCreate(cmp, _); } });
    return rows;
  }

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
        window.OnhostDialog.prompt(_('URL Discord webhooku kanálu:', 'Discord channel webhook URL:'), 'https://discord.com/api/webhooks/').then(function (url) {
        if (!url || url.indexOf('https://discord.com/api/webhooks/') !== 0 && url.indexOf('https://discordapp.com/api/webhooks/') !== 0) return;
        A().post('/webhooks', { url: url.trim(), events: ['*'] }, A().key()).then(function () { flash(cmp, _('Discord notifikace zapnuty', 'Discord notifications on'), _('Výpadky, deploye, faktury a tikety přijdou do kanálu.', 'Outages, deployments, invoices and tickets arrive in the channel.')); reload(cmp, 'webhooks'); }).catch(function (e) { fail(cmp, _, e); });
        });
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
      [_('všechny rozsahy', 'all scopes'), Object.keys(SCOPES).filter(function (k) { return EXPLICIT_ONLY.indexOf(k) < 0; })]
    ];
    var consoleYes = _('ano — klíč otevře konzoli serveru a spustí na něm příkazy', 'yes — the key opens the server console and runs commands on it');
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
          { key: 'keyConsole', label: _('Konzole a příkazy (services:console)', 'Consoles and commands (services:console)'), kind: 'select', options: [_('ne', 'no'), consoleYes] },
          { key: 'keyDays', label: _('Platnost (dní, max. 365)', 'Validity (days, max. 365)'), ph: '365', kind: 'text' }
        ],
        cta: _('Vytvořit klíč', 'Create key'),
        hint: _('Klíč uvidíte jen jednou, hned po vytvoření. Posílá se v hlavičce Authorization: Bearer. Konzole a příkazy dávejte jen klíči, který je opravdu potřebuje: kdo klíč má, dostane se do serveru.', 'The key is shown once, right after creation. Send it in the Authorization: Bearer header. Give consoles and commands only to a key that really needs them: whoever holds the key gets into the server.'),
        submit: function () {
          var name = String(s.keyName || '').trim();
          if (!name) { flash(cmp, _('Chybí název', 'Name missing'), _('Pojmenujte klíč, ať víte, kdo ho používá.', 'Name the key so you know who uses it.')); return; }
          var preset = presets.filter(function (p) { return p[0] === s.keyScope; })[0] || presets[0];
          var days = parseInt(s.keyDays, 10);
          var scopes = preset[1].concat(s.keyConsole === consoleYes ? ['services:console'] : []);
          A().post('/tokens', { name: name, scopes: scopes, expires_in_days: days > 0 ? Math.min(365, days) : 365 }, A().key()).then(function (r) {
            var d = r.data || r;
            S.lastToken = { name: d.name || name, token: d.token, expires: d.expires_at };
            cmp.setState({ keyName: '', keyConsole: '' });
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
          state: expired ? _('vypršel', 'expired') : ((t.scopes || []).indexOf('services:console') >= 0 ? _('aktivní · konzole', 'active · console') : _('aktivní', 'active')), stateStyle: H.pill(expired ? 'off' : ((t.scopes || []).indexOf('services:console') >= 0 ? 'warn' : 'ok')),
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
        title: _('Discord, webhooky a servisní účty', 'Discord, webhooks and service accounts'),
        rows: discordRows(cmp, _, cs).concat(hooks.length ? hooks.map(function (h) {
          return { title: h.url, meta: ((h.events || ['*']).join(', ')) + (h.last_delivered_at ? ' · ' + _('naposledy ', 'last ') + when(h.last_delivered_at, cs) : ''), value: (h.failures || 0) > 0 ? h.failures + ' ×' : (h.state === 'paused' ? _('pozastaven', 'paused') : _('aktivní', 'active')), kind: (h.failures || 0) > 0 ? 'warn' : 'ok',
            on: function () { if (window.confirm(_('Odebrat webhook ' + h.url + '?', 'Remove webhook ' + h.url + '?'))) A().del('/webhooks/' + encodeURIComponent(h.id)).then(function () { reload(cmp, 'webhooks'); }).catch(function (e) { fail(cmp, _, e); }); } };
        }) : [{ title: _('Zatím žádný webhook', 'No webhook yet'), meta: _('podepsané události (objednávky, služby, faktury, domény, tikety) s opakováním a historií doručení', 'signed events (orders, services, invoices, domains, tickets) with retries and a delivery history'), value: '—', kind: 'off' }]).concat(serviceAccountRows(cmp, _, cs))
      },
      advice: {
        title: _('Přidat webhook', 'Add a webhook'), lead: _('Události o objednávkách, službách, fakturách, doménách a tiketech posíláme podepsané na vaši HTTPS adresu. Doručení opakujeme a jeho historii uvidíte zde.', 'Events about orders, services, invoices, domains and tickets are delivered signed to your HTTPS endpoint, with retries and a visible delivery history.'), cta: _('Přidat webhook →', 'Add a webhook →'),
        on: function () {
          window.OnhostDialog.prompt(_('URL webhooku (musí začínat https://):', 'Webhook URL (must start with https://):'), 'https://').then(function (url) {
          if (!url || url === 'https://') return;
          A().post('/webhooks', { url: url.trim(), events: ['*'] }, A().key()).then(function (r) {
            var d = r.data || r;
            flash(cmp, _('Webhook přidán', 'Webhook added'), d.secret ? _('Podpisový klíč (zobrazí se jen jednou): ', 'Signing secret (shown once): ') + d.secret : url);
            if (d.secret) S.lastToken = { name: 'webhook', token: d.secret, expires: null };
            reload(cmp, 'webhooks');
          }).catch(function (e) { fail(cmp, _, e); });
          });
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

  /* ── Notification preferences (TASK-0070) ────────────────────────────────────────────────
     GET/PUT /v1/notifications/preferences had no screen: which kinds of notice arrive by e-mail and in the panel. Mandatory notices
     (security, invoices, legal, domain expiry, incidents) stay on and are shown as such; the server refuses to switch them off anyway. */
  var NOTIFY_KINDS = [['service', 'služby (spuštění, pozastavení, výpadky, nasazení)', 'services (activation, suspension, outages, deploys)'], ['ticket', 'tikety podpory', 'support tickets'], ['order', 'objednávky a schvalování', 'orders and approvals'], ['domain', 'domény (registrace, obnovy)', 'domains (registration, renewals)'], ['wallet', 'kredit a dobití', 'credit and top-ups'], ['dunning', 'upomínky a nezaplacené obnovy', 'reminders and unpaid renewals'], ['account', 'účet, tým a věrnostní program', 'account, team and loyalty']];
  function escHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function notifyDialog(cmp, _) {
    if (S.notifyOpen || !A()) return;
    S.notifyOpen = true;
    A().get('/notifications/preferences').then(function (r) {
      var prefs = (r && r.data) || [], mandatory = (r && r.mandatory) || [];
      var on = function (kind, channel) { var p = prefs.filter(function (x) { return x.kind === kind && x.channel === channel; })[0]; return p ? !!p.enabled : true; };
      var wrap = document.createElement('div');
      wrap.setAttribute('role', 'dialog'); wrap.setAttribute('aria-modal', 'true'); wrap.setAttribute('aria-labelledby', 'onhost-notify-title');
      wrap.style.cssText = 'position:fixed;inset:0;z-index:130;display:flex;align-items:center;justify-content:center;background:rgba(20,18,17,.55);font-family:inherit';
      var btn = 'font:inherit;font-size:13.5px;padding:9px 14px;border-radius:8px;cursor:pointer;';
      var cell = 'padding:8px 6px;border-top:1px solid color-mix(in srgb,var(--fg,#201e1d) 12%,transparent);font-size:13.5px';
      wrap.innerHTML = '<form style="background:var(--bg,#fff);color:var(--fg,#201e1d);width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 48px);overflow:auto;padding:24px;border:1px solid color-mix(in srgb,var(--fg,#201e1d) 15%,transparent);border-radius:12px;box-shadow:0 24px 60px rgba(0,0,0,.28);display:flex;flex-direction:column;gap:12px">'
        + '<div id="onhost-notify-title" style="font-weight:700;font-size:17px">' + escHtml(_('Upozornění', 'Notifications')) + '</div>'
        + '<div style="font-size:13.5px;line-height:1.5;opacity:.85">' + escHtml(_('Vyberte, co vám má chodit e-mailem a do panelu. Bezpečnostní upozornění, doklady, právní oznámení, konec domény a výpadky chodí vždy.', 'Choose what reaches you by e-mail and in the panel. Security notices, documents, legal notices, domain expiry and outages always arrive.')) + '</div>'
        + '<table style="border-collapse:collapse;width:100%"><thead><tr><th scope="col" style="text-align:left;font-size:12px;padding:6px">' + escHtml(_('Druh', 'Kind')) + '</th><th scope="col" style="font-size:12px;padding:6px">' + escHtml(_('E-mail', 'E-mail')) + '</th><th scope="col" style="font-size:12px;padding:6px">' + escHtml(_('V panelu', 'In the panel')) + '</th></tr></thead><tbody>'
        + NOTIFY_KINDS.map(function (k) {
          var label = isCs(cmp) ? k[1] : k[2], locked = mandatory.indexOf(k[0]) >= 0;
          return '<tr><th scope="row" style="text-align:left;font-weight:500;' + cell + '">' + escHtml(label) + '</th>' + ['mail', 'inapp'].map(function (ch) {
            return '<td style="text-align:center;' + cell + '"><input type="checkbox" data-kind="' + k[0] + '" data-channel="' + ch + '" aria-label="' + escHtml(label + ' · ' + (ch === 'mail' ? _('e-mail', 'e-mail') : _('v panelu', 'in the panel'))) + '"' + (locked || on(k[0], ch) ? ' checked' : '') + (locked ? ' disabled' : '') + '></td>';
          }).join('') + '</tr>';
        }).join('') + '</tbody></table>'
        + '<div data-err role="alert" style="display:none;color:#8f1c0a;font-size:13px"></div>'
        + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px">'
        + '<button type="button" data-cancel style="' + btn + 'border:1px solid color-mix(in srgb,var(--fg,#201e1d) 30%,transparent);background:transparent;color:inherit">' + escHtml(_('Zavřít', 'Close')) + '</button>'
        + '<button type="submit" style="' + btn + 'font-weight:600;border:1px solid var(--acc,#ec3013);background:var(--acc,#ec3013);color:#fff">' + escHtml(_('Uložit', 'Save')) + '</button>'
        + '</div></form>';
      document.body.appendChild(wrap);
      var form = wrap.querySelector('form'), err = wrap.querySelector('[data-err]'), submit = wrap.querySelector('[type=submit]');
      function close() { wrap.remove(); document.removeEventListener('keydown', onKey, true); S.notifyOpen = false; }
      function onKey(ev) { if (ev.key === 'Escape') { ev.stopPropagation(); close(); } }
      document.addEventListener('keydown', onKey, true);
      wrap.querySelector('[data-cancel]').addEventListener('click', close);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault(); ev.stopPropagation();
        var changed = Array.prototype.slice.call(form.querySelectorAll('input[data-kind]:not([disabled])')).filter(function (i) { return i.checked !== on(i.getAttribute('data-kind'), i.getAttribute('data-channel')); });
        if (!changed.length) { close(); return; }
        submit.disabled = true;
        changed.reduce(function (p, i) { return p.then(function () { return A().put('/notifications/preferences', { kind: i.getAttribute('data-kind'), channel: i.getAttribute('data-channel'), enabled: i.checked }); }); }, Promise.resolve())
          .then(function () { close(); flash(cmp, _('Upozornění uložena', 'Notifications saved'), changed.length + ' ' + plural(changed.length, ['změna', 'změny', 'změn'], ['change', 'changes'], cmp)); })
          .catch(function (e) { submit.disabled = false; err.style.display = 'block'; err.textContent = (e && e.message) || _('Nepodařilo se uložit.', 'Could not save.'); });
      });
      setTimeout(function () { var first = form.querySelector('input[data-kind]:not([disabled])'); if (first) first.focus(); }, 30);
    }).catch(function (e) { S.notifyOpen = false; fail(cmp, _, e); });
  }

  /* ── Account: profile and billing identity ──────────────────────────────────────────── */
  function accountView(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state, u = me() || {}, o = (u.organization) || {}, orgs = orgList();
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
      [_('Doporučte nás', 'Refer us'), rf && rf.code ? rf.code : (rf ? _('kód ještě nemáte', 'no code yet') : _('načítám…', 'loading…')), rf && rf.counts ? (cq(_, rf.counts.rewarded, ['odměněná', 'odměněné', 'odměněných'], ['rewarded', 'rewarded']) + ' · ' + rf.counts.pending + _(' čeká', ' pending')) : _('body a kredit pro obě strany po první platbě', 'points and credit for both sides after the first payment'), 'ok', rf && rf.code ? _('Zkopírovat odkaz', 'Copy the link') : _('Získat kód', 'Get a code'), function () {
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
        (on ? window.OnhostDialog.prompt(_('Název stránky (např. jméno vaší firmy):', 'Page title (e.g. your company name):'), (sp.settings && sp.settings.title) || (org && org.name) || '') : Promise.resolve(null)).then(function (title) {
        if (on && title === null) return;
        A().patch('/organizations/' + encodeURIComponent(orgId()), { status_page: on ? { enabled: true, title: title || undefined } : { enabled: false } }).then(function () { S.statusPage = undefined; rerender(cmp); flash(cmp, on ? _('Stránka stavu zapnuta', 'Status page on') : _('Stránka stavu vypnuta', 'Status page off'), on ? sp.url + '\n' + _('Odznak: ', 'Badge: ') + sp.badge_url + '\n' + (sp.cname_hint || '') : ''); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
        });
      }],
      // the customer's own status host (audit §5k-3): set the host, verify its CNAME, the edge issues the certificate on demand
      [_('Stavová stránka na vlastní doméně', 'Status page on your own domain'), sp && sp.settings ? (sp.settings.domain ? sp.settings.domain + (sp.settings.domain_verified_at ? _(' · ověřeno', ' · verified') : _(' · čeká na ověření CNAME', ' · awaiting CNAME')) : _('nenastaveno', 'not set')) : _('načítám…', 'loading…'), sp ? _('CNAME na ', 'CNAME to ') + (sp.expected_cname || '') : '', sp && sp.settings && sp.settings.domain_verified_at ? 'ok' : 'off', sp && sp.settings && sp.settings.domain && !sp.settings.domain_verified_at ? _('Ověřit', 'Verify') : _('Nastavit', 'Set'), function () {
        if (!orgId() || !sp) return;
        if (sp.settings && sp.settings.domain && !sp.settings.domain_verified_at) {
          A().post('/account/status-page/verify', {}, A().key()).then(function (r) { var d = r.data || r; S.statusPage = undefined; rerender(cmp); flash(cmp, d.verified ? _('Doména ověřena', 'Domain verified') : _('CNAME zatím nesedí', 'CNAME not there yet'), d.verified ? (d.url || '') : _('Nalezeno: ', 'Found: ') + ((d.found || []).join(', ') || '—') + '\n' + _('Očekáváno: ', 'Expected: ') + (d.expected_cname || '')); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
          return;
        }
        window.OnhostDialog.prompt(_('Hostname stránky stavu (např. status.vase-domena.cz); prázdné = zrušit:', 'Status page host name (e.g. status.your-domain.cz); empty = remove:'), (sp.settings && sp.settings.domain) || '').then(function (domain) {
        if (domain === null) return;
        A().patch('/organizations/' + encodeURIComponent(orgId()), { status_page: { domain: domain.trim() } }).then(function () { S.statusPage = undefined; rerender(cmp); flash(cmp, domain.trim() ? _('Doména uložena', 'Domain saved') : _('Doména odebrána', 'Domain removed'), domain.trim() ? _('Nastavte CNAME ', 'Set a CNAME ') + domain.trim() + ' → ' + (sp.expected_cname || '') + _(' a klikněte na Ověřit.', ' and click Verify.') : ''); }).catch(function (e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); });
        });
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
      // TASK-0070: a person in several organizations chooses the one the panel works in (OnhostOrganizations in the session bridge)
      orgs.length > 1 ? [_('Organizace', 'Organization'), (o.name || '—'), _('jste členem ', 'member of ') + orgs.length + ' ' + plural(orgs.length, ['organizace', 'organizací', 'organizací'], ['organization', 'organizations'], cmp) + ' · ' + orgs.filter(function (x) { return !o.id || x.id !== o.id; }).map(function (x) { return x.name; }).join(', '), 'ok', _('Přepnout', 'Switch'), function () { if (window.OnhostOrganizations) window.OnhostOrganizations.dialog(); }] : null,
      [_('Jméno', 'Name'), u.name || '—', _('zobrazuje se týmu i podpoře', 'shown to your team and to support'), 'ok', _('Upravit', 'Edit'), function () { window.OnhostDialog.prompt(_('Jméno:', 'Name:'), u.name || '').then(function (n) { if (!n || !n.trim()) return; A().patch('/me', { name: n.trim() }).then(function () { u.name = n.trim(); flash(cmp, _('Jméno uloženo', 'Name saved'), n.trim()); rerender(cmp); }).catch(function (e) { fail(cmp, _, e); }); }); }],
      u.email_verified === false ? [_('Přihlašovací e-mail', 'Sign-in e-mail'), u.email || '—', _('neověřený · potvrďte odkaz z e-mailu, jinak vám nemusí chodit doklady', 'not verified · confirm the link in the e-mail, otherwise documents may not reach you'), 'warn', _('Poslat ověření znovu', 'Resend verification'), function () { resendVerification(cmp, _); }] : [_('Přihlašovací e-mail', 'Sign-in e-mail'), u.email || '—', _('ověřený · doklady a upozornění', 'verified · documents and alerts'), 'ok', _('Změnit přes podporu', 'Change via support'), function () { flash(cmp, _('Změna e-mailu', 'E-mail change'), _('Přihlašovací e-mail měníme po ověření identity — napište prosím podpoře.', 'The sign-in e-mail is changed after identity verification — please write to support.')); }],
      [_('Heslo a 2FA', 'Password and 2FA'), mfaOn() ? _('heslo + autentikátor', 'password + authenticator') : _('jen heslo', 'password only'), mfaOn() ? _('v pořádku', 'in order') : _('doporučujeme zapnout 2FA', 'we recommend 2FA'), mfaOn() ? 'ok' : 'warn', _('Zabezpečení', 'Security'), function () { goSecurity(cmp); }],
      // TASK-0070: which notices arrive by e-mail and in the panel (GET/PUT /v1/notifications/preferences had no screen)
      [_('Upozornění', 'Notifications'), _('e-mail a panel podle druhu', 'e-mail and panel per kind'), _('bezpečnost, doklady a výpadky chodí vždy', 'security, documents and outages always arrive'), 'ok', _('Nastavit', 'Set'), function () { notifyDialog(cmp, _); }],
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
      [_('Jazyk panelu', 'Panel language'), (u.locale || 'cs') === 'en' ? 'English' : 'Čeština', _('faktury, e-maily i podpora', 'invoices, e-mails and support'), 'ok', _('Přepnout', 'Switch'), function () { var next = (u.locale || 'cs') === 'en' ? 'cs' : 'en'; A().patch('/me', { locale: next }).then(function () { u.locale = next; try { document.documentElement.lang = next; } catch (x) {} cmp.setState({ lang: next }); flash(cmp, _('Jazyk přepnut', 'Language switched'), next === 'cs' ? 'Čeština' : 'English'); }).catch(function (e) { fail(cmp, _, e); }); }],
      [_('Zákaznický profil', 'Customer profile'), (org && org.customer_class === 'b2b') || o.ico ? _('firma (B2B)', 'company (B2B)') : _('spotřebitel (B2C)', 'consumer (B2C)'), (b.ico || o.ico) ? 'IČO ' + (b.ico || o.ico) : _('bez IČO', 'no company id'), 'ok', _('Fakturační údaje', 'Billing details'), function () { flash(cmp, _('Fakturační údaje', 'Billing details'), _('Upravte je ve formuláři níže.', 'Edit them in the form below.')); }]
    ];
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Nastavení účtu', 'Account settings'),
      stats: [
        H.stat(_('Zákazníkem od', 'Customer since'), u.since ? day(u.since, cs) : '—', '', 3, 40, 10, 'ok'),
        H.stat(_('Organizace', 'Organization'), o.name || '—', u.member_role ? roleLabel(u.member_role, cs) : '—', 11, 90, 6, 'ok'),
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
      rows: profileRows.filter(function (r) { return r && (H.match(r[0]) || H.match(r[1])); }).map(function (r) {
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
  /* TASK-0070: the person's open web sessions (/v1/me/sessions) — every signed-in browser, not only this one — each with its own
     sign-out; "sign out the others" ends all the others. The server ends them through the bus and logs each browser out on its
     next request; the current one is signed out with the usual sign-out. */
  function device(ua, _) {
    var s = String(ua || ''), browser = (s.match(/(Edg|OPR|Firefox|Chrome|Safari)\/[\d]+/) || [null])[0];
    var os = /iPhone|iPad/.test(s) ? 'iOS' : (/Android/.test(s) ? 'Android' : (/Windows/.test(s) ? 'Windows' : (/Mac OS X|Macintosh/.test(s) ? 'macOS' : (/Linux/.test(s) ? 'Linux' : ''))));
    if (!browser && !os) return s ? s.slice(0, 48) : _('neznámé zařízení', 'unknown device');
    return (browser ? browser.replace('Edg/', 'Edge ').replace('OPR/', 'Opera ').replace('/', ' ') : _('prohlížeč', 'browser')) + (os ? ' · ' + os : '');
  }
  function sessionsView(cmp, _, H) {
    load(cmp, 'tokens', '/tokens');
    load(cmp, 'sessions', '/me/sessions');
    var cs = isCs(cmp), tokens = (S.tokens || []).filter(function (t) { return !t.revoked_at; });
    var sessions = Array.isArray(S.sessions) ? S.sessions : [], others = sessions.filter(function (x) { return !x.current; });
    var endOne = function (x) {
      if (!window.confirm(_('Odhlásit zařízení ' + device(x.user_agent, _) + '? Při dalším použití se bude muset přihlásit znovu.', 'Sign out ' + device(x.user_agent, _) + '? It will have to sign in again on its next use.'))) return;
      A().del('/me/sessions/' + encodeURIComponent(x.id)).then(function () { flash(cmp, _('Zařízení odhlášeno', 'Device signed out'), device(x.user_agent, _)); reload(cmp, 'sessions'); }).catch(function (e) { fail(cmp, _, e); });
    };
    var endOthers = function () {
      if (!others.length) { flash(cmp, _('Žádná jiná relace', 'No other session'), _('Přihlášeni jste jen v tomto prohlížeči.', 'You are signed in in this browser only.')); return; }
      if (!window.confirm(_('Odhlásit všechna ostatní zařízení (' + others.length + ')? Tento prohlížeč zůstane přihlášený.', 'Sign out every other device (' + others.length + ')? This browser stays signed in.'))) return;
      A().post('/me/sessions/end-others', {}, A().key()).then(function (r) {
        var n = Number((r && (r.ended != null ? r.ended : (r.data && r.data.ended))) || 0);
        flash(cmp, _('Ostatní zařízení odhlášena', 'Other devices signed out'), n + ' ' + plural(n, ['relace', 'relace', 'relací'], ['session', 'sessions'], cmp));
        reload(cmp, 'sessions');
      }).catch(function (e) { fail(cmp, _, e); });
    };
    var listed = sessions.length ? sessions : [{ id: null, current: true, user_agent: navigator.userAgent, ip: null, last_seen_at: null, signed_in_at: null }];
    var sessionRows = listed.map(function (x) {
      return {
        name: device(x.user_agent, _), sub: x.current ? _('tato relace', 'this session') : (_('přihlášeno ', 'signed in ') + (when(x.signed_in_at, cs) || '—')),
        c2: x.ip || (x.current ? _('aktuální zařízení', 'this device') : '—'), state: x.current ? _('tato', 'this one') : _('aktivní', 'active'),
        stateStyle: H.pill('ok'), barStyle: H.bar(x.current ? 100 : 70, 'ok'), metric: x.current ? _('právě teď', 'right now') : (when(x.last_seen_at, cs) || '—'),
        rowStyle: H.rowStyle, action: _('Odhlásit', 'Sign out'), actionCls: 'btn btn-secondary',
        onAction: x.current ? function () { if (window.OnhostSession) window.OnhostSession.signOut(); } : function () { endOne(x); }
      };
    });
    var rows = sessionRows.concat(tokens.map(function (t) { return { name: t.name, sub: _('API klíč', 'API key'), c2: (t.scopes || []).join(', '), state: _('klíč', 'key'), stateStyle: H.pill('off'), barStyle: H.bar(55, 'ok'), metric: t.last_used_at ? when(t.last_used_at, cs) : _('nepoužit', 'unused'), rowStyle: H.rowStyle, action: _('Zrušit', 'Revoke'), actionCls: 'btn btn-secondary', onAction: function () { if (window.confirm(_('Zrušit klíč ' + t.name + '?', 'Revoke key ' + t.name + '?'))) A().del('/tokens/' + encodeURIComponent(t.id)).then(function () { reload(cmp, 'tokens'); }).catch(function (e) { fail(cmp, _, e); }); } }; }));
    var count = listed.length;
    var advice = others.length
      ? { title: _('Odhlásit ostatní zařízení', 'Sign out the other devices'), lead: _('Jste přihlášeni ještě na ' + others.length + ' ' + plural(others.length, ['dalším zařízení', 'dalších zařízeních', 'dalších zařízeních'], ['other device', 'other devices'], cmp) + '. Jedním klikem je odhlásíte; tento prohlížeč zůstane přihlášený.', 'You are also signed in on ' + others.length + ' ' + plural(others.length, ['other device', 'other devices'], ['other device', 'other devices'], cmp) + '. One click signs them out; this browser stays signed in.'), cta: _('Odhlásit ostatní', 'Sign out the others'), on: endOthers }
      : (mfaOn()
        ? { title: _('Změna hesla odhlásí ostatní zařízení', 'A password change signs out other devices'), lead: _('Pokud máte podezření na cizí přihlášení, změňte heslo v Zabezpečení — všechny ostatní relace skončí.', 'If you suspect a foreign sign-in, change the password under Security — every other session ends.'), cta: _('Zabezpečení →', 'Security →'), on: function () { goSecurity(cmp); } }
        : { title: _('Zapněte dvoufázové ověření', 'Turn on two-factor'), lead: _('Každé nové přihlášení pak potvrdí kód z autentikátoru.', 'Every new sign-in is then confirmed by a code from the authenticator.'), cta: _('Zapnout 2FA →', 'Turn on 2FA →'), on: function () { goSecurity(cmp); } });
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Relace a zařízení', 'Sessions and devices'),
      stats: [H.stat(_('Aktivní relace', 'Active sessions'), String(count), count === 1 ? _('jen tato', 'this one only') : _('včetně této', 'including this one'), 5, Math.min(100, count * 25), 12, count > 3 ? 'warn' : 'ok'), H.stat(_('API klíče', 'API keys'), String(tokens.length), '', 13, Math.min(100, tokens.length * 20), 10, 'ok'), H.stat(_('Dvoufázové ověření', 'Two-factor'), mfaOn() ? _('zapnuto', 'on') : _('vypnuto', 'off'), '', 21, mfaOn() ? 100 : 30, 8, mfaOn() ? 'ok' : 'warn'), H.stat(_('Ochrana', 'Protection'), _('8 pokusů → 15 min', '8 attempts → 15 min'), _('zámek přihlášení', 'sign-in lock'), 29, 60, 6, 'ok')],
      tableTitle: _('Aktivní relace a klíče', 'Active sessions and keys'), tableNote: _('odhlášené zařízení skončí při svém dalším požadavku · změna hesla odhlásí ostatní zařízení', 'a signed-out device ends on its next request · a password change signs the others out'),
      cols: [_('Zařízení', 'Device'), _('Adresa / rozsah', 'Address / scope'), _('Stav', 'State'), _('Aktivita', 'Activity'), ''],
      rows: rows, filters: [], readOnly: true,
      side: { title: _('Doporučení', 'Recommendation'), rows: [{ title: _('Neznámé zařízení? Odhlaste ho a změňte heslo', 'An unknown device? Sign it out and change the password'), meta: _('Nastavení → Zabezpečení a 2FA', 'Settings → Security and 2FA'), value: '', kind: others.length ? 'warn' : 'ok' }, { title: _('Zapnuté 2FA', 'Two-factor on'), meta: mfaOn() ? _('chrání každé nové přihlášení', 'protects every new sign-in') : _('doporučujeme zapnout', 'we recommend turning it on'), value: mfaOn() ? '✓' : '—', kind: mfaOn() ? 'ok' : 'warn' }] },
      advice: advice
    };
  }

  /* ── Team and roles ─────────────────────────────────────────────────────────────────── */
  function teamView(cmp, _, H) {
    var cs = isCs(cmp), s = cmp.state, u = me() || {};
    if (orgId()) load(cmp, 'org', '/organizations/' + encodeURIComponent(orgId()));
    var org = S.org || null, members = (org && org.members) || [];
    /* TASK-0035 (IF-17): the admin controls follow the role the server reports. The old default made a person whose role was
       not reported an owner here; no reported role is no admin — the server refuses either way, the page must not pretend. */
    var myRole = u.member_role || null;
    var canManage = myRole === 'owner' || myRole === 'org_admin';
    var invites = (org && org.invitations) || [];
    var assignable = ROLES.filter(function (r) { return r[0] !== 'owner'; }); // the owner changes only by a transfer
    var removeLabel = _('— odebrat z týmu —', '— remove from the team —');
    var editing = canManage && s.teamEdit ? members.filter(function (m) { return m.user_id === s.teamEdit && m.role !== 'owner' && m.user_id !== u.id; })[0] : null;
    var editForm = editing ? {
      title: _('Upravit přístup: ', 'Edit access: ') + (editing.name || editing.email || '') + (editing.email ? ' · ' + editing.email : ''),
      note: _('nová role platí hned · odebráním skončí i jeho Discord a action hooky', 'a new role applies at once · removal also ends their Discord link and action hooks'),
      fields: [{ key: 'teamRole', label: _('Role', 'Role'), kind: 'select', options: assignable.map(function (r) { return cs ? r[1] : r[2]; }).concat([removeLabel]) }],
      cta: _('Uložit přístup', 'Save access'), hint: _('Úpravu zavřete tlačítkem v řádku člena.', 'Close the edit with the button in the member\'s row.'),
      submit: function () {
        var pick = s.teamRole, base = '/organizations/' + encodeURIComponent(orgId()) + '/members/' + encodeURIComponent(editing.user_id), req;
        if (pick === removeLabel) {
          if (!window.confirm(_('Odebrat ' + (editing.email || editing.name) + ' z týmu? Přístup, Discord a jeho action hooky skončí okamžitě.', 'Remove ' + (editing.email || editing.name) + ' from the team? Access, Discord and their action hooks end at once.'))) return;
          req = A().del(base);
        } else {
          var role = assignable.filter(function (r) { return r[1] === pick || r[2] === pick; })[0];
          if (!role) { flash(cmp, _('Vyberte roli', 'Pick a role'), ''); return; }
          if (role[0] === editing.role) { flash(cmp, _('Role se nemění', 'The role stays'), roleLabel(role[0], cs)); return; }
          req = A().patch(base, { role: role[0] });
        }
        req.then(function () { cmp.setState({ teamEdit: null, teamRole: '' }); flash(cmp, _('Přístup upraven', 'Access updated'), editing.email || ''); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
      }
    } : null;
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Tým a práva', 'Team and roles'),
      stats: [H.stat(_('Členů', 'Members'), String(members.length), '', 3, Math.min(100, members.length * 20), 12, 'ok'), H.stat(_('Vlastníků', 'Owners'), String(members.filter(function (m) { return m.role === 'owner'; }).length), '', 11, 40, 8, 'ok'), H.stat(_('Vaše role', 'Your role'), myRole ? roleLabel(myRole, cs) : '—', '', 19, 100, 6, myRole ? 'ok' : 'warn'), H.stat(_('Rolí k dispozici', 'Roles available'), String(ROLES.length), '', 27, 60, 6, 'ok')],
      form: editForm || (canManage ? {
        title: _('Pozvat člena', 'Invite a member'), note: _('pozvánka platí 7 dní · role určuje rozsah, ne důvěru', 'the invitation is valid 7 days · a role grants scope, not trust'),
        fields: [{ key: 'invEmail', label: 'E-mail', ph: 'kolega@firma.cz', kind: 'text' }, { key: 'invRole', label: _('Role', 'Role'), kind: 'select', options: assignable.map(function (r) { return cs ? r[1] : r[2]; }) }, { key: 'invUntil', label: _('Přístup do (volitelné)', 'Access until (optional)'), ph: 'RRRR-MM-DD', kind: 'text' }],
        cta: _('Poslat pozvánku', 'Send invitation'), hint: _('Pozvaný dostane e-mail s odkazem; do přijetí nemá k účtu přístup.', 'The invitee gets an e-mail link; no access until it is accepted.'),
        submit: function () {
          var email = String(s.invEmail || '').trim(), role = roleKey(s.invRole || (cs ? 'jen čtení' : 'viewer'));
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { flash(cmp, _('Neplatný e-mail', 'Invalid e-mail'), ''); return; }
          var untilText = String(s.invUntil || '').trim(), until = null; // lecturers, contractors: the access ends by itself on that day
          if (untilText !== '') {
            var parsed = /^\d{4}-\d{2}-\d{2}$/.test(untilText) ? new Date(untilText + 'T23:59:59') : null;
            if (!parsed || isNaN(parsed.getTime()) || parsed.getTime() <= Date.now()) { flash(cmp, _('Neplatné datum konce přístupu', 'Invalid end of access'), _('zadejte budoucí den ve tvaru RRRR-MM-DD', 'enter a future day as YYYY-MM-DD')); return; }
            until = parsed.toISOString();
          }
          A().post('/organizations/' + encodeURIComponent(orgId()) + '/invitations', until ? { email: email, role: role, access_until: until } : { email: email, role: role }, A().key()).then(function () { cmp.setState({ invEmail: '', invUntil: '' }); flash(cmp, _('Pozvánka odeslána', 'Invitation sent'), email + ' · ' + roleLabel(role, cs)); reload(cmp, 'org'); }).catch(function (e) { fail(cmp, _, e); });
        }
      } : null),
      tableTitle: _('Členové', 'Members'), tableNote: _('role a stav přístupu', 'role and access state'),
      cols: [_('Člen', 'Member'), _('Role', 'Role'), _('Stav', 'State'), _('Od', 'Since'), ''],
      rows: members.filter(function (m) { return H.match(m.name) || H.match(m.email); }).map(function (m) {
        var self = m.user_id === u.id, owner = m.role === 'owner';
        return {
          name: (m.name || m.email || '—') + (self ? _(' (vy)', ' (you)') : ''), sub: (m.email || '') + (m.access_until ? _(' · přístup do ', ' · access until ') + day(m.access_until, cs) : ''), c2: roleLabel(m.role, cs),
          state: m.state === 'active' ? _('aktivní', 'active') : (m.state || '—'), stateStyle: H.pill(m.state === 'active' ? 'ok' : 'warn'), barStyle: H.bar(owner ? 100 : 60, 'ok'), metric: day(m.joined_at, cs), rowStyle: H.rowStyle,
          action: canManage && !self && !owner ? (editing && editing.user_id === m.user_id ? _('Zavřít úpravu', 'Close edit') : _('Upravit přístup', 'Edit access')) : '', actionCls: 'btn btn-secondary',
          onAction: function () { // a role SELECT in the form above, never a typed key (IF-17: window.prompt took any text, "odebrat" removed without asking)
            if (!canManage || self || owner) return;
            if (editing && editing.user_id === m.user_id) { cmp.setState({ teamEdit: null, teamRole: '' }); return; }
            cmp.setState({ teamEdit: m.user_id, teamRole: roleLabel(m.role, cs) });
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

  window.OnhostPanelAccount = { apiView: apiView, securityView: securityView, accountView: accountView, sessionsView: sessionsView, teamView: teamView, reload: reload, roles: ROLES.filter(function (r) { return ORG_ONLY.indexOf(r[0]) < 0; }), scopes: SCOPES };
})();
