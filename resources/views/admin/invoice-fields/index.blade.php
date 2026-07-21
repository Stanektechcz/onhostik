@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Vlastní pole faktur';
    $breadcrumbItems = ['Nastavení' => route('admin.settings.index'), 'Vlastní pole faktur' => ''];
@endphp

@section('title', 'Vlastní pole faktur')

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-3">
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Nové pole</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.invoice-fields.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Popis pole *</label>
                            <input type="text" name="label" class="form-control form-control-sm @error('label') is-invalid @enderror"
                                   value="{{ old('label') }}" placeholder="PO číslo, Projekt…" required>
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Typ *</label>
                            <select name="type" class="form-select form-select-sm">
                                @foreach(\App\Domains\Billing\Models\InvoiceFieldDefinition::TYPES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Pořadí</label>
                            <input type="number" name="sort_order" class="form-control form-control-sm"
                                   value="{{ old('sort_order', 0) }}" min="0">
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="is_required" value="1"
                                   id="isRequired" @checked(old('is_required'))>
                            <label class="form-check-label f-12" for="isRequired">Povinné pole</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="show_on_invoice" value="1"
                                   id="showOnInvoice" checked>
                            <label class="form-check-label f-12" for="showOnInvoice">Zobrazit na faktuře</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">Přidat pole</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border"><h5>Definovaná pole ({{ $definitions->count() }})</h5></div>
                <div class="card-body pt-0">
                    @if($definitions->isEmpty())
                        <p class="text-center f-light py-4">Žádná vlastní pole. Přidejte první pole.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Popis</th>
                                        <th>Klíč</th>
                                        <th>Typ</th>
                                        <th>Povinné</th>
                                        <th>Na faktuře</th>
                                        <th>Stav</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($definitions as $def)
                                    <tr>
                                        <td class="f-w-500">{{ $def->label }}</td>
                                        <td><code class="f-11">{{ $def->key }}</code></td>
                                        <td class="f-12">{{ \App\Domains\Billing\Models\InvoiceFieldDefinition::TYPES[$def->type] ?? $def->type }}</td>
                                        <td>
                                            @if($def->is_required)
                                                <span class="badge badge-light-danger f-10">Ano</span>
                                            @else
                                                <span class="f-light f-12">Ne</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($def->show_on_invoice)
                                                <i data-feather="check" style="width:14px;height:14px;" class="text-success"></i>
                                            @else
                                                <i data-feather="x" style="width:14px;height:14px;" class="text-muted"></i>
                                            @endif
                                        </td>
                                        <td>
                                            @if($def->is_active)
                                                <span class="badge badge-light-success f-10">Aktivní</span>
                                            @else
                                                <span class="badge badge-light-secondary f-10">Neaktivní</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-xs"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editFieldModal{{ $def->id }}">
                                                Upravit
                                            </button>
                                            <form method="POST" action="{{ route('admin.invoice-fields.destroy', $def) }}"
                                                  class="inline" data-confirm="Smazat pole?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                            </form>
                                        </td>
                                    </tr>

                                    {{-- Edit modal --}}
                                    <div class="modal fade" id="editFieldModal{{ $def->id }}" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <form method="POST"
                                                  action="{{ route('admin.invoice-fields.update', $def) }}"
                                                  class="modal-content">
                                                @csrf @method('PUT')
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Upravit pole</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-2">
                                                        <label class="form-label f-12">Popis *</label>
                                                        <input type="text" name="label" class="form-control form-control-sm"
                                                               value="{{ $def->label }}" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label f-12">Typ</label>
                                                        <select name="type" class="form-select form-select-sm">
                                                            @foreach(\App\Domains\Billing\Models\InvoiceFieldDefinition::TYPES as $k => $l)
                                                                <option value="{{ $k }}" @selected($def->type === $k)>{{ $l }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label f-12">Pořadí</label>
                                                        <input type="number" name="sort_order" class="form-control form-control-sm"
                                                               value="{{ $def->sort_order }}" min="0">
                                                    </div>
                                                    <div class="form-check mb-1">
                                                        <input class="form-check-input" type="checkbox" name="is_required" value="1"
                                                               @checked($def->is_required)>
                                                        <label class="form-check-label f-12">Povinné</label>
                                                    </div>
                                                    <div class="form-check mb-1">
                                                        <input class="form-check-input" type="checkbox" name="show_on_invoice" value="1"
                                                               @checked($def->show_on_invoice)>
                                                        <label class="form-check-label f-12">Zobrazit na faktuře</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                                               @checked($def->is_active)>
                                                        <label class="form-check-label f-12">Aktivní</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
