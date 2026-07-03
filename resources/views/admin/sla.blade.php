@extends('layouts.panel')

@php
    $breadcrumbTitle = 'SLA přehled';
    $breadcrumbItems = ['SLA přehled' => ''];
@endphp

@section('title', 'SLA přehled — monitoring')

@section('content')
<div class="container-fluid">

    {{-- Platform SLA header KPIs --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                @php
                    $sla30Color = $platformSla30 === null ? 'secondary' : ($platformSla30 >= 99.9 ? 'success' : ($platformSla30 >= 99 ? 'warning' : 'danger'));
                @endphp
                <div class="card-body {{ $sla30Color }}">
                    <span class="f-light">Platf. SLA 30 dní</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ $platformSla30 !== null ? number_format($platformSla30, 2, ',', ' ') . ' %' : '—' }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="check-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                @php
                    $sla90Color = $platformSla90 === null ? 'secondary' : ($platformSla90 >= 99.9 ? 'success' : ($platformSla90 >= 99 ? 'warning' : 'danger'));
                @endphp
                <div class="card-body {{ $sla90Color }}">
                    <span class="f-light">Platf. SLA 90 dní</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ $platformSla90 !== null ? number_format($platformSla90, 2, ',', ' ') . ' %' : '—' }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="bar-chart-2"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body {{ $openIncidents->count() > 0 ? 'danger' : 'success' }}">
                    <span class="f-light">Aktuální incidenty</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ $openIncidents->count() }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="zap"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body {{ $recentClosed->count() > 5 ? 'warning' : 'success' }}">
                    <span class="f-light">Incidenty (30 dní)</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ $stats->sum('incidents30') }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="alert-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Open incidents banner --}}
    @if($openIncidents->count() > 0)
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12">
            <div class="card border-0" style="background: var(--danger-color, #dc3545); color:#fff">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i data-feather="alert-octagon" style="width:18px;height:18px;"></i>
                        <strong>{{ $openIncidents->count() }} otevřený incident{{ $openIncidents->count() > 1 ? 'ů' : '' }}</strong>
                    </div>
                    @foreach($openIncidents as $inc)
                    <div class="f-12 mb-1">
                        <strong>{{ $inc->monitor?->name ?? '—' }}</strong>
                        @if($inc->monitor?->service?->customer)
                            — zákazník <em>{{ $inc->monitor->service->customer->email }}</em>
                        @endif
                        · od {{ $inc->started_at?->format('d.m. H:i') }}
                        @if($inc->reason)
                            · {{ $inc->reason }}
                        @endif
                        @if($inc->severity)
                            <span class="badge ms-1"
                                  style="background:rgba(255,255,255,.25); color:#fff; font-size:10px;">{{ $inc->severity }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-12 card-gap">
        {{-- Per-monitor SLA table --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="SLA dle monitorů">
                @if($stats->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="activity" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">Žádné monitory.</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>Monitor / Zákazník</th>
                                <th>SLA 30 dní</th>
                                <th>SLA 90 dní</th>
                                <th class="text-center">Incidenty (30d)</th>
                                <th class="text-center">Otevřené</th>
                                <th>MTTR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats->sortBy('uptime30') as $row)
                            @php
                                $u30 = $row['uptime30'];
                                $u90 = $row['uptime90'];
                                $c30Color = $u30 >= 99.9 ? 'success' : ($u30 >= 99 ? 'warning' : 'danger');
                                $c90Color = $u90 >= 99.9 ? 'success' : ($u90 >= 99 ? 'warning' : 'danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="f-w-500">
                                        {{ $row['monitor']->name }}
                                        @if($row['monitor']->provider === 'internal_mock')
                                            <span class="badge badge-light-warning f-10 ms-1">MOCK</span>
                                        @endif
                                    </div>
                                    <div class="f-12 f-light">
                                        {{ $row['monitor']->type }} · {{ $row['monitor']->target }}
                                    </div>
                                    @if($row['customer'])
                                    <div class="f-12">
                                        <a href="{{ route('admin.customers.show', $row['customer']) }}" class="f-light">
                                            {{ $row['customer']->email }}
                                        </a>
                                    </div>
                                    @endif
                                </td>
                                <td style="min-width:130px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px">
                                            <div class="progress-bar bg-{{ $c30Color }}" style="width:{{ $u30 }}%"></div>
                                        </div>
                                        <span class="f-12 f-light text-nowrap">{{ number_format($u30, 2, ',', '') }} %</span>
                                    </div>
                                </td>
                                <td style="min-width:130px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px">
                                            <div class="progress-bar bg-{{ $c90Color }}" style="width:{{ $u90 }}%"></div>
                                        </div>
                                        <span class="f-12 f-light text-nowrap">{{ number_format($u90, 2, ',', '') }} %</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $row['incidents30'] > 0 ? 'warning' : 'success' }}">
                                        {{ $row['incidents30'] }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($row['openIncidents'] > 0)
                                        <span class="badge badge-light-danger">{{ $row['openIncidents'] }}</span>
                                    @else
                                        <span class="f-light">—</span>
                                    @endif
                                </td>
                                <td class="f-12 f-light">
                                    @if($row['mttr'] !== null)
                                        @if($row['mttr'] < 60)
                                            {{ $row['mttr'] }} min
                                        @else
                                            {{ round($row['mttr'] / 60, 1) }} hod
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </x-panel.card>
        </div>

        {{-- Incident history timeline --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Poslední incidenty (30 dní)">
                @if($recentClosed->isEmpty())
                    <p class="f-light mb-0">Žádné incidenty v posledních 30 dnech.</p>
                @else
                <div class="activity-log" style="max-height:520px;overflow-y:auto;">
                    <div class="basic-timeline">
                        <ul>
                            @foreach($recentClosed as $incident)
                            @php
                                $duration = $incident->started_at && $incident->resolved_at
                                    ? $incident->started_at->diffInMinutes($incident->resolved_at)
                                    : null;
                                $sevColor = match(strtolower($incident->severity ?? '')) {
                                    'critical' => 'danger',
                                    'high'     => 'warning',
                                    'medium'   => 'info',
                                    default    => 'secondary',
                                };
                            @endphp
                            <li>
                                <div class="timeline-dot-success"></div>
                                <div class="ms-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <p class="f-w-500 mb-0">{{ $incident->monitor?->name }}</p>
                                        @if($incident->severity)
                                        <span class="badge badge-light-{{ $sevColor }} ms-2 flex-shrink-0 f-10">
                                            {{ $incident->severity }}
                                        </span>
                                        @endif
                                    </div>
                                    @if($incident->monitor?->service?->customer)
                                    <p class="f-11 f-light mb-0">{{ $incident->monitor->service->customer->email }}</p>
                                    @endif
                                    @if($incident->reason)
                                    <p class="f-12 f-light mb-0">{{ $incident->reason }}</p>
                                    @endif
                                    <p class="f-11 f-light mb-0 mt-1">
                                        {{ $incident->started_at?->format('d.m. H:i') }}
                                        → {{ $incident->resolved_at?->format('H:i') }}
                                        @if($duration !== null)
                                        <span class="ms-1">({{ $duration < 60 ? $duration . ' min' : round($duration/60, 1) . ' hod' }})</span>
                                        @endif
                                    </p>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif
            </x-panel.card>
        </div>
    </div>

    {{-- SLA target reference --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12">
            <x-panel.card title="SLA cíle">
                <div class="d-flex flex-wrap gap-4 f-12">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light-success">≥ 99,9 %</span>
                        <span class="f-light">Plnění SLA — žádné výpadky</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light-warning">99,0 – 99,9 %</span>
                        <span class="f-light">Přijatelná úroveň — max 43 min výpadku/30 dní</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light-danger">&lt; 99,0 %</span>
                        <span class="f-light">Porušení SLA — vyžaduje řešení</span>
                    </div>
                </div>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
