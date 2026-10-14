<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>Protokol ověřené obnovy {{ $run->id }}</title>
<style>
  @page { margin: 22mm 18mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #201e1d; margin: 0; }
  h1 { font-size: 18pt; margin: 0 0 4mm; letter-spacing: -.02em; }
  h2 { font-size: 11pt; margin: 6mm 0 2mm; }
  .kicker { font-size: 8pt; letter-spacing: .14em; text-transform: uppercase; color: #ec3013; font-weight: bold; }
  .rule { border-top: 2px solid #201e1d; margin: 5mm 0; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; padding: 1.6mm 1.5mm; }
  th { text-align: left; font-size: 8pt; letter-spacing: .08em; text-transform: uppercase; border-bottom: 2px solid #201e1d; }
  .rows td { border-bottom: 1px solid rgba(32,30,29,.25); }
  .muted { color: rgba(32,30,29,.62); font-size: 8.5pt; }
  .box { background: #eae9e9; padding: 3mm 4mm; }
  .st { white-space: nowrap; font-weight: bold; }
</style>
</head>
<body>
{{--
  W2-4: the protocol of one verified restore. Only facts with their timestamps: each step is "prošlo", "neprošlo" or "neověřeno",
  the measured RPO and RTO, what was and what could not be suppressed in the copy, and what the run does not say. It never calls
  anything safe or promised; VerifiedRestoreProtocol keeps the words out (a test reads the HTML this PDF is made of).
--}}
<div class="kicker">ONhost · Ověřená obnova / Verified restore</div>
<h1>Protokol ověřené obnovy</h1>
<table>
  <tr><td style="width:38%">Protokol</td><td>{{ $run->id }}</td></tr>
  <tr><td>Služba</td><td>{{ $serviceLabel }} ({{ $run->service_id }})</td></tr>
  <tr><td>Spuštěno</td><td>{{ $run->trigger === 'staff' ? 'na žádost podpory' : 'podle plánu doplňku' }}</td></tr>
  <tr><td>Výsledek obnovy</td><td class="st">{{ $outcomeText }}</td></tr>
</table>
<div class="rule"></div>

<h2>Časy (UTC)</h2>
<table class="rows">
  <tr><td style="width:38%">Záloha dokončena</td><td>{{ $at($run->backup_taken_at) }}</td></tr>
  <tr><td>Začátek obnovy</td><td>{{ $at($run->started_at) }}</td></tr>
  <tr><td>Kontroly dokončeny</td><td>{{ $at($run->checks_done_at) }}</td></tr>
  <tr><td>Protokol uzavřen</td><td>{{ $at($run->finished_at) }}</td></tr>
  <tr><td>RPO (stáří obnovené zálohy v okamžiku obnovy)</td><td>{{ $seconds($run->rpo_seconds) }}</td></tr>
  <tr><td>RTO (od začátku obnovy po dokončení kontrol)</td><td>{{ $seconds($run->rto_seconds) }}</td></tr>
</table>

<h2>Kroky</h2>
<table class="rows">
  <tr><th style="width:30%">Krok</th><th style="width:14%">Stav</th><th style="width:20%">Čas (UTC)</th><th>Zjištění</th></tr>
  @foreach ($steps as $step)
  <tr><td>{{ $step['label'] }}</td><td class="st">{{ $step['status_text'] }}</td><td>{{ $step['at'] }}</td><td>{{ $step['detail'] }}</td></tr>
  @endforeach
</table>

@if ($skipped !== [])
<h2>Přeskočené neúplné zálohy</h2>
<table class="rows">
  @foreach ($skipped as $set)
  <tr><td style="width:38%">{{ $set['backup_id'] ?? '' }} ({{ $set['taken_at'] ?? '' }})</td><td>{{ $set['reason'] ?? '' }}</td></tr>
  @endforeach
</table>
@endif

<h2>Potlačení vedlejších účinků v kopii</h2>
<p class="muted">Provedeno:</p>
<ul>
  @forelse ($applied as $line)<li>{{ $line }}</li>@empty<li>nic</li>@endforelse
</ul>
<p class="muted">Nebylo možné provést:</p>
<ul>
  @forelse ($notApplied as $line)<li>{{ $line }}</li>@empty<li>nic</li>@endforelse
</ul>

<h2>Co tento protokol říká a co ne</h2>
<div class="box">
  @foreach ($limits as $line)
  <p>{{ $line }}</p>
  @endforeach
</div>
</body>
</html>
