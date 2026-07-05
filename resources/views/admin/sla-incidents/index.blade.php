@extends('layouts.panel')

@section('title', 'SLA Incidenty')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">SLA Incidenty</h1>
        <a href="{{ route('admin.sla-incidents.create') }}" class="btn btn-sm btn-danger">+ Nový incident</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0">{{ $stats['total'] }}</div>
                    <div class="text-muted small">Celkem</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-danger">{{ $stats['open'] }}</div>
                    <div class="text-muted small">Otevřené</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-success">{{ $stats['resolved'] }}</div>
                    <div class="text-muted small">Vyřešené</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-warning">{{ $stats['breached'] }}</div>
                    <div class="text-muted small">SLA porušení</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form class="row g-2 mb-3" method="GET">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">— Stav —</option>
                <option value="open" @selected(request('status') === 'open')>Otevřené</option>
                <option value="investigating" @selected(request('status') === 'investigating')>Šetření</option>
                <option value="resolved" @selected(request('status') === 'resolved')>Vyřešené</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="severity" class="form-select form-select-sm">
                <option value="">— Závažnost —</option>
                @foreach(['critical' => 'Kritická', 'high' => 'Vysoká', 'medium' => 'Střední', 'low' => 'Nízká'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('severity') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary">Filtrovat</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Služba</th>
                        <th>Zákazník</th>
                        <th>Závažnost</th>
                        <th>Stav</th>
                        <th>Výpadek</th>
                        <th>SLA</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                    <tr>
                        <td class="fw-semibold small">{{ $incident->title }}</td>
                        <td class="small">{{ $incident->service?->name ?? '—' }}</td>
                        <td class="small">{{ $incident->service?->customer?->company ?? '—' }}</td>
                        <td><span class="{{ $incident->severityBadgeClass() }}">{{ $incident->severityLabel() }}</span></td>
                        <td><span class="{{ $incident->statusBadgeClass() }}">{{ $incident->statusLabel() }}</span></td>
                        <td class="small">{{ $incident->status === 'resolved' ? $incident->durationLabel() : '—' }}</td>
                        <td>
                            @if($incident->sla_breached)
                                <span class="badge bg-danger">Porušeno</span>
                            @elseif($incident->status === 'resolved')
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $incident->started_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.sla-incidents.show', $incident) }}" class="btn btn-xs btn-outline-secondary">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-muted text-center py-3">Žádné incidenty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $incidents->links() }}</div>

</div>
@endsection
