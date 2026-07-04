@extends('layouts.panel')
@section('title', 'SLA Monitor')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3>SLA Monitor</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.support.index') }}">Podpora</a></li>
                    <li class="breadcrumb-item active">SLA Monitor</li>
                </ol>
            </div>
            <div class="col-sm-6 text-end">
                <span class="badge bg-danger fs-6 me-2">
                    {{ $breached->count() }} porušeno
                </span>
                <span class="badge bg-warning fs-6">
                    {{ $atRisk->count() }} v ohrožení
                </span>
            </div>
        </div>
    </div>

    <x-panel.flash />

    {{-- At-risk tickets --}}
    @if ($atRisk->isNotEmpty())
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i data-feather="clock" class="me-1 text-warning"></i>
                V ohrožení — SLA vyprší do 2 hodin
                <span class="badge bg-warning ms-2">{{ $atRisk->count() }}</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Předmět</th>
                            <th>Zákazník</th>
                            <th>Priorita</th>
                            <th>SLA vypršení</th>
                            <th>Zbývá</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($atRisk as $ticket)
                    <tr>
                        <td><code>{{ $ticket->id }}</code></td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->customer?->company_name ?: $ticket->customer?->email }}</td>
                        <td>
                            <span class="badge bg-{{ $ticket->priority->color() }}">
                                {{ $ticket->priority->label() }}
                            </span>
                        </td>
                        <td>{{ $ticket->sla_deadline?->format('d.m.Y H:i') }}</td>
                        <td class="text-warning fw-semibold">
                            {{ $ticket->sla_deadline?->diffForHumans() }}
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $ticket) }}"
                               class="btn btn-xs btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Breached tickets --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i data-feather="alert-triangle" class="me-1 text-danger"></i>
                Porušené SLA — neuzavřené tikety
                <span class="badge bg-danger ms-2">{{ $breached->count() }}</span>
            </h5>
        </div>
        @if ($breached->isEmpty())
        <div class="card-body text-center text-muted py-4">
            <i data-feather="check-circle" class="text-success" style="width:40px;height:40px"></i>
            <p class="mt-2 mb-0">Žádné porušené SLA.</p>
        </div>
        @else
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Předmět</th>
                            <th>Zákazník</th>
                            <th>Priorita</th>
                            <th>SLA termín</th>
                            <th>Detekce porušení</th>
                            <th>Stav</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($breached as $ticket)
                    <tr>
                        <td><code>{{ $ticket->id }}</code></td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->customer?->company_name ?: $ticket->customer?->email }}</td>
                        <td>
                            <span class="badge bg-{{ $ticket->priority->color() }}">
                                {{ $ticket->priority->label() }}
                            </span>
                        </td>
                        <td class="text-danger">{{ $ticket->sla_deadline?->format('d.m.Y H:i') }}</td>
                        <td>{{ $ticket->sla_breach_notified_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <span class="badge bg-{{ $ticket->status->color() }}">
                                {{ $ticket->status->label() }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $ticket) }}"
                               class="btn btn-xs btn-outline-danger">Detail</a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
