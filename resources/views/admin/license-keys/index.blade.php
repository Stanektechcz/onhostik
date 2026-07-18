@extends('layouts.panel')
@section('title', 'Licenční klíče')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Licenční klíče">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produkt</th>
                                <th>Klíč</th>
                                <th>Zákazník</th>
                                <th>Stav</th>
                                <th>Platnost do</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($keys as $key)
                            <tr>
                                <td>{{ $key->product_name }}</td>
                                <td><code>{{ $key->license_key }}</code></td>
                                <td>{{ $key->customer?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($key->status) {
                                            'available' => 'success',
                                            'assigned'  => 'primary',
                                            'expired'   => 'warning',
                                            'revoked'   => 'danger',
                                            default     => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ $key->status }}</span>
                                </td>
                                <td>{{ $key->expires_at ? \Carbon\Carbon::parse($key->expires_at)->format('d.m.Y') : '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.license-keys.update', $key) }}" class="flex gap-1 items-center">
                                        @csrf @method('PATCH')
                                        <select name="status" class="form-select form-select-sm" style="min-width:110px">
                                            @foreach($statuses as $s)
                                                <option value="{{ $s }}" {{ $key->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Uložit</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $keys->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat licenční klíč">
                <form method="POST" action="{{ route('admin.license-keys.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název produktu</label>
                        <input type="text" name="product_name" class="form-control @error('product_name') is-invalid @enderror" value="{{ old('product_name') }}" maxlength="100" required>
                        @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Licenční klíč</label>
                        <input type="text" name="license_key" class="form-control @error('license_key') is-invalid @enderror" value="{{ old('license_key') }}" maxlength="255" required>
                        @error('license_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zákazník ID (volitelné)</label>
                        <input type="number" name="customer_id" class="form-control @error('customer_id') is-invalid @enderror" value="{{ old('customer_id') }}">
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                        <label class="form-label">Poznámky</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" maxlength="500">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
