@extends('layouts.panel')

@section('title', 'Zálohy služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Filters --}}
        <div class="col-span-12">
            <x-panel.card title="Filtr">
                <form method="GET" action="{{ route('admin.service-backup-logs.index') }}" class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-auto">
                        <label class="form-label form-label-sm mb-1">Service ID</label>
                        <input type="number" name="service_id" class="form-control form-control-sm"
                            value="{{ $serviceId }}" placeholder="ID služby" style="width:140px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label form-label-sm mb-1">Stav</label>
                        <select name="status" class="form-select form-select-sm" style="width:160px;">
                            <option value="">— Vše —</option>
                            <option value="running"   @selected($status === 'running')>Running</option>
                            <option value="success"   @selected($status === 'success')>Success</option>
                            <option value="failed"    @selected($status === 'failed')>Failed</option>
                            <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrovat</button>
                        <a href="{{ route('admin.service-backup-logs.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
                    </div>
                </form>
            </x-panel.card>
        </div>

        {{-- Table --}}
        <div class="col-span-12">
            <x-panel.card title="Záznamy záloh">
                @if($logs->isEmpty())
                    <p class="text-muted">Žádné záznamy.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Service ID</th>
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
                                    <span class="badge bg-light text-dark border">{{ $log->service_id }}</span>
                                </td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'running'   => 'primary',
                                            'success'   => 'success',
                                            'failed'    => 'danger',
                                            'cancelled' => 'secondary',
                                        ];
                                        $color = $statusColors[$log->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $color }}">{{ ucfirst($log->status) }}</span>
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
                                        <span title="{{ $log->error_message }}">
                                            {{ Str::limit($log->error_message, 60) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="f-12">
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
                <div class="mt-3">{{ $logs->appends(request()->query())->links() }}</div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
