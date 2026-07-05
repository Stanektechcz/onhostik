@extends('layouts.panel')

@section('title', 'Okna údržby')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Okna údržby služeb</h1>
        <a href="{{ route('admin.maintenance.create') }}" class="btn btn-primary btn-sm">+ Nové okno</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Filter tabs --}}
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'upcoming' ? 'active' : '' }}"
               href="{{ route('admin.maintenance.index', ['status' => 'upcoming']) }}">Nadcházející</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'all' ? 'active' : '' }}"
               href="{{ route('admin.maintenance.index', ['status' => 'all']) }}">Vše</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'past' ? 'active' : '' }}"
               href="{{ route('admin.maintenance.index', ['status' => 'past']) }}">Minulé</a>
        </li>
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Služba / zákazník</th>
                        <th>Začátek</th>
                        <th>Konec</th>
                        <th>Trvání</th>
                        <th>Stav</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($windows as $w)
                    <tr>
                        <td>
                            <strong>{{ $w->title }}</strong>
                            @if($w->description)
                                <div class="text-muted small">{{ Str::limit($w->description, 60) }}</div>
                            @endif
                        </td>
                        <td class="small">
                            @if($w->service)
                                {{ $w->service->label ?? "#{$w->service->id}" }}<br>
                                <span class="text-muted">{{ $w->service->customer?->user->name ?? '—' }}</span>
                            @else
                                <span class="text-muted">Globální</span>
                            @endif
                        </td>
                        <td class="small">{{ $w->scheduled_start->format('d.m.Y H:i') }}</td>
                        <td class="small">{{ $w->scheduled_end->format('d.m.Y H:i') }}</td>
                        <td class="small text-muted">{{ $w->durationMinutes() }} min</td>
                        <td>
                            <span class="badge {{ $w->statusBadgeClass() }}">{{ $w->statusLabel() }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            @if($w->status === 'scheduled')
                                <form action="{{ route('admin.maintenance.start', $w) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-warning">Zahájit</button>
                                </form>
                            @elseif($w->status === 'in_progress')
                                <form action="{{ route('admin.maintenance.complete', $w) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-success">Dokončit</button>
                                </form>
                            @endif
                            @if(in_array($w->status, ['scheduled', 'in_progress']))
                                <form action="{{ route('admin.maintenance.cancel', $w) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-secondary">Zrušit</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.maintenance.edit', $w) }}" class="btn btn-xs btn-outline-secondary">Upravit</a>
                            <form action="{{ route('admin.maintenance.destroy', $w) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Smazat okno údržby?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádná okna údržby.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $windows->links() }}</div>

</div>
@endsection
