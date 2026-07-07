@extends('layouts.panel')

@section('title', 'Tržby dle kategorie produktu')

@section('content')
<div class="container-fluid">
    <x-panel.card title="Tržby dle kategorie produktu — posledních 12 měsíců">
        @if($rows->isEmpty())
            <p class="text-muted">Nedostatek dat.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kategorie</th>
                        <th class="text-end">Tržby</th>
                        <th class="text-end">Faktur</th>
                        <th>Podíl</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr>
                        <td class="f-w-500">{{ $row->product_type }}</td>
                        <td class="text-end f-w-600">{{ number_format($row->revenue_minor / 100, 2, ',', ' ') }} Kč</td>
                        <td class="text-end text-muted">{{ $row->invoice_count }}</td>
                        <td style="min-width:120px">
                            @php $pct = round($row->revenue_minor / $totalRevenue * 100) @endphp
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-primary" style="width:{{ $pct }}%"></div>
                            </div>
                            <small class="text-muted">{{ $pct }}%</small>
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
