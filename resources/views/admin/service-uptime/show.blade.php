@extends('layouts.panel')
@section('title', 'Uptime: ' . $service->label)
@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold {{ ($uptime ?? 100) >= 99 ? 'text-success' : (($uptime ?? 100) >= 95 ? 'text-warning' : 'text-danger') }}">
                        {{ $uptime !== null ? number_format((float)$uptime, 2) . ' %' : 'N/A' }}
                    </div>
                    <small class="text-muted">Uptime (30 dní)</small>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Historie checků">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Čas</th><th>Typ</th><th>Cíl</th><th class="text-end">MS</th><th>Stav</th></tr></thead>
                <tbody>
                    @forelse($checks as $c)
                    <tr>
                        <td class="small text-muted">{{ $c->checked_at?->format('d.m. H:i') }}</td>
                        <td>{{ strtoupper($c->check_type) }}</td>
                        <td class="text-truncate" style="max-width:200px">{{ $c->target }}</td>
                        <td class="text-end">{{ $c->response_ms ?? '—' }}</td>
                        <td>
                            @if($c->is_up)
                                <span class="badge bg-success">UP</span>
                            @else
                                <span class="badge bg-danger" title="{{ $c->error_message }}">DOWN</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted">Žádné záznamy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $checks->links() }}</div>
    </x-panel.card>
</div>
@endsection
