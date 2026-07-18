@extends('layouts.panel')

@php($breadcrumbTitle = 'Nová ad-hoc faktura')
@php($breadcrumbItems = [__('panel.nav.admin_invoices') => route('admin.invoices.index'), 'Nová ad-hoc faktura' => ''])

@section('title', 'Nová ad-hoc faktura')

@section('content')
<div class="container-fluid py-4" style="max-width:860px">

    <x-panel.flash />

    <h1 class="h4 mb-4">
        <i data-feather="file-plus" style="width:20px;height:20px" class="me-1"></i>
        Nová ad-hoc faktura
    </h1>

    @if($errors->has('adhoc'))
        <div class="alert alert-danger">{{ $errors->first('adhoc') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.invoices.adhoc-store') }}" id="adhocForm">
        @csrf

        {{-- Customer + metadata --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0 f-14">Zákazník a termíny</h5>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label font-semibold">Zákazník <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">— Vyberte zákazníka —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>
                                    {{ $c->company_name ?: ($c->user?->name ?? '—') }} ({{ $c->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label font-semibold">Datum splatnosti <span class="text-danger">*</span></label>
                        <input type="date"
                               name="due_date"
                               class="form-control @error('due_date') is-invalid @enderror"
                               value="{{ old('due_date', now()->addDays(14)->format('Y-m-d')) }}"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               required>
                        @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12">
                        <label class="form-label font-semibold">Poznámky</label>
                        <textarea name="notes"
                                  class="form-control @error('notes') is-invalid @enderror"
                                  rows="2"
                                  placeholder="Nepovinná interní nebo zákaznická poznámka…">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="card mb-3">
            <div class="card-header flex justify-between items-center">
                <h5 class="card-title mb-0 f-14">Položky faktury</h5>
                <button type="button" class="btn btn-outline-primary btn-xs" id="addItemBtn">
                    <i data-feather="plus" style="width:12px;height:12px"></i> Přidat řádek
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Popis <span class="text-danger">*</span></th>
                                <th style="width:80px">Počet</th>
                                <th style="width:150px">Cena bez DPH (Kč)</th>
                                <th style="width:100px">Sazba DPH</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            @php($oldItems = old('items', [[]]))
                            @foreach($oldItems as $i => $oldItem)
                            <tr class="item-row">
                                <td class="ps-3">
                                    <input type="text"
                                           name="items[{{ $i }}][description]"
                                           class="form-control form-control-sm @error('items.'.$i.'.description') is-invalid @enderror"
                                           value="{{ $oldItem['description'] ?? '' }}"
                                           placeholder="Popis služby…"
                                           required>
                                    @error('items.'.$i.'.description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number"
                                           name="items[{{ $i }}][quantity]"
                                           class="form-control form-control-sm"
                                           value="{{ $oldItem['quantity'] ?? 1 }}"
                                           min="1" max="9999" required>
                                </td>
                                <td>
                                    {{-- Display in Kč; JS converts to minor units (haléře) on submit --}}
                                    <input type="number"
                                           name="items[{{ $i }}][unit_price_minor]"
                                           class="form-control form-control-sm"
                                           value="{{ isset($oldItem['unit_price_minor']) ? number_format((int)$oldItem['unit_price_minor'] / 100, 2, '.', '') : '' }}"
                                           step="0.01" min="0" placeholder="0.00"
                                           data-price-kc
                                           required>
                                </td>
                                <td>
                                    <select name="items[{{ $i }}][vat_rate]" class="form-select form-select-sm">
                                        <option value="21" @selected(($oldItem['vat_rate'] ?? '21') == '21')>21 %</option>
                                        <option value="0" @selected(($oldItem['vat_rate'] ?? '') == '0')>0 %</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 remove-row">
                                        <i data-feather="x" style="width:14px;height:14px"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i data-feather="check" style="width:14px;height:14px"></i>
                Vystavit fakturu
            </button>
            <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">Zrušit</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    let rowIndex = document.querySelectorAll('.item-row').length;

    // Convert Kč display values → integer minor units before submit
    document.getElementById('adhocForm').addEventListener('submit', function () {
        document.querySelectorAll('[data-price-kc]').forEach(function (inp) {
            inp.value = Math.round(parseFloat(inp.value || '0') * 100);
        });
    });

    function buildRow(idx) {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td class="ps-3">' +
                '<input type="text" name="items[' + idx + '][description]" ' +
                       'class="form-control form-control-sm" placeholder="Popis služby…" required>' +
            '</td>' +
            '<td>' +
                '<input type="number" name="items[' + idx + '][quantity]" ' +
                       'class="form-control form-control-sm" value="1" min="1" max="9999" required>' +
            '</td>' +
            '<td>' +
                '<input type="number" name="items[' + idx + '][unit_price_minor]" ' +
                       'class="form-control form-control-sm" data-price-kc ' +
                       'step="0.01" min="0" placeholder="0.00" required>' +
            '</td>' +
            '<td>' +
                '<select name="items[' + idx + '][vat_rate]" class="form-select form-select-sm">' +
                    '<option value="21">21 %</option>' +
                    '<option value="0">0 %</option>' +
                '</select>' +
            '</td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-link text-danger p-0 remove-row">' +
                    '<i data-feather="x" style="width:14px;height:14px"></i>' +
                '</button>' +
            '</td>';
        return tr;
    }

    document.getElementById('addItemBtn').addEventListener('click', function () {
        const tr = buildRow(rowIndex++);
        document.getElementById('itemsBody').appendChild(tr);
        if (typeof feather !== 'undefined') feather.replace();
    });

    document.getElementById('itemsBody').addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        if (document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
        }
    });
})();
</script>
@endpush
@endsection
