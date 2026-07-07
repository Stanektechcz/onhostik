@extends('layouts.panel')

@php
    $breadcrumbTitle = 'API Analytics';
    $breadcrumbItems = ['Admin' => route('admin.dashboard'), 'API Analytics' => ''];
@endphp

@section('title', 'API Token Usage Analytics')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Celkem požadavků</p>
                    <h4 class="mb-0">{{ number_format($totalRequests) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Dnes</p>
                    <h4 class="mb-0">{{ number_format($requestsToday) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Posledních 7 dní</p>
                    <h4 class="mb-0">{{ number_format($requests7d) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Chyby (4xx/5xx)</p>
                    <h4 class="mb-0 {{ $errorCount > 0 ? 'txt-danger' : '' }}">{{ number_format($errorCount) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Chybovost</p>
                    <h4 class="mb-0 {{ $errorRate > 5 ? 'txt-warning' : '' }}">{{ $errorRate }}&nbsp;%</h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Prům. odezva</p>
                    <h4 class="mb-0">{{ $avgResponseMs }}&nbsp;<small class="f-12">ms</small></h4>
                </div>
            </div>
        </div>
    </div>

    @if($totalRequests === 0)
        <div class="alert alert-light-secondary text-center py-4">
            <i data-feather="activity" style="width:32px;height:32px" class="mb-2 d-block mx-auto text-muted"></i>
            <p class="mb-0 text-muted">Zatím nejsou žádné záznamy API usage.</p>
        </div>
    @else
        <div class="row g-3">
            {{-- Top endpoints --}}
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="mb-0">Top 10 endpointů <small class="text-muted f-12">(posledních 7 dní)</small></h6>
                    </div>
                    <div class="card-body p-0">
                        @if($topEndpoints->isEmpty())
                            <p class="text-muted f-12 p-3 mb-0">Žádná data za posledních 7 dní.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Metoda</th>
                                            <th>Endpoint</th>
                                            <th class="text-end">Požadavky</th>
                                            <th class="text-end">Chyby</th>
                                            <th class="text-end">Ø odezva</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topEndpoints as $ep)
                                            <tr>
                                                <td>
                                                    <span class="badge badge-light-{{ match($ep->method) {
                                                        'GET'    => 'primary',
                                                        'POST'   => 'success',
                                                        'PUT',
                                                        'PATCH'  => 'warning',
                                                        'DELETE' => 'danger',
                                                        default  => 'secondary',
                                                    } }} f-10">{{ $ep->method }}</span>
                                                </td>
                                                <td class="font-monospace f-12">{{ $ep->endpoint }}</td>
                                                <td class="text-end f-12 f-w-500">{{ number_format($ep->total) }}</td>
                                                <td class="text-end f-12 {{ $ep->errors > 0 ? 'txt-danger' : 'text-muted' }}">
                                                    {{ $ep->errors > 0 ? number_format($ep->errors) : '—' }}
                                                </td>
                                                <td class="text-end f-12 text-muted">{{ (int) $ep->avg_ms }}&nbsp;ms</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Top consumers --}}
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="mb-0">Top 10 spotřebitelů <small class="text-muted f-12">(posledních 7 dní)</small></h6>
                    </div>
                    <div class="card-body p-0">
                        @if($topConsumers->isEmpty())
                            <p class="text-muted f-12 p-3 mb-0">Žádná data za posledních 7 dní.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Uživatel</th>
                                            <th class="text-end">Požadavky</th>
                                            <th class="text-end">Chyby</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topConsumers as $row)
                                            @php $rowUser = $userMap[$row->user_id] ?? null; @endphp
                                            <tr>
                                                <td class="f-12">
                                                    @if($rowUser)
                                                        <a href="{{ route('admin.customers.show', $rowUser) }}" class="f-w-500">
                                                            {{ $rowUser->name }}
                                                        </a>
                                                        <span class="d-block f-10 text-muted">{{ $rowUser->email }}</span>
                                                    @else
                                                        <span class="text-muted">Neznámý (#{{ $row->user_id }})</span>
                                                    @endif
                                                </td>
                                                <td class="text-end f-12 f-w-500">{{ number_format($row->total) }}</td>
                                                <td class="text-end f-12 {{ $row->errors > 0 ? 'txt-danger' : 'text-muted' }}">
                                                    {{ $row->errors > 0 ? number_format($row->errors) : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Daily volume (last 7 days) --}}
                <div class="card mt-3">
                    <div class="card-header py-3">
                        <h6 class="mb-0">Denní provoz <small class="text-muted f-12">(posledních 7 dní)</small></h6>
                    </div>
                    <div class="card-body">
                        @php
                            $days = collect();
                            for ($i = 6; $i >= 0; $i--) {
                                $day     = now()->subDays($i)->format('Y-m-d');
                                $label   = now()->subDays($i)->format('d.m');
                                $count   = $dailyVolume[$day]->total ?? 0;
                                $days->push(['label' => $label, 'count' => (int) $count]);
                            }
                            $maxCount = $days->max('count') ?: 1;
                        @endphp
                        <div class="d-flex align-items-end gap-1" style="height:60px;">
                            @foreach($days as $d)
                                @php $pct = max(4, (int) round($d['count'] / $maxCount * 100)); @endphp
                                <div class="flex-fill d-flex flex-column align-items-center" title="{{ $d['label'] }}: {{ $d['count'] }}">
                                    <div class="bg-primary rounded-top" style="width:100%;height:{{ $pct }}%;min-height:4px;opacity:.75;"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex gap-1 mt-1">
                            @foreach($days as $d)
                                <div class="flex-fill text-center f-10 text-muted">{{ $d['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
