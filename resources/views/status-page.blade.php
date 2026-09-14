<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="120">
<title>{{ $status['title'] }} · stav služeb</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f2ef; color: #201e1d; }
  main { max-width: 720px; margin: 6vh auto; padding: 0 16px; }
  .card { background: #fff; border: 1px solid #e3ded8; border-radius: 12px; padding: 20px 24px; margin-bottom: 16px; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 0 0 10px; color: #6f6862; text-transform: uppercase; letter-spacing: .04em; }
  .overall { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; }
  .dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
  .ok { background: #1f7a3f; } .warn { background: #b8860b; } .bad { background: #b3261e; } .maint { background: #3b5bdb; }
  .row { display: flex; justify-content: space-between; padding: 8px 0; border-top: 1px solid #f0ece7; font-size: 14px; }
  .row:first-of-type { border-top: 0; }
  .muted { color: #6f6862; font-size: 12px; }
</style>
</head>
<body>
<main>
@php
  $tone = ['operational' => 'ok', 'maintenance' => 'maint', 'degraded' => 'warn', 'partial_outage' => 'bad', 'major_outage' => 'bad'][$status['overall']] ?? 'warn';
  $label = ['operational' => 'Vše v provozu', 'maintenance' => 'Probíhá údržba', 'degraded' => 'Omezený provoz', 'partial_outage' => 'Částečný výpadek', 'major_outage' => 'Výpadek'][$status['overall']] ?? $status['overall'];
  $mon = ['up' => 'ok', 'down' => 'bad', 'pending' => 'warn', 'degraded' => 'warn'];
@endphp
  <div class="card">
    <h1>{{ $status['title'] }}</h1>
    <div class="overall"><span class="dot {{ $tone }}"></span>{{ $label }}</div>
    <div class="muted">Stav služeb provozovaných na platformě ONhost · aktualizováno {{ \Carbon\Carbon::parse($status['generated_at'])->format('j. n. Y H:i') }}</div>
  </div>
  @if ($status['monitors'])
  <div class="card">
    <h2>Weby a služby</h2>
    @foreach ($status['monitors'] as $m)
      <div class="row"><span><span class="dot {{ $mon[$m['state']] ?? 'warn' }}" style="margin-right:8px"></span>{{ $m['host'] }}</span><span class="muted">{{ $m['state'] === 'up' ? 'dostupný' : ($m['state'] === 'down' ? 'nedostupný' : $m['state']) }}{{ $m['ms'] !== null ? ' · '.$m['ms'].' ms' : '' }}</span></div>
    @endforeach
  </div>
  @endif
  <div class="card">
    <h2>Infrastruktura</h2>
    @forelse ($status['components'] as $c)
      <div class="row"><span>{{ $c['name'] }}</span><span class="muted">{{ ['operational' => 'v provozu', 'maintenance' => 'údržba', 'degraded' => 'omezeno', 'partial_outage' => 'částečný výpadek', 'major_outage' => 'výpadek'][$c['state']] ?? $c['state'] }}</span></div>
    @empty
      <div class="muted">Žádné komponenty k zobrazení.</div>
    @endforelse
  </div>
  @if ($status['incidents'] || $status['maintenance'])
  <div class="card">
    <h2>Incidenty a odstávky</h2>
    @foreach ($status['incidents'] as $i)
      <div class="row"><span>{{ $i['title'] }}</span><span class="muted">{{ strtoupper($i['severity']) }} · {{ $i['resolved_at'] ? 'vyřešeno' : 'řešíme' }}</span></div>
    @endforeach
    @foreach ($status['maintenance'] as $w)
      <div class="row"><span>Údržba: {{ $w['title'] }}</span><span class="muted">{{ \Carbon\Carbon::parse($w['starts_at'])->format('j. n. H:i') }} – {{ \Carbon\Carbon::parse($w['ends_at'])->format('j. n. H:i') }}</span></div>
    @endforeach
  </div>
  @endif
  <div class="muted">Stránka se obnovuje každé dvě minuty. Odznak: <code>{{ url('/stav/'.$status['slug'].'/badge.svg') }}</code></div>
</main>
</body>
</html>
