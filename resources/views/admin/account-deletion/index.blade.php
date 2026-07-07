@extends('layouts.panel')

@section('title', 'Žádosti o smazání účtu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Žádosti o smazání účtu">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Uživatel</th>
                        <th>Důvod</th>
                        <th>Stav</th>
                        <th>Plánované smazání</th>
                        <th>Vytvořeno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td>{{ $req->user?->name }} <small class="text-muted">{{ $req->user?->email }}</small></td>
                        <td class="text-muted small">{{ Str::limit($req->reason, 80) ?? '—' }}</td>
                        <td>
                            @php $badges = ['pending'=>'warning text-dark','approved'=>'danger','rejected'=>'secondary','completed'=>'success'] @endphp
                            <span class="badge bg-{{ $badges[$req->status] ?? 'secondary' }}">{{ $req->status }}</span>
                        </td>
                        <td class="text-muted small">{{ $req->scheduled_deletion_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="text-muted small">{{ $req->created_at?->format('d.m.Y') }}</td>
                        <td>
                            @if($req->status === 'pending')
                            <form method="POST" action="{{ route('admin.account-deletion.approve', $req) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="admin_note" value="">
                                <button class="btn btn-sm btn-outline-danger">Schválit</button>
                            </form>
                            <form method="POST" action="{{ route('admin.account-deletion.reject', $req) }}" class="d-inline ms-1">
                                @csrf @method('PATCH')
                                <input type="hidden" name="admin_note" value="Zamítnuto administrátorem.">
                                <button class="btn btn-sm btn-outline-secondary">Zamítnout</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Žádné žádosti.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->links() }}</div>
    </x-panel.card>
</div>
@endsection
