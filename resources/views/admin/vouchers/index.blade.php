@extends('layouts.panel')

@section('title', 'Správa voucherů')

@section('content')
<x-panel.flash />

<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Vouchery">
            <div class="mb-3 d-flex gap-2">
                <a href="{{ route('admin.vouchers.index') }}"
                   class="btn btn-sm {{ !request('status_filter') ? 'btn-primary' : 'btn-outline-secondary' }}">Všechny</a>
                <a href="{{ route('admin.vouchers.index', ['status_filter' => 'active']) }}"
                   class="btn btn-sm {{ request('status_filter') === 'active' ? 'btn-success' : 'btn-outline-success' }}">Aktivní</a>
                <a href="{{ route('admin.vouchers.index', ['status_filter' => 'inactive']) }}"
                   class="btn btn-sm {{ request('status_filter') === 'inactive' ? 'btn-secondary' : 'btn-outline-secondary' }}">Neaktivní</a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kód</th>
                            <th>Typ</th>
                            <th>Hodnota</th>
                            <th>Použito / Max</th>
                            <th>Platnost</th>
                            <th>Aktivní</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vouchers as $voucher)
                            @php
                                $typeBadge = match($voucher->type) {
                                    'credit'           => 'success',
                                    'discount_percent' => 'info',
                                    'discount_fixed'   => 'primary',
                                    default            => 'secondary',
                                };
                                $typeLabels = [
                                    'credit'           => 'Kredit',
                                    'discount_percent' => 'Sleva %',
                                    'discount_fixed'   => 'Sleva pevná',
                                ];
                            @endphp
                            <tr>
                                <td><code>{{ $voucher->code }}</code></td>
                                <td><span class="badge bg-{{ $typeBadge }}">{{ $typeLabels[$voucher->type] ?? $voucher->type }}</span></td>
                                <td>
                                    @if ($voucher->type === 'discount_percent')
                                        {{ $voucher->value }} %
                                    @else
                                        {{ number_format($voucher->value / 100, 2) }} {{ $voucher->currency ?? 'CZK' }}
                                    @endif
                                </td>
                                <td>{{ $voucher->used_count }} / {{ $voucher->max_uses ?? '∞' }}</td>
                                <td class="text-nowrap">
                                    {{ $voucher->expires_at ? \Carbon\Carbon::parse($voucher->expires_at)->format('d.m.Y') : '—' }}
                                </td>
                                <td>
                                    @if ($voucher->is_active)
                                        <span class="badge bg-success">Aktivní</span>
                                    @else
                                        <span class="badge bg-secondary">Neaktivní</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.vouchers.update', $voucher) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $voucher->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ $voucher->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Žádné vouchery nenalezeny.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($vouchers->hasPages())
                <div class="mt-3">
                    {{ $vouchers->withQueryString()->links() }}
                </div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Nový voucher">
            <form method="POST" action="{{ route('admin.vouchers.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">Kód <span class="text-danger">*</span></label>
                    <input type="text" id="code" name="code" class="form-control font-monospace @error('code') is-invalid @enderror"
                           value="{{ old('code') }}" maxlength="50" required>
                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Typ <span class="text-danger">*</span></label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">Vyberte typ</option>
                        <option value="credit" @selected(old('type') === 'credit')>Kredit</option>
                        <option value="discount_percent" @selected(old('type') === 'discount_percent')>Sleva %</option>
                        <option value="discount_fixed" @selected(old('type') === 'discount_fixed')>Sleva pevná</option>
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="value" class="form-label">Hodnota (haléře nebo %) <span class="text-danger">*</span></label>
                    <input type="number" id="value" name="value" class="form-control @error('value') is-invalid @enderror"
                           value="{{ old('value') }}" min="1" required>
                    @error('value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="currency" class="form-label">Měna</label>
                    <input type="text" id="currency" name="currency" class="form-control @error('currency') is-invalid @enderror"
                           value="{{ old('currency', 'CZK') }}" maxlength="3">
                    @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="max_uses" class="form-label">Max. použití</label>
                    <input type="number" id="max_uses" name="max_uses" class="form-control @error('max_uses') is-invalid @enderror"
                           value="{{ old('max_uses') }}" min="1" placeholder="Neomezeno">
                    @error('max_uses') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="expires_at" class="form-label">Platnost do</label>
                    <input type="date" id="expires_at" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror"
                           value="{{ old('expires_at') }}">
                    @error('expires_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Vytvořit voucher</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
