@extends('layouts.panel')

@section('title', 'API Usage Analytics')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0">API Usage Analytics</h1>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Celkové volání</div>
                    <div class="h3 fw-bold mb-0 text-primary">{{ number_format($total) }}</div>
                    <div class="text-muted" style="font-size:11px">API požadavků</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Chybovost</div>
                    <div class="h3 fw-bold mb-0 {{ $errorRate > 10 ? 'text-danger' : ($errorRate > 5 ? 'text-warning' : 'text-success') }}">
                        {{ $errorRate }}%
                    </div>
                    <div class="text-muted" style="font-size:11px">odpovědí ≥ 400</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Průměrná odezva</div>
                    <div class="h3 fw-bold mb-0 {{ $avgResponse > 500 ? 'text-danger' : ($avgResponse > 200 ? 'text-warning' : 'text-success') }}">
                        {{ number_format($avgResponse) }} ms
                    </div>
                    <div class="text-muted" style="font-size:11px">response time</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Top endpoints --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>Top endpointy</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th class="text-end">Volání</th>
                                <th class="text-end">Ø ms</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topEndpoints as $ep)
                                <tr>
                                    <td><span class="badge bg-{{ match($ep->method) { 'GET' => 'info', 'POST' => 'success', 'DELETE' => 'danger', default => 'secondary' } }}">{{ $ep->method }}</span></td>
                                    <td class="small font-monospace">{{ $ep->endpoint }}</td>
                                    <td class="text-end small">{{ number_format($ep->hits) }}</td>
                                    <td class="text-end small">{{ $ep->avg_ms }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Žádná data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Status code distribution --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>HTTP status kódy</strong></div>
                <div class="card-body">
                    @php $total2 = $statusDist->sum('count'); @endphp
                    @forelse($statusDist as $row)
                        @php
                            $pct = $total2 > 0 ? round($row->count / $total2 * 100) : 0;
                            $color = $row->status_code < 300 ? 'success' : ($row->status_code < 400 ? 'info' : ($row->status_code < 500 ? 'warning' : 'danger'));
                        @endphp
                        <div class="d-flex align-items-center mb-2 gap-2">
                            <span class="badge bg-{{ $color }}" style="min-width:40px">{{ $row->status_code }}</span>
                            <div class="progress flex-grow-1" style="height:14px">
                                <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                            </div>
                            <span class="small text-muted" style="min-width:35px;text-align:right">{{ $row->count }}</span>
                        </div>
                    @empty
                        <p class="text-muted">Žádná data</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- 14-day trend --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Trend (posledních 14 dní)</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Den</th>
                                <th class="text-end">Volání</th>
                                <th class="text-end">Chyby</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trend as $row)
                                <tr>
                                    <td class="small">{{ $row->day }}</td>
                                    <td class="text-end small">{{ $row->hits }}</td>
                                    <td class="text-end small {{ $row->errors > 0 ? 'text-danger' : 'text-muted' }}">{{ $row->errors }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Žádná data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top users --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Top uživatelé</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Uživatel</th>
                                <th class="text-end">Volání</th>
                                <th class="text-end">Chyby</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topUsers as $u)
                                <tr>
                                    <td class="small">
                                        <a href="{{ route('admin.customers.show', $u->id) }}" class="text-primary">{{ $u->name }}</a>
                                        <br><span class="text-muted" style="font-size:11px">{{ $u->email }}</span>
                                    </td>
                                    <td class="text-end small">{{ number_format($u->hits) }}</td>
                                    <td class="text-end small {{ $u->errors > 0 ? 'text-danger' : 'text-muted' }}">{{ $u->errors }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Žádná data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent log --}}
    <div class="card">
        <div class="card-header"><strong>Posledních 25 volání</strong></div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Čas</th>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th class="text-end">ms</th>
                        <th>Uživatel</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $log)
                        <tr>
                            <td class="small text-muted text-nowrap">{{ $log->created_at?->format('d.m. H:i:s') }}</td>
                            <td><span class="badge bg-{{ match($log->method) { 'GET' => 'info', 'POST' => 'success', 'DELETE' => 'danger', default => 'secondary' } }}">{{ $log->method }}</span></td>
                            <td class="small font-monospace">{{ $log->endpoint }}</td>
                            <td>
                                <span class="badge bg-{{ $log->status_code < 300 ? 'success' : ($log->status_code < 400 ? 'info' : ($log->status_code < 500 ? 'warning' : 'danger')) }}">
                                    {{ $log->status_code }}
                                </span>
                            </td>
                            <td class="text-end small">{{ $log->response_time_ms ?? '—' }}</td>
                            <td class="small">{{ $log->user?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Žádná volání</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
