@extends('layouts.panel')

@section('title', 'Analytika chargebacků')

@section('content')
<x-panel.flash />

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <x-panel.card title="Celkem chargebacků">
            <p class="display-6 mb-0">{{ number_format($totalCount) }}</p>
        </x-panel.card>
    </div>
    <div class="col-md-6">
        <x-panel.card title="Celková částka">
            <p class="display-6 mb-0">{{ number_format($totalAmount / 100, 2) }} CZK</p>
        </x-panel.card>
    </div>
</div>

<x-panel.card title="Statistiky podle stavu">
    @php
        $statusLabels = [
            'received'    => 'Přijato',
            'under_review' => 'Ve šetření',
            'won'         => 'Vyhráno',
            'lost'        => 'Prohráno',
            'refunded'    => 'Vráceno',
        ];
        $statusBadges = [
            'received'    => 'secondary',
            'under_review' => 'warning',
            'won'         => 'success',
            'lost'        => 'danger',
            'refunded'    => 'info',
        ];
    @endphp
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Stav</th>
                    <th>Počet</th>
                    <th>Celková částka</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stats as $stat)
                    <tr>
                        <td>
                            <span class="badge bg-{{ $statusBadges[$stat->status] ?? 'secondary' }}">
                                {{ $statusLabels[$stat->status] ?? $stat->status }}
                            </span>
                        </td>
                        <td>{{ number_format($stat->count) }}</td>
                        <td>{{ number_format(($stat->total_amount ?? 0) / 100, 2) }} CZK</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-3">Žádné záznamy.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel.card>

<x-panel.card title="Posledních 10 chargebacků">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Zákazník</th>
                    <th>Faktura ID</th>
                    <th>Částka</th>
                    <th>Stav</th>
                    <th>Přijato</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentChargebacks as $chargeback)
                    <tr>
                        <td>
                            @if ($chargeback->customer)
                                {{ $chargeback->customer->name ?? '#' . $chargeback->customer->id }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $chargeback->invoice_id }}</td>
                        <td>{{ number_format($chargeback->amount / 100, 2) }} {{ $chargeback->currency ?? 'CZK' }}</td>
                        <td>
                            <span class="badge bg-{{ $statusBadges[$chargeback->status] ?? 'secondary' }}">
                                {{ $statusLabels[$chargeback->status] ?? $chargeback->status }}
                            </span>
                        </td>
                        <td class="text-nowrap">
                            {{ $chargeback->received_at ? \Carbon\Carbon::parse($chargeback->received_at)->format('d.m.Y') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Žádné záznamy.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel.card>
@endsection
