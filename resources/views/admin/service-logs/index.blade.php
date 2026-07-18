@extends('layouts.panel')

@section('title', 'Protokol služeb')

@section('content')
<x-panel.flash />

<x-panel.card title="Filtr">
    <form method="GET" action="{{ route('admin.service-logs.index') }}" class="grid grid-cols-12 gap-2 items-end">
        <div class="col-auto">
            <label for="service_id" class="form-label">Služba ID</label>
            <input type="text" id="service_id" name="service_id" class="form-control" value="{{ request('service_id') }}" placeholder="ID služby">
        </div>
        <div class="col-auto">
            <label for="level" class="form-label">Úroveň</label>
            <select id="level" name="level" class="form-select">
                <option value="">Všechny</option>
                @foreach ($levels as $level)
                    <option value="{{ $level }}" @selected(request('level') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
            <a href="{{ route('admin.service-logs.index') }}" class="btn btn-secondary">Zrušit</a>
        </div>
    </form>
</x-panel.card>

<x-panel.card title="Protokol služeb">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Služba ID</th>
                    <th>Úroveň</th>
                    <th>Zdroj</th>
                    <th>Zpráva</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $badgeClass = match($log->level) {
                            'debug'   => 'secondary',
                            'info'    => 'info',
                            'warning' => 'warning',
                            'error'   => 'danger',
                            default   => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($log->logged_at)->format('d.m.Y H:i:s') }}</td>
                        <td>{{ $log->service_id }}</td>
                        <td><span class="badge bg-{{ $badgeClass }}">{{ $log->level }}</span></td>
                        <td>{{ $log->source ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($log->message, 100) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Žádné záznamy nenalezeny.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="mt-3">
            {{ $logs->withQueryString()->links() }}
        </div>
    @endif
</x-panel.card>
@endsection
