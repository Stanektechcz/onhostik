@extends('layouts.front')

@section('title', 'Stav systému — Onhost.cz')
@section('meta_description', 'Aktuální stav služeb a infrastruktury Onhost.cz — uptime, incidenty a plánovaná údržba.')

@section('content')
<div class="top-header">
    <div class="total-grad-inverse"></div>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 text-center">
                <div class="wrapper">
                    @if($overallStatus === 'operational')
                        <div class="mb-3" style="font-size:48px;">✅</div>
                        <h1 class="heading">Všechny služby fungují</h1>
                        <p class="subheading">Provoz je v pořádku · průměrná dostupnost {{ $avgUptime }}%</p>
                    @elseif($overallStatus === 'degraded')
                        <div class="mb-3" style="font-size:48px;">⚠️</div>
                        <h1 class="heading">Snížený výkon</h1>
                        <p class="subheading">{{ $downCount }} ze {{ $upCount + $downCount }} služeb hlásí výpadek</p>
                    @else
                        <div class="mb-3" style="font-size:48px;">🔴</div>
                        <h1 class="heading">Výpadek služeb</h1>
                        <p class="subheading">Pracujeme na nápravě — sledujte tuto stránku</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<section class="section-b-space pt-5 pb-5">
    <div class="container">

        {{-- Open incidents --}}
        @if($openIncidents->isNotEmpty())
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-body">
                        <h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Aktivní incidenty</h5>
                        @foreach($openIncidents as $inc)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <strong>{{ $inc->monitor?->label ?? '—' }}</strong>
                                <span class="text-muted ms-2 small">{{ $inc->reason ?? 'Výpadek detekován' }}</span>
                            </div>
                            <span class="badge bg-danger">Probíhá · od {{ $inc->started_at?->format('H:i d.m.Y') }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Service grid --}}
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-3">Stav komponent</h3>
            </div>
            @forelse($monitors as $monitor)
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        @if($monitor->status === \App\Domains\Monitoring\Enums\MonitorStatus::Up->value)
                            <span style="color:#22c55e;font-size:20px;">●</span>
                        @else
                            <span style="color:#ef4444;font-size:20px;">●</span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $monitor->label }}</div>
                            <div class="text-muted small">
                                {{ $monitor->uptime_percent !== null ? number_format((float)$monitor->uptime_percent, 1) . '%' : '—' }} dostupnost
                                @if($monitor->last_check_at)
                                    · kontrola {{ $monitor->last_check_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $monitor->status === \App\Domains\Monitoring\Enums\MonitorStatus::Up->value ? 'bg-success' : 'bg-danger' }}">
                            {{ $monitor->status === \App\Domains\Monitoring\Enums\MonitorStatus::Up->value ? 'Online' : 'Výpadek' }}
                        </span>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <p class="text-muted">Monitoring zatím není nakonfigurován. Přidejte monitory v admin panelu.</p>
            </div>
            @endforelse
        </div>

        {{-- Recent resolved incidents --}}
        @if($recentIncidents->isNotEmpty())
        <div class="row">
            <div class="col-12">
                <h3 class="mb-3">Nedávné incidenty</h3>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Služba</th>
                                    <th>Příčina</th>
                                    <th>Začátek</th>
                                    <th>Délka výpadku</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentIncidents as $inc)
                                <tr>
                                    <td>{{ $inc->monitor?->label ?? '—' }}</td>
                                    <td>{{ $inc->reason ?? '—' }}</td>
                                    <td>{{ $inc->started_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        @if($inc->started_at && $inc->resolved_at)
                                            {{ gmdate('H:i:s', (int)$inc->started_at->diffInSeconds($inc->resolved_at)) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</section>
@endsection
