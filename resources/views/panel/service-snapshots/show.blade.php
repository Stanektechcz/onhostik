@extends('layouts.panel')

@section('title', 'Historie zdrojů — ' . $service->name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('panel.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">
            &larr; Zpět na službu
        </a>
    </div>

    <x-panel.card title="Historie využití zdrojů — {{ $service->name }}">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>Zaznamenáno</th>
                        <th class="text-right">Disk (GB)</th>
                        <th class="text-right">Bandwidth (GB)</th>
                        <th class="text-right">CPU (%)</th>
                        <th class="text-right">RAM (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($snapshots as $snap)
                    <tr>
                        <td>{{ $snap->recorded_at->format('d.m.Y H:i') }}</td>
                        <td class="text-right">{{ $snap->disk_gb ?? '—' }}</td>
                        <td class="text-right">{{ $snap->bandwidth_gb ?? '—' }}</td>
                        <td class="text-right">{{ $snap->cpu_percent !== null ? $snap->cpu_percent . '%' : '—' }}</td>
                        <td class="text-right">{{ $snap->ram_mb ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Žádné záznamy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $snapshots->links() }}</div>
    </x-panel.card>
</div>
@endsection
