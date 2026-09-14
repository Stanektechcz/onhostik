<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 15px; line-height: 1.6; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 900px; margin: 0 auto; padding: 32px 24px 64px; }
  h1 { font-size: 28px; margin: 0 0 6px; line-height: 1.2; }
  .meta { color: var(--color-neutral-700, #605d5d); font-size: 13px; margin-bottom: 28px; }
  article h2 { font-size: 18px; margin: 32px 0 10px; letter-spacing: .02em; }
  article h3 { font-size: 15.5px; margin: 22px 0 8px; }
  article p, article li { max-width: 78ch; }
  article table { border-collapse: collapse; font-size: 14px; margin: 12px 0; }
  article th, article td { text-align: left; padding: 6px 10px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 18%, transparent); vertical-align: top; }
  .others { margin-top: 48px; padding-top: 18px; border-top: 2px solid var(--color-text, #201e1d); font-size: 13.5px; }
  .others a { margin-right: 18px; color: inherit; }
  footer { max-width: 900px; margin: 0 auto; padding: 0 24px 40px; color: var(--color-neutral-700, #605d5d); font-size: 12.5px; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Dokumenty</span>
  <a href="/">← onhost.cz</a>
  <a href="/dokumenty">Všechny dokumenty</a>
  <span class="spacer"></span>
  <span>{{ $entity?->name ?? 'ONhost' }}</span>
</header>
<main>
  <h1>{{ $title }}</h1>
  <div class="meta">Verze {{ $document->version }} · účinná od {{ $document->effective_from?->format('j. n. Y') }}@if ($document->effective_to) · do {{ $document->effective_to->format('j. n. Y') }}@endif · {{ $document->required_for_checkout ? 'součást každé objednávky' : 'platí pro uvedené služby' }}</div>
  <article>{!! $html !!}</article>
  <div class="others">
    <strong>Další dokumenty:</strong>
    @foreach ($others as $other)
      <a href="/dokumenty/{{ $other['slug'] }}">{{ $other['title'] }}</a>
    @endforeach
  </div>
</main>
<footer>
  {{ $entity?->name ?? 'ONhost' }}@if ($entity?->ico) · IČO {{ $entity->ico }}@endif @if ($entity?->dic) · DIČ {{ $entity->dic }}@endif @if ($entity) · {{ implode(', ', array_filter([(string) ($entity->address['street'] ?? ''), trim(((string) ($entity->address['postal_code'] ?? '')).' '.((string) ($entity->address['city'] ?? '')))])) }}@endif
</footer>
</body>
</html>
