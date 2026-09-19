<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Provoz — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; }
  .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
  .kpi { border: 2px solid var(--color-text, #201e1d); padding: 12px 14px; }
  .kpi b { display: block; font-size: 26px; font-family: var(--font-heading, inherit); }
  .kpi span { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); }
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
  .actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .err { font-size: 12px; color: var(--color-neutral-700, #605d5d); overflow-wrap: anywhere; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz"><strong>Provoz</strong></a>
  <a href="/sprava/nastaveni/zivotni-cyklus">Životní cyklus služeb</a>
  <a href="/sprava/nastaveni/tarify">Tarify a verze</a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Provoz: operace a uzly</h1>
    <p class="lead">Co je právě zaseknuté napříč všemi zákazníky: operace čekající na uzel (přechodná chyba, zkoušíme znovu), operace selhané za posledních 24 hodin a operace běžící déle než obvykle. U každého uzlu je záznam z posledních 15 minut; uzel, který jen selhává, platforma sama odstaví z umísťování a po prvních úspěších ho vrátí. Tlačítka jdou přes stejné příkazy jako API (audit, oprávnění).</p>
    <div id="alert" hidden></div>
  </section>

  <section class="kpis" id="kpis"></section>

  <section class="panel">
    <h2>Čekající na uzel (zkoušíme znovu)</h2>
    <table><thead><tr><th>Operace</th><th>Služba / zákazník</th><th>Uzel</th><th>Pokus</th><th>Chyba</th><th>Další pokus</th><th></th></tr></thead><tbody id="stalled"><tr><td colspan="7" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Selhalo za 24 hodin</h2>
    <table><thead><tr><th>Operace</th><th>Služba / zákazník</th><th>Uzel</th><th>Skončilo</th><th>Chyba</th><th></th></tr></thead><tbody id="failed"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Běží déle než 10 minut</h2>
    <table><thead><tr><th>Operace</th><th>Služba / zákazník</th><th>Uzel</th><th>Běží od</th><th>Krok</th><th></th></tr></thead><tbody id="long"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Uzly</h2>
    <table><thead><tr><th>Uzel</th><th>Instance</th><th>Stav</th><th>Zdraví</th><th>15 min: úspěchy / přechodné chyby</th><th></th></tr></thead><tbody id="nodes"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>
</main>
<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'ops-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
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
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function when(iso) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString('cs-CZ') + ' ' + d.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }); }
  function alertBox(text, ok) { var a = document.getElementById('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; }
  function who(o) { return esc(o.service_id || o.domain_id || '—') + (o.organization_id ? '<br><span class="muted mono">' + esc(o.organization_id) + '</span>' : ''); }
  function opRow(o, extraCells, canRetry) {
    var err = o.error && o.error.message ? '<div class="err">' + esc(o.error.message) + '</div>' : '';
    var acts = '<div class="actions">' + (canRetry ? '<button class="btn" data-retry="' + esc(o.id) + '">Zkusit znovu</button>' : '') + '<button class="btn" data-cancel="' + esc(o.id) + '">Zrušit</button></div>';
    return '<tr><td><span class="mono">' + esc(o.kind) + '</span>' + (o.step_label ? '<br><span class="muted">' + esc(o.step_label) + '</span>' : '') + '<br><span class="muted mono">' + esc(o.id) + '</span></td><td>' + who(o) + '</td><td>' + esc(o.node || o.provider_instance_id || '—') + '</td>' + extraCells + '<td>' + err + '</td>' + '<td>' + acts + '</td></tr>';
  }
  function render(d) {
    var c = d.counts || {};
    document.getElementById('kpis').innerHTML = [['Čeká na uzel', c.stalled], ['Selhalo / 24 h', c.failed_24h], ['Běží dlouho', c.long_running], ['Odstavené uzly', c.draining]].map(function (k) { return '<div class="kpi"><b>' + esc(k[1] || 0) + '</b><span>' + esc(k[0]) + '</span></div>'; }).join('');
    var st = document.getElementById('stalled');
    st.innerHTML = (d.stalled || []).length ? d.stalled.map(function (o) { return opRow(o, '<td>' + esc(o.attempts) + '</td>', true).replace('<td><div class="err">', '<td><div class="err">').replace('</td><td><div class="actions">', '</td><td>' + esc(when(o.next_run_at)) + '</td><td><div class="actions">'); }).join('') : '<tr><td colspan="7" class="muted">nic nečeká</td></tr>';
    var fa = document.getElementById('failed');
    fa.innerHTML = (d.failed || []).length ? d.failed.map(function (o) { return opRow(o, '<td>' + esc(when(o.finished_at)) + '</td>', true); }).join('') : '<tr><td colspan="6" class="muted">za posledních 24 hodin nic neselhalo</td></tr>';
    var lo = document.getElementById('long');
    lo.innerHTML = (d.long_running || []).length ? d.long_running.map(function (o) { return opRow(o, '<td>' + esc(when(o.started_at)) + '</td>', false).replace('<td><div class="err"></div></td>', '<td>' + esc(o.step_label || (o.step + ' / ' + o.steps_total)) + '</td>'); }).join('') : '<tr><td colspan="6" class="muted">nic neběží déle než obvykle</td></tr>';
    var no = document.getElementById('nodes');
    no.innerHTML = (d.nodes || []).length ? d.nodes.map(function (n) {
      var state = '<span class="tag ' + (n.state === 'active' ? 'ok' : (n.state === 'draining' ? 'warn' : 'off')) + '">' + esc(n.state) + (n.auto_drained ? ' · auto' : '') + '</span>' + (n.suggest_drain ? ' <span class="tag bad">jen selhává</span>' : '');
      var health = n.health ? '<span class="tag ' + (n.health.up ? 'ok' : 'bad') + '">' + (n.health.up ? 'up' : 'down') + '</span> <span class="muted">' + esc(when(n.health.checked_at)) + '</span>' + (n.health.last_error ? '<div class="err">' + esc(n.health.last_error) + '</div>' : '') : '<span class="muted">bez měření</span>';
      var acts = '<div class="actions">' + (n.state === 'active' ? '<button class="btn" data-node="' + esc(n.id) + '" data-instance="' + esc(n.instance ? n.instance.key : '') + '" data-state="draining">Odstavit</button>' : '<button class="btn primary" data-node="' + esc(n.id) + '" data-instance="' + esc(n.instance ? n.instance.key : '') + '" data-state="active">Vrátit</button>') + '</div>';
      return '<tr><td><strong>' + esc(n.name) + '</strong><br><span class="muted">' + esc(n.region) + ' · ' + esc(n.role) + '</span></td><td>' + esc(n.instance ? n.instance.name || n.instance.key : '—') + '</td><td>' + state + '</td><td>' + health + '</td><td>' + esc(n.succeeded) + ' / ' + esc(n.transient_failures) + '</td><td>' + acts + '</td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">žádné uzly</td></tr>';
  }
  function load() { api('GET', '/staff/provisioning/board').then(function (r) { render(r.data || r); }).catch(function (e) { alertBox('Nástěnku se nepodařilo načíst: ' + e.message); }); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('button[data-retry],button[data-cancel],button[data-node]') : null;
    if (!b) return;
    if (b.dataset.retry) { api('POST', '/staff/provisioning/jobs/' + encodeURIComponent(b.dataset.retry) + '/retry', { reason: 'nástěnka provozu' }).then(function () { alertBox('Operace zařazena znovu.', true); load(); }).catch(function (err) { alertBox('Nelze zopakovat: ' + err.message); }); return; }
    if (b.dataset.cancel) { var reason = window.prompt('Důvod zrušení (min. 5 znaků):', 'zrušeno z nástěnky provozu'); if (!reason || reason.length < 5) return; api('POST', '/staff/provisioning/jobs/' + encodeURIComponent(b.dataset.cancel) + '/cancel', { reason: reason }).then(function () { alertBox('Operace zrušena.', true); load(); }).catch(function (err) { alertBox('Nelze zrušit: ' + err.message); }); return; }
    if (b.dataset.node) { var to = b.dataset.state, why = window.prompt(to === 'draining' ? 'Důvod odstavení uzlu:' : 'Důvod vrácení uzlu:', to === 'draining' ? 'odstaveno z nástěnky provozu' : 'vráceno z nástěnky provozu'); if (why === null) return; api('POST', '/staff/integrations/' + encodeURIComponent(b.dataset.instance) + '/nodes/' + encodeURIComponent(b.dataset.node) + '/state', { state: to, reason: why || null }).then(function () { alertBox(to === 'draining' ? 'Uzel odstaven z umísťování.' : 'Uzel opět přijímá služby.', true); load(); }).catch(function (err) { alertBox('Změna stavu neprošla: ' + err.message); }); }
  });
  load();
  setInterval(load, 30000);
})();
</script>
</body>
</html>
