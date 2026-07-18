@extends('layouts.panel')

@section('title', 'Příjmy podle zemí')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Příjmy podle zemí zákazníků">
        @if($rows->isEmpty())
            <p class="text-muted">Žádné zaplacené faktury.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kód země</th>
                        <th class="text-right">Zákazníků</th>
                        <th class="text-right">Faktur</th>
                        <th class="text-right">Příjmy (Kč)</th>
                        <th>Podíl</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $pct = round($row->revenue_minor / $totalRevenue * 100, 1); @endphp
                    <tr>
                        <td><strong>{{ strtoupper($row->country_code ?? 'N/A') }}</strong></td>
                        <td class="text-right">{{ number_format($row->customer_count) }}</td>
                        <td class="text-right">{{ number_format($row->invoice_count) }}</td>
                        <td class="text-right">{{ number_format($row->revenue_minor / 100, 2) }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="progress grow" style="height:6px">
                                    <div class="progress-bar bg-primary" style="width:{{ $pct }}%"></div>
                                </div>
                                <small class="text-muted">{{ $pct }}%</small>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
