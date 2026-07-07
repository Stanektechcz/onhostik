@extends('layouts.panel')

@section('title', 'Historie e-mailů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Historie odeslaných e-mailů">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>Předmět</th>
                        <th>Příjemce</th>
                        <th>Stav</th>
                        <th>Odesláno</th>
                        <th>Doručeno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $d)
                    <tr>
                        <td>{{ $d->subject }}</td>
                        <td>{{ $d->recipient }}</td>
                        <td>
                            @php $colors = ['queued'=>'secondary','sent'=>'info','delivered'=>'success','bounced'=>'danger','failed'=>'warning']; @endphp
                            <span class="badge bg-{{ $colors[$d->status] ?? 'secondary' }}">{{ $d->status }}</span>
                        </td>
                        <td>{{ $d->sent_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $d->delivered_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Žádné e-maily.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $deliveries->links() }}</div>
    </x-panel.card>
</div>
@endsection
