<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Schvalování — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); flex-wrap: wrap; }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #9a9797); }
  td { padding: 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .btn:disabled { opacity: .5; cursor: default; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .hint { font-size: 12px; color: var(--color-neutral-700, #605d5d); margin-top: 4px; }
  label { display: block; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); margin-bottom: 5px; }
  input[type=number], input[type=text], select { font: inherit; padding: 7px 9px; border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); color: inherit; max-width: 100%; }
  .pick { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
  .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; }
  dialog { border: 2px solid var(--color-text, #201e1d); padding: 20px; max-width: 420px; font: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
  .field { display: flex; flex-direction: column; gap: 5px; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/zivotni-cyklus">Životní cyklus služeb</a>
  <a href="/sprava/nastaveni/tarify">Tarify a verze</a>
  <a href="/sprava/nastaveni/schvalovani"><strong>Schvalování</strong></a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Schvalování</h1>
    <p class="lead">Kritická akce obsluhy (právní blokace, role, hromadné úpravy kreditu…) potřebuje <strong>druhého člověka</strong>. Kdo ji zkusí sám, dostane odmítnutí a tady mu vznikne žádost. Schválit ji může jen někdo jiný, kdo by akci směl provést sám — a jen po druhém ověření. Žadatel pak stejnou akci zopakuje; schválení platí jednou, pro přesně tu akci, 24 hodin.</p>
    <div id="alert" hidden></div>
    <p class="hint" id="mode"></p>
  </section>

  <section class="panel">
    <h2>Čeká na rozhodnutí</h2>
    <table><thead><tr><th>Akce</th><th>Žádá</th><th>Důvod a obsah</th><th>Platí do</th><th></th></tr></thead><tbody id="pending"><tr><td colspan="5" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Rozhodnuté a použité</h2>
    <table><thead><tr><th>Akce</th><th>Žádal</th><th>Stav</th><th>Rozhodl</th><th>Poznámka</th><th>Kdy</th></tr></thead><tbody id="history"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>
</main>

<dialog id="stepup">
  <form method="dialog" id="stepup-form">
    <h2>Druhé ověření</h2>
    <p class="muted">Schválení je podpis druhého člověka. Zadejte kód z autentikátoru nebo záložní kód.</p>
    <div class="field"><label for="s-method">Metoda</label><select id="s-method"><option value="totp">TOTP (autentikátor)</option><option value="recovery">záložní kód</option><option value="password">heslo (jen dokud není zapnuté TOTP a mimo produkci)</option></select></div>
    <div class="field" style="margin-top:10px"><label for="s-code">Kód</label><input type="text" id="s-code" inputmode="numeric" autocomplete="one-time-code" required></div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Ověřit a pokračovat</button><button class="btn" value="cancel" type="button" id="s-cancel">Zrušit</button></div>
    <p class="hint" id="s-error" style="color:#ae1800"></p>
  </form>
</dialog>

<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  var state = { retry: null, cancel: null };
  var $ = function (id) { return document.getElementById(id); };
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'apr-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function when(iso) { return iso ? new Date(iso).toLocaleString('cs-CZ', { dateStyle: 'short', timeStyle: 'short' }) : '—'; }
  function say(text, ok) { var a = $('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; window.scrollTo({ top: 0, behavior: 'smooth' }); }
  function api(method, path, body) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.status = r.status; throw e; }
        return j;
      });
    });
  }
  /* deciding is HIGH-risk: the API answers step_up_required until the session holds a fresh grant — ask, verify, retry */
  function guarded(action) {
    return action().catch(function (e) {
      if (e.error !== 'step_up_required') throw e;
      return new Promise(function (resolve, reject) {
        state.retry = function () { return action().then(resolve, reject); };
        state.cancel = function () { reject(new Error('Druhé ověření zrušeno — nic se nezměnilo.')); };
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

  var LABEL = { pending: 'čeká', approved: 'schváleno', rejected: 'zamítnuto', consumed: 'použito', expired: 'vypršelo' };
  function payload(p) {
    var inner = (p && p.payload) || p || {};
    return Object.keys(inner).filter(function (k) { return k !== 'op'; }).map(function (k) { var v = inner[k]; return '<span class="mono">' + esc(k) + '</span> ' + esc(typeof v === 'object' ? JSON.stringify(v) : String(v)); }).join(' · ');
  }
  function render(r) {
    var rows = r.data || [], meta = r.meta || {};
    $('mode').innerHTML = meta.four_eyes === false
      ? '<span class="tag off">režim jednoho provozovatele</span> Kritické akce teď druhého člověka nevyžadují (ONHOST_FOUR_EYES=false na serveru); druhé ověření platí dál a audit to u každé akce uvádí.'
      : (meta.second_person_exists ? 'Rozhodovat smí ' + esc(meta.deciders) + ' lidí.' : '<span class="tag" style="background:#e0a100;border-color:transparent">pozor</span> Kromě vás nesmí žádosti rozhodovat nikdo další — kritickou akci vám nemá kdo schválit. Přidělte oprávnění <span class="mono">iam.approval.decide</span> dalšímu člověku, nebo na serveru nastavte režim jednoho provozovatele.');
    var pending = rows.filter(function (a) { return a.state === 'pending'; });
    $('pending').innerHTML = pending.length ? pending.map(function (a) {
      var buttons = a.mine ? '<span class="muted">vaše žádost — rozhoduje někdo jiný</span>'
        : (meta.can_decide ? '<div class="actions"><button class="btn primary" data-approve="' + esc(a.id) + '">Schválit</button><button class="btn" data-reject="' + esc(a.id) + '">Zamítnout</button></div>' : '');
      return '<tr><td><strong class="mono">' + esc(a.action) + '</strong><br><span class="muted mono">' + esc(a.permission || '') + '</span></td><td>' + esc(a.requested_by && a.requested_by.name || '') + '<br><span class="muted">' + esc(when(a.created_at)) + '</span></td>'
        + '<td>' + (a.reason ? '<strong>' + esc(a.reason) + '</strong><br>' : '') + '<span class="muted">' + payload(a.payload) + '</span></td><td>' + esc(when(a.expires_at)) + '</td><td>' + buttons + '</td></tr>';
    }).join('') : '<tr><td colspan="5" class="muted">nic nečeká</td></tr>';
    var done = rows.filter(function (a) { return a.state !== 'pending'; });
    $('history').innerHTML = done.length ? done.map(function (a) {
      return '<tr><td class="mono">' + esc(a.action) + '</td><td>' + esc(a.requested_by && a.requested_by.name || '') + '</td><td><span class="tag ' + (a.state === 'approved' || a.state === 'consumed' ? 'ok' : 'off') + '">' + esc(LABEL[a.state] || a.state) + '</span></td><td>' + esc(a.decided_by && a.decided_by.name || '—') + '</td><td>' + esc(a.decision_note || '') + '</td><td>' + esc(when(a.decided_at || a.created_at)) + '</td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">zatím nic</td></tr>';
  }
  function load() { api('GET', '/staff/approvals').then(render).catch(function (e) { say('Žádosti se nepodařilo načíst: ' + e.message); }); }

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('button[data-approve],button[data-reject]') : null;
    if (!t) return;
    var approve = t.hasAttribute('data-approve'), id = approve ? t.dataset.approve : t.dataset.reject, note = null;
    if (approve) { if (!window.confirm('Schválit tuto akci? Žadatel ji potom smí jednou provést.')) return; }
    else { note = window.prompt('Proč žádost zamítáte?', ''); if (!note) return; }
    guarded(function () { return api('POST', '/staff/approvals/' + encodeURIComponent(id) + '/decision', { decision: approve ? 'approved' : 'rejected', note: note }); })
      .then(function () { say(approve ? 'Schváleno. Žadatel teď akci zopakuje.' : 'Zamítnuto.', true); load(); })
      .catch(function (err) { say('Rozhodnutí neprošlo: ' + err.message); load(); });
  });

  load();
  setInterval(load, 30000);
})();
</script>
</body>
</html>