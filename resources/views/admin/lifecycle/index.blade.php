@extends('layouts.panel')

@section('title', 'Životní cyklus služeb')

@section('content')
<div class="container-fluid py-4">

    <h1 class="h4 mb-4">Životní cyklus služeb</h1>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center border-danger">
                <div class="card-body py-3">
                    <div class="h3 mb-0 text-danger">{{ $stats['suspended'] }}</div>
                    <div class="text-muted small">Pozastavené služby</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <div class="h3 mb-0 text-warning">{{ $stats['expiring_soon'] }}</div>
                    <div class="text-muted small">Vyprší do 14 dní</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 mb-0 text-secondary">{{ $stats['overdue'] }}</div>
                    <div class="text-muted small">Po splatnosti (risk)</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'suspended' ? 'active' : '' }}"
               href="{{ route('admin.lifecycle.index', ['tab' => 'suspended']) }}">
                Pozastavené
                @if($stats['suspended'] > 0)
                    <span class="badge bg-danger ms-1">{{ $stats['suspended'] }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'expiring' ? 'active' : '' }}"
               href="{{ route('admin.lifecycle.index', ['tab' => 'expiring']) }}">
                Vyprší brzy
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Zákazník</th>
                        <th>Produkt</th>
                        <th>Stav</th>
                        @if($tab === 'suspended')
                            <th>Důvod</th>
                            <th>Pozastaveno</th>
                        @else
                            <th>Příští platba</th>
                        @endif
                        <th class="text-end">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                    <tr>
                        <td class="small text-muted">{{ $service->id }}</td>
                        <td class="small">{{ $service->customer?->company ?? '—' }}</td>
                        <td class="small">{{ $service->product?->name ?? $service->label ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $service->status->badgeClass() }}">{{ $service->status->label() }}</span>
                        </td>
                        @if($tab === 'suspended')
                            <td class="small text-muted">{{ $service->suspension_reason ?? '—' }}</td>
                            <td class="small text-muted">{{ $service->suspended_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        @else
                            <td class="small {{ $service->next_due_date?->isPast() ? 'text-danger' : 'text-warning' }}">
                                {{ $service->next_due_date?->format('d.m.Y') ?? '—' }}
                            </td>
                        @endif
                        <td class="text-end text-nowrap">
                            @if($service->status->value === 'suspended')
                                <form action="{{ route('admin.lifecycle.unsuspend', $service) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-success">Obnovit</button>
                                </form>
                                <form action="{{ route('admin.lifecycle.terminate', $service) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Opravdu ukončit tuto službu?')">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-danger">Ukončit</button>
                                </form>
                            @elseif($service->status->value === 'active')
                                <form action="{{ route('admin.lifecycle.suspend', $service) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="manual_admin">
                                    <button class="btn btn-xs btn-outline-warning">Pozastavit</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádné služby.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $services->links() }}</div>

</div>
@endsection
