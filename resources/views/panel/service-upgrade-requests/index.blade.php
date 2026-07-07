@extends('layouts.panel')

@section('title', 'Žádosti o upgrade služby')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Moje žádosti o upgrade">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Služba</th>
                            <th>Požadovaný plán ID</th>
                            <th>Stav</th>
                            <th>Datum</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $req)
                            @php
                                $statusColors = [
                                    'pending'  => 'secondary',
                                    'approved' => 'info',
                                    'applied'  => 'success',
                                    'rejected' => 'danger',
                                ];
                                $statusLabels = [
                                    'pending'  => 'Čeká',
                                    'approved' => 'Schváleno',
                                    'applied'  => 'Aplikováno',
                                    'rejected' => 'Zamítnuto',
                                ];
                            @endphp
                            <tr>
                                <td>{{ $req->service?->name ?? "#{$req->service_id}" }}</td>
                                <td class="text-muted small">{{ $req->requested_plan_id ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">
                                        {{ $statusLabels[$req->status] ?? $req->status }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $req->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('panel.service-upgrade-requests.show', $req) }}"
                                        class="btn btn-sm btn-outline-secondary">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Žádné žádosti o upgrade.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="mt-3">{{ $requests->links() }}</div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Nová žádost o upgrade">
            <form method="POST" action="{{ route('panel.service-upgrade-requests.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">ID služby <span class="text-danger">*</span></label>
                    <input type="number" name="service_id"
                        class="form-control @error('service_id') is-invalid @enderror"
                        value="{{ old('service_id') }}" min="1" required>
                    @error('service_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">ID požadovaného plánu</label>
                    <input type="number" name="requested_plan_id"
                        class="form-control @error('requested_plan_id') is-invalid @enderror"
                        value="{{ old('requested_plan_id') }}" min="1">
                    @error('requested_plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Nepovinné — ponechte prázdné, pokud nevíte.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Vaše poznámka</label>
                    <textarea name="customer_note"
                        class="form-control @error('customer_note') is-invalid @enderror"
                        rows="3" maxlength="1000"
                        placeholder="Popište, co potřebujete upgradovat...">{{ old('customer_note') }}</textarea>
                    @error('customer_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Odeslat žádost</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
