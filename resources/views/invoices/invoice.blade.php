<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>{{ $invoice->number }}</title>
<style>
  @page { margin: 22mm 18mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #201e1d; margin: 0; }
  h1 { font-size: 20pt; margin: 0 0 4mm; letter-spacing: -.02em; }
  .kicker { font-size: 8pt; letter-spacing: .14em; text-transform: uppercase; color: #ec3013; font-weight: bold; }
  .rule { border-top: 2px solid #201e1d; margin: 5mm 0; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; padding: 2mm 1.5mm; }
  th { text-align: left; font-size: 8.5pt; letter-spacing: .08em; text-transform: uppercase; border-bottom: 2px solid #201e1d; }
  .lines td { border-bottom: 1px solid rgba(32,30,29,.25); }
  .num { text-align: right; white-space: nowrap; }
  .muted { color: rgba(32,30,29,.62); font-size: 9pt; }
  .total { font-size: 14pt; font-weight: bold; }
  .box { background: #eae9e9; padding: 3mm 4mm; }
</style>
</head>
<body>
<table>
  <tr>
    <td style="width:55%">
      <div class="kicker">{{ $title }}</div>
      <h1>{{ $invoice->number }}</h1>
      <div class="muted">Vystaveno / Issued: {{ $invoice->issued_at?->timezone('Europe/Prague')->format('d.m.Y') }}<br>
      DUZP / Supply date: {{ $invoice->supply_date?->format('d.m.Y') }}<br>
      Splatnost / Due: {{ $invoice->due_at?->timezone('Europe/Prague')->format('d.m.Y') }}<br>
      Variabilní symbol / Reference: {{ $invoice->payment_reference }}
      @if($invoice->corrects_invoice_id)<br>Opravuje doklad / Corrects: {{ $invoice->meta['original_number'] ?? '' }}@endif
      </div>
    </td>
    <td style="width:45%" class="box">
      <strong>Dodavatel / Supplier</strong><br>
      {{ $invoice->seller['name'] ?? '' }}<br>
      {{ $invoice->seller['address']['street'] ?? '' }}, {{ $invoice->seller['address']['postal_code'] ?? '' }} {{ $invoice->seller['address']['city'] ?? '' }}<br>
      IČO {{ $invoice->seller['ico'] ?? '' }} · DIČ {{ $invoice->seller['dic'] ?? '' }}<br>
      IBAN {{ $invoice->seller['iban'] ?? '' }} · BIC {{ $invoice->seller['bic'] ?? '' }}
    </td>
  </tr>
</table>
<div class="rule"></div>
<table>
  <tr>
    <td style="width:55%">
      <strong>Odběratel / Customer</strong><br>
      {{ $invoice->buyer['name'] ?? '' }}<br>
      {{ $invoice->buyer['street'] ?? '' }}<br>
      {{ $invoice->buyer['postal_code'] ?? '' }} {{ $invoice->buyer['city'] ?? '' }}, {{ $invoice->buyer['country'] ?? '' }}<br>
      @if(!empty($invoice->buyer['ico']))IČO {{ $invoice->buyer['ico'] }} @endif
      @if(!empty($invoice->buyer['vat_id']))· DIČ / VAT ID {{ $invoice->buyer['vat_id'] }}@endif
    </td>
    <td class="muted">Způsob úhrady / Payment: {{ $invoice->payment_method ?? '—' }}<br>
      Měna / Currency: {{ $invoice->currency }}<br>
      Stav / State: {{ $invoice->state }}</td>
  </tr>
</table>
<div class="rule"></div>
{{-- only trailing decimal zeros go: rtrim('0') turned a stored "0" into "" (a reverse-charge line read " %") and "10" into "1" --}}
@php($num = fn ($v) => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v)
<table class="lines">
  <thead><tr><th>Položka / Item</th><th class="num">Množství</th><th class="num">Cena bez DPH</th><th class="num">DPH %</th><th class="num">DPH</th><th class="num">Celkem</th></tr></thead>
  <tbody>
  @foreach($lines as $line)
    <tr>
      <td>{{ $line->description }}@if($line->period_from)<br><span class="muted">{{ $line->period_from->format('d.m.Y') }} – {{ $line->period_to?->format('d.m.Y') }}</span>@endif</td>
      <td class="num">{{ $num($line->qty) }} {{ $line->unit }}</td>
      <td class="num">{{ $money($line->net_minor) }}</td>
      <td class="num">{{ $num($line->tax_rate) }} %</td>
      <td class="num">{{ $money($line->tax_minor) }}</td>
      <td class="num">{{ $money($line->total_minor) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
<table style="margin-top:6mm">
  <tr><td style="width:60%" class="muted">
    @foreach($invoice->tax_summary ?? [] as $row)
      Sazba {{ $num($row['rate']) }} % ({{ $row['category'] }}): základ {{ $money($row['net']) }}, DPH {{ $money($row['tax']) }}<br>
    @endforeach
    @if(!empty($invoice->meta['czk']))
      @php($czk = $invoice->meta['czk'])
      @php($kc = fn (int $minor) => number_format($minor / 100, 2, ',', ' ').' Kč')
      <br>Kurz ČNB {{ $czk['basis'] === 'original' ? 'původního plnění' : 'ke dni plnění' }} ({{ \Illuminate\Support\Carbon::parse($czk['valid_on'])->format('d.m.Y') }}): {{ $czk['amount'] }} {{ $czk['currency'] }} = {{ str_replace('.', ',', $czk['rate']) }} CZK / CNB rate<br>
      @foreach($czk['summary'] as $row)
        DPH v CZK — sazba {{ $num($row['rate']) }} %: základ {{ $kc($row['net_minor']) }}, daň {{ $kc($row['tax_minor']) }}<br>
      @endforeach
      DPH celkem v CZK / VAT in CZK: <strong>{{ $kc($czk['tax_minor']) }}</strong><br>
    @endif
    @if(collect($lines)->contains(fn ($l) => $l->tax_category === 'AE'))
      <br>Daň odvede zákazník (reverse charge, čl. 196 směrnice 2006/112/ES). / VAT to be accounted for by the recipient.
      {{-- TASK-0031 (D31.4): both VAT IDs and the VIES check the reverse charge rests on --}}
      @php($vatCheck = is_array($invoice->meta['vat'] ?? null) ? $invoice->meta['vat'] : ($invoice->buyer['vat_check'] ?? null))
      <br>DIČ dodavatele {{ ($invoice->seller['vat_id'] ?? null) ?: ($invoice->seller['dic'] ?? '') }} · DIČ odběratele {{ $invoice->buyer['vat_id'] ?? '' }}@if(is_array($vatCheck) && ($vatCheck['source'] ?? null) === 'staff') · registrace k DPH doložena mimo VIES @elseif(is_array($vatCheck) && !empty($vatCheck['checked_at'])) · ověřeno ve VIES {{ \Illuminate\Support\Carbon::parse($vatCheck['checked_at'])->timezone('Europe/Prague')->format('d.m.Y') }}@if(!empty($vatCheck['consultation_number'])) (č. {{ $vatCheck['consultation_number'] }})@endif @endif
    @endif
    @if(collect($lines)->contains(fn ($l) => $l->tax_category === 'O'))
      <br>Místo plnění mimo EU — není předmětem české DPH. / Outside the scope of Czech VAT.
    @endif
  </td>
  <td class="num">
    Bez DPH / Net: {{ $money($invoice->subtotal_minor - $invoice->discount_minor) }}<br>
    DPH / VAT: {{ $money($invoice->tax_minor) }}<br>
    <span class="total">Celkem / Total: {{ $money($invoice->total_minor) }}</span>
    @if($invoice->state === 'PAID')<br><span class="muted">Uhrazeno / Paid {{ $invoice->paid_at?->timezone('Europe/Prague')->format('d.m.Y') }}</span>@endif
  </td></tr>
</table>
<div class="rule"></div>
<div class="muted">Doklad byl vystaven elektronicky systémem ONhost Control Plane. Hash dokumentu je uložen v auditním záznamu. {{ $invoice->note }}</div>
@if(!empty($invoice->meta['green']) && (int) ($invoice->meta['green']['services'] ?? 0) > 0)
<div class="muted">Uhlíková stopa služeb v období (odhad podle prodané paměti, PUE a intenzity dodavatele energie): {{ number_format((float) $invoice->meta['green']['kwh'], 1, ',', ' ') }} kWh · {{ number_format((float) $invoice->meta['green']['gco2'] / 1000, 2, ',', ' ') }} kg CO₂e · {{ (int) $invoice->meta['green']['renewable_pct'] }} % z obnovitelných zdrojů / Carbon footprint of the period (estimate).</div>
@endif
</body>
</html>
