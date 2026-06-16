<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 30px; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .badge { display: inline; padding: 2px 8px; border: 1px solid #777; border-radius: 4px; font-size: 10px; }
        .two-col { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .two-col td { vertical-align: top; width: 50%; padding: 0 0 0 0; }
        .two-col td:first-child { padding-right: 20px; }
        .label { color: #777; font-size: 10px; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
        .items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .items th { font-size: 10px; text-transform: uppercase; color: #777; text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; }
        .items td { padding: 7px 4px; border-bottom: 1px solid #eee; text-align: left; }
        .items td.right { text-align: right; }
        .items th.right { text-align: right; }
        .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .totals-wrap td { padding: 0; }
        .totals-wrap td:first-child { width: 60%; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 3px 4px; }
        .totals td:last-child { text-align: right; }
        .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #222; padding-top: 6px; }
        .note { margin-top: 28px; font-size: 10px; color: #777; }
        hr { border: none; border-top: 1px solid #eee; margin: 16px 0; }
    </style>
</head>
<body>
    <h1>{{ $invoice->type->label() }} {{ $invoice->number }}</h1>
    <span class="badge">{{ $invoice->status->label() }}</span>

    <table class="two-col">
        <tr>
            <td>
                <div class="label">Dodavatel</div>
                {{ config('billing.supplier.name') }}<br>
                @if(config('billing.supplier.street')){{ config('billing.supplier.street') }}<br>@endif
                @if(config('billing.supplier.city')){{ config('billing.supplier.zip') }} {{ config('billing.supplier.city') }}<br>@endif
                @if(config('billing.supplier.ic'))<strong>IČ:</strong> {{ config('billing.supplier.ic') }}<br>@endif
                @if(config('billing.supplier.dic'))<strong>DIČ:</strong> {{ config('billing.supplier.dic') }}@endif
            </td>
            <td>
                <div class="label">Odběratel</div>
                {{ $invoice->snapshot_name }}<br>
                @if($invoice->snapshot_company && $invoice->snapshot_company !== $invoice->snapshot_name){{ $invoice->snapshot_company }}<br>@endif
                @if($invoice->snapshot_street){{ $invoice->snapshot_street }}<br>@endif
                @if($invoice->snapshot_city){{ $invoice->snapshot_zip }} {{ $invoice->snapshot_city }}@if($invoice->snapshot_country_code) ({{ $invoice->snapshot_country_code }})@endif<br>@endif
                @if($invoice->snapshot_registration_number)<strong>IČ:</strong> {{ $invoice->snapshot_registration_number }}<br>@endif
                @if($invoice->snapshot_vat_number)<strong>DIČ:</strong> {{ $invoice->snapshot_vat_number }}@endif
            </td>
        </tr>
    </table>

    <hr>

    <table class="two-col">
        <tr>
            <td>
                <div class="label">Platební údaje</div>
                Vystaveno: <strong>{{ $invoice->issue_date?->format('d.m.Y') }}</strong><br>
                Splatnost: <strong>{{ $invoice->due_date?->format('d.m.Y') }}</strong><br>
                @if($invoice->taxable_supply_date)DUZP: {{ $invoice->taxable_supply_date->format('d.m.Y') }}<br>@endif
                Variabilní symbol: <strong>{{ $invoice->variable_symbol }}</strong>
            </td>
            <td>
                @if($invoice->paid_at)
                    <div class="label">Stav platby</div>
                    Uhrazeno dne: <strong>{{ $invoice->paid_at->format('d.m.Y H:i') }}</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Položka</th>
                <th>Množství</th>
                <th>DPH %</th>
                <th class="right">Cena bez DPH</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                    <td class="right">{{ \App\Domains\Shared\Support\MoneyFormatter::format($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td>
                <table class="totals">
                    <tr><td>Základ daně</td><td>{{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->subtotal) }}</td></tr>
                    <tr><td>DPH</td><td>{{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->tax_amount) }}</td></tr>
                    <tr class="total-row"><td>Celkem k úhradě</td><td>{{ \App\Domains\Shared\Support\MoneyFormatter::format($invoice->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($invoice->notes)
        <p class="note">{{ $invoice->notes }}</p>
    @endif

    @unless($invoice->isTaxDocument())
        <p class="note">Tato zálohová faktura není daňovým dokladem. Daňový doklad bude vystaven po přijetí platby.</p>
    @endunless
</body>
</html>
