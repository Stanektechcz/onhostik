@extends('layouts.panel')

@section('title', 'ARPU — průměrný příjem na zákazníka')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600">{{ number_format($latestArpu / 100, 0, ',', ' ') }} Kč</h5>
                        <span class="f-light f-12">ARPU (poslední měsíc)</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600 {{ $growth >= 0 ? 'font-success' : 'font-danger' }}">
                            {{ $growth >= 0 ? '+' : '' }}{{ $growth }} %
                        </h5>
                        <span class="f-light f-12">Změna MoM</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600">{{ number_format($avgArpu / 100, 0, ',', ' ') }} Kč</h5>
                        <span class="f-light f-12">Průměrné ARPU (12 měs.)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="ARPU — 12měsíční trend">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Měsíc</th>
                        <th class="text-right">Počet zákazníků</th>
                        <th class="text-right">Příjem (Kč)</th>
                        <th class="text-right">ARPU (Kč)</th>
                        <th>Vizualizace</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($arpuData as $row)
                    <tr>
                        <td class="f-w-500">{{ $row['month'] }}</td>
                        <td class="text-right f-12">{{ $row['customers'] }}</td>
                        <td class="text-right f-12">{{ number_format($row['revenue'] / 100, 0, ',', ' ') }}</td>
                        <td class="text-right f-w-600">{{ number_format($row['arpu'] / 100, 0, ',', ' ') }}</td>
                        <td style="min-width: 180px;">
                            @php $pct = $maxArpu > 0 ? round($row['arpu'] / $maxArpu * 100) : 0; @endphp
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info"
                                     role="progressbar"
                                     style="width: {{ $pct }}%"
                                     aria-valuenow="{{ $pct }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel.card>
</div>
@endsection
