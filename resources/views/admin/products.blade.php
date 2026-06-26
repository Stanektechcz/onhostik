@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_products');
    $breadcrumbItems = [__('panel.nav.admin_products') => ''];
@endphp

@section('title', __('panel.nav.admin_products'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />
        @error('product')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">+ Přidat produkt</a>
        </div>

        @foreach($products as $product)
            @php($salesMode = $product->sales_mode ?? \App\Domains\Products\Enums\SalesMode::SelfService)
            <x-panel.card :title="$product->name" :subtitle="$product->type->label() . ' · ' . ($product->provisioning_driver?->label() ?? '')">
                {{-- one hidden form per plan; inputs reference it via the HTML5 form attribute --}}
                @foreach($product->pricingPlans as $plan)
                    <form id="plan-{{ $plan->id }}" method="POST" action="{{ route('admin.products.plans.update', $plan) }}">
                        @csrf
                        @method('PUT')
                    </form>
                @endforeach

                <x-panel.data-table :headers="[__('panel.orders.plan'), 'CZK / měs.', 'EUR / měs.', __('panel.admin.active'), 'Featured', '']">
                    @foreach($product->pricingPlans as $plan)
                        <tr>
                            <td class="f-w-600">{{ $plan->name }}<br><span class="f-light f-12">{{ $plan->tagline }}</span></td>
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
                            <td><button type="submit" form="plan-{{ $plan->id }}" class="btn btn-primary btn-sm">{{ __('panel.admin.save') }}</button></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                <div class="d-flex gap-2 mt-3 align-items-center">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-secondary btn-sm">Upravit produkt / přidat plán</a>
                    <span class="badge badge-light-{{ $salesMode->color() }}">{{ $salesMode->label() }}</span>
                    @if($product->provisioning_driver?->value === 'pterodactyl')
                        <span class="badge badge-light-danger ms-1">
                            <i data-feather="alert-triangle" style="width:11px;height:11px;"></i>
                            Pterodactyl driver chybí
                        </span>
                    @endif
                </div>
            </x-panel.card>
        @endforeach
    </div>
@endsection
