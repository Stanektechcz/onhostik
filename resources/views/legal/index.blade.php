<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dokumenty — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 15px; line-height: 1.6; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  main { max-width: 900px; margin: 0 auto; padding: 32px 24px 64px; }
  h1 { font-size: 28px; margin: 0 0 18px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-text, #201e1d); }
  td { padding: 10px 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); }
  a { color: inherit; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Dokumenty</span>
  <a href="/">← onhost.cz</a>
</header>
<main>
  <h1>Smluvní dokumenty</h1>
  <p>Aktuální verze dokumentů, se kterými zákazník souhlasí při objednávce a při registraci domény. Každá objednávka uchovává verzi, kterou zákazník odsouhlasil.</p>
  <table>
    <thead><tr><th>Dokument</th><th>Verze</th><th>Účinnost</th></tr></thead>
    <tbody>
    @foreach ($documents as $doc)
      <tr><td><a href="/dokumenty/{{ $doc['slug'] }}">{{ $doc['title'] }}</a></td><td>{{ $doc['version'] }}</td><td>{{ $doc['effective_from']?->format('j. n. Y') }}</td></tr>
    @endforeach
    </tbody>
  </table>
</main>
</body>
</html>
