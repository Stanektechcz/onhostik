@extends('layouts.panel')
@section('title', 'SSL certifikáty – kontroly')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="SSL certifikáty – kontroly">
                <form method="GET" action="{{ route('admin.ssl-certificate-checks.index') }}" class="mb-3">
                    <div class="d-flex gap-2 align-items-center">
                        <select name="status" class="form-select form-select-sm" style="max-width:200px">
                            <option value="">-- všechny stavy --</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
                        @if(request('status'))
                            <a href="{{ route('admin.ssl-certificate-checks.index') }}" class="btn btn-sm btn-outline-danger">Zrušit</a>
                        @endif
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Doména</th>
                                <th>Služba ID</th>
                                <th>Stav</th>
                                <th>Platnost do</th>
                                <th>Vydavatel</th>
                                <th>Zkontrolováno</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($checks as $check)
                            <tr>
                                <td>{{ $check->domain }}</td>
                                <td>{{ $check->service_id }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($check->status) {
                                            'valid'         => 'success',
                                            'expiring_soon' => 'warning',
                                            'expired'       => 'danger',
                                            'invalid'       => 'danger',
                                            'unknown'       => 'secondary',
                                            default         => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ $check->status }}</span>
                                </td>
                                <td>{{ $check->expires_at ? \Carbon\Carbon::parse($check->expires_at)->format('d.m.Y') : '—' }}</td>
                                <td>{{ $check->issuer ?? '—' }}</td>
                                <td>{{ $check->checked_at ? \Carbon\Carbon::parse($check->checked_at)->format('d.m.Y H:i') : '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $checks->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-md-4">
            <x-panel.card title="Přidat kontrolu">
                <form method="POST" action="{{ route('admin.ssl-certificate-checks.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Služba ID</label>
                        <input type="number" name="service_id" class="form-control @error('service_id') is-invalid @enderror" value="{{ old('service_id') }}" required>
                        @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Doména</label>
                        <input type="text" name="domain" class="form-control @error('domain') is-invalid @enderror" value="{{ old('domain') }}" maxlength="255" required>
                        @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stav</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="">-- vyberte --</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" {{ old('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Platnost do</label>
                        <input type="date" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at') }}">
                        @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vydavatel</label>
                        <input type="text" name="issuer" class="form-control @error('issuer') is-invalid @enderror" value="{{ old('issuer') }}" maxlength="255">
                        @error('issuer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum kontroly</label>
                        <input type="datetime-local" name="checked_at" class="form-control @error('checked_at') is-invalid @enderror" value="{{ old('checked_at') }}" required>
                        @error('checked_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chyba</label>
                        <textarea name="error" class="form-control @error('error') is-invalid @enderror" rows="2" maxlength="500">{{ old('error') }}</textarea>
                        @error('error')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
