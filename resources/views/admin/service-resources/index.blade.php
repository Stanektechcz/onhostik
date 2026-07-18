@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Limity zdrojů služeb';
    $breadcrumbItems = ['Limity zdrojů' => ''];
@endphp

@section('title', 'Limity zdrojů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Stats cards --}}
    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['total_monitored'] }}</h3>
                    <small class="f-light">Sledovaných služeb</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 {{ $stats['over_threshold'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $stats['over_threshold'] }}
                    </h3>
                    <small class="f-light">Nad limitem</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 f-14">{{ $stats['last_checked'] ? $stats['last_checked']->diffForHumans() : '—' }}</h3>
                    <small class="f-light">Poslední kontrola</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-no-border flex items-center gap-3">
            <h5 class="mb-0">Využití zdrojů</h5>
            <div class="ms-auto flex gap-2">
                <a href="{{ route('admin.service-resources.index', ['filter' => 'all']) }}"
                   class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">Vše</a>
                <a href="{{ route('admin.service-resources.index', ['filter' => 'alerts']) }}"
                   class="btn btn-sm {{ $filter === 'alerts' ? 'btn-danger' : 'btn-outline-danger' }}">
                    Nad limitem
                </a>
            </div>
        </div>
        <div class="card-body pt-0">
            @if($services->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="cpu" style="width:40px;height:40px;" class="text-muted mb-3 block mx-auto"></i>
                    <h6 class="f-light">Žádná data o využití zdrojů</h6>
                    <p class="f-light f-12">Data se zobrazí po prvním zaznamenání využití.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Služba</th>
                                <th>CPU</th>
                                <th>RAM</th>
                                <th>Disk</th>
                                <th>Bandwidth</th>
                                <th>Kontrola</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                            @php
                                $cpuPct = ($service->cpu_limit_percent && $service->cpu_usage_percent)
                                    ? round(($service->cpu_usage_percent / $service->cpu_limit_percent) * 100)
                                    : null;
                                $ramPct = ($service->ram_limit_mb && $service->ram_usage_mb)
                                    ? round(($service->ram_usage_mb / $service->ram_limit_mb) * 100)
                                    : null;
                                $diskPct = ($service->disk_limit_gb && $service->disk_usage_gb)
                                    ? round(($service->disk_usage_gb / $service->disk_limit_gb) * 100)
                                    : null;
                                $bwPct = ($service->bandwidth_limit_gb && $service->bandwidth_usage_gb)
                                    ? round(($service->bandwidth_usage_gb / $service->bandwidth_limit_gb) * 100)
                                    : null;
                                $threshold = $service->resource_alert_threshold ?? 80;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.services.show', $service) }}" class="f-w-500">
                                        {{ $service->label ?? "Služba #{$service->id}" }}
                                    </a>
                                    <br><small class="f-light f-11">{{ $service->customer?->email }}</small>
                                </td>
                                <td>
                                    @if($cpuPct !== null)
                                        <div class="progress" style="height:6px;width:80px;">
                                            <div class="progress-bar bg-{{ $cpuPct >= $threshold ? 'danger' : 'success' }}"
                                                 style="width:{{ min($cpuPct, 100) }}%"></div>
                                        </div>
                                        <small class="{{ $cpuPct >= $threshold ? 'text-danger' : 'f-light' }} f-10">
                                            {{ $service->cpu_usage_percent }}% / {{ $service->cpu_limit_percent }}%
                                        </small>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ramPct !== null)
                                        <div class="progress" style="height:6px;width:80px;">
                                            <div class="progress-bar bg-{{ $ramPct >= $threshold ? 'danger' : 'success' }}"
                                                 style="width:{{ min($ramPct, 100) }}%"></div>
                                        </div>
                                        <small class="{{ $ramPct >= $threshold ? 'text-danger' : 'f-light' }} f-10">
                                            {{ $service->ram_usage_mb }} / {{ $service->ram_limit_mb }} MB
                                        </small>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($diskPct !== null)
                                        <div class="progress" style="height:6px;width:80px;">
                                            <div class="progress-bar bg-{{ $diskPct >= $threshold ? 'danger' : 'success' }}"
                                                 style="width:{{ min($diskPct, 100) }}%"></div>
                                        </div>
                                        <small class="{{ $diskPct >= $threshold ? 'text-danger' : 'f-light' }} f-10">
                                            {{ $service->disk_usage_gb }} / {{ $service->disk_limit_gb }} GB
                                        </small>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($bwPct !== null)
                                        <div class="progress" style="height:6px;width:80px;">
                                            <div class="progress-bar bg-{{ $bwPct >= $threshold ? 'danger' : 'success' }}"
                                                 style="width:{{ min($bwPct, 100) }}%"></div>
                                        </div>
                                        <small class="{{ $bwPct >= $threshold ? 'text-danger' : 'f-light' }} f-10">
                                            {{ $service->bandwidth_usage_gb }} / {{ $service->bandwidth_limit_gb }} GB
                                        </small>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                                <td class="f-12 f-light">
                                    {{ $service->last_resource_check_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $services->withQueryString()->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
