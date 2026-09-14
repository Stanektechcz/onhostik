/* ONhost panel — web & mail toolkit seams (docs/ui/data-seams.md #31).
 *
 * The workbench (onhost-panel-workbench.api.js) shows the tabs the executor offers. This module fills the tabs the
 * prototype has but the core workbench left generic — terminal, PHP settings, Let's Encrypt/wildcard, add-ons hub
 * (staging, git deploy, WordPress, monitoring, CDN, import, Node projects, security, quotas) and the mail extras
 * (catch-all, mailing lists, spam policies, white/blacklists, forwards, fetchmail, mailbox backups, usage,
 * autoresponder/filters) — and enriches the existing ones (backups: download/delete/schedule; cron: edit/run/logs;
 * databases: export/import/remote access; files: upload/rename/copy/chmod/zip). Everything is a feature-gated API
 * call; the surface stays byte-identical. */
(function () {
  if (window.OnhostPanelTools) return;
  var API = window.OnhostApi, WB = window.OnhostPanelWorkbench;
  if (!API || !WB || !WB.TABS) return;

  /* tab id → feature that switches it on (the workbench filters TABS by GET …/features) */
  var EXTRA = {
    web: [['terminal', 'terminal'], ['phpcli', 'php_settings'], ['le', 'ssl'], ['addons', 'monitoring']],
    mail: [['catchall', 'catchall'], ['lists', 'mailing_lists'], ['spam', 'spam'], ['bw', 'spam'], ['relay', 'forwards'], ['fetch', 'fetchmail'], ['bkp', 'mail_backups'], ['mon', 'mail_usage'], ['addons', 'autoresponder']]
  };
  Object.keys(EXTRA).forEach(function (fam) {
    EXTRA[fam].forEach(function (pair) { if (!WB.TABS[fam].some(function (p) { return p[0] === pair[0]; })) WB.TABS[fam].push(pair); });
  });

  var HUB = ['staging', 'deploy', 'wordpress', 'monitoring', 'cdn', 'import', 'node_projects', 'proxy', 'security', 'quotas', 'hooks'];
  var HUB_LABEL = { staging: ['Staging', 'Staging'], deploy: ['Git deploy', 'Git deploy'], wordpress: ['WordPress', 'WordPress'], monitoring: ['Monitoring', 'Monitoring'], cdn: ['CDN', 'CDN'], import: ['Import webu', 'Site import'], node_projects: ['Node.js', 'Node.js'], proxy: ['Reverzní proxy a index', 'Reverse proxy and index'], security: ['Zabezpečení', 'Security'], quotas: ['Kvóty', 'Quotas'], hooks: ['Akční webhooky', 'Action hooks'] };
  var HOOK_LABELS = { backup: ['Záloha', 'Backup'], 'deploy.run': ['Deploy', 'Deploy'], 'staging.refresh': ['Obnovit staging', 'Refresh staging'], 'staging.push': ['Staging → produkce', 'Staging → production'], 'wp.update': ['Aktualizovat WordPress', 'Update WordPress'], 'wp.cache': ['Redis cache', 'Redis cache'], 'cdn.purge': ['Vyprázdnit CDN', 'Purge CDN'], 'cron.run': ['Spustit cron', 'Run cron'], 'ssl.issue': ['Vystavit certifikát', 'Issue certificate'], 'https.force': ['Vynutit HTTPS', 'Force HTTPS'], power: ['Restart serveru', 'Restart server'] };

  /* ── plumbing ─────────────────────────────────────────────────────────── */
  function ctxOf(cmp, sel, tab, _, X) {
    var f = X.features(cmp, sel);
    if (!f || f.__error) return null;
    var H = cmp.WB_H(sel);
    return { cmp: cmp, sel: sel, tab: tab, _: _, X: X, f: f, H: H, s: H.s, cell: H.cell, A: H.act, F: H.F,
      on: function (k) { return !!(f.features[k] && f.features[k].enabled); },
      limit: function (k) { return f.features[k] ? f.features[k].limit : null; },
      opt: function (k) { return f.features[k] ? f.features[k].options : null; } };
  }
  function res(ctx, kind, query, fresh) {
    var key = ctx.sel.id + ':' + kind + (query ? '?' + query : ''), r = ctx.X.state.resources[key];
    if (r === undefined || fresh) {
      if (r === undefined) ctx.X.state.resources[key] = null;
      ctx.X.load('t:' + key + (fresh ? ':fresh' : ''), '/services/' + ctx.sel.id + '/resources/' + kind + '?' + (query ? query + '&' : '') + (fresh ? 'fresh=1' : '_=1'), ctx.cmp, function (d) { ctx.X.state.resources[key] = d; });
    }
    return r === undefined ? null : r;
  }
  function get(ctx, name, path, fresh) {
    var key = ctx.sel.id + ':' + name, r = ctx.X.state.resources[key];
    if (r === undefined || fresh) {
      if (r === undefined) ctx.X.state.resources[key] = null;
      ctx.X.load('g:' + key + (fresh ? ':fresh' : ''), path, ctx.cmp, function (d) { ctx.X.state.resources[key] = d; });
    }
    return r === undefined ? null : r;
  }
  function drop(ctx, kinds) {
    (kinds || []).forEach(function (k) { Object.keys(ctx.X.state.resources).forEach(function (key) { if (key.indexOf(ctx.sel.id + ':' + k) === 0) delete ctx.X.state.resources[key]; }); });
    ctx.X.rerender(ctx.cmp);
  }
  function later(ctx, kinds) { [2500, 8000, 20000].forEach(function (ms) { setTimeout(function () { drop(ctx, kinds); }, ms); }); }
  function act(ctx, action, params, kinds, okT, okB) {
    return ctx.X.act(ctx.cmp, ctx.sel, action, params, [], okT, okB).then(function (d) { later(ctx, kinds); return d; });
  }
  /* wait for one operation to finish (terminal output, exports, logs) */
  function awaitOp(ctx, opId, done, tries) {
    tries = tries || 0;
    API.get('/services/' + ctx.sel.id + '/operations?limit=30').then(function (r) {
      var list = Array.isArray(r) ? r : (r.data || []);
      var op = list.filter(function (o) { return o.id === opId; })[0];
      if (op && /^(SUCCEEDED|FAILED|CANCELLED)$/.test(op.state)) return done(op);
      if (tries >= 80) return done(null);
      setTimeout(function () { awaitOp(ctx, opId, done, tries + 1); }, tries < 6 ? 1500 : 3000);
    }).catch(function () { done(null); });
  }
  function pick(accept, cb) {
    var input = document.createElement('input');
    input.type = 'file';
    if (accept) input.accept = accept;
    input.style.display = 'none';
    document.body.appendChild(input);
    input.addEventListener('change', function () { var file = input.files && input.files[0]; document.body.removeChild(input); if (file) cb(file); });
    input.click();
  }
  function upload(ctx, path, file, extra) {
    var form = new FormData();
    form.append('file', file, file.name);
    Object.keys(extra || {}).forEach(function (k) { form.append(k, extra[k]); });
    ctx.X.flash(ctx.cmp, ctx._('Nahrávám ', 'Uploading ') + file.name, ctx._('Velké soubory mohou trvat minuty.', 'Large files can take minutes.'));
    return API.upload('/services/' + ctx.sel.id + path, form).then(function (r) { return r.data || r; });
  }
  function bytes(n) { if (n == null) return '—'; if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB'; if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB'; return Math.max(1, Math.round(n / 1024)) + ' kB'; }
  function yesno(ctx, v) { return v ? ctx._('zapnuto', 'on') : ctx._('vypnuto', 'off'); }
  function loadingRow(ctx, msg) { return [{ cells: [ctx.cell(msg || ctx._('načítám…', 'loading…'), '1 1 300px')], note: '' }]; }
  function errRow(ctx, e) { return [{ cells: [ctx.cell(ctx._('Nelze načíst: ', 'Cannot load: ') + e, '1 1 300px')], note: '' }]; }
  function info(ctx, key, title, note, pairs, extra) {
    return { key: key, title: title, note: note, state: '', head: [ctx.cell(ctx._('Parametr', 'Parameter'), '1 1 220px'), ctx.cell(ctx._('Hodnota', 'Value'), '1 1 260px', 1)],
      rows: pairs.map(function (p) { return { cells: [ctx.cell(p[0], '1 1 220px'), ctx.cell(p[1] == null || p[1] === '' ? '—' : String(p[1]), '1 1 260px', 1)], note: p[2] || '', actions: p[3] || [] }; }), extra: extra || [] };
  }
  function table(ctx, key, title, note, data, head, mapRow, form, extra, kind) {
    var rows = data === null ? loadingRow(ctx) : (data && data.__error ? errRow(ctx, data.__error) : (Array.isArray(data) ? data : []).map(mapRow));
    if (Array.isArray(data) && !data.length) rows = loadingRow(ctx, ctx._('zatím nic', 'nothing yet'));
    return { key: key, title: title, note: note, state: Array.isArray(data) ? data.length + (ctx.limit(kind || '') ? ' / ' + ctx.limit(kind) : '') : '', head: head, rows: rows, form: form || null, extra: (extra || []).concat(kind ? [{ label: ctx._('Obnovit', 'Refresh'), on: function () { drop(ctx, [kind]); } }] : []) };
  }
  function refreshBtn(ctx, kinds) { return { label: ctx._('Obnovit', 'Refresh'), on: function () { drop(ctx, kinds); } }; }
  function clearForm(ctx) { ctx.cmp.setState({ wbF: { a: '', b: '', c: '' } }); }
  function v(ctx, k) { return String(ctx.s.wbF[k] || '').trim(); }
  function mailboxChips(ctx, stateKey) {
    var boxes = res(ctx, 'mailboxes');
    var list = Array.isArray(boxes) ? boxes : [];
    var current = ctx.s[stateKey] || (list[0] && list[0].remote_id) || '';
    return { current: current, box: list.filter(function (b) { return String(b.remote_id) === String(current); })[0] || null, chips: list.map(function (b) { var p = {}; p[stateKey] = b.remote_id; return { label: b.address, active: String(b.remote_id) === String(current), on: function () { ctx.cmp.setState(p); } }; }) };
  }

  /* ── entry point ──────────────────────────────────────────────────────── */
  function enhance(cmp, sel, tab, _, core, X) {
    if (tab === 'plan') { var pc = ctxOf(cmp, sel, tab, _, X); if (pc) return planInfo(pc); } // game servers: the prototype's plan tab shows the real plans
    var fam = X.family(sel);
    var builders = fam === 'web' ? WEB : (fam === 'mail' ? MAIL : null);
    if (!builders || !builders[tab]) return core;
    var ctx = ctxOf(cmp, sel, tab, _, X);
    if (!ctx) return core;
    var out = builders[tab](ctx, core);
    return out || core;
  }

  /* ════════════════════════════════ WEB ═══════════════════════════════════ */
  var WEB = {};

  /* Terminal: one command = one audited operation; output comes back through the operation result. */
  WEB.terminal = function (ctx) {
    var _ = ctx._, sel = ctx.sel, cmp = ctx.cmp;
    if (!ctx.on('terminal')) return null;
    var tools = res(ctx, 'tools');
    var term = ctx.X.state.term = ctx.X.state.term || {};
    var t = term[sel.id] = term[sel.id] || { lines: [], cwd: '', running: false };
    var push = function (m, l, src) { t.lines.push({ t: '', src: src || '', m: m, l: l || 'ok' }); if (t.lines.length > 600) t.lines.splice(0, t.lines.length - 600); };
    var run = function (cmd) {
      cmd = String(cmd || '').trim();
      if (!cmd || t.running) return;
      var m = cmd.match(/^cd(?:\s+(\S+))?$/);
      if (m) { var to = m[1] || ''; t.cwd = to === '' || to === '/' || to === '~' ? '' : (to.indexOf('/') === 0 ? to.replace(/^\/+/, '') : (t.cwd ? t.cwd + '/' : '') + to).split('/').reduce(function (acc, p) { if (p === '..') acc.pop(); else if (p && p !== '.') acc.push(p); return acc; }, []).join('/'); push('$ ' + cmd, 'ok'); push('cwd: /' + t.cwd, 'ok'); clearForm(ctx); ctx.X.rerender(cmp); return; }
      push('$ ' + cmd, 'ok', '$');
      t.running = true;
      ctx.X.rerender(cmp);
      API.post('/services/' + sel.id + '/actions', { action: 'command.run', params: { command: cmd, cwd: t.cwd, timeout: 120 } }, API.key()).then(function (r) {
        var d = r.data || r;
        clearForm(ctx);
        awaitOp(ctx, d.operation_id, function (op) {
          t.running = false;
          if (!op) { push(_('(výsledek nedorazil včas — zkuste to znovu)', '(the result did not arrive in time — try again)'), 'err'); }
          else if (op.state !== 'SUCCEEDED') { push((op.error && op.error.message) || _('příkaz selhal', 'the command failed'), 'err'); }
          else {
            var rs = op.result || {};
            if (rs.output == null && rs.exit_code == null) push(_('(terminál byl právě připraven — spusťte příkaz znovu)', '(the terminal was just prepared — run the command again)'), 'ok');
            else { String(rs.output || '').split('\n').slice(0, 400).forEach(function (line) { if (line !== '') push(line, rs.exit_code === 0 ? 'ok' : 'err'); }); push('exit ' + rs.exit_code + ' · ' + (rs.duration_ms || 0) + ' ms' + (rs.timed_out ? ' · timeout' : ''), rs.exit_code === 0 ? 'ok' : 'err'); }
          }
          ctx.X.rerender(cmp);
        });
      }).catch(function (e) { t.running = false; push((e && e.message) || 'error', 'err'); ctx.X.rerender(cmp); });
    };
    var paths = tools && tools.paths ? Object.keys(tools.paths).filter(function (k) { return tools.paths[k]; }) : [];
    var quick = ['ls -la', 'php -v', 'wp plugin list', 'composer install --no-dev', 'git status', 'du -sh .'];
    return { key: 'real:terminal', title: _('Terminál · ', 'Terminal · ') + sel.name,
      note: tools === null ? _('zjišťuji nástroje na serveru…', 'checking the tools on the server…') : (tools && tools.__error ? tools.__error : _('příkazy běží pod účtem webu ', 'commands run under the site user ') + (tools.user || '') + _(' v /', ' in /') + t.cwd + (paths.length ? ' · ' + _('k dispozici: ', 'available: ') + paths.join(', ') : '') + _(' · bez sudo, démonů a interaktivních programů', ' · no sudo, daemons or interactive programs')),
      state: t.running ? _('běží…', 'running…') : (tools && tools.shell === false ? _('připravuje se', 'preparing') : _('připraven', 'ready')), console: true, conStatus: t.running ? 'running' : 'ready', conMeta: tools && tools.document_root ? tools.document_root : sel.name, conPrompt: '$',
      consoleLines: t.lines.length ? t.lines.slice() : [{ t: '', src: '', m: _('Zadejte příkaz — např. wp core version, composer install, git pull.', 'Enter a command — e.g. wp core version, composer install, git pull.'), l: 'ok' }],
      form: { fields: [{ ph: _('příkaz (Enter spustí)', 'command (Enter runs it)') }], on: function () { run(ctx.s.wbF.a); } },
      chips: quick.map(function (q) { return { label: q, active: false, on: function () { run(q); } }; }), chipsLabel: _('Rychlé příkazy', 'Quick commands'),
      extra: [{ label: _('Vyčistit', 'Clear'), on: function () { t.lines = []; ctx.X.rerender(cmp); } }, { label: _('Kořen webu', 'Site root'), on: function () { t.cwd = ''; ctx.X.rerender(cmp); } }, refreshBtn(ctx, ['tools'])] };
  };

  /* PHP settings + extensions (php.ini keys the plan allows, applied per site). */
  WEB.phpcli = function (ctx) {
    var _ = ctx._;
    if (!ctx.on('php_settings')) return null;
    var p = res(ctx, 'php_settings');
    var settings = p && p.settings ? p.settings : {}, editable = p && p.editable ? p.editable : [];
    var rows = p === null ? [] : editable.map(function (k) {
      return [k, settings[k] == null || settings[k] === '' ? _('(výchozí)', '(default)') : settings[k], '', [ctx.A(_('Změnit', 'Change'), function () {
        var val = window.prompt(_('Nová hodnota pro ' + k + ' (např. 256M, 60, On/Off, Europe/Prague):', 'New value for ' + k + ' (e.g. 256M, 60, On/Off, Europe/Prague):'), settings[k] || '');
        if (val === null) return;
        var s = {}; s[k] = String(val).trim();
        act(ctx, 'php.settings', { settings: s }, ['php_settings'], _('Nastavení PHP uloženo', 'PHP setting saved'), _('Platí do minuty pro celý web.', 'Applies within a minute to the whole site.'));
      })]];
    });
    if (p && !p.__error) {
      rows.unshift([_('Verze PHP', 'PHP version'), p.version || '—', _('verzi přepnete v záložce PHP', 'switch the version under PHP')]);
      rows.push([_('open_basedir', 'open_basedir'), p.open_basedir == null ? '—' : yesno(ctx, p.open_basedir), _('web smí číst jen vlastní složky', 'the site may read only its own folders')]);
      rows.push([_('Rozšíření', 'Extensions'), (p.extensions || []).join(', ') || '—', _('instalace dalších rozšíření: napište podpoře', 'more extensions: write to support')]);
    }
    var panel = info(ctx, 'real:phpini', _('Nastavení PHP · ', 'PHP settings · ') + ctx.sel.name, _('limity paměti, uploadů a času běhu skriptů; maximum paměti hlídá tarif', 'memory, upload and execution-time limits; the plan caps the memory limit'), rows.length ? rows : (p && p.__error ? [[_('Chyba', 'Error'), p.__error]] : [[_('Stav', 'State'), _('načítám…', 'loading…')]]), [refreshBtn(ctx, ['php_settings'])]);
    panel.form = { title: _('Nastavit hodnotu', 'Set a value'), fields: [ctx.F('a', _('klíč (např. memory_limit)', 'key (e.g. memory_limit)'), '0 0 220px'), ctx.F('b', _('hodnota', 'value'), '0 0 200px')], submit: _('Uložit', 'Save'), on: function () {
      var k = v(ctx, 'a'), val = v(ctx, 'b');
      if (!k) { ctx.X.flash(ctx.cmp, _('Zadejte klíč', 'Enter a key'), ''); return; }
      var s = {}; s[k] = val;
      act(ctx, 'php.settings', { settings: s }, ['php_settings'], _('Nastavení PHP uloženo', 'PHP setting saved'), '');
    } };
    return panel;
  };

  /* HTTP/3 and HSTS controls shared by the SSL tabs. */
  function httpExtras(ctx, panel) {
    var _ = ctx._;
    if (ctx.on('http3')) {
      var hv = res(ctx, 'http_versions');
      panel.rows.push({ cells: [ctx.cell('HTTP/2 · HTTP/3', '1 1 220px'), ctx.cell(hv === null ? _('načítám…', 'loading…') : (hv.__error ? hv.__error : 'HTTP/2 ' + yesno(ctx, hv.http2) + ' · HTTP/3 ' + (hv.http3_available ? yesno(ctx, hv.http3) : _('server nepodporuje', 'not supported by the server'))), '1 1 260px', 1)], note: '', actions: hv && hv.http3_available ? [ctx.A(hv.http3 ? _('Vypnout HTTP/3', 'Turn HTTP/3 off') : _('Zapnout HTTP/3', 'Turn HTTP/3 on'), function () { act(ctx, 'http3.set', { enabled: !hv.http3 }, ['http_versions'], 'HTTP/3', _('Změna platí do minuty.', 'Applies within a minute.')); })] : [] });
    }
    if (ctx.on('hsts')) {
      var sec = res(ctx, 'security');
      panel.rows.push({ cells: [ctx.cell('HSTS', '1 1 220px'), ctx.cell(sec === null ? _('načítám…', 'loading…') : (sec.__error ? sec.__error : yesno(ctx, sec.hsts) + (sec.hsts ? ' · max-age 1 rok, includeSubDomains' : '')), '1 1 260px', 1)], note: _('prohlížeče si zapamatují, že web běží jen na HTTPS', 'browsers remember the site runs on HTTPS only'), actions: sec && !sec.__error ? [ctx.A(sec.hsts ? _('Vypnout HSTS', 'Turn HSTS off') : _('Zapnout HSTS', 'Turn HSTS on'), function () { var rules = Object.assign({}, sec, { hsts: !sec.hsts }); delete rules.desired; delete rules.supports; act(ctx, 'security.set', { rules: rules }, ['security'], 'HSTS', ''); })] : [] });
    }
    return panel;
  }
  WEB.ssl = function (ctx, core) { return core && core.rows ? httpExtras(ctx, core) : core; };

  /* Let's Encrypt tab: platform-issued wildcard certificates (DNS-01 through our DNS) + HTTP/3 + HSTS. */
  WEB.le = function (ctx, core) {
    var _ = ctx._;
    if (!ctx.on('ssl_wildcard')) return WEB.ssl(ctx, core);
    var c = res(ctx, 'certificates');
    var rows = c === null ? loadingRow(ctx) : (c.__error ? errRow(ctx, c.__error) : (c.certificates || []).map(function (x) {
      var st = { pending: ['objednáno', 'ordered'], validating: ['ověřujeme DNS', 'validating DNS'], issued: ['vystaven', 'issued'], failed: ['selhalo', 'failed'] }[x.state] || [x.state, x.state];
      return { cells: [ctx.cell((x.domains || []).join(', '), '1 1 240px', 1), ctx.cell(_(st[0], st[1]), '0 0 130px'), ctx.cell(x.expires_at ? String(x.expires_at).slice(0, 10) + (x.days_left != null ? ' · ' + x.days_left + _(' dní', ' days') : '') : '—', '0 0 160px', 1)], note: x.last_error || (x.issuer ? x.issuer : ''), actions: [] };
    }));
    if (c && !c.__error && !(c.certificates || []).length) rows = loadingRow(ctx, _('zatím žádný wildcard certifikát', 'no wildcard certificate yet'));
    var panel = { key: 'real:wildcard', title: _("Let's Encrypt a wildcard · ", "Let's Encrypt and wildcard · ") + ctx.sel.name,
      note: _('certifikát pro doménu i všechny subdomény (*.' + (c && c.domain ? c.domain : ctx.sel.name) + '); ověřuje se DNS záznamem v naší DNS a obnovuje 30 dní před expirací', 'a certificate for the domain and every subdomain (*.' + (c && c.domain ? c.domain : ctx.sel.name) + '); validated by a DNS record in our DNS and renewed 30 days before expiry') + (c && c.dns_managed === false ? ' · ' + _('DNS domény není u ONhost — wildcard vystavit nejde', 'the DNS is not at ONhost — no wildcard possible') : ''),
      state: c && c.certificates ? c.certificates.length + ' ' + _('certifikátů', 'certificates') : '', head: [ctx.cell(_('Domény', 'Domains'), '1 1 240px'), ctx.cell(_('Stav', 'State'), '0 0 130px'), ctx.cell(_('Platí do', 'Valid until'), '0 0 160px')], rows: rows,
      extra: [{ label: _('Vystavit wildcard certifikát', 'Issue a wildcard certificate'), primary: true, on: function () {
        if (c && c.dns_managed === false) { ctx.X.flash(ctx.cmp, _('DNS není u ONhost', 'DNS is not at ONhost'), _('Wildcard certifikát ověřujeme DNS záznamem; přesuňte DNS domény k nám v sekci Domény.', 'Wildcard certificates are validated by a DNS record; move the domain DNS to us under Domains.')); return; }
        if (window.confirm(_('Vystavit certifikát pro ' + (c && c.domain ? c.domain : ctx.sel.name) + ' a *.' + (c && c.domain ? c.domain : ctx.sel.name) + '?', 'Issue a certificate for ' + (c && c.domain ? c.domain : ctx.sel.name) + ' and its subdomains?'))) act(ctx, 'ssl.wildcard', {}, ['certificates', 'certificate'], _('Vystavujeme certifikát', 'Issuing the certificate'), _('Ověření DNS a instalace trvá 2–5 minut.', 'DNS validation and installation take 2–5 minutes.'));
      } }, refreshBtn(ctx, ['certificates'])] };
    return httpExtras(ctx, panel);
  };

  /* Backups: download, delete and the schedule on top of the core list. */
  WEB.bkp = function (ctx, core) {
    var _ = ctx._, sel = ctx.sel;
    if (!core || !core.rows) return core;
    var list = ctx.X.state.resources[sel.id + ':backups'];
    if (Array.isArray(list)) {
      list.forEach(function (x, i) {
        var row = core.rows[i];
        if (!row || !row.cells) return;
        row.actions = row.actions || [];
        if (ctx.on('backup_download') && x.state === 'completed') row.actions.push(ctx.A(_('Stáhnout', 'Download'), function () { window.open(API.base + '/services/' + sel.id + '/backups/' + x.id + '/download', '_blank', 'noopener'); }));
        if (ctx.on('backup_delete') && x.state === 'completed' && !x.protected) row.actions.push(ctx.A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat tuto zálohu?', 'Delete this backup?'))) act(ctx, 'backup.delete', { remote_id: x.remote_id || x.id }, [], _('Záloha smazána', 'Backup deleted'), '').then(function () { delete ctx.X.state.resources[sel.id + ':backups']; setTimeout(function () { delete ctx.X.state.resources[sel.id + ':backups']; ctx.X.rerender(ctx.cmp); }, 4000); }); }));
        if (x.kind) row.note = (row.note ? row.note + ' · ' : '') + { manual: _('ruční', 'manual'), scheduled: _('plánovaná', 'scheduled'), 'pre-push': _('před přenosem stagingu', 'before staging push'), final: _('závěrečná', 'final') }[x.kind] || x.kind;
      });
    }
    var sch = ctx.opt('backup_schedule');
    if (ctx.on('backup_schedule') && sch) {
      core.note = core.note + ' · ' + _('plán: ', 'schedule: ') + ({ '15m': _('každých 15 min', 'every 15 min'), hourly: _('každou hodinu', 'hourly'), '6h': _('každých 6 h', 'every 6 h'), daily: _('denně', 'daily'), weekly: _('týdně', 'weekly') }[sch.frequency] || sch.frequency) + ', ' + sch.days + _(' dní', ' days') + ', ' + sch.generations + _(' generací', ' generations');
      core.form = { title: _('Plán záloh (v mezích tarifu)', 'Backup schedule (within the plan)'), fields: [ctx.F('a', _('frekvence: 15m | hourly | 6h | daily | weekly', 'frequency: 15m | hourly | 6h | daily | weekly'), '0 0 260px'), ctx.F('b', _('uchovat dní', 'keep days'), '0 0 120px'), ctx.F('c', _('generací', 'generations'), '0 0 120px')], submit: _('Uložit plán', 'Save schedule'), on: function () {
        var body = {};
        if (v(ctx, 'a')) body.frequency = v(ctx, 'a');
        if (v(ctx, 'b')) body.days = parseInt(v(ctx, 'b'), 10);
        if (v(ctx, 'c')) body.generations = parseInt(v(ctx, 'c'), 10);
        API.put('/services/' + sel.id + '/backups/schedule', body).then(function () { ctx.X.flash(ctx.cmp, _('Plán záloh uložen', 'Backup schedule saved'), ''); clearForm(ctx); delete ctx.X.state.features[sel.id]; ctx.X.rerender(ctx.cmp); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Plán se neuložil', 'Schedule not saved'), (e && e.message) || ''); });
      } };
    }
    return core;
  };

  /* Cron: edit, run now, logs, enable/disable. */
  WEB.cron = function (ctx, core) {
    var _ = ctx._, sel = ctx.sel;
    if (!core || !core.rows || !(ctx.on('cron_edit') || ctx.on('cron_logs'))) return core;
    if (ctx.s.wbCronLog) {
      var logs = res(ctx, 'cron_logs', 'remote_id=' + encodeURIComponent(ctx.s.wbCronLog));
      var lines = Array.isArray(logs) ? logs : (logs && logs.lines ? logs.lines : []);
      return { key: 'real:cronlog', title: _('Log úlohy · ', 'Job log · ') + sel.name, note: _('posledních 200 řádků výstupu úlohy', 'the last 200 lines of the job output'), state: logs === null ? _('načítám', 'loading') : lines.length + _(' řádků', ' lines'), console: true, conStatus: logs === null ? 'loading' : 'loaded', conMeta: sel.name, conPrompt: '', consoleLines: (logs && logs.__error ? [{ t: '', src: '', m: logs.__error, l: 'err' }] : lines.map(function (l) { return { t: '', src: '', m: String(l), l: /error|fatal|failed/i.test(String(l)) ? 'err' : 'ok' }; })).slice().reverse(), form: null,
        extra: [{ label: _('Zpět na úlohy', 'Back to jobs'), primary: true, on: function () { ctx.cmp.setState({ wbCronLog: null }); } }, { label: _('Obnovit', 'Refresh'), on: function () { drop(ctx, ['cron_logs']); } }] };
    }
    var list = res(ctx, 'cron');
    if (Array.isArray(list)) {
      list.forEach(function (d, i) {
        var row = core.rows[i];
        if (!row || !row.cells) return;
        row.actions = row.actions || [];
        if (ctx.on('cron_edit')) {
          row.actions.unshift(ctx.A(_('Upravit', 'Edit'), function () { ctx.cmp.setState({ wbCronEdit: d.remote_id, wbF: { a: d.schedule || '', b: d.command || '', c: d.label || '' } }); }));
          row.actions.push(ctx.A(_('Spustit teď', 'Run now'), function () { act(ctx, 'cron.run', { remote_id: d.remote_id }, ['cron_logs'], _('Úloha spuštěna', 'Job started'), _('Výstup najdete v logu úlohy.', 'The output is in the job log.')); }));
          row.actions.push(ctx.A(d.active === false ? _('Zapnout', 'Enable') : _('Vypnout', 'Disable'), function () { act(ctx, 'cron.update', { remote_id: d.remote_id, active: d.active === false }, ['cron'], _('Úloha upravena', 'Job updated'), ''); }));
        }
        if (ctx.on('cron_logs')) row.actions.push(ctx.A(_('Log', 'Log'), function () { ctx.cmp.setState({ wbCronLog: d.remote_id }); }));
      });
    }
    if (ctx.on('cron_edit') && ctx.s.wbCronEdit) {
      var id = ctx.s.wbCronEdit;
      core.form = { title: _('Upravit úlohu', 'Edit the job'), fields: [ctx.F('a', '0 3 * * *', '0 0 130px'), ctx.F('b', _('příkaz', 'command'), '1 1 300px'), ctx.F('c', _('popisek', 'label'), '0 0 140px')], submit: _('Uložit změny', 'Save changes'), on: function () {
        act(ctx, 'cron.update', { remote_id: id, schedule: v(ctx, 'a'), command: v(ctx, 'b'), label: v(ctx, 'c') }, ['cron'], _('Úloha upravena', 'Job updated'), '').then(function () { ctx.cmp.setState({ wbCronEdit: null }); });
      } };
      core.extra = (core.extra || []).concat([{ label: _('Zrušit úpravu', 'Cancel editing'), on: function () { ctx.cmp.setState({ wbCronEdit: null, wbF: { a: '', b: '', c: '' } }); } }]);
    }
    return core;
  };

  /* Databases: export (download through the API), import (upload an SQL dump), remote access. */
  WEB.dbs = function (ctx, core) {
    var _ = ctx._, sel = ctx.sel;
    if (!core || !core.rows) return core;
    var list = res(ctx, 'databases');
    if (!Array.isArray(list)) return core;
    list.forEach(function (d, i) {
      var row = core.rows[i];
      if (!row || !row.cells) return;
      row.actions = row.actions || [];
      if (ctx.on('db_export')) {
        row.actions.unshift(ctx.A(_('Import SQL', 'Import SQL'), function () {
          pick('.sql,.gz,.zip', function (file) {
            upload(ctx, '/uploads', file).then(function (u) { return act(ctx, 'database.import', { remote_id: d.remote_id, upload_id: u.upload_id }, ['databases'], _('Import běží', 'Import running'), _('Data se vkládají do ' + d.name + '; větší dumpy trvají minuty.', 'The data goes into ' + d.name + '; larger dumps take minutes.')); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nahrání selhalo', 'Upload failed'), (e && e.message) || ''); });
          });
        }));
        row.actions.unshift(ctx.A(_('Export', 'Export'), function () {
          API.post('/services/' + sel.id + '/actions', { action: 'database.export', params: { remote_id: d.remote_id } }, API.key()).then(function (r) {
            var op = (r.data || r).operation_id;
            ctx.X.flash(ctx.cmp, _('Připravuji export', 'Preparing the export'), _('Stažení začne automaticky, jakmile je dump hotový.', 'The download starts as soon as the dump is ready.'));
            awaitOp(ctx, op, function (o) {
              if (o && o.state === 'SUCCEEDED' && o.result && o.result.download_token) window.open(API.base + '/services/' + sel.id + '/downloads/' + o.result.download_token, '_blank', 'noopener');
              else ctx.X.flash(ctx.cmp, _('Export se nezdařil', 'Export failed'), (o && o.error && o.error.message) || _('Zkuste to znovu.', 'Try again.'));
            });
          }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Export se nezdařil', 'Export failed'), (e && e.message) || ''); });
        }));
      }
      if (ctx.on('db_access')) row.actions.push(ctx.A(_('Vzdálený přístup', 'Remote access'), function () {
        var ips = window.prompt(_('Povolit připojení zvenčí k ' + d.name + '? Zadejte IP adresy oddělené čárkou (prázdné = odkudkoli, "off" = vypnout):', 'Allow outside connections to ' + d.name + '? Enter IP addresses separated by commas (empty = anywhere, "off" = disable):'), '');
        if (ips === null) return;
        var off = ips.trim().toLowerCase() === 'off';
        act(ctx, 'database.access', { remote_id: d.remote_id, remote: !off, hosts: off ? [] : ips.split(/[\s,;]+/).filter(Boolean) }, ['database_access'], _('Vzdálený přístup nastaven', 'Remote access set'), off ? _('Databáze přijímá jen místní připojení.', 'The database accepts local connections only.') : _('Připojte se na adresu webu, port 3306.', 'Connect to the site address, port 3306.'));
      }));
    });
    return core;
  };

  /* File manager: upload, rename, copy, chmod, zip/unzip on top of the core listing. */
  WEB.files = function (ctx, core) {
    var _ = ctx._, sel = ctx.sel;
    if (!core || !core.rows || !ctx.on('files_advanced')) return core;
    var dir = String(ctx.s.wbDir || '').replace(/^\/+|\/+$/g, '');
    var listing = ctx.X.state.resources[sel.id + ':files:' + dir];
    var refresh = function () { delete ctx.X.state.resources[sel.id + ':files:' + dir]; ctx.X.rerender(ctx.cmp); [2500, 8000].forEach(function (ms) { setTimeout(function () { delete ctx.X.state.resources[sel.id + ':files:' + dir]; ctx.X.rerender(ctx.cmp); }, ms); }); };
    var byName = {};
    core.rows.forEach(function (row) { if (row.cells && row.cells[0]) byName[row.cells[0].t] = row; });
    (listing && listing.entries ? listing.entries : []).forEach(function (e) {
      var row = byName[e.name];
      if (!row) return;
      var path = (dir ? dir + '/' : '') + e.name;
      row.actions = row.actions || [];
      row.actions.push(ctx.A(_('Přejmenovat', 'Rename'), function () { var to = window.prompt(_('Nový název:', 'New name:'), e.name); if (!to || to === e.name) return; act(ctx, 'file.rename', { from: path, to: (dir ? dir + '/' : '') + to }, [], _('Přejmenováno', 'Renamed'), '').then(refresh); }));
      row.actions.push(ctx.A(_('Kopírovat', 'Copy'), function () { var to = window.prompt(_('Cílová cesta kopie:', 'Target path of the copy:'), path + (e.type === 'dir' ? '-copy' : '.bak')); if (!to) return; act(ctx, 'file.copy', { from: path, to: to }, [], _('Zkopírováno', 'Copied'), '').then(refresh); }));
      row.actions.push(ctx.A(_('Práva', 'Permissions'), function () { var mode = window.prompt(_('Práva (např. 644 pro soubory, 755 pro složky):', 'Permissions (e.g. 644 for files, 755 for folders):'), e.type === 'dir' ? '755' : '644'); if (!mode) return; act(ctx, 'file.chmod', { path: path, mode: mode.trim() }, [], _('Práva nastavena', 'Permissions set'), '').then(refresh); }));
      if (/\.(zip|tar\.gz|tgz)$/i.test(e.name)) row.actions.push(ctx.A(_('Rozbalit', 'Extract'), function () { act(ctx, 'file.extract', { path: path, target: dir }, [], _('Rozbaluji', 'Extracting'), '').then(refresh); }));
      else row.actions.push(ctx.A(_('Zabalit (zip)', 'Zip'), function () { act(ctx, 'file.archive', { paths: [path], target: path.replace(/\/+$/, '') + '.zip' }, [], _('Balím', 'Zipping'), '').then(refresh); }));
    });
    core.extra = [{ label: _('Nahrát soubor', 'Upload a file'), primary: true, on: function () {
      pick('', function (file) { upload(ctx, '/files/upload', file, { path: dir }).then(function () { ctx.X.flash(ctx.cmp, _('Nahráno', 'Uploaded'), file.name); refresh(); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nahrání selhalo', 'Upload failed'), (e && e.message) || ''); }); });
    } }].concat(core.extra || []);
    return core;
  };

  /* Quotas next to usage. */
  WEB.quota = function (ctx, core) {
    var _ = ctx._;
    if (!core || !core.rows || !ctx.on('quotas')) return core;
    core.rows = core.rows.filter(function (r) { return !(r.cells && r.cells[0] && /^node /.test(String(r.cells[0].t))); }); // server-level metrics are not the customer's
    var q = res(ctx, 'quotas');
    var cell = function (t, mono) { return { t: t, style: 'flex:1 1 200px;min-width:0;font-size:13px;' + (mono ? 'font-family:ui-monospace,Menlo,monospace;font-size:12px' : '') }; };
    if (q && !q.__error) {
      [[_('Obsazený prostor', 'Disk used'), bytes(q.disk_used_bytes) + (q.disk_limit_bytes ? ' / ' + bytes(q.disk_limit_bytes) : '')], [_('Přenos tento měsíc', 'Traffic this month'), bytes(q.traffic_used_bytes) + (q.traffic_limit_bytes ? ' / ' + bytes(q.traffic_limit_bytes) : '')], [_('Počet souborů', 'Files'), q.inodes_used == null ? '—' : String(q.inodes_used)], [_('Změřeno', 'Measured'), ctx.X.since(ctx.cmp, q.measured_at) || '—']].forEach(function (p) { core.rows.push({ cells: [cell(p[0]), cell(p[1], 1)], note: '' }); });
    }
    core.extra = (core.extra || []).concat([refreshBtn(ctx, ['quotas', 'usage'])]);
    return core;
  };

  /* Add-ons hub: staging, git deploy, WordPress, monitoring, CDN, import, Node.js, security, quotas. */
  WEB.addons = function (ctx) {
    var _ = ctx._;
    var avail = HUB.filter(function (k) { return k === 'hooks' ? (ctx.f.actions || []).some(function (a) { return HOOK_LABELS[a]; }) : ctx.on(k); });
    if (!avail.length) return null;
    var current = avail.indexOf(ctx.s.wbTool) >= 0 ? ctx.s.wbTool : avail[0];
    var panel = SECTION[current](ctx) || info(ctx, 'real:hub', ctx.sel.name, '', []);
    panel.chips = avail.map(function (k) { return { label: _(HUB_LABEL[k][0], HUB_LABEL[k][1]), active: k === current, on: function () { ctx.cmp.setState({ wbTool: k, wbF: { a: '', b: '', c: '' } }); } }; });
    panel.chipsLabel = _('Nástroj', 'Tool');
    return panel;
  };

  var SECTION = {};

  SECTION.staging = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var st = res(ctx, 'staging');
    if (st === null) return info(ctx, 'real:staging', _('Staging · ', 'Staging · ') + sel.name, '', [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (st.__error) return info(ctx, 'real:staging', _('Staging · ', 'Staging · ') + sel.name, '', [[_('Chyba', 'Error'), st.__error]]);
    var note = _('kopie webu na adrese pro testování změn; obnovíte ji z produkce a hotové změny přenesete zpět (soubory i databáze)', 'a copy of the site for testing changes; refresh it from production and push finished changes back (files and databases)');
    if (st.role === 'staging') return info(ctx, 'real:staging', _('Staging · ', 'Staging · ') + sel.name, _('toto je staging kopie produkčního webu', 'this is the staging copy of a production site'), [[_('Produkce', 'Production'), st.production ? st.production.domain : '—']]);
    var pairs = [[_('Stav', 'State'), { none: _('nezaložen', 'not created'), provisioning: _('zakládá se…', 'being created…'), ready: _('připraven', 'ready'), failed: _('založení selhalo', 'creation failed'), deleted: _('smazán', 'deleted') }[st.state] || st.state]];
    if (st.staging) pairs.push([_('Adresa stagingu', 'Staging address'), st.staging.url]);
    else pairs.push([_('Adresa stagingu', 'Staging address'), st.proposed_domain || '—', _('vznikne při založení', 'created on demand')]);
    if (st.last_synced_at) pairs.push([_('Naposledy obnoven z produkce', 'Last refreshed from production'), ctx.X.since(ctx.cmp, st.last_synced_at)]);
    if (st.last_pushed_at) pairs.push([_('Naposledy přenesen do produkce', 'Last pushed to production'), ctx.X.since(ctx.cmp, st.last_pushed_at)]);
    if (st.databases && st.databases.length) pairs.push([_('Databáze', 'Databases'), st.databases.map(function (d) { return d.source_name + ' → ' + d.target_name; }).join(', ')]);
    var panel = info(ctx, 'real:staging', _('Staging · ', 'Staging · ') + sel.name, note, pairs);
    panel.extra = [];
    if (st.state === 'none' || st.state === 'failed' || st.state === 'deleted') panel.extra.push({ label: _('Založit staging', 'Create staging'), primary: true, on: function () { if (window.confirm(_('Založit staging kopii webu na ' + (st.proposed_domain || '') + '? Soubory a databáze se zkopírují z produkce.', 'Create a staging copy at ' + (st.proposed_domain || '') + '? Files and databases are copied from production.'))) act(ctx, 'staging.create', { databases: true }, ['staging'], _('Staging se zakládá', 'Creating staging'), _('Založení a kopie trvají několik minut; průběh je v záložce Provoz a NOC.', 'Creation and the copy take a few minutes; progress is under Operations and NOC.')); } });
    if (st.state === 'ready') {
      panel.extra.push({ label: _('Otevřít staging', 'Open staging'), on: function () { window.open(st.staging.url, '_blank', 'noopener'); } });
      panel.extra.push({ label: _('Obnovit z produkce', 'Refresh from production'), on: function () { if (window.confirm(_('Přepsat staging aktuální produkcí (soubory i databáze)?', 'Overwrite staging with current production (files and databases)?'))) act(ctx, 'staging.refresh', { databases: true }, ['staging'], _('Obnovuji staging', 'Refreshing staging'), ''); } });
      panel.extra.push({ label: _('Přenést do produkce', 'Push to production'), primary: true, on: function () { if (window.confirm(_('Přenést staging do PRODUKCE? Produkční soubory a databáze se nahradí kopií ze stagingu; před přenosem vytvoříme zálohu.', 'Push staging to PRODUCTION? Production files and databases are replaced by the staging copy; a backup is taken first.'))) act(ctx, 'staging.push', { databases: true, confirm: true }, ['staging', 'backups'], _('Přenáším do produkce', 'Pushing to production'), _('Nejdřív záloha, pak kopie; průběh je v záložce Provoz a NOC.', 'Backup first, then the copy; progress is under Operations and NOC.')); } });
      panel.extra.push({ label: _('Smazat staging', 'Delete staging'), on: function () { if (window.confirm(_('Smazat staging web včetně jeho databází?', 'Delete the staging site with its databases?'))) act(ctx, 'staging.delete', {}, ['staging'], _('Staging se ruší', 'Deleting staging'), ''); } });
    }
    panel.extra.push(refreshBtn(ctx, ['staging']));
    return panel;
  };

  SECTION.deploy = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var d = get(ctx, 'deploy', '/services/' + sel.id + '/deploy');
    var title = _('Git deploy · ', 'Git deploy · ') + sel.name;
    if (d === null) return info(ctx, 'real:deploy', title, '', [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (d.__error) return info(ctx, 'real:deploy', title, '', [[_('Chyba', 'Error'), d.__error]]);
    var save = function (patch) {
      var body = Object.assign({ repository: d.source ? d.source.repository : '', branch: d.source ? d.source.branch : 'main' }, patch);
      API.put('/services/' + sel.id + '/deploy', body).then(function (r) {
        var x = r.data || r;
        clearForm(ctx);
        ctx.X.flash(ctx.cmp, _('Repozitář připojen', 'Repository connected'), x.webhook_secret ? _('Webhook secret (zobrazen jen teď): ', 'Webhook secret (shown only now): ') + x.webhook_secret : _('Nastavení uloženo.', 'Settings saved.'));
        if (x.webhook_secret) ctx.X.state.resources[sel.id + ':deploysecret'] = x.webhook_secret;
        drop(ctx, ['deploy']);
      }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se uložit', 'Could not save'), (e && e.message) || ''); });
    };
    if (!d.configured) {
      var p0 = info(ctx, 'real:deploy', title, _('připojte GitHub, GitLab nebo libovolný git přes SSH; každý push na větev nasadíme jako nový release s okamžitým rollbackem', 'connect GitHub, GitLab or any git over SSH; every push to the branch becomes a release with instant rollback'), [[_('Stav', 'State'), _('repozitář není připojen', 'no repository connected')], [_('Strategie', 'Strategy'), d.strategy === 'run_path' ? _('přepnutí kořene webu na release', 'switching the web root to the release') : _('symlink web → release', 'symlink web → release')]]);
      p0.form = { title: _('Připojit repozitář', 'Connect a repository'), fields: [ctx.F('a', _('owner/repo (GitHub), gitlab.com/skupina/repo nebo git@host:cesta.git', 'owner/repo (GitHub), gitlab.com/group/repo or git@host:path.git'), '1 1 300px'), ctx.F('b', _('větev (main)', 'branch (main)'), '0 0 140px'), ctx.F('c', _('build příkaz (volitelně, např. composer install --no-dev)', 'build command (optional, e.g. composer install --no-dev)'), '1 1 280px')], submit: _('Připojit', 'Connect'), on: function () {
        if (!v(ctx, 'a')) { ctx.X.flash(ctx.cmp, _('Zadejte repozitář', 'Enter a repository'), ''); return; }
        save({ repository: v(ctx, 'a'), branch: v(ctx, 'b') || 'main', build_command: v(ctx, 'c') });
      } };
      return p0;
    }
    var src = d.source, last = d.last_deployment;
    var secret = ctx.X.state.resources[sel.id + ':deploysecret'];
    var pairs = [[_('Repozitář', 'Repository'), src.repository + ' · ' + src.branch], [_('Deploy klíč (přidejte jako read-only Deploy key)', 'Deploy key (add as a read-only deploy key)'), src.public_key], [_('Webhook (push → deploy)', 'Webhook (push → deploy)'), d.webhook_url, secret ? _('secret: ', 'secret: ') + secret : _('secret byl zobrazen při připojení; nový vygenerujete tlačítkem', 'the secret was shown when connecting; generate a new one with the button')], [_('Automatický deploy', 'Auto deploy'), yesno(ctx, src.auto_deploy)], [_('Build', 'Build'), src.build_command || '—'], [_('Po nasazení', 'After deploy'), (src.hooks || []).join(' ; ') || '—'], [_('Poslední deploy', 'Last deployment'), last ? (last.ref + ' · ' + (last.sha || '').slice(0, 7) + ' · ' + { succeeded: _('úspěch', 'success'), failed: _('selhal', 'failed'), running: _('běží', 'running'), queued: _('čeká', 'queued'), rolled_back: _('vráceno', 'rolled back') }[last.state] + ' · ' + ctx.X.since(ctx.cmp, last.finished_at || last.started_at)) : '—']];
    var panel = info(ctx, 'real:deploy', title, _('release = složka na serveru; přepnutí je atomické a zpět jde jedním kliknutím · uchováváme ' + src.keep_releases + ' release', 'a release is a folder on the server; the switch is atomic and one click reverts · we keep ' + src.keep_releases + ' releases'), pairs);
    (d.deployments || []).slice(0, 10).forEach(function (x) {
      panel.rows.push({ cells: [ctx.cell(_('Deploy ', 'Deployment ') + ctx.X.since(ctx.cmp, x.started_at), '1 1 220px'), ctx.cell(x.ref + ' · ' + (x.sha || '').slice(0, 7) + ' · ' + x.state + (x.trigger ? ' · ' + x.trigger : ''), '1 1 260px', 1)], note: x.message || '', actions: x.state === 'succeeded' && x.release ? [ctx.A(_('Rollback', 'Roll back'), function () { if (window.confirm(_('Vrátit web na release ' + x.release + '?', 'Roll the site back to ' + x.release + '?'))) act(ctx, 'deploy.rollback', { deployment_id: x.id }, ['deploy'], _('Rollback běží', 'Rolling back'), ''); })] : (x.log ? [ctx.A(_('Log', 'Log'), function () { window.alert(x.log.slice(-4000)); })] : []) });
    });
    panel.form = { title: _('Upravit nastavení', 'Edit settings'), fields: [ctx.F('a', _('větev', 'branch'), '0 0 140px'), ctx.F('b', _('build příkaz', 'build command'), '1 1 260px'), ctx.F('c', _('příkazy po nasazení (oddělte ;)', 'post-deploy commands (separate with ;)'), '1 1 260px')], submit: _('Uložit', 'Save'), on: function () {
      save({ branch: v(ctx, 'a') || src.branch, build_command: v(ctx, 'b'), hooks: v(ctx, 'c') ? v(ctx, 'c').split(/\s*;\s*/).filter(Boolean) : [] });
    } };
    panel.extra = [
      { label: _('Nasadit teď', 'Deploy now'), primary: true, on: function () { act(ctx, 'deploy.run', {}, ['deploy'], _('Deploy běží', 'Deploying'), _('Klon, build, přepnutí; průběh je v záložce Provoz a NOC.', 'Clone, build, switch; progress is under Operations and NOC.')); } },
      { label: src.auto_deploy ? _('Vypnout auto deploy', 'Turn auto deploy off') : _('Zapnout auto deploy', 'Turn auto deploy on'), on: function () { save({ auto_deploy: !src.auto_deploy }); } },
      { label: _('Nový webhook secret', 'New webhook secret'), on: function () { API.post('/services/' + sel.id + '/deploy/rotate-secret', {}, API.key()).then(function (r) { var x = r.data || r; ctx.X.state.resources[sel.id + ':deploysecret'] = x.webhook_secret; ctx.X.flash(ctx.cmp, _('Nový secret', 'New secret'), x.webhook_secret); ctx.X.rerender(ctx.cmp); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); }); } },
      { label: _('Odpojit repozitář', 'Disconnect'), on: function () { if (window.confirm(_('Odpojit repozitář? Nasazené soubory zůstávají.', 'Disconnect the repository? Deployed files stay.'))) API.del('/services/' + sel.id + '/deploy').then(function () { delete ctx.X.state.resources[sel.id + ':deploysecret']; drop(ctx, ['deploy']); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); }); } },
      refreshBtn(ctx, ['deploy'])
    ];
    return panel;
  };

  SECTION.wordpress = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var w = res(ctx, 'wordpress');
    var title = _('WordPress · ', 'WordPress · ') + sel.name;
    if (w === null) return info(ctx, 'real:wp', title, _('čtu stav instalace přes WP-CLI…', 'reading the installation through WP-CLI…'), [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (w.__error) return info(ctx, 'real:wp', title, '', [[_('Chyba', 'Error'), w.__error]]);
    if (w.installed === null) return info(ctx, 'real:wp', title, _('připravujeme přístup na server pro tento web; zkuste to za minutu', 'preparing the server access for this site; try again in a minute'), [[_('WordPress', 'WordPress'), _('zjišťuji…', 'checking…')]], [{ label: _('Obnovit', 'Refresh'), on: function () { res(ctx, 'wordpress', '', true); } }]);
    if (!w.installed) {
      var p0 = info(ctx, 'real:wp', title, _('v kořeni webu není WordPress; nainstalujeme ho čistý přes WP-CLI (vlastní databáze, čeština) nebo přeneste existující web přes Import', 'no WordPress in the site root; we install a clean one through WP-CLI (own database, your language) or bring an existing site over with Import'), [[_('WordPress', 'WordPress'), _('nenalezen', 'not found')], [_('Adresa po instalaci', 'Address after install'), 'https://' + sel.name + '/']]);
      var dbs = res(ctx, 'databases');
      p0.form = { title: _('Nainstalovat WordPress', 'Install WordPress'), fields: [ctx.F('a', _('název webu', 'site title'), '0 0 220px'), ctx.F('b', _('e-mail správce', 'admin e-mail'), '0 0 220px'), ctx.F('c', _('heslo správce (min. 12 znaků; prázdné = vygenerujeme)', 'admin password (min. 12 chars; empty = generated)'), '1 1 260px'), ctx.F('d', _('existující databáze (název; prázdné = založíme novou)', 'existing database (name; empty = we create one)'), '0 0 260px'), ctx.F('e', _('heslo té databáze', 'that database\'s password'), '0 0 200px')], submit: _('Nainstalovat', 'Install'), on: function () {
        if (!v(ctx, 'a') || !v(ctx, 'b')) { ctx.X.flash(ctx.cmp, _('Vyplňte název a e-mail', 'Fill in the title and e-mail'), ''); return; }
        var params = { title: v(ctx, 'a'), admin_email: v(ctx, 'b'), admin_password: v(ctx, 'c'), locale: cs(ctx) ? 'cs_CZ' : 'en_US' };
        if (v(ctx, 'd')) { // a database made in the panel: its id from the listing, its password from the customer
          var chosen = (Array.isArray(dbs) ? dbs : []).filter(function (x) { return x.name === v(ctx, 'd'); })[0];
          if (!chosen) { ctx.X.flash(ctx.cmp, _('Databáze nenalezena', 'Database not found'), _('Zadejte přesný název databáze ze záložky Databáze.', 'Enter the exact database name from the Databases tab.')); return; }
          if (!v(ctx, 'e')) { ctx.X.flash(ctx.cmp, _('Zadejte heslo databáze', 'Enter the database password'), _('Heslo databáze založené v panelu neuchováváme.', 'We do not keep the password of a database created in the panel.')); return; }
          params.database_id = String(chosen.remote_id || chosen.id); params.database_password = v(ctx, 'e');
        }
        if (!window.confirm(_('Nainstalovat WordPress do kořene webu ' + sel.name + '? Existující index soubory se přepíší.', 'Install WordPress into the root of ' + sel.name + '? Existing index files are overwritten.'))) return;
        API.post('/services/' + sel.id + '/actions', { action: 'wp.install', params: params }, API.key()).then(function (r) {
          var d = r.data || r;
          clearForm(ctx);
          ctx.X.flash(ctx.cmp, _('Instalace běží', 'Installing'), _('Trvá asi minutu; přihlašovací údaje ukážeme po dokončení.', 'Takes about a minute; the sign-in details are shown when done.'));
          awaitOp(ctx, d.operation_id, function (op) {
            if (op && op.state === 'SUCCEEDED' && op.result) { ctx.X.state.resources[sel.id + ':wpinstall'] = op.result; ctx.X.flash(ctx.cmp, _('WordPress nainstalován', 'WordPress installed'), (op.result.admin_url || '') + ' · ' + (op.result.admin_user || '') + ' / ' + (op.result.admin_password || '')); }
            else ctx.X.flash(ctx.cmp, _('Instalace selhala', 'Install failed'), (op && op.error && op.error.message) || _('Zkuste to znovu.', 'Try again.'));
            drop(ctx, ['wordpress', 'files', 'databases']);
          });
        }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Instalaci nelze spustit', 'Cannot start the install'), (e && e.message) || ''); });
      } };
      var inst = ctx.X.state.resources[sel.id + ':wpinstall'];
      if (inst && inst.admin_url) p0.rows.push({ cells: [ctx.cell(_('Přihlášení (zobrazeno jednou)', 'Sign-in (shown once)'), '1 1 220px'), ctx.cell(inst.admin_url + ' · ' + inst.admin_user + ' / ' + inst.admin_password, '1 1 260px', 1)], note: '', actions: [] });
      p0.extra = [refreshBtn(ctx, ['wordpress'])];
      return p0;
    }
    var staged = w.staging && w.staging.ready;
    var pairs = [
      [_('Verze', 'Version'), w.version + (w.core_update ? ' → ' + w.core_update.version + _(' k dispozici', ' available') : ' · ' + _('aktuální', 'up to date'))],
      [_('Aktualizace pluginů / šablon', 'Plugin / theme updates'), (w.updates ? w.updates.plugins : 0) + ' / ' + (w.updates ? w.updates.themes : 0)],
      [_('Redis object cache', 'Redis object cache'), w.redis ? (w.redis.enabled ? _('zapnutá a připojená', 'on and connected') : (w.redis.reachable ? _('vypnutá (Redis je k dispozici)', 'off (Redis is available)') : _('Redis na serveru není dostupný', 'Redis is not available on the server'))) : '—'],
      [_('Integrita jádra', 'Core integrity'), w.checksums_ok ? _('soubory jádra odpovídají', 'core files match') : _('soubory jádra se liší — zkontrolujte web', 'core files differ — check the site')],
      [_('WP_DEBUG', 'WP_DEBUG'), yesno(ctx, w.debug)], [_('Adresa', 'Home URL'), w.home || '—'], [_('Databáze', 'Database'), bytes(w.db_size_bytes)], [_('Zjištěno', 'Checked'), ctx.X.since(ctx.cmp, w.checked_at)]
    ];
    var panel = info(ctx, 'real:wp', title, _('aktualizace nejdřív proběhne na stagingu a po kontrole na produkci (když staging existuje); jinak přímo', 'updates run on staging first and, after a health check, on production (when staging exists); otherwise directly'), pairs);
    (w.plugins || []).filter(function (p) { return p.update === 'available'; }).slice(0, 15).forEach(function (p) {
      panel.rows.push({ cells: [ctx.cell(_('Plugin ', 'Plugin ') + (p.title || p.name), '1 1 220px'), ctx.cell(p.version + ' → ' + (p.update_version || '?') + ' · ' + p.status, '1 1 260px', 1)], note: '', actions: [ctx.A(_('Aktualizovat', 'Update'), function () { act(ctx, 'wp.plugin', { slug: p.name, op: 'update' }, ['wordpress'], _('Aktualizuji plugin', 'Updating the plugin'), ''); }), ctx.A(p.status === 'active' ? _('Deaktivovat', 'Deactivate') : _('Aktivovat', 'Activate'), function () { act(ctx, 'wp.plugin', { slug: p.name, op: p.status === 'active' ? 'deactivate' : 'activate' }, ['wordpress'], _('Plugin', 'Plugin'), ''); })] });
    });
    panel.form = { title: _('Plugin', 'Plugin'), fields: [ctx.F('a', _('slug pluginu (např. wordfence)', 'plugin slug (e.g. wordfence)'), '0 0 220px'), ctx.F('b', _('install | activate | deactivate | update | delete', 'install | activate | deactivate | update | delete'), '0 0 260px')], submit: _('Provést', 'Run'), on: function () {
      if (!v(ctx, 'a')) { ctx.X.flash(ctx.cmp, _('Zadejte slug pluginu', 'Enter the plugin slug'), ''); return; }
      act(ctx, 'wp.plugin', { slug: v(ctx, 'a').toLowerCase(), op: v(ctx, 'b').toLowerCase() || 'install' }, ['wordpress'], _('Plugin', 'Plugin'), '');
    } };
    panel.extra = [
      { label: staged ? _('Aktualizovat vše (staging → produkce)', 'Update everything (staging → production)') : _('Aktualizovat vše', 'Update everything'), primary: true, on: function () { if (window.confirm(staged ? _('Aktualizovat jádro, pluginy i šablony? Nejdřív na stagingu, po kontrole na produkci.', 'Update core, plugins and themes? Staging first, production after the check.') : _('Aktualizovat jádro, pluginy i šablony přímo na produkci? (Bez stagingu doporučujeme nejdřív zálohu.)', 'Update core, plugins and themes directly on production? (Without staging, back up first.)'))) act(ctx, 'wp.update', { what: 'all', staged: !!staged }, ['wordpress'], _('Aktualizace běží', 'Updating'), _('Průběh je v záložce Provoz a NOC.', 'Progress is under Operations and NOC.')); } },
      { label: _('Jen jádro', 'Core only'), on: function () { act(ctx, 'wp.update', { what: 'core', staged: !!staged }, ['wordpress'], _('Aktualizace jádra běží', 'Updating the core'), ''); } },
      { label: w.redis && w.redis.enabled ? _('Vypnout Redis cache', 'Turn Redis cache off') : _('Zapnout Redis cache', 'Turn Redis cache on'), on: function () { act(ctx, 'wp.cache', { enabled: !(w.redis && w.redis.enabled) }, ['wordpress'], _('Object cache', 'Object cache'), ''); } },
      { label: _('Obnovit', 'Refresh'), on: function () { res(ctx, 'wordpress', '', true); } }
    ];
    return panel;
  };

  SECTION.monitoring = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var m = get(ctx, 'monitoring', '/services/' + sel.id + '/monitoring');
    var title = _('Monitoring dostupnosti · ', 'Uptime monitoring · ') + sel.name;
    if (m === null) return info(ctx, 'real:mon', title, '', [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (m.__error) return info(ctx, 'real:mon', title, '', [[_('Chyba', 'Error'), m.__error]]);
    var monitors = m.monitors || [];
    var rows = monitors.map(function (x) {
      var up = x.uptime || x.windows || {};
      var pct = function (k) { var w = up[k]; return w && w.uptime_pct != null ? w.uptime_pct + ' %' : '—'; };
      var st = { up: _('běží', 'up'), down: _('výpadek', 'down'), pending: _('čeká na první kontrolu', 'waiting for the first check'), paused: _('pozastaven', 'paused') }[x.state] || x.state;
      return { cells: [ctx.cell(x.name || x.url, '1 1 220px'), ctx.cell(st + (x.last_ms != null ? ' · ' + x.last_ms + ' ms' : '') + (x.last_status ? ' · HTTP ' + x.last_status : ''), '0 0 200px'), ctx.cell(pct('24h') + ' / ' + pct('7d') + ' / ' + pct('30d'), '0 0 190px', 1)], note: (x.url || '') + (x.keyword ? ' · ' + _('hledá: ', 'expects: ') + x.keyword : '') + (x.last_error ? ' · ' + x.last_error : '') + (x.enabled === false ? ' · ' + _('vypnutý', 'disabled') : ''),
        actions: [ctx.A(x.notify ? _('Neupozorňovat', 'Mute alerts') : _('Upozorňovat', 'Alert me'), function () { API.put('/services/' + sel.id + '/monitoring', { id: x.id, notify: !x.notify }).then(function () { drop(ctx, ['monitoring']); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); }); }), ctx.A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat tuto kontrolu?', 'Delete this check?'))) API.del('/services/' + sel.id + '/monitoring/' + x.id).then(function () { drop(ctx, ['monitoring']); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); }); })] };
    });
    return { key: 'real:mon', title: title, note: _('kontrolujeme z naší sítě každou minutu; po ' + (m.threshold || 3) + ' neúspěších hlásíme výpadek e-mailem a v panelu, návrat také', 'checked from our network every minute; after ' + (m.threshold || 3) + ' failures we report an outage by e-mail and in the panel, and the recovery too'),
      state: monitors.length + (m.limit ? ' / ' + m.limit : '') + ' ' + _('kontrol', 'checks'), head: [ctx.cell(_('Kontrola', 'Check'), '1 1 220px'), ctx.cell(_('Stav', 'State'), '0 0 200px'), ctx.cell(_('Dostupnost 24 h / 7 d / 30 d', 'Uptime 24 h / 7 d / 30 d'), '0 0 190px')], rows: rows.length ? rows : loadingRow(ctx, _('zatím žádná kontrola', 'no checks yet')),
      form: { title: _('Přidat kontrolu', 'Add a check'), fields: [ctx.F('a', 'https://' + sel.name + '/', '1 1 260px'), ctx.F('b', _('název (volitelně)', 'name (optional)'), '0 0 160px'), ctx.F('c', _('text, který má stránka obsahovat (volitelně)', 'text the page must contain (optional)'), '0 0 220px')], submit: _('Přidat', 'Add'), on: function () {
        if (!v(ctx, 'a')) { ctx.X.flash(ctx.cmp, _('Zadejte adresu', 'Enter a URL'), ''); return; }
        API.put('/services/' + sel.id + '/monitoring', { url: v(ctx, 'a'), name: v(ctx, 'b') || undefined, keyword: v(ctx, 'c') || undefined, notify: true }).then(function () { clearForm(ctx); ctx.X.flash(ctx.cmp, _('Kontrola přidána', 'Check added'), ''); drop(ctx, ['monitoring']); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se přidat', 'Could not add'), (e && e.message) || ''); });
      } },
      extra: [refreshBtn(ctx, ['monitoring'])] };
  };

  SECTION.cdn = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var c = res(ctx, 'cdn');
    var title = _('CDN · ', 'CDN · ') + sel.name;
    if (c === null) return info(ctx, 'real:cdn', title, '', [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (c.__error) return info(ctx, 'real:cdn', title, '', [[_('Chyba', 'Error'), c.__error]]);
    var st = { off: _('vypnuto', 'off'), pending_ns: _('čeká na přesměrování domény', 'waiting for the domain delegation'), active: _('aktivní', 'active'), disabled: _('vypnuto', 'off') }[c.state] || c.state;
    var pairs = [[_('Stav', 'State'), st], [_('Doména', 'Domain'), c.apex + (c.domain !== c.apex ? ' (' + c.domain + ')' : '')]];
    if (c.enabled) {
      pairs.push([_('Jmenné servery CDN', 'CDN nameservers'), (c.nameservers || []).join(', '), c.domain_managed ? _('doménu jsme přesměrovali automaticky', 'we switched the domain automatically') : _('nastavte je u registrátora domény', 'set them at the domain registrar')]);
      pairs.push([_('Chráněné adresy', 'Proxied hosts'), (c.proxied_records || []).join(', ') || '—']);
      var s = c.settings || {};
      pairs.push([_('Nastavení', 'Settings'), 'TLS ' + (s.ssl || 'full') + ' · HTTPS ' + yesno(ctx, s.always_use_https) + ' · HTTP/3 ' + yesno(ctx, s.http3) + ' · ' + _('úroveň ochrany ', 'security level ') + (s.security_level || 'medium') + ' · TLS min ' + (s.min_tls_version || '1.2')]);
      if (c.analytics) pairs.push([_('Posledních 24 h', 'Last 24 h'), c.analytics.requests + _(' požadavků · ', ' requests · ') + bytes(c.analytics.bandwidth_bytes) + (c.analytics.cached_ratio != null ? ' · ' + Math.round(c.analytics.cached_ratio * 100) + _(' % z cache', ' % cached') : '') + (c.analytics.threats ? ' · ' + c.analytics.threats + _(' hrozeb blokováno', ' threats blocked') : '')]);
      if (c.last_error) pairs.push([_('Poslední chyba', 'Last error'), c.last_error]);
    } else {
      pairs.push([_('DNS domény', 'Domain DNS'), c.dns_managed ? _('u ONhost — záznamy zrcadlíme na edge automaticky', 'at ONhost — records are mirrored to the edge automatically') : _('mimo ONhost — na edge nastavíme jen web (A/AAAA)', 'outside ONhost — only the web records (A/AAAA) go to the edge')]);
    }
    var panel = info(ctx, 'real:cdn', title, _('edge síť před webem: cache statiky, ochrana proti útokům, HTTP/3 a TLS na okraji; DNS záznamy zůstávají u nás a na edge se zrcadlí', 'an edge network in front of the site: static cache, attack protection, HTTP/3 and TLS at the edge; DNS records stay with us and are mirrored'), pairs);
    panel.extra = [];
    if (!c.available) panel.note = _('CDN není v tomto tarifu nebo není na platformě nastavené.', 'CDN is not in this plan or not configured on the platform.');
    else if (!c.enabled) panel.extra.push({ label: _('Zapnout CDN', 'Turn CDN on'), primary: true, on: function () { if (window.confirm(_('Zapnout CDN pro ' + c.apex + '? ' + (c.domain_managed ? 'Doménu přesměrujeme na jmenné servery CDN automaticky.' : 'Po zapnutí nastavte u registrátora jmenné servery CDN.'), 'Turn the CDN on for ' + c.apex + '?'))) act(ctx, 'cdn.enable', { settings: {} }, ['cdn'], _('Zapínám CDN', 'Turning the CDN on'), _('Zóna, záznamy a nastavení edge; aktivace po přesměrování domény trvá až hodiny.', 'Zone, records and edge settings; activation after the delegation takes up to hours.')); } });
    else {
      panel.form = { title: _('Nastavení edge', 'Edge settings'), fields: [ctx.F('a', _('úroveň ochrany: low | medium | high | under_attack', 'security level: low | medium | high | under_attack'), '0 0 300px'), ctx.F('b', _('TLS režim: full | strict', 'TLS mode: full | strict'), '0 0 200px'), ctx.F('c', _('development_mode on/off (vypne cache na 3 h)', 'development_mode on/off (bypasses cache for 3 h)'), '0 0 260px')], submit: _('Uložit', 'Save'), on: function () {
        var settings = {};
        if (v(ctx, 'a')) settings.security_level = v(ctx, 'a');
        if (v(ctx, 'b')) settings.ssl = v(ctx, 'b');
        if (v(ctx, 'c')) settings.development_mode = /^(on|1|true|ano)$/i.test(v(ctx, 'c'));
        act(ctx, 'cdn.enable', { settings: settings }, ['cdn'], _('Nastavení CDN uloženo', 'CDN settings saved'), '');
      } };
      panel.extra.push({ label: _('Vyprázdnit cache', 'Purge cache'), primary: true, on: function () { act(ctx, 'cdn.purge', { settings: {} }, ['cdn'], _('Cache vyprázdněna', 'Cache purged'), _('Edge si stáhne čerstvý obsah při dalším požadavku.', 'The edge fetches fresh content on the next request.')); } });
      panel.extra.push({ label: _('Vypnout CDN', 'Turn CDN off'), on: function () { if (window.confirm(_('Vypnout CDN a vrátit doménu na naše jmenné servery?', 'Turn the CDN off and return the domain to our nameservers?'))) act(ctx, 'cdn.disable', { settings: {} }, ['cdn'], _('Vypínám CDN', 'Turning the CDN off'), ''); } });
    }
    panel.extra.push(refreshBtn(ctx, ['cdn']));
    return panel;
  };

  SECTION['import'] = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var list = res(ctx, 'imports');
    var kind = ctx.s.wbImportKind || 'upload';
    var panel = table(ctx, 'real:import', _('Import webu · ', 'Site import · ') + sel.name, _('přeneste web z jiného hostingu: záloha z cPanelu/Plesku, archiv (zip/tar.gz) nebo jeho URL; soubory jdou do kořene webu, SQL dumpy se stanou databázemi a WordPress přesměrujeme na novou doménu', 'move a site from another host: a cPanel/Plesk backup, an archive (zip/tar.gz) or its URL; files go to the site root, SQL dumps become databases and WordPress is re-pointed at the new domain'),
      list, [ctx.cell(_('Zdroj', 'Source'), '1 1 220px'), ctx.cell(_('Stav', 'State'), '0 0 120px'), ctx.cell(_('Výsledek', 'Result'), '1 1 220px')],
      function (x) { var s = x.stats || {}; return { cells: [ctx.cell((x.kind || '') + ' · ' + (x.source || ''), '1 1 220px', 1), ctx.cell({ running: _('běží', 'running'), succeeded: _('hotovo', 'done'), failed: _('selhalo', 'failed') }[x.state] || x.state, '0 0 120px'), ctx.cell(s.files != null ? s.files + _(' souborů · ', ' files · ') + (s.databases || 0) + _(' databází', ' databases') + (s.wordpress ? ' · WordPress' : '') : '—', '1 1 220px', 1)], note: x.log ? String(x.log).split('\n').slice(-2).join(' · ') : '', actions: x.log ? [ctx.A(_('Log', 'Log'), function () { window.alert(String(x.log).slice(-4000)); })] : [] }; },
      { title: _('Import z URL', 'Import from a URL'), fields: [ctx.F('a', 'https://…/backup.tar.gz', '1 1 300px'), ctx.F('b', _('podsložka v kořeni webu (volitelně)', 'sub-folder in the site root (optional)'), '0 0 220px')], submit: _('Importovat z URL', 'Import from URL'), on: function () {
        if (!v(ctx, 'a')) { ctx.X.flash(ctx.cmp, _('Zadejte URL archivu', 'Enter the archive URL'), ''); return; }
        act(ctx, 'import.run', { kind: 'url', source: v(ctx, 'a'), files: true, databases: true, subdir: v(ctx, 'b') }, ['imports', 'files', 'databases'], _('Import běží', 'Import running'), _('Stažení, rozbalení a nahrání trvají podle velikosti minuty až desítky minut.', 'Download, unpack and upload take minutes to tens of minutes depending on size.'));
      } },
      [{ label: _('Nahrát archiv (', 'Upload an archive (') + { upload: _('obecný', 'generic'), cpanel: 'cPanel', plesk: 'Plesk' }[kind] + ')', primary: true, on: function () {
        pick('.zip,.tar,.gz,.tgz', function (file) { upload(ctx, '/uploads', file).then(function (u) { return act(ctx, 'import.run', { kind: kind, source: u.upload_id, files: true, databases: true, subdir: v(ctx, 'b') }, ['imports', 'files', 'databases'], _('Import běží', 'Import running'), ''); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nahrání selhalo', 'Upload failed'), (e && e.message) || ''); }); });
      } }], 'imports');
    panel.chips = [['upload', _('Obecný archiv', 'Generic archive')], ['cpanel', _('Záloha cPanel', 'cPanel backup')], ['plesk', _('Záloha Plesk', 'Plesk backup')]].map(function (k) { return { label: k[1], active: kind === k[0], on: function () { ctx.cmp.setState({ wbImportKind: k[0] }); } }; });
    panel.chipsLabel = _('Typ archivu', 'Archive type');
    return panel;
  };

  SECTION.node_projects = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var list = res(ctx, 'node_projects');
    var panel = table(ctx, 'real:node', _('Node.js projekty · ', 'Node.js projects · ') + sel.name, _('aplikace běžící na portu pod správcem procesů; doménu na ni nasměrujeme reverzní proxy', 'apps running on a port under the process manager; a domain is pointed at it through a reverse proxy'),
      list, [ctx.cell(_('Projekt', 'Project'), '1 1 200px'), ctx.cell(_('Port · verze', 'Port · version'), '0 0 140px'), ctx.cell(_('Stav', 'State'), '0 0 100px')],
      function (x) { return { cells: [ctx.cell(x.name + (x.domains && x.domains.length ? ' · ' + x.domains.join(', ') : ''), '1 1 200px', 1), ctx.cell((x.port || '—') + ' · ' + (x.version || '—'), '0 0 140px', 1), ctx.cell(x.state || '—', '0 0 100px')], note: x.path || '', actions: ['start', 'stop', 'restart', 'delete'].map(function (op) { return ctx.A({ start: _('Start', 'Start'), stop: _('Stop', 'Stop'), restart: _('Restart', 'Restart'), delete: _('Smazat', 'Delete') }[op], function () { if (op !== 'delete' || window.confirm(_('Smazat projekt ' + x.name + '?', 'Delete project ' + x.name + '?'))) act(ctx, 'node.action', { remote_id: x.remote_id, op: op }, ['node_projects'], _('Projekt: ', 'Project: ') + op, ''); }); }) }; },
      { title: _('Nový projekt', 'New project'), fields: [ctx.F('a', _('název', 'name'), '0 0 160px'), ctx.F('b', _('složka v kořeni webu (např. app) a start skript (např. app.js), oddělte mezerou', 'folder in the site root (e.g. app) and start script (e.g. app.js), space separated'), '1 1 320px'), ctx.F('c', _('port (1024–65535)', 'port (1024–65535)'), '0 0 140px')], submit: _('Vytvořit', 'Create'), on: function () {
        var parts = v(ctx, 'b').split(/\s+/);
        act(ctx, 'node.create', { name: v(ctx, 'a'), path: parts[0] || '', script: parts[1] || 'index.js', port: parseInt(v(ctx, 'c'), 10) || 0, domains: [sel.name] }, ['node_projects'], _('Projekt se zakládá', 'Creating the project'), '');
      } }, [], 'node_projects');
    return panel;
  };

  /* Reverse proxies (a path forwarded to a local port or another server) and the default documents of the site. */
  SECTION.proxy = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var list = res(ctx, 'proxies');
    var panel = table(ctx, 'real:proxy', _('Reverzní proxy · ', 'Reverse proxy · ') + sel.name, _('cesta webu obsloužená jiným serverem nebo aplikací na portu (Node.js, Docker, API); hlavičky Host a X-Forwarded-For posíláme dál', 'a path of the site served by another server or an app on a port (Node.js, Docker, API); Host and X-Forwarded-For headers are passed on'),
      list, [ctx.cell(_('Název · cesta', 'Name · path'), '1 1 200px'), ctx.cell(_('Cíl', 'Target'), '1 1 220px'), ctx.cell(_('Stav', 'State'), '0 0 100px')],
      function (x) { return { cells: [ctx.cell(x.name + ' · ' + (x.path || '/'), '1 1 200px', 1), ctx.cell(x.target, '1 1 220px', 1), ctx.cell((x.enabled === false ? _('vypnuto', 'off') : _('aktivní', 'active')) + (x.cache ? ' · cache' : ''), '0 0 100px')],
        actions: [ctx.A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat proxy ' + x.name + '?', 'Delete proxy ' + x.name + '?'))) act(ctx, 'proxy.delete', { remote_id: x.remote_id }, ['proxies'], _('Proxy smazána', 'Proxy deleted'), ''); })] }; },
      { title: _('Nová proxy', 'New proxy'), fields: [ctx.F('a', _('název (např. api)', 'name (e.g. api)'), '0 0 160px'), ctx.F('b', _('cíl, např. http://127.0.0.1:3000', 'target, e.g. http://127.0.0.1:3000'), '1 1 260px'), ctx.F('c', _('cesta (např. /api; prázdné = celý web)', 'path (e.g. /api; empty = whole site)'), '0 0 220px')], submit: _('Vytvořit', 'Create'), on: function () {
        act(ctx, 'proxy.create', { name: v(ctx, 'a'), target: v(ctx, 'b'), path: v(ctx, 'c') || '/' }, ['proxies'], _('Proxy se zakládá', 'Creating the proxy'), '');
      } }, [], 'proxies');
    var docs = res(ctx, 'default_docs');
    var names = docs && !docs.__error && docs.names ? docs.names : null;
    if (ctx.on('default_docs')) {
      panel.extra = (panel.extra || []).concat([{ label: _('Výchozí soubory: ', 'Default documents: ') + (names === null ? _('načítám…', 'loading…') : (names.length ? names.join(', ') : _('výchozí serveru', 'server default'))), on: function () {
        var cur = window.prompt(_('Pořadí výchozích souborů (oddělte čárkou):', 'Default document order (comma separated):'), names && names.length ? names.join(', ') : 'index.php, index.html');
        if (cur === null) return;
        act(ctx, 'index.set', { names: cur.split(',').map(function (n) { return n.trim(); }).filter(Boolean) }, ['default_docs'], _('Výchozí soubory nastaveny', 'Default documents set'), '');
      } }]);
    }
    return panel;
  };

  SECTION.security = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var sec = res(ctx, 'security');
    var title = _('Zabezpečení webu · ', 'Site security · ') + sel.name;
    if (sec === null) return info(ctx, 'real:sec', title, '', [[_('Stav', 'State'), _('načítám…', 'loading…')]]);
    if (sec.__error) return info(ctx, 'real:sec', title, '', [[_('Chyba', 'Error'), sec.__error]]);
    var supports = sec.supports || [];
    var save = function (patch, okT) { var rules = Object.assign({}, sec, patch); delete rules.desired; delete rules.supports; act(ctx, 'security.set', { rules: rules }, ['security'], okT || _('Pravidla uložena', 'Rules saved'), _('Platí do minuty.', 'Applies within a minute.')); };
    var toggle = function (k, label) { return ctx.A(sec[k] ? _('Vypnout', 'Turn off') : _('Zapnout', 'Turn on'), function () { var p = {}; p[k] = !sec[k]; save(p, label); }); };
    var pairs = [
      [_('Blokované IP adresy', 'Blocked IP addresses'), (sec.deny || []).join(', ') || '—', _('nastavíte formulářem níže', 'set with the form below')],
      [_('Povolené jen tyto IP', 'Only these IPs allowed'), (sec.allow || []).join(', ') || _('(všichni)', '(everyone)'), _('prázdné = web je veřejný', 'empty = the site is public')],
      [_('Blokování škodlivých botů', 'Bad-bot blocking'), yesno(ctx, sec.bots), '', [toggle('bots', _('Boti', 'Bots'))]],
      [_('Ochrana proti hotlinkingu', 'Hotlink protection'), yesno(ctx, sec.hotlink) + (sec.hotlink && sec.hotlink_allow && sec.hotlink_allow.length ? ' · ' + _('povoleno: ', 'allowed: ') + sec.hotlink_allow.join(', ') : ''), _('obrázky a videa jen z vlastního webu', 'images and videos only from your own site'), [toggle('hotlink', _('Hotlink', 'Hotlink'))]],
      [_('Bezpečnostní hlavičky', 'Security headers'), yesno(ctx, sec.headers), 'X-Frame-Options, X-Content-Type-Options, Referrer-Policy', [toggle('headers', _('Hlavičky', 'Headers'))]],
      [_('HSTS', 'HSTS'), yesno(ctx, sec.hsts), '', ctx.on('hsts') ? [toggle('hsts', 'HSTS')] : []]
    ];
    if (supports.indexOf('rate') >= 0 || sec.rate) pairs.push([_('Limit požadavků (anti-DDoS)', 'Request limits (anti-DDoS)'), sec.rate ? sec.rate.perip + _(' spojení/IP · ', ' conn/IP · ') + sec.rate.perserver + _(' spojení celkem · ', ' conn total · ') + sec.rate.limit_rate + ' kB/s' : _('vypnuto', 'off'), '', [ctx.A(sec.rate ? _('Vypnout', 'Turn off') : _('Zapnout', 'Turn on'), function () { save({ rate: sec.rate ? null : { perip: 30, perserver: 300, limit_rate: 512 } }, _('Limit požadavků', 'Request limits')); })]]);
    var panel = info(ctx, 'real:sec', title, _('pravidla vkládáme do konfigurace webserveru; blokace platí do minuty a nezasahují do vašich vlastních direktiv', 'rules go into the web server configuration; blocks apply within a minute and never touch your own directives'), pairs);
    panel.form = { title: _('IP pravidla a hotlink', 'IP rules and hotlink'), fields: [ctx.F('a', _('blokovat IP/CIDR (oddělte čárkou; prázdné = nic)', 'block IP/CIDR (comma separated; empty = none)'), '1 1 240px'), ctx.F('b', _('povolit jen IP/CIDR (prázdné = veřejný web)', 'allow only IP/CIDR (empty = public site)'), '1 1 240px'), ctx.F('c', _('hotlink povolen z domén (oddělte čárkou)', 'hotlink allowed from domains (comma separated)'), '1 1 220px')], submit: _('Uložit', 'Save'), on: function () {
      var split = function (k) { return v(ctx, k).split(/[\s,;]+/).filter(Boolean); };
      save({ deny: split('a'), allow: split('b'), hotlink_allow: split('c') });
    } };
    panel.extra = [refreshBtn(ctx, ['security'])];
    return panel;
  };

  /* Action hooks: a signed URL that runs one action of this service — for Discord bots, CI or a phone shortcut. */
  SECTION.hooks = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var d = get(ctx, 'hooks', '/hooks/actions?service=' + encodeURIComponent(sel.id));
    var list = d && !d.__error ? (d.hooks || []) : null;
    var actions = (ctx.f.actions || []).filter(function (a) { return HOOK_LABELS[a]; });
    var label = function (a) { return HOOK_LABELS[a] ? _(HOOK_LABELS[a][0], HOOK_LABELS[a][1]) : a; };
    var shown = ctx.X.state.resources[sel.id + ':hookurl'];
    var rows = list === null ? loadingRow(ctx) : (d.__error ? errRow(ctx, d.__error) : list.map(function (h) {
      return { cells: [ctx.cell(h.name, '1 1 200px'), ctx.cell(label(h.action), '0 0 180px'), ctx.cell((h.uses || 0) + '× ' + (h.last_used_at ? ctx.X.since(ctx.cmp, h.last_used_at) : '') + (h.last_result && h.last_result !== 'accepted' ? ' · ' + h.last_result : ''), '0 0 190px', 1)], note: shown && shown.id === h.id ? shown.url : (h.enabled ? '' : _('vypnutý', 'disabled')),
        actions: [ctx.A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat webhook ' + h.name + '? URL přestane platit okamžitě.', 'Delete hook ' + h.name + '? The URL stops working immediately.'))) API.del('/hooks/actions/' + encodeURIComponent(h.id)).then(function () { drop(ctx, ['hooks']); }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || ''); }); })] };
    }));
    if (list !== null && !d.__error && !list.length) rows = loadingRow(ctx, _('zatím žádný akční webhook', 'no action hook yet'));
    return { key: 'real:hooks', title: _('Akční webhooky · ', 'Action hooks · ') + sel.name,
      note: _('URL, která spustí jednu akci této služby: POST z Discord bota, CI, cronu nebo zkratky na telefonu. Token je součástí URL a ukážeme ho jen jednou; akce běží pod vaším účtem a je auditovaná.', 'a URL that runs one action of this service: POST it from a Discord bot, CI, cron or a phone shortcut. The token is part of the URL and shown once; the action runs under your account and is audited.'),
      state: list ? list.length + ' / 50' : '', head: [ctx.cell(_('Název', 'Name'), '1 1 200px'), ctx.cell(_('Akce', 'Action'), '0 0 180px'), ctx.cell(_('Použití', 'Uses'), '0 0 190px')], rows: rows,
      form: { title: _('Nový akční webhook', 'New action hook'), fields: [ctx.F('a', _('název (např. Záloha z Discordu)', 'name (e.g. Backup from Discord)'), '0 0 240px'), ctx.F('b', _('akce: ', 'action: ') + actions.join(' | '), '1 1 300px')], submit: _('Vytvořit', 'Create'), on: function () {
        var action = v(ctx, 'b') || actions[0];
        if (actions.indexOf(action) < 0) { ctx.X.flash(ctx.cmp, _('Neznámá akce', 'Unknown action'), actions.join(', ')); return; }
        var params = action === 'power' ? { power_action: 'reboot' } : (action === 'backup' ? { kind: 'manual' } : (action === 'staging.push' ? { confirm: true, databases: true } : (action === 'wp.update' ? { what: 'all', staged: true } : {})));
        API.post('/hooks/actions', { service_id: sel.id, name: v(ctx, 'a') || label(action), action: action, params: params }, API.key()).then(function (r) {
          var x = r.data || r;
          clearForm(ctx);
          ctx.X.state.resources[sel.id + ':hookurl'] = { id: x.hook && x.hook.id, url: x.url };
          ctx.X.flash(ctx.cmp, _('Webhook vytvořen', 'Hook created'), _('URL (zobrazeno jednou): ', 'URL (shown once): ') + x.url);
          drop(ctx, ['hooks']);
        }).catch(function (e) { ctx.X.flash(ctx.cmp, _('Nepodařilo se vytvořit', 'Could not create'), (e && e.message) || ''); });
      } },
      extra: [refreshBtn(ctx, ['hooks'])] };
  };

  /* Plan change: the plans of the product priced per the subscription period and what a change costs now; "Přejít" orders it (pro-rated, from credit or by transfer). */
  function fmtMoney(ctx, m) { var n = m && typeof m === 'object' ? parseFloat(m.decimal != null ? m.decimal : (m.minor || 0) / 100) : (parseFloat(m) || 0); return ctx.cmp && typeof ctx.cmp.money === 'function' ? ctx.cmp.money(n) : String(n); }
  /* opt = a plan row (plan change, the subscription's period) or a period row (the current plan billed per the other period: a new period starts now) */
  function changePlan(ctx, opt, d) {
    var _ = ctx._, cmp = ctx.cmp, sel = ctx.sel, A = window.OnhostApi, D = window.ONHOST_PANEL || {};
    var periodChange = !!opt.period && !opt.plan_key, period = periodChange ? opt.period : d.period, per = period === 'year' ? _('rok', 'year') : _('měsíc', 'month');
    var now = opt.change_now && opt.change_now.minor ? fmtMoney(ctx, opt.change_now) : null, ask;
    if (periodChange) {
      ask = _('Přejít na ' + (period === 'year' ? 'roční' : 'měsíční') + ' platbu? Nové období začne dnes' + (opt.period_end_after ? ' a běží do ' + new Date(opt.period_end_after).toLocaleDateString('cs-CZ') : '') + '; zaplatíte ' + (now || '0 Kč') + ' bez DPH (' + fmtMoney(ctx, opt.price) + ' / ' + per + ' minus nevyužitý zbytek současného období' + (opt.unused_credit && opt.unused_credit.minor ? ' ' + fmtMoney(ctx, opt.unused_credit) : '') + ').',
        'Switch to ' + (period === 'year' ? 'yearly' : 'monthly') + ' billing? A new period starts today' + (opt.period_end_after ? ' and runs until ' + new Date(opt.period_end_after).toLocaleDateString('en-GB') : '') + '; you pay ' + (now || '0') + ' excl. VAT (' + fmtMoney(ctx, opt.price) + ' / ' + per + ' minus the unused rest of the current period' + (opt.unused_credit && opt.unused_credit.minor ? ' ' + fmtMoney(ctx, opt.unused_credit) : '') + ').');
    } else {
      ask = now
        ? _('Přejít na tarif ' + opt.name + '? Doplatek do konce období ' + now + ' bez DPH, od dalšího období ' + fmtMoney(ctx, opt.price) + ' / ' + per + '.', 'Switch to the ' + opt.name + ' plan? You pay ' + now + ' excl. VAT for the rest of the period, then ' + fmtMoney(ctx, opt.price) + ' / ' + per + '.')
        : _('Přejít na tarif ' + opt.name + '? Bez doplatku; od dalšího období ' + fmtMoney(ctx, opt.price) + ' / ' + per + '. Nižší limity platí ihned.', 'Switch to the ' + opt.name + ' plan? Nothing to pay now; ' + fmtMoney(ctx, opt.price) + ' / ' + per + ' from the next period. Lower limits apply at once.');
    }
    if (!window.confirm(ask)) return;
    var consents = {}, person = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.name) || '';
    Object.keys(D.consents || {}).forEach(function (k) { consents[k] = { version: D.consents[k], person: person }; });
    var currency = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.organization && window.ONHOST.user.organization.currency) || 'CZK';
    var item = periodChange ? { product_key: d.product_key || (d.plans[0] && d.plans[0].product_key), plan_key: d.current_plan, qty: 1, period: period, config: { upgrade_of: sel.id } } : { product_key: opt.product_key, plan_key: opt.plan_key, qty: 1, period: d.period, config: { upgrade_of: sel.id } };
    A.put('/cart', { items: [item], commit_months: 1, currency: currency, promo_code: null })
      .then(function () { return A.post('/cart/quote', {}); })
      .then(function (q) {
        var quote = q.data || q, total = (quote.total || 0) / 100, credit = cmp.state && typeof cmp.state.credit === 'number' ? cmp.state.credit : 0, mode = total <= 0 || credit >= total ? 'wallet' : 'bank';
        return A.post('/orders', { quote_id: quote.quote_id, consents: consents, payment: { mode: mode }, source: 'panel' }, 'panel-plan:' + quote.quote_id).then(function (r) { return { r: r.data || r, mode: mode }; });
      })
      .then(function (x) {
        var o = x.r.order || x.r;
        ctx.X.flash(cmp, (periodChange ? _('Změna období ', 'Billing period change ') : _('Změna tarifu ', 'Plan change ')) + (o.number || '') + _(' přijata', ' received'), x.mode === 'wallet' ? _('Uhrazeno z kreditu; nové limity platí do minuty.', 'Paid from credit; the new limits apply within a minute.') : _('Zálohová faktura je ve Fakturaci; po připsání platby tarif změníme.', 'The proforma is in Billing; the plan changes once the payment arrives.'));
        drop(ctx, ['plans', 'quotas']);
        if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh();
      })
      .catch(function (e) { ctx.X.flash(cmp, _('Změna tarifu neprošla', 'Plan change failed'), (e && e.message) || ''); });
  }
  function planPairs(ctx, pairs) {
    var _ = ctx._, r = get(ctx, 'plans', '/services/' + encodeURIComponent(ctx.sel.id) + '/plans');
    if (r === null) { pairs.push([_('Tarify', 'Plans'), _('načítám…', 'loading…')]); return; }
    var d = r && r.data && r.data.plans ? r.data : r;
    if (!d || d.__error || !d.plans) return;
    var per = d.period === 'year' ? _('/ rok', '/ year') : _('/ měsíc', '/ month');
    d.plans.forEach(function (p) {
      var e = p.entitlements || {};
      var sum = [e.sites ? e.sites + _(' web', ' site') + (e.sites > 1 ? _('y', 's') : '') : null, e.nvme_gb ? e.nvme_gb + ' GB NVMe' : null, e.php_workers ? e.php_workers + ' PHP' : null, e.vcpu ? e.vcpu + ' vCPU' : null, e.ram_mb ? Math.round(e.ram_mb / 1024) + ' GB RAM' : null, e.mailboxes ? e.mailboxes + _(' schránek', ' mailboxes') : null].filter(Boolean).join(' · ');
      pairs.push([p.name + (p.current ? _(' · aktuální', ' · current') : ''), fmtMoney(ctx, p.price) + ' ' + per + (sum ? ' · ' + sum : ''),
        p.current ? _('platí do ', 'until ') + (d.period_end ? new Date(d.period_end).toLocaleDateString('cs-CZ') : '') : (p.direction === 'upgrade' ? _('doplatek teď ', 'pay now ') + fmtMoney(ctx, p.change_now) + _(' bez DPH', ' excl. VAT') : _('bez doplatku, nižší limity ihned', 'no charge, lower limits at once')),
        p.current || !d.changeable ? [] : [ctx.A(_('Přejít', 'Switch'), function () { changePlan(ctx, p, d); })]]);
    });
    // the billing period of the current plan: monthly or a year in advance (a switch starts a new period today, the unused rest is credited)
    (d.periods || []).forEach(function (p) {
      var yearly = p.period === 'year', label = yearly ? _('Roční platba', 'Yearly billing') : _('Měsíční platba', 'Monthly billing');
      var saving = yearly && p.saving_per_year && p.saving_per_year.minor ? _(' · ušetříte ', ' · saves ') + fmtMoney(ctx, p.saving_per_year) + _(' za rok', ' a year') : '';
      pairs.push([label + (p.current ? _(' · aktuální', ' · current') : ''), fmtMoney(ctx, p.price) + (yearly ? _(' / rok', ' / year') : _(' / měsíc', ' / month')) + saving,
        p.current ? _('období do ', 'period until ') + (p.period_end_after ? new Date(p.period_end_after).toLocaleDateString('cs-CZ') : '') : _('nové období od dnes; teď zaplatíte ', 'new period from today; pay now ') + fmtMoney(ctx, p.change_now) + _(' bez DPH', ' excl. VAT'),
        p.current || !d.changeable ? [] : [ctx.A(yearly ? _('Platit ročně', 'Bill yearly') : _('Platit měsíčně', 'Bill monthly'), function () { changePlan(ctx, p, d); })]]);
    });
  }
  function planInfo(ctx) {
    var _ = ctx._, pairs = [];
    planPairs(ctx, pairs);
    return info(ctx, 'real:plan', _('Výkon a tarif · ', 'Resources and plan · ') + ctx.sel.name, _('změna tarifu proběhne za provozu; doplatek je poměrná část do konce období, nová cena platí od příštího období', 'a plan change happens live; you pay the pro-rated difference for the rest of the period, the new price applies from the next one'), pairs, [refreshBtn(ctx, ['plans'])]);
  }

  SECTION.quotas = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    var q = res(ctx, 'quotas');
    var pairs = q === null ? [[_('Stav', 'State'), _('načítám…', 'loading…')]] : (q.__error ? [[_('Chyba', 'Error'), q.__error]] : [[_('Obsazený prostor', 'Disk used'), bytes(q.disk_used_bytes) + (q.disk_limit_bytes ? ' / ' + bytes(q.disk_limit_bytes) : '')], [_('Přenos tento měsíc', 'Traffic this month'), bytes(q.traffic_used_bytes) + (q.traffic_limit_bytes ? ' / ' + bytes(q.traffic_limit_bytes) : '')], [_('Počet souborů', 'Files'), q.inodes_used == null ? '—' : String(q.inodes_used)], [_('Změřeno', 'Measured'), ctx.X.since(ctx.cmp, q.measured_at) || '—']]);
    planPairs(ctx, pairs);
    return info(ctx, 'real:quotas', _('Kvóty · ', 'Quotas · ') + sel.name, _('prostor a přenos podle tarifu; při překročení web nepozastavujeme, ale ozveme se', 'disk and traffic per plan; we do not suspend the site when exceeded, we get in touch'), pairs, [refreshBtn(ctx, ['quotas'])]);
  };

  /* ════════════════════════════════ MAIL ══════════════════════════════════ */
  var MAIL = {};

  MAIL.catchall = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('catchall')) return null;
    var c = res(ctx, 'mail_catchall');
    var has = c && !c.__error && c.destination;
    var panel = info(ctx, 'real:catchall', _('Catch-all · ', 'Catch-all · ') + sel.name, _('pošta pro neexistující adresy @' + sel.name + ' se doručí do zvolené schránky; filtry jednotlivých schránek najdete v Doplňcích', 'mail for non-existent addresses @' + sel.name + ' lands in the chosen mailbox; per-mailbox filters are under Add-ons'),
      [[_('Catch-all', 'Catch-all'), c === null ? _('načítám…', 'loading…') : (c.__error ? c.__error : (has ? c.destination + (c.active === false ? ' · ' + _('vypnutý', 'off') : '') : _('vypnutý', 'off')))]]);
    panel.form = { title: _('Nastavit catch-all', 'Set the catch-all'), fields: [ctx.F('a', 'schranka@' + sel.name, '0 0 260px')], submit: _('Uložit', 'Save'), on: function () { act(ctx, 'catchall.set', { destination: v(ctx, 'a') }, ['mail_catchall'], _('Catch-all nastaven', 'Catch-all set'), ''); } };
    panel.extra = [];
    if (has) panel.extra.push({ label: _('Zrušit catch-all', 'Remove catch-all'), on: function () { act(ctx, 'catchall.set', { destination: '' }, ['mail_catchall'], _('Catch-all zrušen', 'Catch-all removed'), ''); } });
    panel.extra.push(refreshBtn(ctx, ['mail_catchall']));
    return panel;
  };

  MAIL.lists = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('mailing_lists')) return null;
    return table(ctx, 'real:mlists', _('Mailing listy · ', 'Mailing lists · ') + sel.name, _('diskusní a rozesílací seznamy (Mailman); správce spravuje členy ve webovém rozhraní seznamu', 'discussion and announcement lists (Mailman); the owner manages members in the list web interface'),
      res(ctx, 'mail_lists'), [ctx.cell(_('Seznam', 'List'), '1 1 200px'), ctx.cell(_('Adresa', 'Address'), '1 1 220px'), ctx.cell(_('Stav', 'State'), '0 0 90px')],
      function (x) { return { cells: [ctx.cell(x.name, '1 1 200px', 1), ctx.cell(x.email, '1 1 220px', 1), ctx.cell(x.active === false ? _('vypnutý', 'off') : _('aktivní', 'active'), '0 0 90px')], note: '', actions: [ctx.A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat seznam ' + x.name + '?', 'Delete list ' + x.name + '?'))) act(ctx, 'list.delete', { remote_id: x.remote_id }, ['mail_lists']); })] }; },
      { title: _('Nový seznam', 'New list'), fields: [ctx.F('a', _('název (např. novinky)', 'name (e.g. news)'), '0 0 160px'), ctx.F('b', _('e-mail správce', 'owner e-mail'), '0 0 220px'), ctx.F('c', _('heslo správce (min. 12 znaků)', 'owner password (min. 12 chars)'), '0 0 220px')], submit: _('Vytvořit', 'Create'), on: function () { act(ctx, 'list.create', { name: v(ctx, 'a'), email: v(ctx, 'b'), password: v(ctx, 'c') }, ['mail_lists']); } },
      [{ label: _('Vygenerovat heslo', 'Generate password'), on: function () { var p = ctx.X.password(20); var next = Object.assign({}, ctx.s.wbF); next.c = p; ctx.cmp.setState({ wbF: next }); ctx.X.flash(ctx.cmp, _('Heslo vygenerováno', 'Password generated'), p); } }], 'mail_lists');
  };

  MAIL.spam = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('spam')) return null;
    var mb = mailboxChips(ctx, 'wbMailbox');
    var sp = res(ctx, 'mail_spam', mb.current ? 'remote_id=' + encodeURIComponent(mb.current) : '');
    var policies = sp && sp.policies ? sp.policies : [];
    var current = sp && sp.mailbox ? sp.mailbox : null;
    var pairs = [[_('Schránka', 'Mailbox'), mb.box ? mb.box.address : _('(vyberte)', '(choose)')], [_('Aktuální politika', 'Current policy'), sp === null ? _('načítám…', 'loading…') : (sp.__error ? sp.__error : (current && current.policy ? current.policy : _('výchozí domény', 'domain default')))]];
    policies.forEach(function (p) { pairs.push([p.name, current && String(current.policy_id) === String(p.remote_id) ? _('✓ aktivní', '✓ active') : '', '', mb.current ? [ctx.A(_('Použít', 'Use'), function () { act(ctx, 'spam.policy', { remote_id: mb.current, policy_id: p.remote_id }, ['mail_spam'], _('Politika nastavena', 'Policy set'), ''); })] : []]); });
    var panel = info(ctx, 'real:spam', _('Spamfilter a politiky · ', 'Spam filter and policies · ') + sel.name, _('politika určuje, jak přísně se spam značkuje, do karantény nebo zahazuje; whitelist a blacklist jsou vedle', 'the policy decides how strictly spam is tagged, quarantined or dropped; white/blacklists are next door'), pairs, [refreshBtn(ctx, ['mail_spam'])]);
    panel.chips = mb.chips; panel.chipsLabel = _('Schránka', 'Mailbox');
    return panel;
  };

  MAIL.bw = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('spam')) return null;
    return table(ctx, 'real:bw', _('Whitelist a blacklist · ', 'Whitelist and blacklist · ') + sel.name, _('odesílatelé (adresa nebo *@domena), kteří filtrem vždy projdou / nikdy neprojdou', 'senders (address or *@domain) that always pass / never pass the filter'),
      res(ctx, 'mail_spam_lists'), [ctx.cell(_('Odesílatel', 'Sender'), '1 1 240px'), ctx.cell(_('Seznam', 'List'), '0 0 120px')],
      function (x) { return { cells: [ctx.cell(x.address, '1 1 240px', 1), ctx.cell(x.kind === 'blacklist' ? _('blacklist', 'blacklist') : _('whitelist', 'whitelist'), '0 0 120px')], note: x.active === false ? _('vypnutý', 'off') : '', actions: [ctx.A(_('Smazat', 'Delete'), function () { act(ctx, 'spam.list.delete', { kind: x.kind, remote_id: x.remote_id }, ['mail_spam_lists']); })] }; },
      { title: _('Přidat odesílatele', 'Add a sender'), fields: [ctx.F('a', _('adresa nebo *@domena.cz', 'address or *@domain.com'), '0 0 240px'), ctx.F('b', _('whitelist | blacklist', 'whitelist | blacklist'), '0 0 160px')], submit: _('Přidat', 'Add'), on: function () { act(ctx, 'spam.list.add', { kind: v(ctx, 'b').toLowerCase() === 'blacklist' ? 'blacklist' : 'whitelist', address: v(ctx, 'a') }, ['mail_spam_lists']); } }, [], 'mail_spam_lists');
  };

  MAIL.relay = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('forwards')) return null;
    return table(ctx, 'real:fwd', _('Přesměrování · ', 'Forwards · ') + sel.name, _('adresa, která poštu posílá dál na jinou (i cizí) adresu; aliasy do vlastních schránek jsou v záložce Aliasy', 'an address that forwards mail to another (even external) address; aliases into your own mailboxes are under Aliases'),
      res(ctx, 'mail_forwards'), [ctx.cell(_('Z adresy', 'From'), '1 1 220px'), ctx.cell(_('Na adresu', 'To'), '1 1 220px')],
      function (x) { return { cells: [ctx.cell(x.source, '1 1 220px', 1), ctx.cell(x.destination, '1 1 220px', 1)], note: x.active === false ? _('vypnuté', 'off') : '', actions: [ctx.A(_('Smazat', 'Delete'), function () { act(ctx, 'forward.delete', { remote_id: x.remote_id }, ['mail_forwards']); })] }; },
      { title: _('Nové přesměrování', 'New forward'), fields: [ctx.F('a', 'info@' + sel.name, '0 0 220px'), ctx.F('b', _('cíl (např. jmeno@gmail.com)', 'target (e.g. name@gmail.com)'), '0 0 240px')], submit: _('Přidat', 'Add'), on: function () { act(ctx, 'forward.create', { source: v(ctx, 'a'), destination: v(ctx, 'b') }, ['mail_forwards']); } }, [], 'mail_forwards');
  };

  MAIL.fetch = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('fetchmail')) return null;
    return table(ctx, 'real:fetch', _('Fetchmail · ', 'Fetchmail · ') + sel.name, _('stahujeme poštu z cizí schránky (IMAP/POP3) do vaší; hodí se při stěhování', 'we pull mail from an external mailbox (IMAP/POP3) into yours; handy when migrating'),
      res(ctx, 'mail_fetchmail'), [ctx.cell(_('Zdroj', 'Source'), '1 1 240px'), ctx.cell(_('Do schránky', 'Into'), '1 1 200px'), ctx.cell(_('Stav', 'State'), '0 0 90px')],
      function (x) { return { cells: [ctx.cell((x.type || '') + ' ' + x.user + '@' + x.host, '1 1 240px', 1), ctx.cell(x.destination, '1 1 200px', 1), ctx.cell(x.active === false ? _('vypnuté', 'off') : _('aktivní', 'active'), '0 0 90px')], note: x['delete'] ? _('zprávy se po stažení mažou', 'messages are deleted after fetching') : '', actions: [ctx.A(_('Smazat', 'Delete'), function () { act(ctx, 'fetchmail.delete', { remote_id: x.remote_id }, ['mail_fetchmail']); })] }; },
      { title: _('Nový účet ke stahování', 'New fetch account'), fields: [ctx.F('a', _('server a typ, např. imap.seznam.cz imapssl', 'host and type, e.g. imap.gmail.com imapssl'), '1 1 260px'), ctx.F('b', _('uživatel a heslo (oddělte mezerou)', 'user and password (space separated)'), '1 1 260px'), ctx.F('c', _('cílová schránka @' + sel.name, 'target mailbox @' + sel.name), '0 0 200px')], submit: _('Přidat', 'Add'), on: function () {
        var a = v(ctx, 'a').split(/\s+/), b = v(ctx, 'b').split(/\s+/);
        act(ctx, 'fetchmail.create', { host: a[0] || '', type: a[1] || 'imapssl', user: b[0] || '', password: b.slice(1).join(' '), destination: v(ctx, 'c'), delete: false }, ['mail_fetchmail']);
      } }, [], 'mail_fetchmail');
  };

  MAIL.bkp = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('mail_backups')) return null;
    var mb = mailboxChips(ctx, 'wbMailbox');
    var panel = table(ctx, 'real:mbkp', _('Zálohy schránek · ', 'Mailbox backups · ') + sel.name, _('zálohy jednotlivých schránek (celý obsah); obnova nahradí aktuální obsah schránky', 'per-mailbox backups (whole contents); a restore replaces the current contents'),
      res(ctx, 'mail_backups'), [ctx.cell(_('Schránka', 'Mailbox'), '1 1 220px'), ctx.cell(_('Vytvořena', 'Created'), '0 0 150px'), ctx.cell(_('Velikost', 'Size'), '0 0 100px')],
      function (x) { var box = (Array.isArray(res(ctx, 'mailboxes')) ? res(ctx, 'mailboxes') : []).filter(function (b) { return b.address === x.mailbox || String(b.remote_id) === String(x.mailbox); })[0]; return { cells: [ctx.cell(x.mailbox, '1 1 220px', 1), ctx.cell(ctx.X.since(ctx.cmp, x.created_at), '0 0 150px', 1), ctx.cell(bytes(x.size_bytes), '0 0 100px', 1)], note: '', actions: box ? [ctx.A(_('Obnovit', 'Restore'), function () { if (window.confirm(_('Obnovit schránku ' + x.mailbox + ' ze zálohy? Aktuální obsah se nahradí.', 'Restore ' + x.mailbox + ' from this backup? Current contents are replaced.'))) act(ctx, 'mailbox.restore', { remote_id: box.remote_id, backup_id: x.remote_id }, ['mail_backups'], _('Obnova běží', 'Restoring'), ''); })] : [] }; },
      null, [{ label: _('Zálohovat ', 'Back up ') + (mb.box ? mb.box.address : ''), primary: true, on: function () { if (mb.current) act(ctx, 'mailbox.backup', { remote_id: mb.current }, ['mail_backups'], _('Záloha se vytváří', 'Creating the backup'), ''); } }], 'mail_backups');
    panel.chips = mb.chips; panel.chipsLabel = _('Schránka', 'Mailbox');
    return panel;
  };

  MAIL.mon = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('mail_usage')) return null;
    var u = res(ctx, 'mail_usage');
    var rows = u === null ? [[_('Stav', 'State'), _('načítám…', 'loading…')]] : (u.__error ? [[_('Chyba', 'Error'), u.__error]] : (u.mailboxes || []).map(function (m) { return [m.mailbox, bytes(m.used_bytes) + (m.quota_bytes ? ' / ' + bytes(m.quota_bytes) + ' (' + Math.round(m.used_bytes / m.quota_bytes * 100) + ' %)' : '')]; }));
    if (u && !u.__error && !(u.mailboxes || []).length) rows = [[_('Schránky', 'Mailboxes'), _('zatím žádné', 'none yet')]];
    var panel = info(ctx, 'real:musage', _('Obsazení schránek · ', 'Mailbox usage · ') + sel.name, _('kvótu schránky změníte v záložce Schránky; webmail běží na serveru vaší služby', 'change a mailbox quota under Mailboxes; webmail runs on your service\'s server'), rows, [refreshBtn(ctx, ['mail_usage'])]);
    if (u && u.webmail) panel.extra.unshift({ label: _('Otevřít webmail', 'Open webmail'), primary: true, on: function () { window.open(u.webmail, '_blank', 'noopener'); } });
    return panel;
  };

  /* Mail add-ons: autoresponder and filters per mailbox. */
  MAIL.addons = function (ctx) {
    var _ = ctx._, sel = ctx.sel;
    if (!ctx.on('autoresponder') && !ctx.on('mail_filters')) return null;
    var mb = mailboxChips(ctx, 'wbMailbox');
    var section = ctx.s.wbMailTool === 'filters' && ctx.on('mail_filters') ? 'filters' : (ctx.on('autoresponder') ? 'autoresponder' : 'filters');
    var panel;
    if (!mb.current) panel = info(ctx, 'real:mtools', _('Automatická odpověď a filtry · ', 'Autoresponder and filters · ') + sel.name, _('nejdřív založte schránku', 'create a mailbox first'), [[_('Schránky', 'Mailboxes'), _('zatím žádné', 'none yet')]]);
    else if (section === 'autoresponder') {
      var ar = res(ctx, 'mail_autoresponder', 'remote_id=' + encodeURIComponent(mb.current));
      panel = info(ctx, 'real:ar', _('Automatická odpověď · ', 'Autoresponder · ') + (mb.box ? mb.box.address : ''), _('odpověď „mimo kancelář“; každému odesílateli jednou za den; volitelně jen v zadaném období', 'an "out of office" reply; once a day per sender; optionally only in a date range'),
        ar === null ? [[_('Stav', 'State'), _('načítám…', 'loading…')]] : (ar.__error ? [[_('Chyba', 'Error'), ar.__error]] : [[_('Stav', 'State'), yesno(ctx, ar.enabled)], [_('Předmět', 'Subject'), ar.subject || '—'], [_('Text', 'Text'), ar.text || '—'], [_('Období', 'Period'), (ar.start || '—') + ' → ' + (ar.end || '—')]]));
      panel.form = { title: _('Nastavit automatickou odpověď', 'Set the autoresponder'), fields: [ctx.F('a', _('předmět', 'subject'), '0 0 220px'), ctx.F('b', _('text odpovědi', 'reply text'), '1 1 300px'), ctx.F('c', _('od do (YYYY-MM-DD YYYY-MM-DD, volitelně)', 'from to (YYYY-MM-DD YYYY-MM-DD, optional)'), '0 0 240px')], submit: _('Zapnout', 'Turn on'), on: function () {
        var d = v(ctx, 'c').split(/\s+/);
        act(ctx, 'autoresponder.set', { remote_id: mb.current, enabled: true, subject: v(ctx, 'a'), text: v(ctx, 'b'), start: d[0] || '', end: d[1] || '' }, ['mail_autoresponder'], _('Automatická odpověď zapnuta', 'Autoresponder on'), '');
      } };
      panel.extra = [];
      if (ar && ar.enabled) panel.extra.push({ label: _('Vypnout', 'Turn off'), on: function () { act(ctx, 'autoresponder.set', { remote_id: mb.current, enabled: false, subject: ar.subject || '', text: ar.text || '' }, ['mail_autoresponder'], _('Automatická odpověď vypnuta', 'Autoresponder off'), ''); } });
      panel.extra.push(refreshBtn(ctx, ['mail_autoresponder']));
    } else {
      panel = table(ctx, 'real:filters', _('Filtry · ', 'Filters · ') + (mb.box ? mb.box.address : ''), _('pravidla při doručení: přesun do složky, smazání nebo zastavení dalších pravidel', 'rules at delivery: move to a folder, delete or stop further rules'),
        res(ctx, 'mail_filters', 'remote_id=' + encodeURIComponent(mb.current)), [ctx.cell(_('Pravidlo', 'Rule'), '1 1 200px'), ctx.cell(_('Podmínka', 'Condition'), '1 1 240px'), ctx.cell(_('Akce', 'Action'), '0 0 140px')],
        function (x) { return { cells: [ctx.cell(x.name, '1 1 200px'), ctx.cell(x.source + ' ' + x.op + ' „' + x.term + '“', '1 1 240px', 1), ctx.cell(x.action + (x.target ? ' → ' + x.target : ''), '0 0 140px', 1)], note: x.active === false ? _('vypnutý', 'off') : '', actions: [ctx.A(_('Smazat', 'Delete'), function () { act(ctx, 'filter.delete', { remote_id: x.remote_id, mailbox_id: mb.current }, ['mail_filters']); })] }; },
        { title: _('Nový filtr', 'New filter'), fields: [ctx.F('a', _('název', 'name'), '0 0 160px'), ctx.F('b', _('Subject|From|To contains|is|begins|ends hledaný text', 'Subject|From|To contains|is|begins|ends term'), '1 1 300px'), ctx.F('c', _('move Složka | delete | stop', 'move Folder | delete | stop'), '0 0 200px')], submit: _('Přidat', 'Add'), on: function () {
          var b = v(ctx, 'b').split(/\s+/), c = v(ctx, 'c').split(/\s+/);
          act(ctx, 'filter.create', { remote_id: mb.current, name: v(ctx, 'a'), source: b[0] || 'Subject', op: b[1] || 'contains', term: b.slice(2).join(' '), action: c[0] || 'move', target: c.slice(1).join(' ') }, ['mail_filters']);
        } }, [], 'mail_filters');
    }
    panel.chips = mb.chips.concat([{ label: '· ' + _('Automatická odpověď', 'Autoresponder'), active: section === 'autoresponder', on: function () { ctx.cmp.setState({ wbMailTool: 'autoresponder' }); } }, { label: '· ' + _('Filtry', 'Filters'), active: section === 'filters', on: function () { ctx.cmp.setState({ wbMailTool: 'filters' }); } }]);
    panel.chipsLabel = _('Schránka', 'Mailbox');
    return panel;
  };

  /* one-click billing period switch from the service summary: loads the plans, picks the period row and runs the ordinary change flow */
  function switchPeriod(cmp, sel, _, X, period) {
    var ctx = ctxOf(cmp, sel, 'quotas', _, X), A = window.OnhostApi;
    if (!ctx || !A) return;
    A.get('/services/' + encodeURIComponent(sel.id) + '/plans').then(function (r) {
      var d = r && r.data ? r.data : r, row = ((d && d.periods) || []).filter(function (p) { return p.period === period; })[0];
      if (!d || !row) { ctx.X.flash(cmp, _('Změna období není k dispozici', 'Period change unavailable'), _('Tarif se v tomto období neprodává.', 'The plan is not sold per this period.')); return; }
      if (row.current) { ctx.X.flash(cmp, _('Beze změny', 'No change'), _('Služba už se platí takto.', 'The service is already billed this way.')); return; }
      if (!d.changeable) { ctx.X.flash(cmp, _('Změna období není k dispozici', 'Period change unavailable'), _('Služba nebo předplatné nejsou aktivní.', 'The service or its subscription is not active.')); return; }
      changePlan(ctx, row, d);
    }).catch(function (e) { ctx.X.flash(cmp, _('Změna období neprošla', 'Period change failed'), (e && e.message) || ''); });
  }

  window.OnhostPanelTools = { enhance: enhance, EXTRA: EXTRA, HUB: HUB, switchPeriod: switchPeriod, planPanel: function (cmp, sel, _, X) { var ctx = ctxOf(cmp, sel, 'plan', _, X); return ctx ? planInfo(ctx) : null; } };
})();
