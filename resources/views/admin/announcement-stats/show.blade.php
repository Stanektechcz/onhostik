@extends('layouts.panel')

@section('title', 'Detail oznámení — ' . $announcement->title)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('admin.announcement-stats.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět</a>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Uživatelé, kteří zavřeli oznámení">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Uživatel</th><th>Čas zavření</th></tr></thead>
                        <tbody>
                            @forelse($dismissals as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td class="text-muted small">{{ $user->pivot->dismissed_at ? \Carbon\Carbon::parse($user->pivot->dismissed_at)->format('d.m.Y H:i') : '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="2" class="text-center text-muted">Zatím nikdo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $dismissals->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-md-4">
            <x-panel.card title="Hodinová statistika zavření">
                @if($hourlyStats->isEmpty())
                    <p class="text-muted">Žádná data.</p>
                @else
                <ul class="list-group list-group-flush">
                    @foreach($hourlyStats as $stat)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">{{ $stat->hour }}</span>
                        <span class="badge bg-primary">{{ $stat->cnt }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
