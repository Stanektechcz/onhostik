@extends('layouts.panel')

@section('title', 'Žádosti o obnovení ze snímku')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Moje žádosti o obnovení">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Služba</th>
                            <th>Snapshot ID</th>
                            <th>Bod obnovení</th>
                            <th>Stav</th>
                            <th>Poznámka</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $req)
                            @php
                                $statusColors = [
                                    'pending'    => 'secondary',
                                    'approved'   => 'info',
                                    'processing' => 'warning',
                                    'completed'  => 'success',
                                    'rejected'   => 'danger',
                                ];
                                $statusLabels = [
                                    'pending'    => 'Čeká',
                                    'approved'   => 'Schváleno',
                                    'processing' => 'Probíhá',
                                    'completed'  => 'Dokončeno',
                                    'rejected'   => 'Zamítnuto',
                                ];
                            @endphp
                            <tr>
                                <td>{{ $req->service?->name ?? "#{$req->service_id}" }}</td>
                                <td class="small font-monospace">{{ $req->snapshot_id }}</td>
                                <td class="small">{{ $req->restore_point }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">
                                        {{ $statusLabels[$req->status] ?? $req->status }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $req->customer_note ?? '—' }}</td>
                                <td class="small text-muted">{{ $req->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Žádné žádosti o obnovení.</td>
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
        <x-panel.card title="Nová žádost o obnovení">
            <form method="POST" action="{{ route('panel.snapshot-restore-requests.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">ID služby <span class="text-danger">*</span></label>
                    <input type="number" name="service_id"
                        class="form-control @error('service_id') is-invalid @enderror"
                        value="{{ old('service_id') }}" min="1" required>
                    @error('service_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Snapshot ID <span class="text-danger">*</span></label>
                    <input type="text" name="snapshot_id"
                        class="form-control @error('snapshot_id') is-invalid @enderror"
                        value="{{ old('snapshot_id') }}" maxlength="100" required>
                    @error('snapshot_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Bod obnovení <span class="text-danger">*</span></label>
                    <input type="text" name="restore_point"
                        class="form-control @error('restore_point') is-invalid @enderror"
                        value="{{ old('restore_point') }}" maxlength="255" required
                        placeholder="např. 2026-07-01 00:00:00">
                    @error('restore_point') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Vaše poznámka</label>
                    <textarea name="customer_note"
                        class="form-control @error('customer_note') is-invalid @enderror"
                        rows="3" maxlength="1000">{{ old('customer_note') }}</textarea>
                    @error('customer_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Odeslat žádost</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
