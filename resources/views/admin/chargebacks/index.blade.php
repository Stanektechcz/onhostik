@extends('layouts.panel')

@section('title', 'Chargebacky')

@section('content')
<x-panel.flash />

<x-panel.card title="Chargebacky">
    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.chargebacks.index') }}" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">Všechny stavy</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>
                        {{ ucfirst(str_replace('_', ' ', $s)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
            @if (request('status'))
                <a href="{{ route('admin.chargebacks.index') }}" class="btn btn-sm btn-link">Zrušit filtr</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Zákazník</th>
                    <th>Faktura ID</th>
                    <th>Částka</th>
                    <th>Důvod</th>
                    <th>Stav</th>
                    <th>Přijato</th>
                    <th>Termín</th>
                    <th>Referenční č.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chargebacks as $cb)
                    @php
                        $statusColors = [
                            'received'     => 'secondary',
                            'under_review' => 'warning',
                            'won'          => 'success',
                            'lost'         => 'danger',
                            'refunded'     => 'info',
                        ];
                        $statusLabels = [
                            'received'     => 'Přijato',
                            'under_review' => 'V řešení',
                            'won'          => 'Vyhráno',
                            'lost'         => 'Prohráno',
                            'refunded'     => 'Vráceno',
                        ];
                    @endphp
                    <tr>
                        <td>{{ $cb->customer?->name ?? "#{$cb->customer_id}" }}</td>
                        <td class="text-muted small">{{ $cb->invoice_id ?? '—' }}</td>
                        <td>{{ number_format($cb->amount / 100, 2) }} {{ $cb->currency }}</td>
                        <td class="small">{{ $cb->reason }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.chargebacks.update', $cb) }}" class="d-flex gap-1 align-items-center">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="form-select form-select-sm" style="min-width:120px">
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s }}" @selected($cb->status === $s)>
                                            {{ $statusLabels[$s] ?? $s }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">OK</button>
                            </form>
                            <span class="badge bg-{{ $statusColors[$cb->status] ?? 'secondary' }} mt-1 d-inline-block">
                                {{ $statusLabels[$cb->status] ?? $cb->status }}
                            </span>
                        </td>
                        <td class="small text-muted">
                            {{ $cb->received_at ? \Carbon\Carbon::parse($cb->received_at)->format('d.m.Y') : '—' }}
                        </td>
                        <td class="small text-muted">
                            {{ $cb->deadline_at ? \Carbon\Carbon::parse($cb->deadline_at)->format('d.m.Y') : '—' }}
                        </td>
                        <td class="small text-muted">{{ $cb->gateway_reference ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">Žádné chargebacky.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($chargebacks->hasPages())
        <div class="mt-3">{{ $chargebacks->links() }}</div>
    @endif
</x-panel.card>
@endsection
