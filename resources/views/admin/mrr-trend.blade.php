@extends('layouts.panel')

@section('title', 'MRR Trend')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <div>
                            <h5 class="mb-0 f-w-600">{{ number_format($lastMonth / 100, 0, ',', ' ') }} Kč</h5>
                            <span class="f-light f-12">MRR (poslední měsíc)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <div>
                            <h5 class="mb-0 f-w-600 {{ $growth >= 0 ? 'font-success' : 'font-danger' }}">
                                {{ $growth >= 0 ? '+' : '' }}{{ $growth }} %
                            </h5>
                            <span class="f-light f-12">Růst MoM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <div>
                            <h5 class="mb-0 f-w-600">{{ number_format($avgMonthly / 100, 0, ',', ' ') }} Kč</h5>
                            <span class="f-light f-12">Průměrné MRR (12 měs.)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <div>
                            <h5 class="mb-0 f-w-600">{{ number_format($totalLtm / 100, 0, ',', ' ') }} Kč</h5>
                            <span class="f-light f-12">Celkem LTM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="MRR — 12měsíční trend">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Měsíc</th>
                        <th class="text-end">MRR (Kč)</th>
                        <th class="text-end">Počet faktur</th>
                        <th>Vizualizace</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxMrr = $mrrData->max('mrr_minor') ?: 1; @endphp
                    @foreach($mrrData as $row)
                    <tr>
                        <td class="f-w-500">{{ $row['month'] }}</td>
                        <td class="text-end f-w-600">
                            {{ number_format($row['mrr_minor'] / 100, 0, ',', ' ') }} Kč
                        </td>
                        <td class="text-end f-12">{{ $row['invoice_count'] }}</td>
                        <td style="min-width: 200px;">
                            @php $pct = $maxMrr > 0 ? round($row['mrr_minor'] / $maxMrr * 100) : 0; @endphp
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary"
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
