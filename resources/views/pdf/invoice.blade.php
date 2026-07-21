@php
    // $brand is injected by InvoicePdfService for reseller invoices; empty array = default OnHost branding
    $brand       ??= [];
    $supplierName  = $brand['name']         ?? config('billing.supplier.name', 'OnHost');
    $supplierEmail = $brand['email']        ?? config('mail.from.address', 'info@onhost.cz');
    $supplierWeb   = $brand['website']      ?? 'onhost.cz';
    $supplierStreet = $brand['street']      ?? config('billing.supplier.street');
    $supplierZip   = $brand['zip']          ?? config('billing.supplier.zip');
    $supplierCity  = $brand['city']         ?? config('billing.supplier.city');
    $supplierIc    = $brand['ic']           ?? config('billing.supplier.ic');
    $supplierDic   = $brand['dic']          ?? config('billing.supplier.dic');
    $supplierBank  = $brand['bank_account'] ?? config('billing.supplier.bank_account_czk');
    $supplierFooter = $brand['footer_note'] ?? null;
    $primaryColor  = $brand['primary_color'] ?? '#7366FF';
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style type="text/css">
        @page { margin: 26px 34px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #000248;
            margin: 0;
            padding: 0;
        }
        /* A4 portrait content area is ~527pt wide — the wrapper must fill the
           page, never a fixed pixel width (1100px overflowed off the page). */
        .wrap { width: 100%; max-width: 760px; margin: 0 auto; }
        table { border-collapse: collapse; }
        /* Header */
        .header-row { display: flex; justify-content: space-between; align-items: center; padding: 24px 0 8px; }
        .logo { font-size: 22px; font-weight: 700; color: {{ $primaryColor }}; }
        .contact-pill {
            background: {{ $primaryColor }};
            padding: 14px 36px; border-bottom-left-radius: 60px;
            display: inline-block; color: #fff; font-size: 13px;
        }
        .contact-pill span { margin: 0 12px; }
        /* Billing row */
        .billing-row { display: flex; justify-content: space-between; padding: 18px 0 12px; }
        .billing-to-label { font-size: 16px; font-weight: 600; color: {{ $primaryColor }}; margin-bottom: 8px; }
        .billing-name { font-size: 16px; font-weight: 600; color: #000248; margin-bottom: 6px; }
        .billing-text { font-size: 14px; color: #52526C; opacity: 0.8; margin-bottom: 4px; }
        .invoice-title { font-size: 38px; font-weight: 600; color: #000248; margin: 0 0 10px; }
        .invoice-label { font-size: 16px; color: {{ $primaryColor }}; font-weight: 600; margin-bottom: 14px; }
        .invoice-detail { color: #52526C; margin-bottom: 8px; font-size: 14px; }
        /* Info boxes */
        .info-boxes { width: 100%; border-spacing: 4px; margin-bottom: 20px; }
        .info-box { background: #F5F6F9; padding: 12px 18px; }
        .info-box-label { font-size: 12px; font-weight: 500; color: #52526C; opacity: 0.8; margin: 0; line-height: 1.8; }
        .info-box-value { font-size: 15px; font-weight: 600; color: #000248; }
        /* Items table */
        .items { width: 100%; border-spacing: 0; margin-bottom: 0; }
        .items thead tr { background: {{ $primaryColor }}; }
        .items th { padding: 14px 14px; text-align: left; color: #fff; font-size: 15px; font-weight: 600; }
        .items th:first-child { border-top-left-radius: 8px; }
        .items th:last-child { border-top-right-radius: 8px; text-align: right; }
        .items td { padding: 13px 14px; border-bottom: 1px solid rgba(82,82,108,0.2); font-size: 13px; }
        .items td.num-col { text-align: center; width: 8%; background: #F5F6F9; }
        .items td.price-col { text-align: right; background: #F5F6F9; width: 14%; }
        .items td.qty-col { text-align: center; width: 10%; }
        .items .item-name { font-size: 15px; font-weight: 600; color: #000248; margin: 2px 0; }
        .items .item-sub { color: #52526C; font-size: 12px; opacity: 0.8; }
        /* Totals */
        .total-row td { padding: 14px 14px 10px; color: #52526C; font-size: 13px; opacity: 0.8; font-weight: 600; text-align: center; }
        .total-val td { background: #F5F6F9; text-align: right; padding: 14px 14px 10px; font-size: 13px; font-weight: 600; color: #000248; }
        .total-due-val td { background: #52526C; padding: 12px 14px; color: #fff; font-size: 15px; font-weight: 600; text-align: center; }
        /* Footer */
        .footer-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 32px; }
        .signature-name { color: #000248; font-size: 15px; font-weight: 600; margin-top: 4px; }
        .signature-role { color: #52526C; font-size: 12px; }
        .btn-print {
            background: #7366FF; color: #fff; border-radius: 8px;
            padding: 14px 24px; font-size: 14px; font-weight: 600;
            text-decoration: none; margin-left: 8px; display: inline-block;
        }
        .btn-download {
            background: rgba(115,102,255,0.1); color: #7366FF; border-radius: 8px;
            padding: 14px 24px; font-size: 14px; font-weight: 600;
            text-decoration: none; margin-left: 8px; display: inline-block;
        }
        .note { font-size: 11px; color: #52526C; margin-top: 20px; }
        hr { border: none; border-top: 1px solid rgba(82,82,108,0.15); margin: 12px 0; }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ── Header ──────────────────────────────────────── --}}
    <table style="width:100%;margin:0;padding:24px 0 8px;">
        <tr style="vertical-align:middle;">
            <td><div class="logo">{{ $supplierName }}</div></td>
            <td style="text-align:right;">
                <div class="contact-pill">
                    <span>{{ $supplierEmail }}</span>
                    <span style="border-left:1px solid rgba(255,255,255,.3);border-right:1px solid rgba(255,255,255,.3);padding:0 16px;">
                        {{ $supplierWeb }}
                    </span>
                    @if($supplierIc)
                    <span>IČ: {{ $supplierIc }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Billing info row ────────────────────────────── --}}
    <table style="width:100%;margin:12px 0;">
        <tr style="vertical-align:top;">
            <td style="width:50%;">
                <div class="billing-to-label">Fakturováno zákazníkovi:</div>
                <div class="billing-name">{{ $invoice->snapshot_name }}</div>
                @if($invoice->snapshot_street)
                <div class="billing-text">{{ $invoice->snapshot_street }}</div>
                @endif
                @if($invoice->snapshot_city)
                <div class="billing-text">{{ $invoice->snapshot_zip }} {{ $invoice->snapshot_city }}@if($invoice->snapshot_country_code) ({{ $invoice->snapshot_country_code }})@endif</div>
                @endif
                @if($invoice->snapshot_registration_number)
                <div class="billing-text">IČ: <strong>{{ $invoice->snapshot_registration_number }}</strong></div>
                @endif
                @if($invoice->snapshot_vat_number)
                <div class="billing-text">DIČ: <strong>{{ $invoice->snapshot_vat_number }}</strong></div>
                @endif
            </td>
            <td style="text-align:right;">
                <div class="invoice-title">{{ mb_strtoupper($invoice->type->label(), 'UTF-8') }}</div>
                <div class="invoice-label">
                    {{ $invoice->isTaxDocument() ? 'Daňový doklad' : 'Zálohová faktura' }}
                </div>
                <div class="invoice-detail"><strong>Dodavatel:</strong> {{ $supplierName }}</div>
                @if($supplierStreet)
                <div class="invoice-detail">{{ $supplierStreet }}, {{ $supplierZip }} {{ $supplierCity }}</div>
                @endif
                @if($supplierIc)
                <div class="invoice-detail">IČ: <strong>{{ $supplierIc }}</strong>
                    @if($supplierDic) &nbsp; DIČ: <strong>{{ $supplierDic }}</strong>@endif
                </div>
                @endif
                @if($supplierBank)
                <div class="invoice-detail">Účet: <strong>{{ $supplierBank }}</strong></div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Info boxes ──────────────────────────────────── --}}
    <table class="info-boxes">
        <tr>
            <td class="info-box">
                <p class="info-box-label">Datum vystavení:</p>
                <span class="info-box-value">{{ $invoice->issue_date?->format('d.m.Y') }}</span>
            </td>
            <td class="info-box">
                <p class="info-box-label">Číslo dokladu:</p>
                <span class="info-box-value">{{ $invoice->number }}</span>
            </td>
            <td class="info-box">
                <p class="info-box-label">Variabilní symbol:</p>
                <span class="info-box-value">{{ $invoice->variable_symbol }}</span>
            </td>
            <td class="info-box">
                <p class="info-box-label">
                    @if($invoice->paid_at) Uhrazeno: @else Splatnost: @endif
                </p>
                <span class="info-box-value">
                    @if($invoice->paid_at)
                        {{ $invoice->paid_at->format('d.m.Y') }}
                    @else
                        {{ $invoice->due_date?->format('d.m.Y') }}
                    @endif
                </span>
            </td>
        </tr>
    </table>

    {{-- ── Platební údaje + QR Platba (audit D45) ──────────
         SPAYD je řetězec, který čtou všechny české bankovní aplikace.
         Vykreslení do QR obrázku vyžaduje QR knihovnu (endroid/qr-code) —
         dokud není nainstalovaná, tiskneme aspoň úplné platební údaje,
         ať zákazník nemusí nic dohledávat. --}}
    @php($spayd = $invoice->paid_at === null
        ? app(\App\Domains\Billing\Services\QrPaymentGenerator::class)->forInvoice($invoice)
        : null)
    @if($spayd !== null)
        <table class="info-boxes" data-spayd="{{ $spayd }}">
            <tr>
                <td class="info-box">
                    <p class="info-box-label">Číslo účtu:</p>
                    <span class="info-box-value">
                        {{ ($invoice->currency->value ?? '') === 'EUR'
                            ? config('billing.supplier.bank_account_eur')
                            : config('billing.supplier.bank_account_czk') }}
                    </span>
                </td>
                <td class="info-box">
                    <p class="info-box-label">Variabilní symbol:</p>
                    <span class="info-box-value">{{ $invoice->variable_symbol }}</span>
                </td>
                <td class="info-box">
                    <p class="info-box-label">Částka:</p>
                    <span class="info-box-value">
                        {{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->total) }}
                    </span>
                </td>
            </tr>
        </table>
    @endif

    {{-- ── Items table ─────────────────────────────────── --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:6%;text-align:center;">č.</th>
                <th>Popis</th>
                <th style="text-align:center;width:10%;">DPH %</th>
                <th style="text-align:center;width:10%;">Základ</th>
                <th style="text-align:right;width:14%;">Celkem</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td class="num-col"><span style="color:#52526C;font-weight:600;">{{ $i + 1 }}</span></td>
                <td style="padding:13px 14px;border-bottom:1px solid rgba(82,82,108,0.2);">
                    <div class="item-name">{{ $item->description }}</div>
                    @if($item->period_from && $item->period_to)
                    <div class="item-sub">Období: {{ $item->period_from->format('d.m.Y') }} – {{ $item->period_to->format('d.m.Y') }}</div>
                    @endif
                </td>
                <td style="text-align:center;padding:13px 14px;border-bottom:1px solid rgba(82,82,108,0.2);background:#F5F6F9;">
                    <span style="color:#52526C;font-weight:600;">{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</span>
                </td>
                <td style="text-align:center;padding:13px 14px;border-bottom:1px solid rgba(82,82,108,0.2);">
                    <span style="color:#52526C;font-weight:600;">{{ \App\Domains\Shared\Support\MoneyFormatter::format($item->subtotal) }}</span>
                </td>
                <td class="price-col">
                    <span style="color:#000248;font-weight:600;opacity:.9;">{{ \App\Domains\Shared\Support\MoneyFormatter::format($item->total) }}</span>
                </td>
            </tr>
            @endforeach

            {{-- Summary rows --}}
            <tr>
                <td></td><td></td><td></td>
                <td class="total-row" style="text-align:center;padding:28px 14px 14px;color:#52526C;font-weight:600;">Základ DPH</td>
                <td style="background:#F5F6F9;text-align:right;padding:28px 14px 14px;">
                    <span style="color:#000248;font-weight:600;opacity:.9;">{{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->subtotal) }}</span>
                </td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td style="text-align:center;padding:0 14px 14px;color:#52526C;font-weight:600;">DPH</td>
                <td style="background:#F5F6F9;text-align:right;padding:0 14px 14px;">
                    <span style="color:#000248;font-weight:600;opacity:.9;">{{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->tax_amount) }}</span>
                </td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td style="text-align:center;padding:0 14px 14px;color:#52526C;font-weight:600;">Celkem k úhradě</td>
                <td style="padding:0 0 0 0;">
                    <div style="background:#52526C;color:#fff;font-weight:600;font-size:15px;text-align:center;padding:13px 14px;margin-top:0;">
                        {{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->total) }}
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ── Footer ──────────────────────────────────────── --}}
    <table style="width:100%;margin-top:32px;">
        <tr style="vertical-align:bottom;">
            <td>
                <div style="border-top:1px solid #000248;width:160px;margin-bottom:4px;padding-top:2px;"></div>
                <div class="signature-name">{{ $supplierName }}</div>
                <div class="signature-role">Vystavil</div>
            </td>
            <td style="text-align:right;">
                @unless($isPdf ?? false)
                <a class="btn-print" id="invoice-print" href="{{ route('panel.billing.invoices.print', $invoice) }}">
                    Vytisknout &rsaquo;
                </a>
                @endunless
            </td>
        </tr>
    </table>

    @unless($invoice->isTaxDocument())
    <p class="note">⚠ Tato zálohová faktura není daňovým dokladem ve smyslu § 31 zákona č. 235/2004 Sb. Daňový doklad bude vystaven po přijetí platby.</p>
    @endunless

    @if($invoice->purchase_order_number)
    <p class="note">Číslo objednávky (PO): <strong>{{ $invoice->purchase_order_number }}</strong></p>
    @endif

    @if($invoice->custom_reference)
    <p class="note">Reference zákazníka: {{ $invoice->custom_reference }}</p>
    @endif

    @if($invoice->notes)
    <p class="note">Poznámka: {{ $invoice->notes }}</p>
    @endif

    @if($supplierFooter)
    <p class="note">{{ $supplierFooter }}</p>
    @endif

</div>

@unless($isPdf ?? false)
{{--
    This template is also served as a normal HTML page (the "Vytisknout" route),
    where CSP applies and an inline onclick would never fire. The href stays as
    the fallback: worst case the user lands on the print view and prints from
    the browser menu.
--}}
<script nonce="{{ $cspNonce ?? '' }}">
document.getElementById('invoice-print').addEventListener('click', function (event) {
    event.preventDefault();
    window.print();
});
</script>
@endunless
</body>
</html>
