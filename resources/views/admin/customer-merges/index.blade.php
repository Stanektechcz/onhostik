@extends('layouts.panel')

@section('title', 'Sloučení zákazníků')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Sloučení zákazníků">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Primární zákazník ID</th>
                            <th>Sloučený zákazník ID</th>
                            <th>Stav</th>
                            <th>Přenesené entity</th>
                            <th>Provedl (user ID)</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($merges as $merge)
                            @php
                                $statusColors = [
                                    'pending'     => 'secondary',
                                    'completed'   => 'success',
                                    'rolled_back' => 'warning',
                                ];
                                $statusLabels = [
                                    'pending'     => 'Čeká',
                                    'completed'   => 'Dokončeno',
                                    'rolled_back' => 'Vráceno',
                                ];
                                $entities = is_array($merge->transferred_entities) ? $merge->transferred_entities : [];
                            @endphp
                            <tr>
                                <td>{{ $merge->primary_customer_id }}</td>
                                <td>{{ $merge->merged_customer_id }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$merge->status] ?? 'secondary' }}">
                                        {{ $statusLabels[$merge->status] ?? $merge->status }}
                                    </span>
                                </td>
                                <td>{{ count($entities) > 0 ? count($entities) : '—' }}</td>
                                <td class="text-muted small">{{ $merge->performed_by }}</td>
                                <td class="text-muted small">{{ $merge->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Žádné záznamy o sloučení.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($merges->hasPages())
                <div class="mt-3">{{ $merges->links() }}</div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Nové sloučení">
            <form method="POST" action="{{ route('admin.customer-merges.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Primární zákazník ID <span class="text-danger">*</span></label>
                    <input type="number" name="primary_customer_id"
                        class="form-control @error('primary_customer_id') is-invalid @enderror"
                        value="{{ old('primary_customer_id') }}" min="1" required>
                    @error('primary_customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Zákazník, který zůstane zachován.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Sloučený zákazník ID <span class="text-danger">*</span></label>
                    <input type="number" name="merged_customer_id"
                        class="form-control @error('merged_customer_id') is-invalid @enderror"
                        value="{{ old('merged_customer_id') }}" min="1" required>
                    @error('merged_customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Zákazník, který bude sloučen do primárního.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Poznámka</label>
                    <textarea name="note" class="form-control @error('note') is-invalid @enderror"
                        rows="3" maxlength="500">{{ old('note') }}</textarea>
                    @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-warning w-100"
                    onclick="return confirm('Opravdu zahájit sloučení zákazníků?')">
                    Vytvořit žádost o sloučení
                </button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
