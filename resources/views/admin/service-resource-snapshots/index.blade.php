@extends('layouts.panel')

@section('title', 'Snapshoty zdrojů služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Záznamy využití zdrojů">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>Služba</th>
                        <th>Zákazník</th>
                        <th class="text-end">Disk (GB)</th>
                        <th class="text-end">Bandwidth (GB)</th>
                        <th class="text-end">CPU (%)</th>
                        <th class="text-end">RAM (MB)</th>
                        <th>Zaznamenáno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($snapshots as $snap)
                    <tr>
                        <td>{{ $snap->service?->name ?? '—' }}</td>
                        <td>{{ $snap->service?->customer?->company_name ?? '—' }}</td>
                        <td class="text-end">{{ $snap->disk_gb ?? '—' }}</td>
                        <td class="text-end">{{ $snap->bandwidth_gb ?? '—' }}</td>
                        <td class="text-end">{{ $snap->cpu_percent !== null ? $snap->cpu_percent . '%' : '—' }}</td>
                        <td class="text-end">{{ $snap->ram_mb ?? '—' }}</td>
                        <td>{{ $snap->recorded_at->format('d.m.Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Žádné záznamy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $snapshots->links() }}</div>
    </x-panel.card>
</div>
@endsection
