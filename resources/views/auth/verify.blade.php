<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Potvrzení e-mailu — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 15px; line-height: 1.6; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  main { max-width: 520px; margin: 0 auto; padding: 48px 24px 64px; }
  h1 { font-size: 30px; margin: 0 0 8px; line-height: 1.15; letter-spacing: -.02em; }
  .lead { color: var(--color-neutral-700, #605d5d); margin: 0 0 24px; }
  .box { border: 2px solid var(--color-text, #201e1d); padding: 18px 20px; }
  .box a { color: inherit; }
  .error { color: #ae1800; }
  .btn { display: inline-block; margin-top: 14px; border: 0; cursor: pointer; background: var(--color-accent, #ec3013); color: #f3f2f2; font-family: var(--font-heading, inherit); font-weight: 800; font-size: 14px; letter-spacing: .04em; text-transform: uppercase; padding: 12px 18px; text-decoration: none; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Účet</span>
  <a href="/">← onhost.cz</a>
  <a href="/prihlaseni">Přihlášení</a>
</header>
<main>
  <h1>Potvrzení e-mailu</h1>
  <p class="lead">Ověřujeme odkaz z e-mailu…</p>
  <div class="box" id="box" data-token="{{ $token }}">Chvilku strpení.</div>
</main>
<script>
(function () {
  var box = document.getElementById('box'), token = box.getAttribute('data-token');
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function show(html) { box.innerHTML = html; }
  if (!token) { show('<span class="error">V odkazu chybí ověřovací kód.</span> Otevřete odkaz z e-mailu znovu, nebo si po přihlášení nechte poslat nový.<br><a class="btn" href="/prihlaseni">Přihlásit se</a>'); return; }
  fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(function () {}).then(function () {
    return fetch('/v1/auth/verify-email', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf(), 'Accept-Language': 'cs' }, body: JSON.stringify({ token: token }) });
  }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); }).then(function (res) {
    if (res.ok) { show('<strong>E-mail je potvrzený.</strong> Doklady a bezpečnostní upozornění vám teď můžeme posílat.<br><a class="btn" href="/panel">Do klientského panelu</a>'); return; }
    show('<span class="error">' + ((res.body && res.body.message) || 'Ověřovací odkaz je neplatný nebo vypršel.') + '</span><br>Po přihlášení si v panelu nechte poslat nový odkaz.<br><a class="btn" href="/prihlaseni">Přihlásit se</a>');
  }).catch(function () { show('<span class="error">Spojení se nezdařilo.</span> Obnovte stránku a zkuste to znovu.'); });
})();
</script>
</body>
</html>
