@extends('layouts.panel')

@section('title', 'Provisioning audit — ' . $service->name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('admin.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět na službu</a>
    </div>

    <x-panel.card title="Provisioning audit — {{ $service->name }}">
        @if($logs->isEmpty())
            <p class="text-muted">Žádné záznamy pro tuto službu.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Akce</th>
                        <th>Driver</th>
                        <th>Výsledek</th>
                        <th>Administrátor</th>
                        <th>Čas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td><code>{{ $log->action }}</code></td>
                        <td>{{ $log->driver }}</td>
                        <td>
                            @if($log->success)
                                <span class="badge bg-success">OK</span>
                            @else
                                <span class="badge bg-danger" title="{{ $log->error_message }}">Chyba</span>
                            @endif
                        </td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
