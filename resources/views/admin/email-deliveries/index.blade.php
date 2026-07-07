@extends('layouts.panel')

@section('title', 'Doručení e-mailů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="recipient" class="form-control form-control-sm"
                    placeholder="Příjemce…" value="{{ request('recipient') }}">
                <select name="status" class="form-select form-select-sm" style="max-width:140px">
                    <option value="">Vše</option>
                    @foreach(['queued','sent','delivered','bounced','failed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">Filtrovat</button>
            </form>
        </div>
    </div>

    <x-panel.card title="Doručení e-mailů">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Příjemce</th>
                        <th>Předmět</th>
                        <th>Uživatel</th>
                        <th>Stav</th>
                        <th>Odesláno</th>
                        <th>Doručeno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $d)
                    <tr>
                        <td class="text-muted">{{ $d->id }}</td>
                        <td>{{ $d->recipient }}</td>
                        <td>{{ Str::limit($d->subject, 50) }}</td>
                        <td>{{ $d->user?->email ?? '—' }}</td>
                        <td>
                            @php $colors = ['queued'=>'secondary','sent'=>'info','delivered'=>'success','bounced'=>'danger','failed'=>'warning']; @endphp
                            <span class="badge bg-{{ $colors[$d->status] ?? 'secondary' }}">{{ $d->status }}</span>
                        </td>
                        <td>{{ $d->sent_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $d->delivered_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Žádné záznamy.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $deliveries->withQueryString()->links() }}</div>
    </x-panel.card>
</div>
@endsection
