<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Nastavení hesla ke schránce</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f2ef; color: #201e1d; }
  main { max-width: 440px; margin: 8vh auto; background: #fff; border: 1px solid #e3ded8; border-radius: 12px; padding: 28px; }
  h1 { font-size: 20px; margin: 0 0 6px; }
  p { margin: 0 0 14px; line-height: 1.45; }
  label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 4px; }
  input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #c9c2ba; border-radius: 8px; font-size: 15px; }
  button { margin-top: 16px; padding: 11px 16px; border: 0; border-radius: 8px; background: #ec3013; color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; }
  .muted { color: #6f6862; font-size: 13px; }
  .err { color: #b3261e; font-weight: 600; }
</style>
</head>
<body>
<main>
@if ($done)
  <h1>Heslo je nastavené</h1>
  <p>Nové heslo platí do minuty ve všech klientech (IMAP, SMTP, webmail). Tento odkaz už nelze použít znovu.</p>
@elseif ($expired)
  <h1>Odkaz už neplatí</h1>
  <p>Odkaz pro nastavení hesla vypršel nebo byl použit. Požádejte správce účtu o nový.</p>
@else
  <h1>Nastavení hesla ke schránce</h1>
  <p>Schránka <strong>{{ $address }}</strong>. Zvolte heslo o délce 12–72 znaků; nikomu ho nesdělujte, ani nám.</p>
  @if ($error)
    <p class="err">{{ $error }}</p>
  @endif
  @if ($errors->any())
    <p class="err">{{ $errors->first() }}</p>
  @endif
  <form method="post" action="{{ url()->current() }}?{{ http_build_query(request()->query()) }}">
    @csrf
    <label for="password">Nové heslo</label>
    <input id="password" name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password">
    <label for="password_confirmation">Znovu pro kontrolu</label>
    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" required autocomplete="new-password">
    <button type="submit">Nastavit heslo</button>
    <p class="muted" style="margin-top:14px">Odkaz je jednorázový a platí 24 hodin od vystavení.</p>
  </form>
@endif
</main>
</body>
</html>
