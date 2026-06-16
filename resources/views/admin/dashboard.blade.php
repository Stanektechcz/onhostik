@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_dashboard');
@endphp

@section('title', __('panel.nav.admin_dashboard'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/chartist.css') }}">
@endpush

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI widgets row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.admin.revenue') . ' CZK (celkem)'"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($revenueCzkMinor, 'CZK'))"
                    icon="trending-up" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.dashboard.active_services')"
                    :value="$activeServices"
                    icon="server" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.dashboard.unpaid_invoices')"
                    :value="$unpaidInvoices"
                    icon="file-text" color="warning" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.dashboard.open_tickets')"
                    :value="$openTickets"
                    icon="life-buoy" color="danger" />
            </div>
        </div>

        {{-- Secondary metric row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $pendingOrders > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">{{ __('panel.nav.admin_orders') }} — čekající</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $pendingOrders }}</h4>
                            @if($processingOrders > 0)
                                <span class="f-light f-12 mb-1">+ {{ $processingOrders }} zpracovává</span>
                            @endif
                        </div>
                        <div class="bg-gradient"><i data-feather="package"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $failedTasks > 0 ? 'danger' : 'success' }}">
                        <span class="f-light">Provisioning — selhání</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $failedTasks }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="alert-triangle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $suspendedServices > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">Pozastavené služby</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $suspendedServices }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="pause-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $aiApprovals > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">AI — čeká na schválení</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $aiApprovals }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="zap"></i></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="row">
            {{-- Revenue chart --}}
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5>Příjmy — posledních 6 měsíců (CZK)</h5>
                        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-primary btn-sm f-12">Všechny platby</a>
                    </div>
                    <div class="card-body pt-0">
                        <canvas id="revenueChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            {{-- Server capacity --}}
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5>{{ __('panel.nav.admin_servers') }}</h5>
                        <a href="{{ route('admin.servers.index') }}" class="btn btn-outline-primary btn-sm f-12">Správa serverů</a>
                    </div>
                    <div class="card-body pt-0">
                        @forelse($servers as $server)
                            @php
                                $max  = $server->max_services ?? max($server->services_count, 1);
                                $pct  = $max > 0 ? min(100, (int) round($server->services_count / $max * 100)) : 0;
                                $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                            @endphp
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-w-600 f-14">{{ $server->name }}</span>
                                    <span class="f-light f-12">
                                        {{ $server->services_count }} / {{ $server->max_services ?? '∞' }}
                                        @if($server->mock_mode)
                                            <span class="badge badge-light-warning ms-1">mock</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $color }}"
                                         role="progressbar"
                                         style="width: {{ $server->max_services ? $pct : 20 }}%"
                                         aria-valuenow="{{ $pct }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="f-12 f-light">{{ $server->driver?->label() }}</span>
                                    <span class="badge badge-light-{{ $server->status === 'active' ? 'success' : 'warning' }} f-12">
                                        {{ $server->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                        @endforelse

                        <div class="border-top pt-3 mt-1 d-flex justify-content-between f-12 f-light">
                            <span>Aktivní domény: <strong>{{ $activeDomains }}</strong></span>
                            <span>Selhání domén: <strong class="{{ $failedDomainTasks > 0 ? 'text-danger' : '' }}">{{ $failedDomainTasks }}</strong></span>
                            <span>Selhané platby: <strong class="{{ $failedPayments > 0 ? 'text-danger' : '' }}">{{ $failedPayments }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Audit log timeline --}}
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                        <h5>{{ __('panel.nav.admin_audit') }} — posledních 8 událostí</h5>
                        <a href="{{ route('admin.logs.audit') }}" class="btn btn-outline-primary btn-sm f-12">Celý log</a>
                    </div>
                    <div class="card-body pt-0">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul class="common-flex">
                                    @foreach($recentAudit as $activity)
                                        @php
                                            $dotColor = match(true) {
                                                str_contains($activity->description, 'fail') || str_contains($activity->description, 'error') => 'danger',
                                                str_contains($activity->description, 'paid') || str_contains($activity->description, 'created') => 'success',
                                                str_contains($activity->description, 'suspend') => 'warning',
                                                default => 'primary',
                                            };
                                        @endphp
                                        <li class="d-flex align-items-start gap-3 py-2">
                                            <div class="activity-dot-{{ $dotColor }} flex-shrink-0 mt-1"></div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <span class="badge badge-light-primary me-1">{{ $activity->log_name }}</span>
                                                        <span class="f-w-500">{{ $activity->description }}</span>
                                                    </div>
                                                    <span class="f-light f-12 text-nowrap ms-2">{{ $activity->created_at?->format('d.m. H:i') }}</span>
                                                </div>
                                                <p class="f-12 f-light mb-0">{{ $activity->causer?->name ?? 'system' }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#adb5bd' : '#6c757d';

    const ctx = document.getElementById('revenueChart').getContext('2d');

    const gradient = ctx.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(115,102,255,0.25)');
    gradient.addColorStop(1, 'rgba(115,102,255,0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! $chartLabels->toJson() !!},
            datasets: [{
                label: 'Příjmy CZK',
                data: {!! $chartData->toJson() !!},
                borderColor: '#7366ff',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#7366ff',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y.toLocaleString('cs-CZ', { minimumFractionDigits: 2 }) + ' Kč'
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: textColor }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: v => v.toLocaleString('cs-CZ') + ' Kč'
                    },
                    beginAtZero: true,
                }
            }
        }
    });
})();
</script>
@endpush
