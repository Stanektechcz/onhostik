<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; color: #333; }
        .header { border-bottom: 2px solid #4361ee; padding-bottom: 8px; margin-bottom: 16px; }
        .section { margin-bottom: 16px; }
        .section h2 { font-size: 13px; color: #4361ee; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        td:first-child { font-weight: bold; width: 40%; color: #555; }
        .footer { margin-top: 40px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #e0e0e0; padding-top: 8px; }
        .badge { background: #4361ee; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Smlouva o poskytování služeb</h1>
        <p style="color:#666;margin:0;">OnHost — {{ now()->format('d.m.Y') }}</p>
    </div>

    <div class="section">
        <h2>Zákazník</h2>
        <table>
            <tr><td>Jméno/Firma</td><td>{{ $service->customer?->company_name ?? ($service->customer?->full_name ?? 'N/A') }}</td></tr>
            @if($service->customer?->vat_number)
            <tr><td>DIČ</td><td>{{ $service->customer->vat_number }}</td></tr>
            @endif
            @if($service->customer?->registration_number)
            <tr><td>IČ</td><td>{{ $service->customer->registration_number }}</td></tr>
            @endif
            <tr><td>Email</td><td>{{ $service->customer?->email ?? '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Předmět smlouvy — Služba</h2>
        <table>
            <tr><td>Název služby</td><td>{{ $service->label }}</td></tr>
            <tr><td>ID služby</td><td>{{ $service->id }}</td></tr>
            <tr>
                <td>Stav</td>
                <td><span class="badge">{{ $service->status->label() }}</span></td>
            </tr>
            @if($service->product)
            <tr><td>Produkt</td><td>{{ $service->product->getTranslation('name', 'cs') }}</td></tr>
            @endif
            @if($service->next_due_date)
            <tr><td>Příští obnovení</td><td>{{ $service->next_due_date->format('d.m.Y') }}</td></tr>
            @endif
            <tr><td>Automatické obnovení</td><td>{{ $service->auto_renew ? 'Zapnuto' : 'Vypnuto' }}</td></tr>
            @if($service->orderItem?->pricingPlan)
            <tr><td>Fakturační cyklus</td><td>{{ $service->orderItem->pricingPlan->billing_cycle?->label() }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h2>Smluvní podmínky</h2>
        <p>Poskytovatel se zavazuje poskytovat zákazníkovi výše specifikovanou službu v souladu se standardními obchodními podmínkami OnHost. Zákazník se zavazuje hradit sjednané poplatky ve splatných termínech.</p>
        <p>Tento dokument je generován automaticky a slouží jako přehled smluvních parametrů. Plné obchodní podmínky jsou dostupné na webových stránkách poskytovatele.</p>
    </div>

    <div class="footer">
        Generováno automaticky systémem OnHost dne {{ now()->format('d.m.Y H:i') }}. Service ID: {{ $service->id }}
    </div>
</body>
</html>
