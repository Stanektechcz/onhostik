@extends('layouts.panel')

@section('title', 'Historie chargebacků')

@section('content')
<x-panel.flash />

<x-panel.card title="Historie chargebacků">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Faktura ID</th>
                    <th>Částka</th>
                    <th>Měna</th>
                    <th>Důvod</th>
                    <th>Stav</th>
                    <th>Přijato</th>
                    <th>Termín</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chargebacks as $chargeback)
                    @php
                        $statusBadge = match($chargeback->status) {
                            'received'    => 'secondary',
                            'under_review' => 'warning',
                            'won'         => 'success',
                            'lost'        => 'danger',
                            'refunded'    => 'info',
                            default       => 'secondary',
                        };
                        $statusLabels = [
                            'received'    => 'Přijato',
                            'under_review' => 'Ve šetření',
                            'won'         => 'Vyhráno',
                            'lost'        => 'Prohráno',
                            'refunded'    => 'Vráceno',
                        ];
                    @endphp
                    <tr>
                        <td>{{ $chargeback->invoice_id }}</td>
                        <td>{{ number_format($chargeback->amount / 100, 2) }}</td>
                        <td>{{ $chargeback->currency }}</td>
                        <td>{{ $chargeback->reason ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $statusBadge }}">
                                {{ $statusLabels[$chargeback->status] ?? $chargeback->status }}
                            </span>
                        </td>
                        <td class="text-nowrap">
                            {{ $chargeback->received_at ? \Carbon\Carbon::parse($chargeback->received_at)->format('d.m.Y') : '—' }}
                        </td>
                        <td class="text-nowrap">
                            {{ $chargeback->deadline_at ? \Carbon\Carbon::parse($chargeback->deadline_at)->format('d.m.Y') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Žádné chargebacky nenalezeny.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($chargebacks->hasPages())
        <div class="mt-3">
            {{ $chargebacks->links() }}
        </div>
    @endif
</x-panel.card>
@endsection
