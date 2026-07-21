<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; line-height: 1.5; }
        h1 { font-size: 18px; color: #333; margin-bottom: 2px; }
        .header { border-bottom: 2px solid #4361ee; padding-bottom: 8px; margin-bottom: 16px; }
        .muted { color: #666; margin: 0; }
        .parties { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .parties td { width: 50%; vertical-align: top; padding: 8px; border: 1px solid #e0e0e0; }
        .parties .role { font-weight: bold; color: #4361ee; font-size: 10px; text-transform: uppercase; }
        .section { margin-bottom: 14px; }
        .section h2 { font-size: 13px; color: #4361ee; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 6px; }
        ul { margin: 4px 0 4px 16px; padding: 0; }
        li { margin-bottom: 2px; }
        table.sub { width: 100%; border-collapse: collapse; }
        table.sub th, table.sub td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #f0f0f0; font-size: 10px; }
        table.sub th { color: #555; }
        .footer { margin-top: 30px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #e0e0e0; padding-top: 8px; }
        .sign { margin-top: 30px; width: 100%; }
        .sign td { width: 50%; padding-top: 30px; border-top: 1px solid #333; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Smlouva o zpracování osobních údajů</h1>
        <p class="muted">Data Processing Agreement dle čl. 28 GDPR &mdash; verze {{ $version }} &mdash; {{ $generatedAt->format('d.m.Y') }}</p>
    </div>

    <table class="parties">
        <tr>
            <td>
                <div class="role">Správce (Controller)</div>
                <strong>{{ $customer->company_name ?? ('Zákazník #' . $customer->id) }}</strong><br>
                @if($customer->registration_number)IČ: {{ $customer->registration_number }}<br>@endif
                @if($customer->vat_number)DIČ: {{ $customer->vat_number }}<br>@endif
                @if($customer->email){{ $customer->email }}@endif
            </td>
            <td>
                <div class="role">Zpracovatel (Processor)</div>
                <strong>{{ $processor['name'] ?? 'Poskytovatel' }}</strong><br>
                @if(!empty($processor['ic']))IČ: {{ $processor['ic'] }}<br>@endif
                @if(!empty($processor['dic']))DIČ: {{ $processor['dic'] }}<br>@endif
                @if(!empty($processor['street'])){{ $processor['street'] }}, {{ $processor['zip'] ?? '' }} {{ $processor['city'] ?? '' }}@endif
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>1. Předmět a doba zpracování</h2>
        <p><strong>Předmět:</strong> {{ $dpa['subject_matter'] }}</p>
        <p><strong>Doba:</strong> {{ $dpa['duration'] }}</p>
        <p><strong>Povaha a účel:</strong> {{ $dpa['nature_purpose'] }}</p>
    </div>

    <div class="section">
        <h2>2. Kategorie osobních údajů</h2>
        <ul>
            @foreach($dpa['data_categories'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>3. Kategorie subjektů údajů</h2>
        <ul>
            @foreach($dpa['data_subjects'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>4. Technická a organizační opatření (čl. 32)</h2>
        <ul>
            @foreach($dpa['security_measures'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>5. Další zpracovatelé (sub-processors)</h2>
        <table class="sub">
            <thead>
                <tr><th>Název</th><th>Účel</th><th>Umístění</th></tr>
            </thead>
            <tbody>
                @foreach($dpa['subprocessors'] as $sp)
                    <tr>
                        <td>{{ $sp['name'] }}</td>
                        <td>{{ $sp['purpose'] }}</td>
                        <td>{{ $sp['location'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>6. Povinnosti zpracovatele</h2>
        <p>Zpracovatel zpracovává osobní údaje pouze na základě doložených pokynů správce,
            zachovává mlčenlivost, zajišťuje bezpečnost dle čl. 32, je nápomocen správci při
            plnění jeho povinností (čl. 32–36) a po ukončení služeb údaje dle volby správce
            vymaže nebo vrátí, není-li dále vyžadováno právem EU nebo členského státu.</p>
    </div>

    <table class="sign">
        <tr>
            <td>Za správce (zákazníka)</td>
            <td>Za zpracovatele</td>
        </tr>
    </table>

    <div class="footer">
        Tento dokument byl vygenerován automaticky {{ $generatedAt->format('d.m.Y H:i') }} a je platný bez podpisu jako doklad o smluvním rámci zpracování.
    </div>
</body>
</html>
