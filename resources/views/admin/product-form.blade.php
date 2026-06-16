@extends('layouts.panel')

@php
    $isNew = $product === null;
    $breadcrumbTitle = $isNew ? 'Nový produkt' : 'Upravit: ' . $product->name;
    $breadcrumbItems = [__('panel.nav.admin_products') => route('admin.products.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- Product info form --}}
        <div class="row">
            <div class="col-xl-8">
                <x-panel.card :title="$breadcrumbTitle">
                    <form method="POST" action="{{ $isNew ? route('admin.products.store') : route('admin.products.update', $product) }}">
                        @csrf
                        @if(!$isNew) @method('PUT') @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-slug">Slug (URL)</label>
                                <input id="p-slug" type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                       value="{{ old('slug', $product?->slug) }}" required maxlength="80" placeholder="webhosting">
                                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-sort">Pořadí</label>
                                <input id="p-sort" type="number" name="sort_order" class="form-control"
                                       value="{{ old('sort_order', $product?->sort_order ?? 0) }}" min="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-type">Typ produktu</label>
                                <select id="p-type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    @foreach($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('type', $product?->type?->value) === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-driver">Provisioning driver</label>
                                <select id="p-driver" name="provisioning_driver" class="form-select">
                                    <option value="">— žádný —</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->value }}" @selected(old('provisioning_driver', $product?->provisioning_driver?->value) === $driver->value)>{{ $driver->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-name-cs">Název (CS) *</label>
                                <input id="p-name-cs" type="text" name="name_cs" class="form-control @error('name_cs') is-invalid @enderror"
                                       value="{{ old('name_cs', $product?->getTranslation('name', 'cs', false)) }}" required maxlength="100">
                                @error('name_cs')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-name-en">Název (EN)</label>
                                <input id="p-name-en" type="text" name="name_en" class="form-control"
                                       value="{{ old('name_en', $product?->getTranslation('name', 'en', false)) }}" maxlength="100">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-desc-cs">Popis (CS)</label>
                                <textarea id="p-desc-cs" name="description_cs" class="form-control" rows="3">{{ old('description_cs', $product?->getTranslation('description', 'cs', false)) }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="p-desc-en">Popis (EN)</label>
                                <textarea id="p-desc-en" name="description_en" class="form-control" rows="3">{{ old('description_en', $product?->getTranslation('description', 'en', false)) }}</textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="p-active" name="is_active" value="1"
                                       @checked(old('is_active', $product?->is_active ?? true))>
                                <label class="form-check-label" for="p-active">Aktivní (zobrazovat zákazníkům)</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('panel.admin.save') }}</button>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">{{ __('panel.common.back') }}</a>
                            @if(!$isNew)
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="ms-auto"
                                      onsubmit="return confirm('Smazat produkt {{ $product->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Smazat produkt</button>
                                </form>
                            @endif
                        </div>
                    </form>
                </x-panel.card>
            </div>
        </div>

        @if(!$isNew)
            {{-- Existing plans --}}
            <x-panel.card :title="__('panel.orders.plan') . 's — ' . $product->name">
                @error('plan')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

                @foreach($product->pricingPlans as $plan)
                    <form id="plan-{{ $plan->id }}" method="POST" action="{{ route('admin.products.plans.update', $plan) }}">
                        @csrf @method('PUT')
                    </form>
                    <form id="plan-del-{{ $plan->id }}" method="POST" action="{{ route('admin.products.plans.delete', $plan) }}"
                          onsubmit="return confirm('Smazat plán {{ $plan->name }}?')">
                        @csrf @method('DELETE')
                    </form>
                @endforeach

                <x-panel.data-table :headers="['Plán', 'Cyklus', 'CZK / měs.', 'EUR / měs.', __('panel.admin.active'), 'Featured', '']">
                    @foreach($product->pricingPlans as $plan)
                        <tr>
                            <td class="f-w-600">{{ $plan->name }}<br><span class="f-light f-12">{{ $plan->tagline }}</span></td>
                            <td>{{ $plan->billing_cycle->label() }}</td>
                            <td style="max-width: 120px;">
                                <input type="number" step="0.01" min="0" name="price_czk" form="plan-{{ $plan->id }}" class="form-control form-control-sm"
                                       value="{{ $plan->price_czk !== null ? $plan->price_czk / 100 : '' }}">
                            </td>
                            <td style="max-width: 120px;">
                                <input type="number" step="0.01" min="0" name="price_eur" form="plan-{{ $plan->id }}" class="form-control form-control-sm"
                                       value="{{ $plan->price_eur !== null ? $plan->price_eur / 100 : '' }}">
                            </td>
                            <td><input type="checkbox" name="is_active" value="1" form="plan-{{ $plan->id }}" @checked($plan->is_active)></td>
                            <td><input type="checkbox" name="is_featured" value="1" form="plan-{{ $plan->id }}" @checked($plan->is_featured)></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="submit" form="plan-{{ $plan->id }}" class="btn btn-primary btn-sm">{{ __('panel.admin.save') }}</button>
                                    <button type="submit" form="plan-del-{{ $plan->id }}" class="btn btn-outline-danger btn-sm">×</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
            </x-panel.card>

            {{-- Add new plan --}}
            <x-panel.card title="Přidat nový plán">
                <form method="POST" action="{{ route('admin.products.plans.add', $product) }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="np-name">Název plánu *</label>
                            <input id="np-name" type="text" name="name_cs" class="form-control @error('name_cs') is-invalid @enderror"
                                   required maxlength="100" placeholder="Starter">
                            @error('name_cs')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="np-tagline">Tagline</label>
                            <input id="np-tagline" type="text" name="tagline_cs" class="form-control" maxlength="200"
                                   placeholder="Ideální pro začátky">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label" for="np-cycle">Cyklus</label>
                            <select id="np-cycle" name="billing_cycle" class="form-select" required>
                                @foreach($cycles as $cycle)
                                    <option value="{{ $cycle->value }}">{{ $cycle->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label" for="np-czk">CZK / měs.</label>
                            <input id="np-czk" type="number" step="0.01" min="0" name="price_czk" class="form-control" placeholder="99.00">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label" for="np-eur">EUR / měs.</label>
                            <input id="np-eur" type="number" step="0.01" min="0" name="price_eur" class="form-control" placeholder="3.99">
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="np-active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="np-active">Aktivní</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="np-featured" name="is_featured" value="1">
                            <label class="form-check-label" for="np-featured">Featured</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">+ Přidat plán</button>
                </form>
            </x-panel.card>
        @endif
    </div>
@endsection
