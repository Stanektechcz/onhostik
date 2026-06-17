@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_dashboard');
    use App\Domains\Shared\Support\MoneyFormatter;
    use Brick\Money\Money;
@endphp

@section('title', __('panel.nav.admin_dashboard'))

@push('styles')
    <style>
        /* Cuba-style KPI widget cards */
        .kpi-card { transition: box-shadow .2s; }
        .kpi-card:hover { box-shadow: 0 6px 24px rgba(115,102,255,.15); }
        .kpi-icon-wrap { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .timeline-dot-primary  { width:12px; height:12px; border-radius:50%; background:#7366ff; flex-shrink:0; margin-top:5px; }
        .timeline-dot-success  { width:12px; height:12px; border-radius:50%; background:#54ba4a; flex-shrink:0; margin-top:5px; }
        .timeline-dot-warning  { width:12px; height:12px; border-radius:50%; background:#ffaa05; flex-shrink:0; margin-top:5px; }
        .timeline-dot-danger   { width:12px; height:12px; border-radius:50%; background:#fc4438; flex-shrink:0; margin-top:5px; }
        .timeline-dot-secondary{ width:12px; height:12px; border-radius:50%; background:#adb5bd; flex-shrink:0; margin-top:5px; }
        .health-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
        .integration-card { border-left: 4px solid transparent; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; }
        .integration-card.healthy  { border-left-color: #54ba4a; background: rgba(84,186,74,.06); }
        .integration-card.error    { border-left-color: #fc4438; background: rgba(252,68,56,.06); }
        .integration-card.inactive { border-left-color: #adb5bd; background: rgba(173,181,189,.06); }
        .integration-card.untested { border-left-color: #ffaa05; background: rgba(255,170,5,.06); }
        .quick-action-btn { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; color:inherit; text-decoration:none; transition:background .15s; }
        .quick-action-btn:hover { background: rgba(115,102,255,.08); color:inherit; }
        .renewal-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(0,0,0,.05); }
        .renewal-row:last-child { border-bottom:none; }
    </style>
@endpush

@section('content')
<div class="container-fluid default-dashboard">
    <x-panel.flash />

    {{-- ── ROW 1: Welcome · 4 KPI widgets · System Health ── --}}
    <div class="row g-3 mb-3">

        {{-- Welcome greeting card --}}
        <div class="col-xl-4 col-md-6">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex flex-column justify-content-between" style="background: linear-gradient(135deg,#7366ff 0%,#563ee0 100%); border-radius: 8px; color:#fff; min-height:180px;">
                    <div>
                        <h4 class="mb-1 fw-semibold" style="color:#fff;">Vítejte, {{ auth()->user()?->name ?? 'Admin' }}!</h4>
                        <p class="mb-0" style="opacity:.85; font-size:14px;">Přehled systému OnHost — {{ now()->format('d. m. Y') }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-3" style="flex-wrap:wrap;">
                        @if($pendingOrders > 0)
                            <span class="badge" style="background:rgba(255,255,255,.25); font-size:12px;">{{ $pendingOrders }} čekajících objednávek</span>
                        @endif
                        @if($failedTasks > 0)
                            <span class="badge" style="background:rgba(252,68,56,.5); font-size:12px;">{{ $failedTasks }} selhání provisioningu</span>
                        @endif
                        @if($overdueInvoices > 0)
                            <span class="badge" style="background:rgba(255,170,5,.5); font-size:12px;">{{ $overdueInvoices }} po splatnosti</span>
                        @endif
                        @if($pendingOrders === 0 && $failedTasks === 0 && $overdueInvoices === 0)
                            <span class="badge" style="background:rgba(84,186,74,.4); font-size:12px;">Vše v pořádku</span>
                        @endif
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('admin.logs.audit') }}" class="btn btn-sm" style="background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.4);">Audit log</a>
                        <a href="{{ route('admin.system.index') }}" class="btn btn-sm ms-2" style="background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.4);">System check</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4 KPI mini-cards --}}
        <div class="col-xl-5 col-md-6">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="card kpi-card small-widget">
                        <div class="card-body success">
                            <span class="f-light">Tržby CZK (celkem)</span>
                            <div class="d-flex align-items-end gap-1 mt-1">
                                <h4 class="mb-0">{{ MoneyFormatter::format(Money::ofMinor($revenueCzkMinor, 'CZK')) }}</h4>
                            </div>
                            <div class="bg-gradient"><i data-feather="trending-up"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card kpi-card small-widget">
                        <div class="card-body primary">
                            <span class="f-light">Aktivní služby</span>
                            <div class="d-flex align-items-end gap-1 mt-1">
                                <h4 class="mb-0">{{ $activeServices }}</h4>
                                @if($suspendedServices > 0)
                                    <span class="f-light f-12 mb-1">+{{ $suspendedServices }} pozastaveno</span>
                                @endif
                            </div>
                            <div class="bg-gradient"><i data-feather="server"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card kpi-card small-widget">
                        <div class="card-body {{ $unpaidInvoices > 0 ? 'warning' : 'secondary' }}">
                            <span class="f-light">Nezaplacené faktury</span>
                            <div class="d-flex align-items-end gap-1 mt-1">
                                <h4 class="mb-0">{{ $unpaidInvoices }}</h4>
                                @if($overdueInvoices > 0)
                                    <span class="f-light f-12 mb-1 txt-danger">{{ $overdueInvoices }} po splatnosti</span>
                                @endif
                            </div>
                            <div class="bg-gradient"><i data-feather="file-text"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card kpi-card small-widget">
                        <div class="card-body {{ $openTickets > 0 ? 'danger' : 'secondary' }}">
                            <span class="f-light">Zákazníci / Tickety</span>
                            <div class="d-flex align-items-end gap-1 mt-1">
                                <h4 class="mb-0">{{ $customerCount }}</h4>
                                @if($openTickets > 0)
                                    <span class="f-light f-12 mb-1 txt-danger">{{ $openTickets }} otevřených</span>
                                @endif
                            </div>
                            <div class="bg-gradient"><i data-feather="users"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- System health --}}
        <div class="col-xl-3 col-md-12">
            <div class="card kpi-card h-100">
                <div class="card-header card-no-border pb-2">
                    <h5>Stav systému</h5>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="f-14">Fronta (pending)</span>
                        <span class="badge {{ $pendingJobs > 10 ? 'badge-light-warning' : 'badge-light-success' }}">{{ $pendingJobs }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="f-14">Provisioning selhání</span>
                        <span class="badge {{ $failedTasks > 0 ? 'badge-light-danger' : 'badge-light-success' }}">{{ $failedTasks }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="f-14">Pozastavené služby</span>
                        <span class="badge {{ $suspendedServices > 0 ? 'badge-light-warning' : 'badge-light-success' }}">{{ $suspendedServices }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="f-14">Selhané platby</span>
                        <span class="badge {{ $failedPayments > 0 ? 'badge-light-danger' : 'badge-light-success' }}">{{ $failedPayments }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                        <span class="f-14">AI schválení</span>
                        <span class="badge {{ $aiApprovals > 0 ? 'badge-light-warning' : 'badge-light-success' }}">{{ $aiApprovals }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="f-14">Aktivní domény</span>
                        <span class="badge badge-light-primary">{{ $activeDomains }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ROW 2: Revenue chart · Server capacity ── --}}
    <div class="row g-3 mb-3">
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

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                    <h5>Servery — vytížení</h5>
                    <a href="{{ route('admin.servers.index') }}" class="btn btn-outline-primary btn-sm f-12">Správa</a>
                </div>
                <div class="card-body pt-0">
                    @forelse($servers as $server)
                        @php
                            $max   = $server->max_services ?? max($server->services_count, 1);
                            $pct   = $max > 0 ? min(100, (int) round($server->services_count / $max * 100)) : 0;
                            $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="f-w-600 f-14">{{ $server->name }}</span>
                                <span class="f-light f-12">
                                    {{ $server->services_count }} / {{ $server->max_services ?? '∞' }}
                                    @if($server->mock_mode ?? false)
                                        <span class="badge badge-light-warning ms-1">mock</span>
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-{{ $color }}"
                                     role="progressbar"
                                     style="width:{{ $server->max_services ? $pct : 20 }}%"
                                     aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="f-12 f-light">{{ $server->driver?->label() ?? $server->driver ?? '—' }}</span>
                                <span class="badge badge-light-{{ ($server->status ?? '') === 'active' ? 'success' : 'warning' }} f-12">{{ $server->status ?? '—' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="d-flex flex-column align-items-center justify-content-center py-4 f-light">
                            <i data-feather="server" class="mb-2" style="width:32px;height:32px;opacity:.4;"></i>
                            <p class="mb-2">Žádné servery</p>
                            <a href="{{ route('admin.servers.create') }}" class="btn btn-primary btn-sm">Přidat server</a>
                        </div>
                    @endforelse

                    @if($servers->isNotEmpty())
                        <div class="border-top pt-2 mt-1 d-flex justify-content-between f-12 f-light">
                            <span>Domény: <strong>{{ $activeDomains }}</strong></span>
                            @if($failedDomainTasks > 0)
                                <span class="txt-danger">Selhání domén: <strong>{{ $failedDomainTasks }}</strong></span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── ROW 3: Recent orders · Audit timeline ── --}}
    <div class="row g-3 mb-3">
        {{-- Recent orders --}}
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                    <h5>Poslední objednávky</h5>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary btn-sm f-12">Všechny objednávky</a>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Zákazník</th>
                                    <th>Celkem</th>
                                    <th>Datum</th>
                                    <th>Stav</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    @php
                                        $statusColor = match($order->status->value) {
                                            'active'      => 'success',
                                            'pending'     => 'warning',
                                            'processing'  => 'info',
                                            'cancelled','fraud' => 'danger',
                                            default       => 'secondary',
                                        };
                                        $statusLabel = match($order->status->value) {
                                            'active'     => 'Aktivní',
                                            'pending'    => 'Čeká',
                                            'processing' => 'Zpracovává',
                                            'cancelled'  => 'Zrušeno',
                                            'fraud'      => 'Podvod',
                                            default      => $order->status->value,
                                        };
                                        $displayName = $order->customer?->company_name
                                            ?? $order->customer?->email
                                            ?? '—';
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="f-w-500 f-14">
                                                #{{ $order->id }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="f-14">{{ $displayName }}</span>
                                        </td>
                                        <td class="f-w-500">
                                            {{ MoneyFormatter::format($order->total) }}
                                        </td>
                                        <td class="f-light f-12">{{ $order->created_at->format('d.m.Y') }}</td>
                                        <td>
                                            <span class="badge badge-light-{{ $statusColor }}">{{ $statusLabel }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center f-light py-4">Žádné objednávky</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Audit timeline --}}
        <div class="col-xl-5">
            <div class="card activity-log notification main-timeline">
                <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                    <h5>Audit log</h5>
                    <a href="{{ route('admin.logs.audit') }}" class="btn btn-outline-primary btn-sm f-12">Celý log</a>
                </div>
                <div class="card-body pt-0 dark-timeline basic-timeline">
                    <ul>
                        @forelse($recentAudit as $activity)
                            @php
                                $dotClass = match(true) {
                                    str_contains($activity->description, 'fail') || str_contains($activity->description, 'error') => 'danger',
                                    str_contains($activity->description, 'paid') || str_contains($activity->description, 'created') => 'success',
                                    str_contains($activity->description, 'suspend') => 'warning',
                                    str_contains($activity->description, 'cancel') => 'danger',
                                    default => 'primary',
                                };
                            @endphp
                            <li class="d-flex gap-3 py-2">
                                <div class="timeline-dot-{{ $dotClass }}"></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge badge-light-primary me-1 f-10">{{ $activity->log_name }}</span>
                                            <span class="f-w-500 f-13">{{ $activity->description }}</span>
                                        </div>
                                        <span class="f-light f-11 text-nowrap ms-2">{{ $activity->created_at?->format('d.m. H:i') }}</span>
                                    </div>
                                    <p class="f-11 f-light mb-0">{{ $activity->causer?->name ?? 'system' }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-center f-light py-4">Žádné záznamy</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ROW 4: Upcoming renewals · Integrations · Quick actions ── --}}
    <div class="row g-3 mb-3">

        {{-- Upcoming renewals / dunning --}}
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                    <h5>Nadcházející obnovy <span class="badge badge-light-primary ms-1">14 dní</span></h5>
                    <a href="{{ route('admin.services.index') }}" class="btn btn-outline-primary btn-sm f-12">Všechny služby</a>
                </div>
                <div class="card-body pt-0">
                    @forelse($upcomingRenewals as $service)
                        @php
                            $daysLeft = (int) now()->diffInDays($service->next_due_date, false);
                            $urgencyColor = $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'success');
                            $customerName = $service->customer?->company_name ?? $service->customer?->email ?? '—';
                        @endphp
                        <div class="renewal-row">
                            <div>
                                <div class="f-14 f-w-500">{{ $service->label ?? ('Služba #' . $service->id) }}</div>
                                <div class="f-12 f-light">{{ $customerName }}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge badge-light-{{ $urgencyColor }}">
                                    {{ $service->next_due_date->format('d.m.Y') }}
                                </span>
                                <div class="f-11 f-light mt-1">za {{ $daysLeft }} dní</div>
                            </div>
                        </div>
                    @empty
                        <div class="d-flex flex-column align-items-center py-4 f-light">
                            <i data-feather="check-circle" class="mb-2" style="width:32px;height:32px;opacity:.4;color:#54ba4a;"></i>
                            <p class="mb-0">Žádné obnovy v příštích 14 dnech</p>
                        </div>
                    @endforelse

                    @if($pendingOrders > 0 || $overdueInvoices > 0)
                        <div class="border-top pt-3 mt-2 d-flex gap-3">
                            @if($pendingOrders > 0)
                                <a href="{{ route('admin.orders.index') }}" class="btn btn-warning btn-sm">
                                    {{ $pendingOrders }} čekajících objednávek
                                </a>
                            @endif
                            @if($overdueInvoices > 0)
                                <a href="{{ route('admin.invoices.index') }}" class="btn btn-danger btn-sm">
                                    {{ $overdueInvoices }} po splatnosti
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Integrations status --}}
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header card-no-border d-flex justify-content-between align-items-center">
                    <h5>Integrace</h5>
                    <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-primary btn-sm f-12">Správa</a>
                </div>
                <div class="card-body pt-0">
                    @php
                        $provisioningMock = (bool) config('provisioning.mock_mode', true);
                        $comgateMock = (bool) config('comgate.test_mode', true);
                    @endphp

                    {{-- Comgate --}}
                    <div class="integration-card {{ $comgateMock ? 'untested' : 'healthy' }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="f-w-600 f-14">Comgate</span>
                                <p class="f-11 f-light mb-0">Platební brána</p>
                            </div>
                            @if($comgateMock)
                                <span class="badge badge-light-warning">TEST MODE</span>
                            @else
                                <span class="badge badge-light-success">PRODUKCE</span>
                            @endif
                        </div>
                    </div>

                    {{-- Provisioning (aaPanel / WEDOS) --}}
                    <div class="integration-card {{ $provisioningMock ? 'untested' : 'healthy' }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="f-w-600 f-14">Provisioning</span>
                                <p class="f-11 f-light mb-0">aaPanel + WEDOS</p>
                            </div>
                            @if($provisioningMock)
                                <span class="badge badge-light-warning">MOCK</span>
                            @else
                                <span class="badge badge-light-success">LIVE</span>
                            @endif
                        </div>
                    </div>

                    {{-- DB integration settings --}}
                    @forelse($integrations as $integration)
                        @php $health = $integration->healthStatus(); @endphp
                        <div class="integration-card {{ $health }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="f-w-600 f-14">{{ $integration->label ?? $integration->provider }}</span>
                                    <p class="f-11 f-light mb-0">{{ $integration->provider }}</p>
                                </div>
                                <div class="text-end">
                                    @switch($health)
                                        @case('healthy')
                                            <span class="badge badge-light-success">OK</span>
                                            @break
                                        @case('error')
                                            <span class="badge badge-light-danger">Chyba</span>
                                            @break
                                        @case('inactive')
                                            <span class="badge badge-light-secondary">Neaktivní</span>
                                            @break
                                        @default
                                            <span class="badge badge-light-warning">Netestováno</span>
                                    @endswitch
                                    @if($integration->mock_mode)
                                        <div class="f-10 f-light mt-1">mock</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                    @endforelse

                    @if($failedDomainTasks > 0)
                        <div class="border-top pt-2 mt-2">
                            <a href="{{ route('admin.provisioning.index') }}" class="d-flex align-items-center gap-2 f-12 txt-danger text-decoration-none">
                                <i data-feather="alert-triangle" style="width:14px;height:14px;"></i>
                                {{ $failedDomainTasks }} selhání doménových úloh
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header card-no-border pb-2">
                    <h5>Rychlé akce</h5>
                </div>
                <div class="card-body pt-0">
                    <a href="{{ route('admin.customers.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="users" style="width:18px;height:18px;color:#7366ff;"></i>
                        <div>
                            <div class="f-14 f-w-500">Zákazníci</div>
                            <div class="f-11 f-light">{{ $customerCount }} celkem</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.orders.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="package" style="width:18px;height:18px;color:#ffaa05;"></i>
                        <div>
                            <div class="f-14 f-w-500">Objednávky</div>
                            <div class="f-11 f-light {{ $pendingOrders > 0 ? 'txt-warning' : 'f-light' }}">{{ $pendingOrders }} čeká</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.invoices.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="file-text" style="width:18px;height:18px;color:#fc4438;"></i>
                        <div>
                            <div class="f-14 f-w-500">Faktury</div>
                            <div class="f-11 {{ $overdueInvoices > 0 ? 'txt-danger' : 'f-light' }}">{{ $unpaidInvoices }} neuhrazeno</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.services.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="server" style="width:18px;height:18px;color:#54ba4a;"></i>
                        <div>
                            <div class="f-14 f-w-500">Služby</div>
                            <div class="f-11 f-light">{{ $activeServices }} aktivních</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.support.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="life-buoy" style="width:18px;height:18px;color:#fc4438;"></i>
                        <div>
                            <div class="f-14 f-w-500">Podpora</div>
                            <div class="f-11 {{ $openTickets > 0 ? 'txt-danger' : 'f-light' }}">{{ $openTickets }} otevřených</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.provisioning.index') }}" class="quick-action-btn border-bottom">
                        <i data-feather="cpu" style="width:18px;height:18px;color:#7366ff;"></i>
                        <div>
                            <div class="f-14 f-w-500">Provisioning</div>
                            <div class="f-11 {{ $failedTasks > 0 ? 'txt-danger' : 'f-light' }}">{{ $failedTasks }} selhání</div>
                        </div>
                    </a>
                    @if($aiApprovals > 0)
                    <a href="{{ route('admin.ai.index') }}" class="quick-action-btn">
                        <i data-feather="zap" style="width:18px;height:18px;color:#ffaa05;"></i>
                        <div>
                            <div class="f-14 f-w-500">AI schválení</div>
                            <div class="f-11 txt-warning">{{ $aiApprovals }} čeká</div>
                        </div>
                    </a>
                    @endif
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
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
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
