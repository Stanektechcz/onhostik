@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Slevové kódy';
    $breadcrumbItems = ['Slevové kódy' => ''];
@endphp

@section('title', 'Slevové kódy')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- Create new code --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Nový slevový kód</h5></div>
                    </div>
                    <div class="card-body custom-input">
                        <form method="POST" action="{{ route('admin.discount-codes.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Kód <small class="f-light">(prázdné = automaticky)</small></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror"
                                       name="code" placeholder="PROMO2026" maxlength="32"
                                       style="text-transform:uppercase;">
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Typ slevy *</label>
                                <select class="form-select" name="type" id="discount-type" onchange="toggleCurrency()">
                                    <option value="percent">Procentuální (%)</option>
                                    <option value="fixed">Pevná částka (Kč/€)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hodnota *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control @error('value') is-invalid @enderror"
                                           name="value" step="0.01" min="0.01" placeholder="10">
                                    <span class="input-group-text" id="value-unit">%</span>
                                </div>
                                @error('value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3" id="currency-field" style="display:none;">
                                <label class="form-label">Měna</label>
                                <select class="form-select" name="currency">
                                    <option value="CZK">CZK</option>
                                    <option value="EUR">EUR</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Max. použití <small class="f-light">(prázdné = neomezeno)</small></label>
                                <input type="number" class="form-control" name="max_uses" min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Platnost do <small class="f-light">(prázdné = navždy)</small></label>
                                <input type="datetime-local" class="form-control" name="expires_at">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Popis</label>
                                <input type="text" class="form-control" name="description" placeholder="Letní akce 2026">
                            </div>
                            <button type="submit" class="btn btn-primary text-white w-full">
                                <i data-feather="plus" style="width:14px;height:14px;"></i>
                                Vytvořit kód
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Codes list --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Slevové kódy ({{ $codes->total() }})</h5>
                        </div>
                        <form method="GET" action="{{ route('admin.discount-codes.index') }}" class="d-flex gap-2 mt-2">
                            <input type="text" name="q" class="form-control form-control-sm"
                                   style="max-width:200px;" placeholder="Hledat kód…" value="{{ request('q') }}">
                            <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                <option value="">Všechny</option>
                                <option value="active" @selected(request('status')==='active')>Aktivní</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Hledat</button>
                        </form>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="recent-table overflow-x-auto custom-scrollbar">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><span class="f-light font-semibold">Kód</span></th>
                                        <th><span class="f-light font-semibold">Typ / Hodnota</span></th>
                                        <th><span class="f-light font-semibold">Použití</span></th>
                                        <th><span class="f-light font-semibold">Platnost</span></th>
                                        <th><span class="f-light font-semibold">Stav</span></th>
                                        <th><span class="f-light font-semibold">Akce</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($codes as $code)
                                    <tr class="inbox-data">
                                        <td>
                                            <code class="f-w-600 f-14">{{ $code->code }}</code>
                                            @if($code->description)
                                                <br><small class="f-light f-11">{{ $code->description }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-light-{{ $code->type === 'percent' ? 'primary' : 'success' }}">
                                                {{ $code->formattedValue() }}
                                            </span>
                                        </td>
                                        <td class="f-13">
                                            {{ $code->used_count }}
                                            @if($code->max_uses) / {{ $code->max_uses }} @else / ∞ @endif
                                        </td>
                                        <td class="f-12 f-light">
                                            {{ $code->expires_at?->format('d.m.Y H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            @if($code->isValid())
                                                <span class="badge badge-light-success">Aktivní</span>
                                            @elseif(!$code->is_active)
                                                <span class="badge badge-light-secondary">Vypnuto</span>
                                            @elseif($code->max_uses && $code->used_count >= $code->max_uses)
                                                <span class="badge badge-light-warning">Vyčerpán</span>
                                            @else
                                                <span class="badge badge-light-danger">Expiroval</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="common-align gap-2 justify-start">
                                                <form method="POST" action="{{ route('admin.discount-codes.toggle', $code) }}"
                                                      style="display:inline;">
                                                    @csrf
                                                    <button type="submit" class="square-white"
                                                            title="{{ $code->is_active ? 'Deaktivovat' : 'Aktivovat' }}">
                                                        <i data-feather="{{ $code->is_active ? 'pause' : 'play' }}"
                                                           style="width:14px;height:14px;"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.discount-codes.destroy', $code) }}"
                                                      style="display:inline;"
                                                      onsubmit="return confirm('Smazat kód {{ $code->code }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="square-white trash-3">
                                                        <svg><use href="{{ asset('panel/assets/svg/icon-sprite.svg#trash1') }}"></use></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 f-light">Žádné slevové kódy.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($codes->hasPages())
                            <div class="px-3 pt-2">{{ $codes->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleCurrency() {
    var type = document.getElementById('discount-type').value;
    document.getElementById('currency-field').style.display = type === 'fixed' ? '' : 'none';
    document.getElementById('value-unit').textContent = type === 'percent' ? '%' : 'Kč';
}
</script>
@endpush
