@extends('layouts.panel')

@section('title', 'Žádosti o přenos domény')

@section('content')
<x-panel.flash />

<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Žádosti o přenos domény">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Doména</th>
                            <th>Auth kód</th>
                            <th>Stav</th>
                            <th>Datum</th>
                            <th>Admin poznámka</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $transferRequest)
                            @php
                                $statusBadge = match($transferRequest->status) {
                                    'pending'    => 'secondary',
                                    'processing' => 'warning',
                                    'completed'  => 'success',
                                    'failed'     => 'danger',
                                    'cancelled'  => 'secondary',
                                    default      => 'secondary',
                                };
                                $statusLabels = [
                                    'pending'    => 'Čeká',
                                    'processing' => 'Zpracovává se',
                                    'completed'  => 'Dokončeno',
                                    'failed'     => 'Neúspěšné',
                                    'cancelled'  => 'Zrušeno',
                                ];
                            @endphp
                            <tr>
                                <td>{{ $transferRequest->domain_name }}</td>
                                <td>
                                    @if ($transferRequest->auth_code)
                                        <span class="font-monospace text-muted">***</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusBadge }}">
                                        {{ $statusLabels[$transferRequest->status] ?? $transferRequest->status }}
                                    </span>
                                </td>
                                <td class="text-nowrap">{{ $transferRequest->created_at?->format('d.m.Y H:i') }}</td>
                                <td>{{ $transferRequest->admin_note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Žádné žádosti o přenos domény.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="mt-3">
                    {{ $requests->links() }}
                </div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Nová žádost o přenos">
            <form method="POST" action="{{ route('panel.domain-transfer-requests.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="domain_name" class="form-label">Název domény <span class="text-danger">*</span></label>
                    <input type="text" id="domain_name" name="domain_name" class="form-control @error('domain_name') is-invalid @enderror"
                           value="{{ old('domain_name') }}" placeholder="example.com" maxlength="255" required>
                    @error('domain_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="auth_code" class="form-label">Auth kód (EPP)</label>
                    <input type="password" id="auth_code" name="auth_code" class="form-control @error('auth_code') is-invalid @enderror"
                           value="{{ old('auth_code') }}" maxlength="255" autocomplete="off">
                    @error('auth_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">Odeslat žádost</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
