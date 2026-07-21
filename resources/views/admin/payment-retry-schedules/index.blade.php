@extends('layouts.panel')
@section('title', 'Plány opakování plateb')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Plány opakování plateb">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Zákazník</th>
                                <th>Faktura ID</th>
                                <th>Datum opakování</th>
                                <th>Stav</th>
                                <th>Pokus č.</th>
                                <th>Poznámka</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($retries as $retry)
                            <tr>
                                <td>{{ $retry->id }}</td>
                                <td>{{ $retry->customer?->name ?? '—' }}</td>
                                <td>{{ $retry->invoice_id }}</td>
                                <td>{{ $retry->retry_at ? \Carbon\Carbon::parse($retry->retry_at)->format('d.m.Y H:i') : '—' }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($retry->status) {
                                            'pending'   => 'secondary',
                                            'attempted' => 'warning',
                                            'succeeded' => 'success',
                                            'cancelled' => 'danger',
                                            default     => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ $retry->status }}</span>
                                </td>
                                <td>{{ $retry->attempt_number ?? '—' }}</td>
                                <td>{{ $retry->note ? \Illuminate\Support\Str::limit($retry->note, 60) : '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.payment-retry-schedules.destroy', $retry) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Opravdu smazat?">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $retries->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat plán opakování">
                <form method="POST" action="{{ route('admin.payment-retry-schedules.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Faktura ID</label>
                        <input type="number" name="invoice_id" class="form-control @error('invoice_id') is-invalid @enderror" value="{{ old('invoice_id') }}" required>
                        @error('invoice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zákazník ID</label>
                        <input type="number" name="customer_id" class="form-control @error('customer_id') is-invalid @enderror" value="{{ old('customer_id') }}" required>
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum opakování</label>
                        <input type="datetime-local" name="retry_at" class="form-control @error('retry_at') is-invalid @enderror" value="{{ old('retry_at') }}" required>
                        @error('retry_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Poznámka</label>
                        <textarea name="note" class="form-control @error('note') is-invalid @enderror" rows="3" maxlength="500">{{ old('note') }}</textarea>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
