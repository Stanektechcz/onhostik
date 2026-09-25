<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Životní cyklus služeb — ONhost</title>
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
  form.policy { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; align-items: end; }
  label { display: block; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); margin-bottom: 5px; }
  input[type=number] { font: inherit; width: 100%; padding: 8px 10px; border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); color: inherit; }
  .hint { font-size: 12px; color: var(--color-neutral-700, #605d5d); margin-top: 4px; }
  dialog { border: 2px solid var(--color-text, #201e1d); padding: 20px; max-width: 420px; font: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
  select, input[type=text] { font: inherit; width: 100%; padding: 8px 10px; border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); color: inherit; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/zivotni-cyklus"><strong>Životní cyklus služeb</strong></a>
  <a href="/sprava/nastaveni/tarify">Tarify a verze</a>
  <a href="/sprava/nastaveni/schvalovani">Schvalování</a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Životní cyklus zrušených služeb</h1>
    <p class="lead">Zrušená služba se nikdy nemaže rovnou. Nejdřív ji ověříme nejméně pěti nezávislými identifikátory, pak vytvoříme kompletní zálohu (soubory, databáze, metadata) a teprve potom službu <strong>deaktivujeme</strong>. Zákazník ji může po nastavenou dobu obnovit; když to neudělá, službu odstraníme a od toho okamžiku běží doba uchování archivu. Obnovení archivu k nové placené službě je zdarma, samotné stažení archivu je zpoplatněné.</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <h2>Pravidla</h2>
    <form class="policy" id="policy">
      <div>
        <label for="grace">Lhůta na obnovu (dny)</label>
        <input type="number" id="grace" name="grace_days" min="1" max="365" required>
        <div class="hint">Jak dlouho zůstane deaktivovaná služba obnovitelná.</div>
      </div>
      <div>
        <label for="retention">Uchování archivu (dny)</label>
        <input type="number" id="retention" name="retention_days" min="30" max="3650" required>
        <div class="hint">Počítá se od odstranění služby, ne od zrušení.</div>
      </div>
      <div>
        <label for="checks">Ověřovacích bodů</label>
        <input type="number" id="checks" name="identity_checks" min="5" max="12" required>
        <div class="hint">Kolik identifikátorů musí sedět, než se smí cokoli smazat.</div>
      </div>
      <div>
        <label for="fee-czk">Poplatek za stažení (Kč bez DPH)</label>
        <input type="number" id="fee-czk" min="0" max="100000" step="1" required>
        <div class="hint">Obnova k nové placené službě zůstává zdarma.</div>
      </div>
      <div>
        <label for="fee-eur">Poplatek za stažení (EUR bez DPH)</label>
        <input type="number" id="fee-eur" min="0" max="100000" step="1" required>
      </div>
      <div><button class="btn primary" type="submit">Uložit pravidla</button></div>
    </form>
  </section>

  <section class="panel">
    <h2>Čeká na odstranění</h2>
    <table><thead><tr><th>Služba</th><th>Zákazník</th><th>Zrušeno kvůli</th><th>Lhůta do</th><th>Archiv</th><th></th></tr></thead><tbody id="pending"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Archivy zrušených služeb</h2>
    <table><thead><tr><th>Archiv</th><th>Zákazník</th><th>Stav</th><th>Velikost</th><th>Části / mezery</th><th>Uchovat do</th></tr></thead><tbody id="archives"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>
</main>

<dialog id="stepup">
  <form method="dialog" id="stepup-form">
    <h2>Druhé ověření</h2>
    <p class="muted">Poplatek za stažení archivu je cena a lhůty rozhodují o datech zákazníků. Zadejte kód z autentikátoru nebo záložní kód.</p>
    <div><label for="s-method">Metoda</label><select id="s-method"><option value="totp">TOTP (autentikátor)</option><option value="recovery">záložní kód</option><option value="password">heslo (jen dokud není zapnuté TOTP a mimo produkci)</option></select></div>
    <div style="margin-top:10px"><label for="s-code">Kód</label><input type="text" id="s-code" inputmode="numeric" autocomplete="one-time-code" required></div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Ověřit a pokračovat</button><button class="btn" value="cancel" type="button" id="s-cancel">Zrušit</button></div>
    <p class="hint" id="s-error" style="color:#ae1800"></p>
  </form>
</dialog>
<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'lc-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function api(method, path, body) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.status = r.status; e.approvalId = j.approval_id; throw e; }
        return j;
      });
    });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function day(iso) { if (!iso) return '—'; return new Date(iso).toLocaleDateString('cs-CZ'); }
  function mb(b) { return (Number(b || 0) / 1048576).toFixed(1) + ' MB'; }
  function alertBox(text, ok) { var a = document.getElementById('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; }
  /* Price and plan changes take a second person (owner decision 13): the refusal carries the request it opened. The form stays as it is: once
     somebody else approves, the same change is sent again unchanged and goes through. */
  function pendingApproval(e) {
    var p = new Error('Změna čeká na schválení druhou osobou (žádost ' + (e.approvalId || '?') + '). Po schválení ji odešlete znovu beze změny — /sprava/nastaveni/schvalovani');
    p.error = 'approval_required'; p.approvalId = e.approvalId; p.status = e.status; p.payload = e.payload;
    return p;
  }
  /* HIGH-risk commands answer step_up_required until the session holds a fresh grant: ask, verify, retry. */
  var retry = null, cancel = null;
  function guarded(action) {
    return action().catch(function (e) {
      if (e.error === 'approval_required') throw pendingApproval(e);
      if (e.error !== 'step_up_required') throw e;
      return new Promise(function (resolve, reject) {
        retry = function () { return action().then(resolve, reject); };
        cancel = function () { reject(new Error('Druhé ověření zrušeno — nic se nezměnilo.')); };
        document.getElementById('s-error').textContent = ''; document.getElementById('s-code').value = '';
        document.getElementById('stepup').showModal();
      });
    });
  }
  document.getElementById('stepup-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    api('POST', '/auth/step-up', { method: document.getElementById('s-method').value, code: document.getElementById('s-code').value.trim() }).then(function () {
      document.getElementById('stepup').close(); var r = retry; retry = null; cancel = null; if (r) r();
    }).catch(function (e) { document.getElementById('s-error').textContent = e.message || 'Ověření se nezdařilo.'; });
  });
  document.getElementById('s-cancel').addEventListener('click', function () { document.getElementById('stepup').close(); retry = null; var c = cancel; cancel = null; if (c) c(); });
  function render(d) {
    var p = d.policy || {};
    document.getElementById('grace').value = p.grace_days || 30;
    document.getElementById('retention').value = p.retention_days || 60;
    document.getElementById('checks').value = p.identity_checks || 5;
    document.getElementById('fee-czk').value = Math.round((p.download_fee_minor && p.download_fee_minor.CZK || 0) / 100);
    document.getElementById('fee-eur').value = Math.round((p.download_fee_minor && p.download_fee_minor.EUR || 0) / 100);

    var pend = document.getElementById('pending');
    pend.innerHTML = (d.pending || []).length ? d.pending.map(function (s) {
      var left = s.days_left == null ? '—' : (s.due ? '<span class="tag bad">lhůta vypršela</span>' : '<span class="tag warn">zbývá ' + esc(s.days_left) + ' dní</span>');
      var archive = s.archive_backup_id ? '<span class="tag ok">záloha hotová</span><br><span class="muted mono">' + esc(s.archive_backup_id) + '</span>' : '<span class="tag bad">bez zálohy</span>';
      return '<tr><td><strong>' + esc(s.label || s.name) + '</strong><br><span class="muted mono">' + esc(s.id) + '</span><br><span class="muted">' + esc(s.family) + ' · ' + esc(s.state) + '</span></td>'
        + '<td>' + esc(s.organization) + '</td><td>' + esc(s.reason || '—') + '</td><td>' + esc(day(s.grace_until)) + '<br>' + left + '</td><td>' + archive + '</td>'
        + '<td><div class="actions"><button class="btn" data-purge="' + esc(s.id) + '">Odstranit hned</button></div></td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">žádná služba nečeká na odstranění</td></tr>';

    var arch = document.getElementById('archives');
    arch.innerHTML = (d.archives || []).length ? d.archives.map(function (a) {
      var state = '<span class="tag ' + (a.state === 'completed' ? 'ok' : 'bad') + '">' + esc(a.state) + '</span>' + (a.paid ? ' <span class="tag">staženo</span>' : '');
      var detail = esc((a.parts || []).join(', ') || '—') + ((a.gaps || []).length ? '<div class="err">' + esc(a.gaps.join(' · ')) + '</div>' : '')
        + (a.error ? '<div class="err">' + esc(a.error) + '</div>' : '');
      return '<tr><td><span class="mono">' + esc(a.id) + '</span><br><span class="muted mono">' + esc(a.service_id) + '</span><br><span class="muted">' + esc(day(a.created_at)) + ' · ' + esc(a.identity_matched || 0) + ' bodů</span></td>'
        + '<td>' + esc(a.organization) + '</td><td>' + state + '</td><td>' + esc(mb(a.size_bytes)) + '</td><td>' + detail + '</td><td>' + esc(day(a.retention_until)) + '</td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">žádné archivy</td></tr>';
  }

  function load() { api('GET', '/staff/provisioning/deletions').then(function (r) { render(r.data || r); }).catch(function (e) { alertBox('Přehled se nepodařilo načíst: ' + e.message); }); }

  document.getElementById('policy').addEventListener('submit', function (e) {
    e.preventDefault();
    var body = {
      grace_days: Number(document.getElementById('grace').value),
      retention_days: Number(document.getElementById('retention').value),
      identity_checks: Number(document.getElementById('checks').value),
      download_fee_minor: { CZK: Math.round(Number(document.getElementById('fee-czk').value) * 100), EUR: Math.round(Number(document.getElementById('fee-eur').value) * 100) }
    };
    guarded(function () { return api('PUT', '/staff/settings/lifecycle', body); }).then(function () { alertBox('Pravidla uložena. Platí pro každé další zrušení služby.', true); load(); })
      .catch(function (err) { alertBox('Uložení neprošlo: ' + err.message); });
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('button[data-purge]') : null;
    if (!b) return;
    var reason = window.prompt('Odstranění přeskočí lhůtu na obnovu. Důvod (min. 5 znaků):', '');
    if (!reason || reason.length < 5) return;
    api('POST', '/staff/provisioning/services/' + encodeURIComponent(b.dataset.purge) + '/purge', { reason: reason })
      .then(function () { alertBox('Odstranění zařazeno; proběhne po ověření identity a kontrole zálohy.', true); load(); })
      .catch(function (err) { alertBox('Odstranění neprošlo: ' + err.message); });
  });

  load();
  setInterval(load, 60000);
})();
</script>
</body>
</html>