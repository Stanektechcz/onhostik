<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Bezpečnostní novinky — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  h3 { font-size: 15px; margin: 0 0 8px; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #9a9797); }
  td { padding: 9px 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.warn { background: #e0a100; border-color: transparent; color: #1a1918; }
  .tag.bad { background: var(--color-accent, #ec3013); border-color: transparent; color: #fff; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .btn:disabled { opacity: .5; cursor: default; }
  .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .field { display: flex; flex-direction: column; gap: 5px; }
  .field label { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); }
  .field input, .field select, .field textarea { font: inherit; font-size: 13px; padding: 8px 10px; border: 1px solid var(--color-text, #201e1d); background: #fff; color: inherit; }
  .hint { font-size: 11.5px; color: var(--color-neutral-700, #605d5d); }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; }
  tr.sel td { background: color-mix(in srgb, var(--color-accent, #ec3013) 7%, transparent); }
  dialog { border: 2px solid var(--color-text, #201e1d); padding: 20px; max-width: 420px; font: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/schvalovani">Schvalování</a>
  <a href="/sprava/nastaveni/bezpecnost"><strong>Bezpečnostní novinky</strong></a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Bezpečnostní novinky</h1>
    <p class="lead">Zranitelnosti ze zdrojů (CISA KEV, NVD, Wordfence, OSV) se kontrolují třikrát denně a ty aktivně zneužívané každou hodinu. Když se některá týká softwaru, který provozujeme nebo hostujeme, vznikne případ. Bezpečné opravy (A) se po schválení nasadí ve vlnách; větší změny (B) jen když je výslovně zahrnete; co opravit nejde (D), tomu zákazník dostane upozornění, co, kde a proč. Schválení nad {{ $singleApproverMax }} webů nebo se zahrnutými B potřebuje druhého člověka. Po upozornění zákazníků vznikne koncept článku na blog, nikdy se nezveřejní sám.</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <h2>Případy</h2>
    <div class="actions" style="margin-bottom:10px">
      <label class="hint" for="f-state">Stav</label>
      <select id="f-state"><option value="open">otevřené</option><option value="halted">zastavené</option><option value="partially_resolved">částečně vyřešené</option><option value="resolved">vyřešené</option><option value="dismissed">zamítnuté</option></select>
    </div>
    <table><thead><tr><th>Zranitelnost</th><th>Závažnost</th><th>Míst</th><th>Stav</th><th>Vznikl</th></tr></thead><tbody id="cases"><tr><td colspan="5" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel" id="detail" hidden></section>

  <div class="grid2">
    <section class="panel">
      <h2>Zdroje</h2>
      <table><thead><tr><th>Zdroj</th><th>Poslední úspěch</th><th>Chyba</th></tr></thead><tbody id="feeds"><tr><td colspan="3" class="muted">načítám…</td></tr></tbody></table>
      <h3 style="margin-top:16px">Inventář softwaru</h3>
      <table><thead><tr><th>Ekosystém</th><th>Položek</th><th>Naposledy viděno</th></tr></thead><tbody id="inventory"></tbody></table>
    </section>
    <section class="panel">
      <h2>Nejnovější zranitelnosti</h2>
      <div class="actions" style="margin-bottom:10px"><input id="q" placeholder="CVE nebo název" style="font:inherit;padding:6px 8px;border:1px solid"><label class="hint"><input type="checkbox" id="kev"> jen aktivně zneužívané</label></div>
      <table><thead><tr><th>Klíč</th><th>Název</th><th>Závažnost</th></tr></thead><tbody id="advisories"><tr><td colspan="3" class="muted">načítám…</td></tr></tbody></table>
    </section>
  </div>
</main>

<dialog id="stepup">
  <form method="dialog" id="stepup-form">
    <h2>Druhé ověření</h2>
    <p class="muted">Schválení opravy a upozornění zákazníků mění weby a posílá e-maily. Zadejte kód z autentikátoru nebo záložní kód.</p>
    <div class="field"><label for="s-method">Metoda</label><select id="s-method"><option value="totp">TOTP (autentikátor)</option><option value="recovery">záložní kód</option><option value="password">heslo (jen dokud není zapnuté TOTP a mimo produkci)</option></select></div>
    <div class="field" style="margin-top:10px"><label for="s-code">Kód</label><input type="text" id="s-code" inputmode="numeric" autocomplete="one-time-code" required></div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Ověřit a pokračovat</button><button class="btn" value="cancel" type="button" id="s-cancel">Zrušit</button></div>
    <p class="hint" id="s-error" style="color:#ae1800"></p>
  </form>
</dialog>

@include('partials.dialog-bridge')
<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  var state = { retry: null, cancel: null, open: null, approvals: {} };
  var $ = function (id) { return document.getElementById(id); };
  var SEV = { critical: ['kritická', 'bad'], high: ['vysoká', 'bad'], medium: ['střední', 'warn'], low: ['nízká', 'off'] };
  var CASE = { new: 'nový', awaiting_approval: 'čeká na schválení', approved: 'opravuje se', halted: 'zastaveno', resolved: 'vyřešeno', partially_resolved: 'částečně vyřešeno', dismissed: 'zamítnuto' };
  var CLS = { A: 'A · bezpečná oprava', B: 'B · větší změna', D: 'D · jen upozornit', P: 'P · naše platforma' };
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'sn-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function when(iso) { return iso ? new Date(iso).toLocaleString('cs-CZ', { dateStyle: 'short', timeStyle: 'short' }) : '—'; }
  function sev(s, kev) { var x = SEV[s] || ['neurčená', 'off']; return '<span class="tag ' + x[1] + '">' + esc(x[0]) + '</span>' + (kev ? ' <span class="tag bad">zneužívaná</span>' : ''); }
  function say(text, ok) { var a = $('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; window.scrollTo({ top: 0, behavior: 'smooth' }); }
  function api(method, path, body, idem) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': idem || key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.status = r.status; e.approvalId = j.approval_id; throw e; }
        return j;
      });
    });
  }
  /* approving and telling customers are HIGH-risk: the API answers step_up_required until the session holds a fresh grant */
  function guarded(action) {
    return action().catch(function (e) {
      if (e.error !== 'step_up_required') throw e;
      return new Promise(function (resolve, reject) {
        state.retry = function () { return action().then(resolve, reject); };
        state.cancel = function () { reject(new Error('Druhé ověření zrušeno, nic se nezměnilo.')); };
        $('s-error').textContent = ''; $('s-code').value = '';
        $('stepup').showModal();
      });
    });
  }
  $('stepup-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    api('POST', '/auth/step-up', { method: $('s-method').value, code: $('s-code').value.trim() }).then(function () {
      $('stepup').close(); var r = state.retry; state.retry = null; state.cancel = null; if (r) r();
    }).catch(function (e) { $('s-error').textContent = e.message || 'Ověření se nezdařilo.'; });
  });
  $('s-cancel').addEventListener('click', function () { $('stepup').close(); state.retry = null; var c = state.cancel; state.cancel = null; if (c) c(); });

  function loadCases() {
    api('GET', '/staff/security/advisory-cases?state=' + encodeURIComponent($('f-state').value)).then(function (r) {
      var rows = r.data || [];
      $('cases').innerHTML = rows.length ? rows.map(function (c) {
        var a = c.advisory || {};
        return '<tr data-id="' + esc(c.id) + '"' + (state.open === c.id ? ' class="sel"' : '') + ' style="cursor:pointer"><td><span class="mono">' + esc(a.key) + '</span><br>' + esc(a.title) + '</td><td>' + sev(c.severity, c.kev) + '</td><td>' + esc(c.matches_count) + '</td><td>' + esc(CASE[c.state] || c.state) + (c.wave ? ' · vlna ' + esc(c.wave) : '') + '</td><td class="muted">' + esc(when(c.created_at)) + '</td></tr>';
      }).join('') : '<tr><td colspan="5" class="muted">žádný případ</td></tr>';
      Array.prototype.forEach.call($('cases').querySelectorAll('tr[data-id]'), function (tr) { tr.addEventListener('click', function () { openCase(tr.getAttribute('data-id')); }); });
    }).catch(function (e) { say('Případy se nepodařilo načíst: ' + e.message); });
  }

  function openCase(id) {
    state.open = id;
    api('GET', '/staff/security/advisory-cases/' + encodeURIComponent(id)).then(function (r) { renderCase(r.data); loadCases(); }).catch(function (e) { say('Případ se nepodařilo načíst: ' + e.message); });
  }

  function renderCase(c) {
    var a = c.advisory || {}, d = $('detail'), decidable = c.state === 'new' || c.state === 'awaiting_approval';
    var refs = (a.references || []).slice(0, 6).map(function (u) { return '<a href="' + esc(u) + '" rel="noopener noreferrer" target="_blank">' + esc(u.replace(/^https:\/\//, '').slice(0, 70)) + '</a>'; }).join('<br>');
    var rows = (c.matches || []).map(function (m) {
      return '<tr><td>' + esc(m.label || m.subject_id || '—') + '<br><span class="muted mono">' + esc(m.subject_type) + '</span></td><td>' + esc(m.ecosystem) + ' · <span class="mono">' + esc(m.product) + '</span></td><td class="mono">' + esc(m.version || '?') + ' → ' + esc(m.fixed_in || '—') + '</td><td>' + esc(CLS[m.class] || m.class) + (m.confidence !== 'exact' ? ' <span class="hint">(' + esc(m.confidence) + ')</span>' : '') + '</td><td>' + esc(m.state) + (m.notified_at ? '<br><span class="hint">upozorněn ' + esc(when(m.notified_at)) + '</span>' : '') + '</td></tr>';
    }).join('');
    var t = c.targets || { safe: 0, with_risky: 0 }, max = c.single_approver_max;
    d.hidden = false;
    d.innerHTML = '<h2>' + esc(a.key) + '</h2><p><strong>' + esc(a.title) + '</strong></p><p class="lead">' + esc(a.summary) + '</p>'
      + '<p>' + sev(c.severity, c.kev) + (a.cvss ? ' <span class="mono">CVSS ' + esc(a.cvss) + '</span>' : '') + ' · stav: <strong>' + esc(CASE[c.state] || c.state) + '</strong>' + (c.halted_reason ? ' · ' + esc(c.halted_reason) : '') + (c.announced_at ? ' · zákazníci upozorněni ' + esc(when(c.announced_at)) : '') + (c.post_id ? ' · <a href="/sprava/nastaveni/obsah">koncept článku na blog</a>' : '') + '</p>'
      + (c.decision_note ? '<p class="hint">Poznámka: ' + esc(c.decision_note) + '</p>' : '')
      + '<p class="hint">' + (refs || 'bez odkazů') + '</p>'
      + '<table><thead><tr><th>Kde</th><th>Software</th><th>Verze → oprava</th><th>Třída</th><th>Stav</th></tr></thead><tbody>' + (rows || '<tr><td colspan="5" class="muted">nic</td></tr>') + '</tbody></table>'
      + (decidable ? '<div class="field" style="margin-top:14px"><label for="c-note">Poznámka k rozhodnutí</label><input id="c-note" maxlength="1000" placeholder="proč zamítáme / co schvalujeme"></div>'
        + '<p class="hint">Schválit bezpečné opravy: ' + esc(t.safe) + ' míst' + (t.safe > max ? ' (víc než ' + esc(max) + ', podepíše druhý člověk)' : '') + '. Včetně větších změn: ' + esc(t.with_risky) + ' míst (vždy druhý člověk).</p>'
        + '<div class="actions">'
        + (c.state === 'new' ? '<button class="btn" data-do="relevant">Týká se nás</button>' : '')
        + '<button class="btn primary" data-do="approve"' + (t.safe ? '' : ' disabled') + '>Schválit bezpečné opravy</button>'
        + '<button class="btn" data-do="approve-risky"' + (t.with_risky > t.safe ? '' : ' disabled') + '>Schválit i větší změny</button>'
        + '<button class="btn" data-do="notify">Jen upozornit zákazníky</button>'
        + '<button class="btn" data-do="dismissed">Netýká se nás</button></div>'
        : (c.announced_at ? '<div class="actions" style="margin-top:14px"><button class="btn" data-do="post">Nový koncept článku</button></div>' : ''));
    Array.prototype.forEach.call(d.querySelectorAll('button[data-do]'), function (b) { b.addEventListener('click', function () { act(c, b.getAttribute('data-do')); }); });
  }

  function act(c, what) {
    var note = $('c-note') ? $('c-note').value.trim() || null : null;
    var done = function (text) { return function () { say(text, true); openCase(c.id); }; };
    var fail = function (e) {
      if (e.error === 'approval_required') {
        state.approvals[c.id] = e.approvalId;
        say('Plán mění mnoho webů nebo obsahuje větší změny: vznikla žádost o schválení' + (e.approvalId ? ' ' + e.approvalId : '') + '. Až ji druhý člověk schválí v sekci Schvalování, klikněte znovu na stejné tlačítko.', true);
        return;
      }
      say('Akce neprošla: ' + e.message);
      openCase(c.id);
    };
    if (what === 'relevant' || what === 'dismissed') {
      if (what === 'dismissed' && !note) { say('Zamítnutí potřebuje poznámku, proč se nás případ netýká.'); return; }
      api('POST', '/staff/security/advisory-cases/' + c.id + '/triage', { decision: what, note: note }).then(done(what === 'dismissed' ? 'Případ zamítnut.' : 'Případ čeká na schválení.'), fail);
    } else if (what === 'approve' || what === 'approve-risky') {
      var risky = what === 'approve-risky';
      if (!window.confirm(risky ? 'Opravit i větší změny (B)? Weby se po nich mohou chovat jinak; jejich zákazníci dostanou upozornění předem.' : 'Spustit bezpečné opravy ve vlnách? Zákazníci, u kterých oprava nejde, dostanou upozornění.')) return;
      var body = { include_risky: risky, plan_revision: c.plan_revision, note: note };
      if (state.approvals[c.id]) body.approval_ids = [state.approvals[c.id]];
      guarded(function () { return api('POST', '/staff/security/advisory-cases/' + c.id + '/approve', body); }).then(function () { delete state.approvals[c.id]; say('Plán schválen, první vlna běží.', true); openCase(c.id); }, fail);
    } else if (what === 'notify') {
      if (!window.confirm('Poslat zákazníkům upozornění bez automatické opravy?')) return;
      guarded(function () { return api('POST', '/staff/security/advisory-cases/' + c.id + '/notify', { note: note }); }).then(done('Zákazníci upozorněni, koncept článku je v sekci Blog a reference.'), fail);
    } else if (what === 'post') {
      api('POST', '/staff/security/advisory-cases/' + c.id + '/blog-draft', {}).then(done('Koncept článku vytvořen v sekci Blog a reference.'), fail);
    }
  }

  function loadHealth() {
    api('GET', '/staff/security/advisories/health').then(function (r) {
      var d = r.data || {}, feeds = d.feeds || [];
      $('feeds').innerHTML = (d.configured || []).map(function (k) {
        var f = feeds.filter(function (x) { return x.key === k; })[0];
        return '<tr><td class="mono">' + esc(k) + '</td><td>' + (f ? '<span class="tag ' + (f.stale ? 'warn' : 'ok') + '">' + esc(when(f.last_success_at)) + '</span>' : '<span class="tag off">zatím neběžel</span>') + '</td><td class="hint">' + esc(f && f.last_error ? f.last_error : '') + '</td></tr>';
      }).join('') || '<tr><td colspan="3" class="muted">žádný zdroj není zapnutý</td></tr>';
      $('inventory').innerHTML = (d.inventory || []).map(function (i) { return '<tr><td class="mono">' + esc(i.ecosystem) + '</td><td>' + esc(i.items) + '</td><td class="muted">' + esc(when(i.seen_at)) + '</td></tr>'; }).join('') || '<tr><td colspan="3" class="muted">inventář je prázdný</td></tr>';
    }).catch(function (e) { say('Stav zdrojů se nepodařilo načíst: ' + e.message); });
  }

  var timer = null;
  function loadAdvisories() {
    var q = $('q').value.trim();
    api('GET', '/staff/security/advisories?per_page=40' + (q ? '&q=' + encodeURIComponent(q) : '') + ($('kev').checked ? '&kev=1' : '')).then(function (r) {
      var rows = r.data || [];
      $('advisories').innerHTML = rows.length ? rows.map(function (a) { return '<tr><td class="mono">' + esc(a.key) + '</td><td>' + esc(a.title) + '</td><td>' + sev(a.severity, a.kev) + '</td></tr>'; }).join('') : '<tr><td colspan="3" class="muted">nic</td></tr>';
    }).catch(function (e) { say('Zranitelnosti se nepodařilo načíst: ' + e.message); });
  }
  $('q').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(loadAdvisories, 300); });
  $('kev').addEventListener('change', loadAdvisories);
  $('f-state').addEventListener('change', loadCases);
  loadCases(); loadHealth(); loadAdvisories();
  setInterval(function () { loadCases(); if (state.open) openCase(state.open); }, 30000);
})();
</script>
</body>
</html>
