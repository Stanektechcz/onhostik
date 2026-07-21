@extends('layouts.panel')

@section('title', 'Příchozí webhooky')

@section('content')
<div class="container-fluid py-4">

    <div class="flex justify-between items-center mb-4">
        <h1 class="h4 mb-0">Příchozí webhooky</h1>
        <a href="{{ route('admin.webhooks.endpoint.create') }}" class="btn btn-primary btn-sm">+ Nový endpoint</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Endpoint cards --}}
    @if($endpoints->isNotEmpty())
    <div class="grid grid-cols-12 gap-3 mb-4">
        @foreach($endpoints as $ep)
        <div class="col-span-12 md:col-span-4">
            <div class="card h-full">
                <div class="card-body">
                    <div class="flex justify-between items-start mb-2">
                        <strong>{{ $ep->name }}</strong>
                        <span class="badge {{ $ep->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $ep->is_active ? 'Aktivní' : 'Neaktivní' }}
                        </span>
                    </div>
                    <div class="small text-muted mb-1">
                        <code>POST /webhook/{{ $ep->source }}</code>
                    </div>
                    <div class="small text-muted">Podpis: {{ $ep->signature_header }} ({{ $ep->signature_algo }})</div>
                    @if($ep->description)
                        <div class="small text-muted mt-1">{{ $ep->description }}</div>
                    @endif
                    <div class="mt-2 flex gap-1 flex-wrap">
                        <a href="{{ route('admin.webhooks.endpoint.edit', $ep) }}"
                           class="btn btn-xs btn-outline-secondary">Upravit</a>
                        <form action="{{ route('admin.webhooks.endpoint.toggle', $ep) }}" method="POST" class="inline">
                            @csrf
                            <button class="btn btn-xs btn-outline-{{ $ep->is_active ? 'warning' : 'success' }}">
                                {{ $ep->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.webhooks.endpoint.destroy', $ep) }}" method="POST" class="inline"
                              data-confirm="Smazat endpoint?">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger">Smazat</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Filter --}}
    <form class="grid grid-cols-12 gap-2 mb-3" method="GET" action="{{ route('admin.webhooks.inbound.index') }}">
        <div class="col-auto">
            <select name="source" class="form-select form-select-sm">
                <option value="">— Všechny zdroje —</option>
                @foreach($sources as $s)
                    <option value="{{ $s }}" @selected($source === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">— Všechny stavy —</option>
                @foreach(['received' => 'Přijato', 'processed' => 'Zpracováno', 'ignored' => 'Ignorováno', 'failed' => 'Chyba'] as $v => $l)
                    <option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
        </div>
    </form>

    {{-- Logs table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Zdroj</th>
                        <th>Typ události</th>
                        <th>Stav</th>
                        <th>Podpis</th>
                        <th>IP adresa</th>
                        <th>Čas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td><strong>{{ $log->source }}</strong></td>
                        <td class="small"><code>{{ $log->event_type ?? '—' }}</code></td>
                        <td>
                            <span class="badge {{ $log->statusBadgeClass() }}">{{ $log->statusLabel() }}</span>
                        </td>
                        <td>
                            @if($log->signature_valid)
                                <span class="badge bg-success">✓ OK</span>
                            @else
                                <span class="badge bg-danger">✗ Chyba</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $log->ip_address ?? '—' }}</td>
                        <td class="small text-muted">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                        <td>
                            <a href="{{ route('admin.webhooks.inbound.show', $log) }}"
                               class="btn btn-xs btn-outline-secondary">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádné záznamy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>

</div>
@endsection
