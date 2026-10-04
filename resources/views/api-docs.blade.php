<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dokumentace API · ONhost</title>
<meta name="description" content="Jak zavolat API ONhost: první volání, hlavičky a limity, webhooky, index chybových kódů a úplná reference z OpenAPI smlouvy.">
<style>
:root { color-scheme: light dark; --bg: #fff; --fg: #1a1918; --muted: #5d5a57; --line: #d8d6d4; --soft: #f3f2f2; --acc: #ae1800; --code-bg: #1a1918; --code-fg: #f3f2f2; }
@media (prefers-color-scheme: dark) { :root { --bg: #141312; --fg: #f3f2f2; --muted: #b5b1ad; --line: #3a3836; --soft: #1f1d1c; --acc: #ff6a4d; --code-bg: #0c0b0b; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
a { color: var(--acc); }
header.top { border-bottom: 1px solid var(--line); background: var(--soft); }
.wrap { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
header.top .wrap { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: center; justify-content: space-between; padding-top: 14px; padding-bottom: 14px; }
header.top strong { font-size: 18px; letter-spacing: -.01em; }
nav.jump { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 14px; }
h1 { font-size: clamp(28px, 5vw, 40px); line-height: 1.15; letter-spacing: -.02em; margin: 36px 0 8px; }
h2 { font-size: 26px; letter-spacing: -.015em; margin: 52px 0 10px; scroll-margin-top: 12px; }
h3 { font-size: 17px; margin: 26px 0 6px; }
p { margin: 8px 0; }
.lead { color: var(--muted); max-width: 70ch; }
code, pre { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
:not(pre) > code { background: var(--soft); padding: 1px 5px; border-radius: 3px; }
pre { background: var(--code-bg); color: var(--code-fg); padding: 14px 16px; overflow-x: auto; margin: 10px 0; line-height: 1.55; }
table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 14px; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { background: var(--soft); font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
.tablewrap { overflow-x: auto; }
ol.steps { padding-left: 22px; }
ol.steps li { margin: 18px 0; }
.method { font-family: ui-monospace, Menlo, monospace; font-weight: 700; font-size: 12px; padding: 2px 6px; border: 1px solid var(--line); }
input[type=search] { width: 100%; max-width: 420px; padding: 9px 12px; font: inherit; border: 1px solid var(--line); background: var(--bg); color: var(--fg); }
.errs td:first-child { white-space: nowrap; }
.errs tr:target td { background: color-mix(in srgb, var(--acc) 14%, transparent); }
.changes article { border-top: 1px solid var(--line); padding: 14px 0; }
.changes .tag { display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border: 1px solid var(--line); }
.changes .tag.breaking { background: #c1121f; color: #fff; border-color: #c1121f; }
#reference { margin-top: 12px; border-top: 2px solid var(--fg); }
footer { margin: 60px 0 30px; color: var(--muted); font-size: 13px; }
.noscript { padding: 14px; background: var(--soft); }
</style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <strong>ONhost API</strong>
    <nav class="jump" aria-label="Obsah stránky">
      <a href="#prvni-volani">První volání</a>
      <a href="#hlavicky-a-limity">Hlavičky a limity</a>
      <a href="#webhooky">Webhooky</a>
      <a href="#chyby">Chyby</a>
      <a href="#zmeny">Změny API</a>
      <a href="#reference">Reference</a>
      <a href="/openapi.yaml">openapi.yaml</a>
      <a href="/dokumentace">Dokumentace</a>
    </nav>
  </div>
</header>
<main class="wrap">
  <h1>Dokumentace API</h1>
  <p class="lead">Adresa API je <code>{{ $base }}</code>. Klíč s rozsahem se posílá v hlavičce <code>Authorization: Bearer</code>. Všechno níže se skládá z toho, co API opravdu dělá — z tras, konfigurace a kódu — a testy hlídají, že se to nerozejde.</p>

  <h2 id="prvni-volani">První volání za 5 minut</h2>
  <p>Příklady jsou pro <code>curl</code>. Klíč vytvoříte v panelu v části Účet → API klíče; rozsahy klíče: @foreach ($scopes as $scope)<code>{{ $scope }}</code>@if (! $loop->last), @endif @endforeach.</p>
  <ol class="steps">
    @foreach ($steps as $step)
      <li>
        <strong>{{ $step['title'] }}</strong>
        <p>{!! preg_replace('~`([^`]+)`~', '<code>$1</code>', e(str_replace('{base}', $base, $step['text']))) !!}</p>
        <pre><code>{{ str_replace('{base}', $base, $step['code']) }}</code></pre>
      </li>
    @endforeach
  </ol>

  <h2 id="hlavicky-a-limity">Hlavičky a limity</h2>
  <p>Na klíč je to <strong>{{ $limits['default'] }}</strong> požadavků za minutu (klíči lze nastavit jiný limit), u veřejných koncových bodů <strong>{{ $limits['public'] }}</strong> za minutu na adresu. Seznamy se stránkují přes <code>limit</code> (výchozí {{ $limits['page'] }}, nejvýš {{ $limits['max_page'] }}) a <code>offset</code>.</p>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Hlavička</th><th>Směr</th><th>Význam</th></tr></thead>
      <tbody>
        @foreach ($headers as [$name, $direction, $meaning])
          <tr><td><code>{{ $name }}</code></td><td>{{ $direction }}</td><td>{!! preg_replace('~`([^`]+)`~', '<code>$1</code>', e($meaning)) !!}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h3>Koncové body, které klíč zvládne</h3>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Metoda a cesta</th><th>Co dělá</th><th>Rozsah klíče</th></tr></thead>
      <tbody>
        @foreach ($endpoints as $e)
          <tr><td><span class="method">{{ $e['m'] }}</span> <code>{{ $e['p'] }}</code></td><td>{{ $e['d'] }}</td><td>{{ $e['scope'] }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p>Úplný seznam všech operací, schémat a odpovědí je v <a href="#reference">referenci</a> níže.</p>

  <h2 id="webhooky">Webhooky</h2>
  <p>Odběr událostí zakládáte v panelu. Každé doručení je <code>POST</code> s JSON obálkou:</p>
  <pre><code>{{ $envelope }}</code></pre>
  <p>Hlavičky: <code>X-ONhost-Event</code>, <code>X-ONhost-Delivery</code>, <code>X-ONhost-Timestamp</code> a <code>X-ONhost-Signature: v1=&lt;hex&gt;</code>, kde <code>hex</code> je HMAC-SHA256 tajemstvím odběru z textu <code>&lt;timestamp&gt;.&lt;tělo&gt;</code>. Události patří do rodin: @foreach ($families as $family)<code>{{ $family }}.*</code>@if (! $loop->last), @endif @endforeach.</p>
  <h3 id="webhooky-opakovani">Opakování</h3>
  <p>{{ $retrySentence }}.</p>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Pokus</th><th>Čas od prvního pokusu</th></tr></thead>
      <tbody>
        @foreach ($retry['offsets'] as $i => $minutes)
          <tr><td>{{ $i + 1 }}</td><td>{{ $minutes === 0 ? 'hned' : \App\Http\Support\PublicApiDocs::duration($minutes) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h2 id="chyby">Chyby</h2>
  <p>Každá chyba je JSON <code>{"error": "kód", "message": "věta", "status": 403, "help": "/dokumentace/api#kód"}</code>. Parsujte <code>error</code>, ne větu. Níže je každý kód, který kód platformy umí vrátit ({{ count($errors) }}); odkaz <code>help</code> vede přímo na jeho řádek. Kromě těchto kódů vrací rámec <code>http_&lt;status&gt;</code> pro stavy bez vlastního kódu.</p>
  <p><label>Hledat kód nebo text: <input type="search" id="err-filter" placeholder="např. idempotency" autocomplete="off"></label></p>
  <div class="tablewrap">
    <table class="errs" id="err-table">
      <thead><tr><th>Kód</th><th>HTTP</th><th>Význam</th></tr></thead>
      <tbody>
        @foreach ($errors as $slug => $row)
          @php($anchor = \App\Http\Support\PublicApiDocs::anchor($slug))
          <tr id="{{ $anchor }}">
            <td>@if ($anchor !== $slug)<a id="{{ $slug }}"></a>@endif<a href="#{{ $anchor }}"><code>{{ $slug }}</code></a></td>
            <td>{{ $row['statuses'] === [] ? '—' : implode(', ', $row['statuses']) }}</td>
            <td>{!! preg_replace('~`([^`]+)`~', '<code>$1</code>', e($row['message'])) !!}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h2 id="zmeny">Změny API</h2>
  <div class="changes">
    @foreach ($changes as $c)
      <article>
        <strong>{!! preg_replace('~`([^`]+)`~', '<code>$1</code>', e($c['t'])) !!}</strong>
        <span class="tag {{ $c['tag'] === 'nekompatibilní' ? 'breaking' : '' }}">{{ $c['tag'] }}</span>
        <div style="color:var(--muted);font-size:13px">{{ $c['date'] }}@if ($c['win'] !== '—') · okno {{ $c['win'] }}@endif</div>
        <p>{!! preg_replace('~`([^`]+)`~', '<code>$1</code>', e($c['d'])) !!}</p>
      </article>
    @endforeach
  </div>

  <h2 id="reference">Reference</h2>
  <p>Vykresleno z <a href="/openapi.yaml">openapi.yaml</a> prohlížečem Redoc, který běží z našeho serveru (žádný externí skript).</p>
</main>
<div id="redoc" data-spec-url="/openapi.yaml"><p class="wrap noscript">Reference potřebuje JavaScript. Smlouvu si můžete stáhnout jako <a href="/openapi.yaml">openapi.yaml</a>.</p></div>
<footer class="wrap">Verze Redoc je přibalená v <code>public/vendor/redoc</code>; smlouva se generuje příkazem <code>php artisan onhost:openapi</code>.</footer>
<script src="/vendor/redoc/redoc.standalone.js"></script>
<script src="/vendor/redoc/api-docs.js"></script>
</body>
</html>
