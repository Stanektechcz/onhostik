@extends('layouts.panel')

@section('title', 'GDPR & Compliance')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0">GDPR Žádosti</h1>
        @if($pendingCount > 0)
            <span class="badge bg-warning text-dark fs-6">{{ $pendingCount }} čeká na vyřízení</span>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Zákazník</th>
                        <th>Typ</th>
                        <th>Stav</th>
                        <th>Poznámka admina</th>
                        <th>Podáno</th>
                        <th>Dokončeno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr class="{{ $req->isPending() ? 'table-warning' : '' }}">
                        <td>
                            <div class="fw-semibold small">{{ $req->customer->user->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:11px">{{ $req->customer->user->email ?? '' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $req->type->color() }}">{{ $req->type->label() }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $req->status->color() }}">{{ $req->status->label() }}</span>
                        </td>
                        <td class="text-muted small">{{ $req->admin_note ?? '—' }}</td>
                        <td class="small text-muted">{{ $req->created_at->format('d.m.Y H:i') }}</td>
                        <td class="small text-muted">{{ $req->completed_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            @if($req->isPending())
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-xs btn-success btn-sm"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#approve-{{ $req->id }}">
                                        Schválit
                                    </button>
                                    <button type="button" class="btn btn-xs btn-danger btn-sm"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#reject-{{ $req->id }}">
                                        Zamítnout
                                    </button>
                                </div>
                                <div class="collapse mt-2" id="approve-{{ $req->id }}">
                                    <form method="POST" action="{{ route('admin.compliance.approve', $req) }}">
                                        @csrf @method('PATCH')
                                        <input type="text" name="admin_note" class="form-control form-control-sm mb-1"
                                               placeholder="Poznámka (nepovinné)">
                                        <button class="btn btn-success btn-sm w-100">Potvrdit schválení</button>
                                    </form>
                                </div>
                                <div class="collapse mt-2" id="reject-{{ $req->id }}">
                                    <form method="POST" action="{{ route('admin.compliance.reject', $req) }}">
                                        @csrf @method('PATCH')
                                        <input type="text" name="admin_note" class="form-control form-control-sm mb-1"
                                               placeholder="Důvod zamítnutí">
                                        <button class="btn btn-danger btn-sm w-100">Potvrdit zamítnutí</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Žádné GDPR žádosti.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>

</div>
@endsection
