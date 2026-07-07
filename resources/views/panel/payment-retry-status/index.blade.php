@extends('layouts.panel')

@section('title', 'Stav opakovaných plateb')

@section('content')
<x-panel.flash />

<x-panel.card title="Stav opakovaných plateb">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Faktura ID</th>
                    <th>Datum opakování</th>
                    <th>Stav</th>
                    <th>Pokus č.</th>
                    <th>Poznámka</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($retries as $retry)
                    @php
                        $statusBadge = match($retry->status) {
                            'pending'   => 'secondary',
                            'attempted' => 'warning',
                            'succeeded' => 'success',
                            'cancelled' => 'danger',
                            default     => 'secondary',
                        };
                        $statusLabels = [
                            'pending'   => 'Čeká',
                            'attempted' => 'Pokus proveden',
                            'succeeded' => 'Úspěch',
                            'cancelled' => 'Zrušeno',
                        ];
                    @endphp
                    <tr>
                        <td>{{ $retry->invoice_id }}</td>
                        <td class="text-nowrap">
                            {{ $retry->retry_at ? \Carbon\Carbon::parse($retry->retry_at)->format('d.m.Y H:i') : '—' }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusBadge }}">
                                {{ $statusLabels[$retry->status] ?? $retry->status }}
                            </span>
                        </td>
                        <td>{{ $retry->attempt_number }}</td>
                        <td>{{ $retry->note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Žádné záznamy o opakovaných platbách.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($retries->hasPages())
        <div class="mt-3">
            {{ $retries->links() }}
        </div>
    @endif
</x-panel.card>
@endsection
