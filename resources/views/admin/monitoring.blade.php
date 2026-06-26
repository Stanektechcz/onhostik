@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_monitoring');
    $breadcrumbItems = [__('panel.nav.admin_monitoring') => ''];
@endphp

@section('title', __('panel.nav.admin_monitoring'))

@section('content')
    <div class="container-fluid">

        {{-- KPI row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $downCount > 0 ? 'danger' : 'success' }}">
                        <span class="f-light">{{ __('panel.admin.down_monitors') }}</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $downCount }}</h4>
                            <span class="f-light f-12 mb-1">/ {{ $totalCount }}</span>
                        </div>
                        <div class="bg-gradient"><i data-feather="alert-triangle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $openIncidents > 0 ? 'warning' : 'success' }}">
                        <span class="f-light">Otevřené incidenty</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $openIncidents }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="zap"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $sslExpiring > 0 ? 'warning' : 'success' }}">
                        <span class="f-light">SSL expiruje do 30 dní</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $sslExpiring }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="shield"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $avgUptime >= 99 ? 'success' : ($avgUptime >= 95 ? 'warning' : 'danger') }}">
                        <span class="f-light">Prům. dostupnost</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $avgUptime > 0 ? number_format($avgUptime, 2, ',', ' ') . ' %' : '—' }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="activity"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Monitors table --}}
            <div class="col-xl-8">
                <x-panel.card :title="__('panel.admin.monitors')">
                    <p class="f-12 f-light mb-3">
                        <i data-feather="alert-triangle" style="width:12px;height:12px;"></i>
                        {{ __('panel.admin.monitoring_mock_note') }}
                    </p>
                    @if($monitors->isEmpty())
                        <div class="text-center py-4">
                            <i data-feather="activity" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                            <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                    <tr>
                                        <th>Monitor</th>
                                        <th>{{ __('panel.common.customer') }}</th>
                                        <th>{{ __('panel.common.status') }}</th>
                                        <th>Dostupnost</th>
                                        <th>SSL expiry</th>
                                        <th>Poslední check</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($monitors as $monitor)
                                        @php
                                            $uptimePct   = (float) ($monitor->uptime_percent ?? 0);
                                            $uptimeColor = $uptimePct >= 99 ? 'success' : ($uptimePct >= 95 ? 'warning' : 'danger');
                                            $sslDays     = $monitor->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null;
                                            $sslColor    = $sslDays === null ? 'secondary' : ($sslDays <= 7 ? 'danger' : ($sslDays <= 30 ? 'warning' : 'success'));
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="f-w-600">
                                                    {{ $monitor->name }}
                                                    @if($monitor->provider === 'internal_mock')
                                                        <span class="badge badge-light-warning f-10 ms-1" title="{{ __('panel.admin.monitoring_mock_note') }}">MOCK</span>
                                                    @endif
                                                </div>
                                                <div class="f-light f-12">{{ $monitor->type }} · {{ $monitor->target }}</div>
                                            </td>
                                            <td class="f-light f-12">
                                                @if($monitor->service?->customer)
                                                    <a href="{{ route('admin.customers.show', $monitor->service->customer) }}" class="f-light">
                                                        {{ $monitor->service->customer->email }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-light-{{ $monitor->status->color() }}">
                                                    {{ $monitor->status->label() }}
                                                </span>
                                            </td>
                                            <td style="min-width: 120px;">
                                                @if($monitor->uptime_percent !== null)
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:6px">
                                                            <div class="progress-bar bg-{{ $uptimeColor }}"
                                                                 style="width: {{ $uptimePct }}%"></div>
                                                        </div>
                                                        <span class="f-12 f-light">{{ number_format($uptimePct, 1) }}%</span>
                                                    </div>
                                                @else
                                                    <span class="f-light">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($monitor->ssl_expires_at)
                                                    <span class="badge badge-light-{{ $sslColor }}">
                                                        {{ $monitor->ssl_expires_at->format('d.m.Y') }}
                                                        @if($sslDays !== null && $sslDays >= 0)
                                                            ({{ $sslDays }}d)
                                                        @else
                                                            <i data-feather="alert-circle" style="width:10px;height:10px"></i>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="f-light">—</span>
                                                @endif
                                            </td>
                                            <td class="f-light f-12">
                                                {{ $monitor->last_check_at?->diffForHumans() ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $monitors->links() }}
                    @endif
                </x-panel.card>
            </div>

            {{-- Incidents timeline --}}
            <div class="col-xl-4">
                <x-panel.card title="Incidenty">
                    @if($incidents->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.dashboard.no_incident') }}</p>
                    @else
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($incidents as $incident)
                                        @php
                                            $dotColor = $incident->isOpen() ? 'danger' : 'success';
                                            $sevColor = match(strtolower($incident->severity ?? '')) {
                                                'critical' => 'danger',
                                                'high'     => 'warning',
                                                'medium'   => 'info',
                                                default    => 'secondary',
                                            };
                                        @endphp
                                        <li>
                                            <div class="timeline-dot-{{ $dotColor }}"></div>
                                            <div class="ms-4">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <p class="f-w-500 mb-0">{{ $incident->monitor?->name }}</p>
                                                        <p class="f-12 f-light mb-0">{{ $incident->reason }}</p>
                                                    </div>
                                                    <span class="badge badge-light-{{ $sevColor }} ms-2 flex-shrink-0">{{ $incident->severity }}</span>
                                                </div>
                                                <p class="f-12 f-light mb-0 mt-1">
                                                    {{ $incident->started_at?->format('d.m. H:i') }}
                                                    @if($incident->resolved_at)
                                                        → {{ $incident->resolved_at->format('H:i') }}
                                                        <span class="badge badge-light-success ms-1">vyřešeno</span>
                                                    @else
                                                        <span class="badge badge-light-danger ms-1">otevřeno</span>
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
    </div>
@endsection
