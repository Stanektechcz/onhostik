@extends('layouts.panel')

@section('title', 'Částečné platby — faktura ' . $invoice->number)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět na fakturu</a>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Zaznamenané částečné platby">
                @if($payments->isEmpty())
                    <p class="text-muted">Žádné částečné platby.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Datum</th><th>Metoda</th><th class="text-end">Částka</th><th>Poznámka</th><th>Admin</th></tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at?->format('d.m.Y') }}</td>
                                <td>{{ $p->payment_method }}</td>
                                <td class="text-end">{{ number_format($p->amount_haler / 100, 2) }} Kč</td>
                                <td class="text-muted small">{{ $p->note ?? '—' }}</td>
                                <td class="text-muted small">{{ $p->adminUser?->name ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2">Celkem zaplaceno</td>
                                <td class="text-end">{{ number_format($totalPaidHaler / 100, 2) }} Kč</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="@if($totalPaidHaler >= $invoiceTotalHaler) text-success @else text-danger @endif">
                                <td colspan="2">Zbývá doplatit</td>
                                <td class="text-end fw-bold">{{ number_format(max(0, $invoiceTotalHaler - $totalPaidHaler) / 100, 2) }} Kč</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </x-panel.card>
        </div>

        <div class="col-md-4">
            <x-panel.card title="Přidat platbu">
                <form method="POST" action="{{ route('admin.invoice-partial-payments.store', $invoice) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Částka (haléře)</label>
                        <input type="number" name="amount_haler" class="form-control @error('amount_haler') is-invalid @enderror" min="1" required>
                        @error('amount_haler')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Metoda platby</label>
                        <select name="payment_method" class="form-select">
                            <option value="bank_transfer">Bankovní převod</option>
                            <option value="cash">Hotovost</option>
                            <option value="card">Karta</option>
                            <option value="other">Jiné</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Datum platby</label>
                        <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Poznámka</label>
                        <input type="text" name="note" class="form-control" maxlength="500">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Uložit platbu</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
