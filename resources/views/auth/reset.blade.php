<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Nastavit nové heslo — ONhost</title>
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
  label { display: block; margin: 0 0 14px; }
  label span { display: block; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 6px; }
  input { width: 100%; box-sizing: border-box; border: 2px solid var(--color-text, #201e1d); background: #f8f4f4; padding: 13px 14px; font: inherit; }
  .hint { font-size: 12px; color: var(--color-neutral-700, #605d5d); margin: -6px 0 14px; }
  .btn { display: inline-block; border: 0; cursor: pointer; background: var(--color-accent, #ec3013); color: #f3f2f2; font-family: var(--font-heading, inherit); font-weight: 800; font-size: 15px; letter-spacing: .04em; text-transform: uppercase; padding: 14px 20px; }
  .btn[disabled] { opacity: .6; cursor: default; }
  .error { display: block; color: #ae1800; font-size: 13px; margin-top: 10px; }
  .done { border: 2px solid var(--color-text, #201e1d); padding: 18px 20px; margin-top: 8px; }
  .done a { color: inherit; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Účet</span>
  <a href="/">← onhost.cz</a>
  <a href="/prihlaseni">Přihlášení</a>
</header>
<main>
  <h1>Nastavit nové heslo</h1>
  <p class="lead">Zvolte heslo ke svému účtu. Po uložení vás rovnou přihlásíme do klientského panelu.</p>
  <form id="reset-form" autocomplete="off" data-token="{{ $token }}">
    <label><span>Nové heslo</span><input id="password" type="password" autocomplete="new-password" required minlength="12"></label>
    <p class="hint">Alespoň 12 znaků, písmena i číslice.</p>
    <label><span>Heslo znovu</span><input id="password2" type="password" autocomplete="new-password" required minlength="12"></label>
    <button class="btn" type="submit" id="submit">Uložit heslo a přihlásit</button>
    <span class="error" id="error" hidden></span>
  </form>
  <div class="done" id="done" hidden>
    <strong>Heslo je nastavené.</strong> Přesměrujeme vás do klientského panelu… <a href="/panel">Otevřít panel</a>
  </div>
</main>
<script>
(function () {
  var form = document.getElementById('reset-form'), err = document.getElementById('error'), btn = document.getElementById('submit');
  function fail(text) { err.textContent = text; err.hidden = false; btn.disabled = false; }
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    err.hidden = true;
    var p1 = document.getElementById('password').value, p2 = document.getElementById('password2').value;
    if (p1.length < 12 || !/[0-9]/.test(p1) || !/[a-zA-Z]/.test(p1)) { fail('Heslo musí mít alespoň 12 znaků, písmena i číslice.'); return; }
    if (p1 !== p2) { fail('Hesla se neshodují.'); return; }
    btn.disabled = true;
    fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(function () {}).then(function () {
      return fetch('/v1/auth/password/reset/confirm', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf(), 'Accept-Language': 'cs' }, body: JSON.stringify({ token: form.getAttribute('data-token'), password: p1 }) });
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); }).then(function (res) {
      if (!res.ok) { var b = res.body || {}; var first = b.errors ? Object.keys(b.errors)[0] : null; fail((first && b.errors[first][0]) || b.message || 'Heslo se nepodařilo uložit — odkaz mohl vypršet. Požádejte o nový na stránce Obnova hesla.'); return; }
      form.hidden = true; document.getElementById('done').hidden = false;
      var target = (res.body && res.body.data && res.body.data.surface === 'admin') ? '/sprava' : '/panel';
      setTimeout(function () { location.href = target; }, 1200);
    }).catch(function () { fail('Spojení se nezdařilo, zkuste to znovu.'); });
  });
})();
</script>
</body>
</html>
