@extends('layouts.panel')

@section('title', 'OAuth Aplikace — Admin')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0">OAuth Aplikace</h1>
        <span class="badge bg-secondary">{{ $apps->total() }} celkem</span>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Název</th>
                        <th>Zákazník</th>
                        <th>Client ID</th>
                        <th>Stav</th>
                        <th>Poslední použití</th>
                        <th>Vytvořeno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($apps as $app)
                    <tr>
                        <td class="fw-semibold">{{ $app->name }}</td>
                        <td class="small text-muted">{{ $app->customer->user->name ?? '—' }}</td>
                        <td><code class="small">{{ $app->client_id }}</code></td>
                        <td>
                            @if($app->is_active)
                                <span class="badge bg-success">Aktivní</span>
                            @else
                                <span class="badge bg-secondary">Neaktivní</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $app->last_used_at?->format('d.m.Y H:i') ?? 'Nikdy' }}
                        </td>
                        <td class="small text-muted">{{ $app->created_at->format('d.m.Y') }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.developer.oauth-apps.destroy', $app) }}"
                                  onsubmit="return confirm('Smazat OAuth aplikaci?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-sm btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Žádné OAuth aplikace.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($apps->hasPages())
            <div class="card-footer">{{ $apps->links() }}</div>
        @endif
    </div>

</div>
@endsection
