@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Cenové overridy';
    $breadcrumbItems = [
        'Reselleri' => route('admin.resellers.index'),
        $reseller->business_name => route('admin.resellers.show', $reseller),
        'Cenové overridy' => '',
    ];
@endphp

@section('title', 'Cenové overridy — ' . $reseller->business_name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Override list --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Platné cenové overridy">
                @if($overrides->isEmpty())
                    <div class="text-center py-4 f-light f-12">
                        Žádné overridy. Reseller používá globální markup {{ $reseller->markup_percent }}%.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover f-13">
                            <thead>
                                <tr>
                                    <th>Tarif</th>
                                    <th class="text-end">CZK (haléře)</th>
                                    <th class="text-end">EUR (centy)</th>
                                    <th class="text-end">USD (centy)</th>
                                    <th>Stav</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overrides as $override)
                                <tr>
                                    <td>
                                        <span class="f-w-500">{{ $override->pricingPlan?->name ?? 'Tarif #' . $override->pricing_plan_id }}</span>
                                    </td>
                                    <td class="text-end">{{ $override->price_czk !== null ? number_format($override->price_czk) : '—' }}</td>
                                    <td class="text-end">{{ $override->price_eur !== null ? number_format($override->price_eur) : '—' }}</td>
                                    <td class="text-end">{{ $override->price_usd !== null ? number_format($override->price_usd) : '—' }}</td>
                                    <td>
                                        @if($override->is_active)
                                            <span class="badge badge-light-success">Aktivní</span>
                                        @else
                                            <span class="badge badge-light-secondary">Neaktivní</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="{{ route('admin.resellers.pricing.destroy', [$reseller, $override]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Smazat override?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-outline-danger">
                                                <i data-feather="trash-2" style="width:11px;height:11px"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel.card>
        </div>

        {{-- Add override --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Přidat / aktualizovat override">
                <form method="POST" action="{{ route('admin.resellers.pricing.store', $reseller) }}" class="custom-input">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Tarif *</label>
                        <select class="form-select" name="pricing_plan_id" required>
                            <option value="">— vyberte —</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name ?? 'Plán #' . $plan->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cena CZK (haléře)</label>
                        <input class="form-control @error('price_czk') is-invalid @enderror"
                               type="number" name="price_czk" min="0" placeholder="např. 29900 = 299 Kč">
                        @error('price_czk')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cena EUR (centy)</label>
                        <input class="form-control @error('price_eur') is-invalid @enderror"
                               type="number" name="price_eur" min="0" placeholder="např. 1200 = 12,00 €">
                        @error('price_eur')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cena USD (centy)</label>
                        <input class="form-control @error('price_usd') is-invalid @enderror"
                               type="number" name="price_usd" min="0">
                        @error('price_usd')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="f-11 f-light mb-3">
                        Globální markup: <strong>{{ $reseller->markup_percent }}%</strong>
                    </div>
                    <button type="submit" class="btn btn-primary text-white w-full">
                        <i data-feather="save" style="width:14px;height:14px"></i> Uložit override
                    </button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
