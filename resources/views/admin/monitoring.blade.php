@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_monitoring');
    $breadcrumbItems = [__('panel.nav.admin_monitoring') => ''];
@endphp

@section('title', __('panel.nav.admin_monitoring'))

@section('content')
    <div class="container-fluid">

        {{-- KPI row --}}
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
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
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
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
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $openAlertCount > 0 ? 'warning' : 'success' }}">
                        <span class="f-light">Aktivní alerty</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $openAlertCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="bell"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
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

        {{-- Uptime breakdown + SLA link --}}
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex flex-wrap gap-3 f-12">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-light-success">≥ 99,9 %</span>
                                    <span class="f-light">Výborná: <strong>{{ $uptimeGroups['excellent'] }}</strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-light-warning">99 – 99,9 %</span>
                                    <span class="f-light">Dobrá: <strong>{{ $uptimeGroups['good'] }}</strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-light-danger">&lt; 99 %</span>
                                    <span class="f-light">Kritická: <strong>{{ $uptimeGroups['poor'] }}</strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-light-secondary">—</span>
                                    <span class="f-light">Neznámá: <strong>{{ $uptimeGroups['unknown'] }}</strong></span>
                                </div>
                            </div>
                            <a href="{{ route('admin.sla.index') }}" class="btn btn-outline-primary btn-sm">
                                <i data-feather="bar-chart-2" style="width:13px;height:13px;"></i> SLA přehled
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 card-gap">
            {{-- Monitors table --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="__('panel.admin.monitors')">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('admin.monitoring.index') }}" class="row g-2 mb-3">
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Všechny stavy</option>
                                @foreach(\App\Domains\Monitoring\Enums\MonitorStatus::cases() as $s)
                                    <option value="{{ $s->value }}" {{ $filterStatus === $s->value ? 'selected' : '' }}>
                                        {{ $s->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Všechny typy</option>
                                @foreach($monitorTypes as $t)
                                    <option value="{{ $t }}" {{ $filterType === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($filterStatus || $filterType)
                        <div class="col-auto">
                            <a href="{{ route('admin.monitoring.index') }}" class="btn btn-sm btn-outline-secondary">
                                Zrušit filtry
                            </a>
                        </div>
                        @endif
                    </form>

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
                                        <th class="text-end">Prahy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($monitors as $monitor)
                                        @php
                                            $uptimePct   = (float) ($monitor->uptime_percent ?? 0);
                                            $uptimeColor = $uptimePct >= 99.9 ? 'success' : ($uptimePct >= 99 ? 'warning' : ($uptimePct > 0 ? 'danger' : 'secondary'));
                                            $sslDays     = $monitor->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null;
                                            $sslColor    = $sslDays === null ? 'secondary' : ($sslDays <= 7 ? 'danger' : ($sslDays <= 30 ? 'warning' : 'success'));
                                            $hasThresholds = $monitor->response_time_threshold_ms || $monitor->uptime_threshold_percent;
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
                                            <td class="text-end">
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-{{ $hasThresholds ? 'primary' : 'secondary' }}"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#threshold-modal-{{ $monitor->id }}"
                                                        title="Nastavit prahy alertů">
                                                    <i data-feather="{{ $hasThresholds ? 'bell' : 'bell-off' }}" style="width:11px;height:11px"></i>
                                                </button>

                                                {{-- Threshold modal --}}
                                                <div class="modal fade" id="threshold-modal-{{ $monitor->id }}" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-sm">
                                                        <div class="modal-content">
                                                            <form method="POST" action="{{ route('admin.monitoring.thresholds', $monitor) }}">
                                                                @csrf
                                                                @method('PUT')
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title f-13">Prahy alertů — {{ $monitor->name }}</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label f-12">Max. odezva (ms)</label>
                                                                        <input type="number" name="response_time_threshold_ms"
                                                                               class="form-control form-control-sm"
                                                                               value="{{ $monitor->response_time_threshold_ms }}"
                                                                               min="1" max="60000" placeholder="prázdné = vypnuto">
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label f-12">Min. dostupnost (%)</label>
                                                                        <input type="number" name="uptime_threshold_percent"
                                                                               class="form-control form-control-sm"
                                                                               value="{{ $monitor->uptime_threshold_percent }}"
                                                                               min="0" max="100" step="0.01" placeholder="prázdné = vypnuto">
                                                                    </div>
                                                                    <div class="mb-0">
                                                                        <label class="form-label f-12">SSL varování (dní před expirací)</label>
                                                                        <input type="number" name="ssl_warn_days"
                                                                               class="form-control form-control-sm"
                                                                               value="{{ $monitor->ssl_warn_days ?? 30 }}"
                                                                               min="1" max="365" required>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                                                                    <button type="submit" class="btn btn-sm btn-primary">Uložit prahy</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
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

            {{-- Open alerts + Incidents timeline --}}
            <div class="col-span-4 xl:col-span-12">

                @if($openAlerts->isNotEmpty())
                <x-panel.card title="Aktivní alerty">
                    <div class="table-responsive mb-0">
                        <table class="table table-sm f-12 mb-0">
                            <thead>
                                <tr>
                                    <th>Monitor</th>
                                    <th>Typ</th>
                                    <th>Aktuální</th>
                                    <th>Práh</th>
                                    <th>Od</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($openAlerts as $alert)
                                <tr>
                                    <td class="f-w-500">{{ $alert->monitor?->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-light-warning f-10">{{ $alert->typeLabel() }}</span>
                                    </td>
                                    <td class="font-monospace">{{ $alert->current_value }}</td>
                                    <td class="font-monospace f-light">{{ $alert->threshold_value }}</td>
                                    <td class="f-light">{{ $alert->triggered_at->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel.card>
                @endif

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
