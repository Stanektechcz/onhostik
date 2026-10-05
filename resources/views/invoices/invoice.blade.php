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
  .lines td, .recap td { border-bottom: 1px solid rgba(32,30,29,.25); }
  .num { text-align: right; white-space: nowrap; }
  .muted { color: rgba(32,30,29,.62); font-size: 9pt; }
  .total { font-size: 14pt; font-weight: bold; }
  .box { background: #eae9e9; padding: 3mm 4mm; }
</style>
</head>
<body>
{{--
  G2: one template for every document, Czech and English side by side. What it prints comes from the frozen document (seller
  incl. its VAT mode, buyer, lines, recap), never from today's configuration. A tax document of a VAT payer carries every particular
  of § 29 of the VAT act: both parties with their numbers, the number, the date of issue and the DUZP, what was supplied (quantity,
  unit price), the base, rate and VAT per rate, the total — and the CZK recap of a document in another currency (§ 29 (1) l).
  The variables after `title` are optional (older callers render with invoice, lines, money and title only).
--}}
@php($taxDocument = $taxDocument ?? (in_array($invoice->type, \Onhost\Domain\Invoicing\CzkTaxStatement::TYPES, true) && (bool) ($invoice->seller['vat_payer'] ?? true)))
@php($sellerVatPayer = $sellerVatPayer ?? (bool) ($invoice->seller['vat_payer'] ?? true))
@php($advances = $advances ?? [])
@php($advanceVat = $advanceVat ?? [])
@php($deducted = $deducted ?? 0)
@php($date = fn ($d) => $d === null ? '' : \Illuminate\Support\Carbon::parse($d)->timezone('Europe/Prague')->format('d.m.Y'))
{{-- only trailing decimal zeros go: rtrim('0') turned a stored "0" into "" (a reverse-charge line read " %") and "10" into "1" --}}
@php($num = fn ($v) => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v)
<table>
  <tr>
    <td style="width:55%">
      <div class="kicker">{{ $title }}</div>
      <h1>{{ $invoice->number }}</h1>
      <div class="muted">Datum vystavení / Date of issue: {{ $invoice->issued_at?->timezone('Europe/Prague')->format('d.m.Y') }}<br>
      @if($taxDocument)Datum uskutečnění zdanitelného plnění / Date of taxable supply: {{ $invoice->supply_date?->format('d.m.Y') }}<br>@endif
      @if($invoice->type !== 'receipt' && $invoice->type !== \Onhost\Domain\Invoicing\InvoiceService::PAYMENT_CONFIRMATION)Splatnost / Due: {{ $invoice->due_at?->timezone('Europe/Prague')->format('d.m.Y') }}<br>@endif
      Variabilní symbol / Reference: {{ $invoice->payment_reference }}
      @if($invoice->corrects_invoice_id)<br>Opravuje doklad / Corrects: {{ $invoice->meta['original_number'] ?? '' }}@endif
      @if(!empty($invoice->meta['advance_for']['proforma_number']))<br>K zálohové faktuře / For proforma: {{ $invoice->meta['advance_for']['proforma_number'] }}@endif
      @if(!empty($invoice->meta['order_number']))<br>Objednávka / Order: {{ $invoice->meta['order_number'] }}@endif
      </div>
    </td>
    <td style="width:45%" class="box">
      <strong>Dodavatel / Supplier</strong><br>
      {{ $invoice->seller['name'] ?? '' }}<br>
      {{ $invoice->seller['address']['street'] ?? '' }}, {{ $invoice->seller['address']['postal_code'] ?? '' }} {{ $invoice->seller['address']['city'] ?? '' }}@if(!empty($invoice->seller['country'])), {{ $invoice->seller['country'] }}@endif<br>
      IČO {{ $invoice->seller['ico'] ?? '' }}@if($sellerVatPayer) · DIČ {{ ($invoice->seller['dic'] ?? '') ?: ($invoice->seller['vat_id'] ?? '') }}@endif<br>
      @unless($sellerVatPayer)Neplátce DPH / Not registered for VAT<br>@endunless
      @if(!empty($invoice->seller['registry']))<span class="muted">{{ $invoice->seller['registry'] }}</span><br>@endif
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
      @if(!empty($invoice->buyer['vat_id']))· DIČ {{ $invoice->buyer['vat_id'] }} / VAT ID @endif
    </td>
    <td class="muted">Způsob úhrady / Payment: {{ $invoice->payment_method ?? '—' }}<br>
      Měna / Currency: {{ $invoice->currency }}<br>
      Stav / State: {{ $invoice->state }}</td>
  </tr>
</table>
<div class="rule"></div>
<table class="lines">
  <thead><tr><th>Položka / Item</th><th class="num">Množství / Qty</th><th class="num">Cena za jednotku bez DPH / Unit price</th><th class="num">Základ / Net</th>@if($sellerVatPayer)<th class="num">DPH % / VAT %</th><th class="num">DPH / VAT</th>@endif<th class="num">Celkem / Total</th></tr></thead>
  <tbody>
  @foreach($lines as $line)
    <tr>
      <td>{{ $line->description }}@if($line->period_from)<br><span class="muted">{{ $line->period_from->format('d.m.Y') }} – {{ $line->period_to?->format('d.m.Y') }}</span>@endif</td>
      <td class="num">{{ $num($line->qty) }} {{ $line->unit }}</td>
      <td class="num">{{ $money($line->unit_net_minor) }}</td>
      <td class="num">{{ $money($line->net_minor) }}</td>
      @if($sellerVatPayer)<td class="num">{{ $num($line->tax_rate) }} %</td>
      <td class="num">{{ $money($line->tax_minor) }}</td>@endif
      <td class="num">{{ $money($line->total_minor) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
{{-- a VAT payer's proforma and statement show the VAT they stand for too; only a tax document carries the DUZP and counts --}}
@if($sellerVatPayer && !empty($invoice->tax_summary))
<table class="recap" style="margin-top:5mm">
  <thead><tr><th>Rekapitulace DPH / VAT summary</th><th class="num">Sazba / Rate</th><th class="num">Základ daně / Tax base</th><th class="num">DPH / VAT</th><th class="num">Celkem / Total</th></tr></thead>
  <tbody>
  @foreach($invoice->tax_summary as $row)
    <tr><td>Sazba {{ $num($row['rate']) }} % ({{ $row['category'] }})</td><td class="num">{{ $num($row['rate']) }} %</td><td class="num">{{ $money((int) $row['net']) }}</td><td class="num">{{ $money((int) $row['tax']) }}</td><td class="num">{{ $money((int) $row['net'] + (int) $row['tax']) }}</td></tr>
  @endforeach
  </tbody>
</table>
@endif
@if($advances !== [])
<table class="recap" style="margin-top:5mm">
  <thead><tr><th>Zúčtování zálohy / Advance settlement</th><th class="num">Sazba / Rate</th><th class="num">Základ daně / Tax base</th><th class="num">DPH / VAT</th><th class="num">Celkem / Total</th></tr></thead>
  <tbody>
  @foreach($advances as $advance)
    <tr><td colspan="5">Odpočet zálohy — daňový doklad k přijaté platbě / Advance deducted — tax receipt {{ $advance['number'] ?? '' }} (DUZP {{ $date($advance['supply_date'] ?? null) }})@if(!empty($advance['proforma_number'])) · zálohová faktura {{ $advance['proforma_number'] }}@endif</td></tr>
  @endforeach
  @foreach($advanceVat as $row)
    <tr><td>Odečteno / Deducted</td><td class="num">{{ $num($row['rate']) }} %</td><td class="num">{{ $money(-$row['net']) }}</td><td class="num">{{ $money(-$row['tax']) }}</td><td class="num">{{ $money(-($row['net'] + $row['tax'])) }}</td></tr>
  @endforeach
  @foreach($invoice->tax_summary ?? [] as $row)
    @php($key = $row['rate'].'|'.$row['category'])
    <tr><td>Rozdíl k vyúčtování / Difference</td><td class="num">{{ $num($row['rate']) }} %</td><td class="num">{{ $money((int) $row['net'] - (int) ($advanceVat[$key]['net'] ?? 0)) }}</td><td class="num">{{ $money((int) $row['tax'] - (int) ($advanceVat[$key]['tax'] ?? 0)) }}</td><td class="num">{{ $money((int) $row['net'] + (int) $row['tax'] - (int) ($advanceVat[$key]['net'] ?? 0) - (int) ($advanceVat[$key]['tax'] ?? 0)) }}</td></tr>
  @endforeach
  </tbody>
</table>
@endif
<table style="margin-top:6mm">
  <tr><td style="width:60%" class="muted">
    @if(!empty($invoice->meta['czk']) && $taxDocument)
      @php($czk = $invoice->meta['czk'])
      @php($kc = fn (int $minor) => number_format($minor / 100, 2, ',', ' ').' Kč')
      Kurz ČNB {{ $czk['basis'] === 'original' ? 'původního plnění' : 'ke dni plnění' }} ({{ \Illuminate\Support\Carbon::parse($czk['valid_on'])->format('d.m.Y') }}): {{ $czk['amount'] }} {{ $czk['currency'] }} = {{ str_replace('.', ',', $czk['rate']) }} CZK / CNB rate<br>
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
    @if($taxDocument && collect($lines)->contains(fn ($l) => $l->tax_category === 'O'))
      <br>Místo plnění mimo EU — není předmětem české DPH. / Outside the scope of Czech VAT.
    @endif
    @if($taxDocument)
      <br>DPH vypočtena ze základu a zaokrouhlena na haléře (§ 37 zákona o DPH). / VAT computed from the base, rounded to the haléř.
    @endif
    @unless($taxDocument)
      <br>Tento doklad není daňovým dokladem. / This document is not a tax document.
    @endunless
  </td>
  <td class="num">
    Bez DPH / Net: {{ $money($invoice->subtotal_minor - $invoice->discount_minor) }}<br>
    @if($sellerVatPayer)DPH / VAT: {{ $money($invoice->tax_minor) }}<br>@endif
    <span class="total">Celkem / Total: {{ $money($invoice->total_minor) }}</span>
    @if($advances !== [])
      <br>Uhrazeno zálohou / Paid in advance: {{ $money(-$deducted) }}
      <br><strong>Zbývá uhradit / Amount due: {{ $money(max(0, (int) $invoice->total_minor - $deducted)) }}</strong>
    @elseif(in_array($invoice->type, ['invoice', 'proforma'], true))
      <br><strong>Celkem k úhradě / Total due: {{ $money(max(0, (int) $invoice->total_minor - (int) $invoice->credited_minor)) }}</strong>
    @endif
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
