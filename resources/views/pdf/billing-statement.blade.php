<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .sub { color: #666; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #4361ee; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 11px; }
        .amount { text-align: right; }
        .totals { margin-top: 16px; text-align: right; font-size: 12px; }
        .totals td { border: none; padding: 3px 8px; }
        .paid { color: #28a745; font-weight: bold; }
        .pending { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Fakturační výkaz</h1>
    <div class="sub">
        Zákazník: {{ $customer->company_name ?? $customer->full_name ?? 'N/A' }} |
        Období: {{ str_pad((string)$month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}
    </div>

    @if($invoices->isEmpty())
        <p>Za toto období nebyly nalezeny žádné faktury.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Číslo faktury</th>
                <th>Datum</th>
                <th>Splatnost</th>
                <th>Stav</th>
                <th class="amount">Celkem</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }}</td>
                <td>{{ $invoice->created_at?->format('d.m.Y') }}</td>
                <td>{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</td>
                <td>{{ $invoice->status->label() }}</td>
                <td class="amount">{{ number_format($invoice->total->getMinorAmount()->toInt() / 100, 2, ',', ' ') }} {{ $invoice->currency->value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Zaplaceno:</td>
            <td class="paid amount">{{ number_format($paidTotal / 100, 2, ',', ' ') }} Kč</td>
        </tr>
        <tr>
            <td>Nezaplaceno:</td>
            <td class="pending amount">{{ number_format($pendingTotal / 100, 2, ',', ' ') }} Kč</td>
        </tr>
    </table>
    @endif

    <div style="margin-top: 40px; font-size: 10px; color: #999; text-align: center;">
        Generováno {{ now()->format('d.m.Y H:i') }} — OnHost
    </div>
</body>
</html>
