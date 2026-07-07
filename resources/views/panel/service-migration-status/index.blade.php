@extends('layouts.panel')

@section('title', 'Stav migrace služeb')

@section('content')
<x-panel.flash />

<x-panel.card title="Probíhající migrace">
    @if($activeMigrations->isEmpty())
        <p class="text-muted">Žádné aktivní migrace.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Ze serveru</th>
                        <th>Na server</th>
                        <th>Stav</th>
                        <th>Zahájeno</th>
                        <th>Dokončeno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activeMigrations as $batch)
                        <tr>
                            <td>{{ $batch->name }}</td>
                            <td>{{ $batch->source_server_id }}</td>
                            <td>{{ $batch->target_server_id }}</td>
                            <td>
                                @php
                                    $badgeClass = match($batch->status) {
                                        'pending'   => 'secondary',
                                        'running'   => 'warning',
                                        'completed' => 'success',
                                        'failed'    => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">{{ $batch->status }}</span>
                            </td>
                            <td>{{ $batch->started_at ? $batch->started_at->format('d.m.Y H:i') : '—' }}</td>
                            <td>{{ $batch->completed_at ? $batch->completed_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>

<x-panel.card title="Dokončené migrace" class="mt-4">
    @if($completedMigrations->isEmpty())
        <p class="text-muted">Žádné dokončené migrace.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Ze serveru</th>
                        <th>Na server</th>
                        <th>Stav</th>
                        <th>Zahájeno</th>
                        <th>Dokončeno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedMigrations as $batch)
                        <tr>
                            <td>{{ $batch->name }}</td>
                            <td>{{ $batch->source_server_id }}</td>
                            <td>{{ $batch->target_server_id }}</td>
                            <td>
                                @php
                                    $badgeClass = match($batch->status) {
                                        'pending'   => 'secondary',
                                        'running'   => 'warning',
                                        'completed' => 'success',
                                        'failed'    => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">{{ $batch->status }}</span>
                            </td>
                            <td>{{ $batch->started_at ? $batch->started_at->format('d.m.Y H:i') : '—' }}</td>
                            <td>{{ $batch->completed_at ? $batch->completed_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
