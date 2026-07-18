@extends('layouts.panel')

@section('title', 'Zálohy služeb')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-11">
        <x-panel.flash />

        <x-panel.card title="Zálohy služeb">
            @if($logs->isEmpty())
                <p class="text-muted">Žádné záznamy záloh.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Stav</th>
                            <th class="text-right">Velikost</th>
                            <th class="text-right">Trvání (s)</th>
                            <th>Chyba</th>
                            <th>Zahájeno</th>
                            <th>Dokončeno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td class="f-12 text-muted">{{ $log->id }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'running'   => 'primary',
                                        'success'   => 'success',
                                        'failed'    => 'danger',
                                        'cancelled' => 'secondary',
                                    ];
                                    $color = $statusColors[$log->status] ?? 'secondary';
                                    $statusLabels = [
                                        'running'   => 'Probíhá',
                                        'success'   => 'Úspěch',
                                        'failed'    => 'Selhání',
                                        'cancelled' => 'Zrušeno',
                                    ];
                                    $label = $statusLabels[$log->status] ?? ucfirst($log->status);
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ $label }}</span>
                            </td>
                            <td class="text-right f-12">
                                @if($log->size_bytes !== null)
                                    @php
                                        $bytes = $log->size_bytes;
                                        if ($bytes >= 1073741824) {
                                            $formatted = round($bytes / 1073741824, 2) . ' GB';
                                        } elseif ($bytes >= 1048576) {
                                            $formatted = round($bytes / 1048576, 2) . ' MB';
                                        } elseif ($bytes >= 1024) {
                                            $formatted = round($bytes / 1024, 1) . ' KB';
                                        } else {
                                            $formatted = $bytes . ' B';
                                        }
                                    @endphp
                                    {{ $formatted }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-right f-12">
                                {{ $log->duration_seconds ?? '—' }}
                            </td>
                            <td class="f-12 text-danger">
                                @if($log->error_message)
                                    <span title="{{ $log->error_message }}">{{ Str::limit($log->error_message, 60) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="f-12 text-muted">
                                {{ $log->started_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="f-12">
                                {{ $log->completed_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $logs->links() }}</div>
            @endif
        </x-panel.card>
    </div>
</div>
@endsection
