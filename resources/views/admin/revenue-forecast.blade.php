@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Prognóza tržeb';
    $breadcrumbItems = ['Admin' => route('admin.dashboard'), 'Prognóza tržeb' => ''];
@endphp

@section('title', 'Prognóza tržeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI cards --}}
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 sm:col-span-4">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Průměr posledních 3 měsíců</p>
                    <h3 class="mb-0">{{ number_format($last3Avg, 0, ',', ' ') }}&nbsp;Kč</h3>
                </div>
            </div>
        </div>
        <div class="col-span-12 sm:col-span-4">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Celkem YTD</p>
                    <h3 class="mb-0">{{ number_format($totalYtd, 0, ',', ' ') }}&nbsp;Kč</h3>
                </div>
            </div>
        </div>
        <div class="col-span-12 sm:col-span-4">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Prognóza (příštích 3 měs.)</p>
                    <h3 class="mb-0 txt-primary">{{ number_format($forecastSum3, 0, ',', ' ') }}&nbsp;Kč</h3>
                    <p class="f-11 text-muted mb-0">Součet otevřených faktur</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3">
        {{-- Historical revenue bar chart --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0">Skutečné příjmy <small class="text-muted">(posledních 12 měsíců, dokončené platby)</small></h6>
                </div>
                <div class="card-body">
                    @php
                        $maxHistorical = $historical->max('czk') ?: 1;
                    @endphp
                    <div class="flex items-end gap-1" style="height:120px;">
                        @foreach($historical as $m)
                            @php $pct = max(2, (int) round($m['czk'] / $maxHistorical * 100)); @endphp
                            <div class="flex-fill flex flex-col items-center"
                                 title="{{ $m['label'] }}: {{ number_format($m['czk'], 0, ',', ' ') }} Kč">
                                <div class="{{ $m['czk'] > 0 ? 'bg-primary' : 'bg-light border' }} rounded w-full"
                                     style="height:{{ $pct }}%;min-height:3px;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-1 mt-1">
                        @foreach($historical as $m)
                            <div class="flex-fill text-center f-10 text-muted" style="overflow:hidden;white-space:nowrap;">
                                {{ $m['label'] }}
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <table class="table table-sm table-borderless mb-0 f-12">
                            <tbody>
                                @foreach($historical->chunk(4) as $row)
                                    <tr>
                                        @foreach($row as $m)
                                            <td class="text-muted">{{ $m['label'] }}</td>
                                            <td class="font-semibold">{{ number_format($m['czk'], 0, ',', ' ') }}&nbsp;Kč</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Forecast table --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0">Prognóza <small class="text-muted">(příštích 6 měsíců, otevřené faktury)</small></h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Měsíc</th>
                                    <th class="text-right">Prognózované příjmy</th>
                                    <th class="text-right">vs. průměr 3m</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($forecast as $m)
                                    @php
                                        $diff    = $m['czk'] - $last3Avg;
                                        $diffPct = $last3Avg > 0 ? round($diff / $last3Avg * 100, 1) : 0;
                                        $color   = $diff >= 0 ? 'success' : 'danger';
                                        $arrow   = $diff >= 0 ? '↑' : '↓';
                                    @endphp
                                    <tr>
                                        <td class="f-12 f-w-500">{{ $m['label'] }}</td>
                                        <td class="text-right f-12">{{ number_format($m['czk'], 0, ',', ' ') }}&nbsp;Kč</td>
                                        <td class="text-right f-12">
                                            @if($last3Avg > 0)
                                                <span class="txt-{{ $color }}">{{ $arrow }} {{ abs($diffPct) }}&nbsp;%</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-light-secondary f-12 mt-3">
                <i data-feather="info" style="width:13px;height:13px"></i>
                Prognóza vychází z otevřených faktur s datem splatnosti v daném měsíci. Nezahrnuje budoucí nové objednávky.
            </div>
        </div>
    </div>
</div>
@endsection
