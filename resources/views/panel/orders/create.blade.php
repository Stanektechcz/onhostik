@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];

    // Build unique product types for filter
    $productTypes = $plans->map(fn($p) => ['slug' => $p->product?->slug, 'name' => $p->product?->name])
                          ->unique('slug')->values();

    // Price range
    $prices = $plans->map(fn($p) => $p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0);
    $priceMin = (int) floor($prices->min() / 100);
    $priceMax = (int) ceil($prices->max() / 100);

    // Billing cycles
    $billingCycles = $plans->map(fn($p) => ['value' => $p->billing_cycle->value, 'label' => $p->billing_cycle->label()])
                           ->unique('value')->values();
@endphp

@section('title', __('panel.nav.new_order'))

@push('styles')
<style>
/* ── Plan selection ── */
.plan-card-item { transition: opacity .2s, transform .15s; }
.plan-card-item.plan-filtered-out { display: none !important; }
.plan-card-item.plan-selected .card {
    border: 2px solid rgba(var(--theme-default),1) !important;
    box-shadow: 0 0 0 4px rgba(var(--theme-default),.12) !important;
}

/* ── product-img visual (replaces e-commerce photo) ── */
.plan-img-wrap {
    position: relative; overflow: hidden; min-height: 160px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, rgba(var(--light-background),1) 0%, rgba(var(--white),1) 100%);
    border-bottom: 1px solid rgba(var(--light-background),1);
}
.plan-img-wrap .plan-icon-inner { text-align: center; z-index: 1; }
.plan-img-wrap .plan-icon-inner i { transition: transform .25s ease; }
.plan-card-item:hover .plan-img-wrap .plan-icon-inner i { transform: scale(1.15); }

/* ── Left filter panel ── */
.left-filter {
    visibility: hidden; height: 0; opacity: 0; z-index: 0;
    min-width: 0; overflow: hidden;
    transition: visibility .3s, opacity .3s, height .3s;
}
.left-filter.open {
    visibility: visible; height: auto; opacity: 1;
}

/* ── Compare bar ── */
#compare-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1050;
    background: rgba(var(--white),1);
    box-shadow: 0 -4px 20px rgba(0,0,0,.15);
    transform: translateY(100%);
    transition: transform .25s ease;
    padding: 12px 24px;
}
#compare-bar.show { transform: translateY(0); }
#compare-bar .compare-plan-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(var(--light-background),1);
    border-radius: 20px; padding: 4px 12px; font-size: 13px;
}

/* ── List view mode ── */
.product-wrapper-grid.list-view .plan-card-item { width: 100% !important; }
.product-wrapper-grid.list-view .product-box { display: flex; align-items: stretch; }
.product-wrapper-grid.list-view .plan-img-wrap { min-width: 160px; min-height: 120px; max-width: 160px; }
.product-wrapper-grid.list-view .product-details { padding: 16px; flex: 1; text-align: left; }
.product-wrapper-grid.list-view .product-details h4 { white-space: normal; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($plans->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="package" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h5 class="f-light">Žádné dostupné tarify</h5>
                <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-primary btn-sm mt-3">
                    <i data-feather="arrow-left" style="width:13px;height:13px;"></i> Zpět
                </a>
            </div>
        </div>
    @else

    {{-- Step indicator --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-primary rounded-circle" style="width:28px;height:28px;line-height:20px;">1</span>
                    <span class="f-w-500">Vyberte tarif</span>
                </div>
                <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-secondary rounded-circle" style="width:28px;height:28px;line-height:20px;">2</span>
                    <span class="f-light">Doména</span>
                </div>
                <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-secondary rounded-circle" style="width:28px;height:28px;line-height:20px;">3</span>
                    <span class="f-light">Potvrzení</span>
                </div>
                @if($mockMode)
                    <span class="badge badge-light-warning ms-auto">MOCK MODE</span>
                @endif
            </div>
        </div>
    </div>

    @error('pricing_plan_id')
        <div class="alert alert-light-danger mb-3">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('panel.orders.store') }}" id="order-form">
    @csrf

    {{-- ═══════════════════════════════════════════════════
         CUBA product-wrapper
    ═══════════════════════════════════════════════════════ --}}
    <div class="container product-wrapper px-0">
      <div class="product-grid">

        {{-- ── Feature bar (view switcher + sort) ── --}}
        <div class="feature-products">
            <div class="grid grid-cols-12 gap-3 mb-3 align-items-center">
                {{-- Left: grid/list + column selectors --}}
                <div class="col-span-6 md:col-span-12 products-total">
                    <div class="square-product-setting d-inline-block">
                        <a class="icon-grid grid-layout-view active-view" href="#" title="Mřížka">
                            <i data-feather="grid"></i>
                        </a>
                    </div>
                    <div class="square-product-setting d-inline-block">
                        <a class="icon-grid m-0 list-layout-view" href="#" title="Seznam">
                            <i data-feather="list"></i>
                        </a>
                    </div>
                    <div class="grid-options d-inline-block">
                        <ul>
                            <li><a class="product-2-layout-view" href="#" title="2 sloupce">
                                <span class="line-grid line-grid-1 bg-primary"></span>
                                <span class="line-grid line-grid-2 bg-primary"></span>
                            </a></li>
                            <li><a class="product-3-layout-view" href="#" title="3 sloupce">
                                <span class="line-grid line-grid-3 bg-primary"></span>
                                <span class="line-grid line-grid-4 bg-primary"></span>
                                <span class="line-grid line-grid-5 bg-primary"></span>
                            </a></li>
                            <li><a class="product-4-layout-view active-layout" href="#" title="4 sloupce">
                                <span class="line-grid line-grid-6 bg-primary"></span>
                                <span class="line-grid line-grid-7 bg-primary"></span>
                                <span class="line-grid line-grid-8 bg-primary"></span>
                                <span class="line-grid line-grid-9 bg-primary"></span>
                            </a></li>
                        </ul>
                    </div>
                </div>
                {{-- Right: result count + sort --}}
                <div class="col-span-6 md:col-span-12 text-end md:text-start">
                    <span class="font-semibold me-3 f-14" id="visible-count">{{ $plans->count() }} tarify</span>
                    <div class="d-inline-block dropdown-product">
                        <select class="form-select form-select-sm" id="sort-plans" style="min-width:180px;">
                            <option value="default">Doporučené</option>
                            <option value="price_asc">Cena: od nejnižší</option>
                            <option value="price_desc">Cena: od nejvyšší</option>
                            <option value="name_asc">Název A–Z</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- ── Filter row: sidebar (col-3) + search (col-9) ── --}}
            <div class="grid grid-cols-12 gap-3 mb-3">

                {{-- Filter sidebar --}}
                <div class="col-span-3 md:col-span-12">
                    <div class="product-sidebar">
                        <div class="filter-section">
                            <div class="card">
                                <div class="card-header" style="cursor:pointer;" id="filter-toggle">
                                    <h6 class="mb-0 font-semibold">
                                        Filtry
                                        <span class="pull-right">
                                            <i data-feather="chevron-down" id="filter-chevron" style="width:16px;height:16px;transition:transform .3s;"></i>
                                        </span>
                                    </h6>
                                </div>
                                <div class="left-filter open" id="left-filter-panel">
                                    <div class="card-body filter-cards-view animate-chk">

                                        {{-- Typ služby --}}
                                        <div class="product-filter">
                                            <h6 class="font-semibold">Typ služby</h6>
                                            <div class="checkbox-animated mt-0">
                                                @foreach($productTypes as $pt)
                                                <label class="block" for="cat-{{ $pt['slug'] }}">
                                                    <input class="checkbox_animated filter-category"
                                                           id="cat-{{ $pt['slug'] }}"
                                                           type="checkbox"
                                                           value="{{ $pt['slug'] }}"
                                                           checked>
                                                    {{ $pt['name'] }}
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Cena --}}
                                        <div class="product-filter">
                                            <h6 class="font-semibold">Cena (Kč/měs)</h6>
                                            <div class="d-flex gap-2 align-items-center mt-2">
                                                <input type="number" class="form-control form-control-sm filter-price"
                                                       id="price-min" placeholder="Od"
                                                       value="{{ $priceMin }}" min="{{ $priceMin }}" max="{{ $priceMax }}">
                                                <span class="f-light">–</span>
                                                <input type="number" class="form-control form-control-sm filter-price"
                                                       id="price-max" placeholder="Do"
                                                       value="{{ $priceMax }}" min="{{ $priceMin }}" max="{{ $priceMax }}">
                                            </div>
                                        </div>

                                        {{-- Fakturační cyklus --}}
                                        @if($billingCycles->count() > 1)
                                        <div class="product-filter">
                                            <h6 class="font-semibold">Fakturační cyklus</h6>
                                            <div class="checkbox-animated mt-0">
                                                @foreach($billingCycles as $bc)
                                                <label class="block" for="bc-{{ $bc['value'] }}">
                                                    <input class="checkbox_animated filter-billing"
                                                           id="bc-{{ $bc['value'] }}"
                                                           type="checkbox"
                                                           value="{{ $bc['value'] }}"
                                                           checked>
                                                    {{ $bc['label'] }}
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Reset --}}
                                        <div class="product-filter pb-0">
                                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="reset-filters">
                                                <i data-feather="refresh-cw" style="width:12px;height:12px;"></i>
                                                Zrušit filtry
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Search --}}
                <div class="col-span-9 md:col-span-12">
                    <div class="form-group m-0">
                        <input class="form-control" type="search" id="plan-search"
                               placeholder="Hledat tarif…" autocomplete="off">
                        <i data-feather="search" style="width:16px;height:16px;position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;" class="text-muted"></i>
                    </div>
                </div>

            </div>
        </div>{{-- /feature-products --}}

        {{-- ═══════════════════════════════════════════════════
             CUBA product-wrapper-grid
        ═══════════════════════════════════════════════════════ --}}
        <div class="product-wrapper-grid" id="product-grid">
          <div class="grid grid-cols-12 card-gap" id="plans-grid">

            {{-- Empty state --}}
            <div class="col-span-12 d-none" id="no-results">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i data-feather="search" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                        <h6 class="f-light">Žádné tarify neodpovídají filtru</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="reset-filters-2">
                            Zrušit filtry
                        </button>
                    </div>
                </div>
            </div>

            @foreach($plans as $plan)
            @php
                $isSelected = (int) old('pricing_plan_id', $selectedPlan) === $plan->id;
                $price   = $plan->priceFor($customer->preferred_currency);
                $minor   = $price?->getMinorAmount()->toInt() ?? 0;
                $whole   = intdiv($minor, 100);
                $curr    = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
                $type    = $plan->product?->type?->value ?? 'webhosting';
                $slug    = $plan->product?->slug ?? 'webhosting';
                $icon    = match($type) { 'vps' => 'cpu', default => 'server' };
                $bgColor = match($type) {
                    'vps'     => 'rgba(var(--theme-secondary),.06)',
                    default   => 'rgba(var(--theme-default),.06)',
                };
                $modalId = 'plan-modal-' . $plan->id;
            @endphp

            <div class="col-span-3 xxl:col-span-4 md:col-span-6 sm:col-span-12 box-col-4 plan-card-item {{ $isSelected ? 'plan-selected' : '' }}"
                 data-plan-id="{{ $plan->id }}"
                 data-slug="{{ $slug }}"
                 data-price="{{ $whole }}"
                 data-name="{{ strtolower($plan->name) }} {{ strtolower($plan->product?->name ?? '') }}"
                 data-billing="{{ $plan->billing_cycle->value }}">

                <div class="card h-100">
                    <div class="product-box">

                        {{-- ── product-img ── --}}
                        <div class="plan-img-wrap product-img" style="background:{{ $bgColor }};">

                            @if($plan->is_featured)
                                <div class="ribbon ribbon-primary"><span>Oblíbený</span></div>
                            @endif

                            <div class="plan-icon-inner">
                                <i data-feather="{{ $icon }}"
                                   style="width:52px;height:52px;stroke-width:1.4;"
                                   class="{{ $type === 'vps' ? 'txt-secondary' : 'txt-primary' }}"></i>
                                <p class="f-light f-12 mt-1 mb-0">{{ $plan->product?->name }}</p>
                            </div>

                            {{-- ── product-hover (3 tlačítka jako v Cuba) ── --}}
                            <div class="product-hover">
                                <ul>
                                    {{-- Objednat (Cart) --}}
                                    <li>
                                        <a class="btn btn-select-plan" href="#"
                                           data-plan-id="{{ $plan->id }}" title="Vybrat tarif">
                                            <i data-feather="shopping-cart"></i>
                                        </a>
                                    </li>
                                    {{-- Náhled (Eye) --}}
                                    <li>
                                        <a class="btn" href="#" title="Náhled"
                                           data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                            <i data-feather="eye"></i>
                                        </a>
                                    </li>
                                    {{-- Porovnat --}}
                                    <li>
                                        <a class="btn btn-compare" href="#" title="Porovnat"
                                           data-plan-id="{{ $plan->id }}"
                                           data-plan-name="{{ $plan->product?->name }} {{ $plan->name }}">
                                            <i data-feather="bar-chart-2"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- ── Quick-view modal ── --}}
                        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header border-0 pb-0">
                                        <div class="product-box w-100">
                                            <div class="grid grid-cols-12 gap-4">
                                                {{-- Left: visual --}}
                                                <div class="col-span-5 lg:col-span-12">
                                                    <div class="plan-img-wrap" style="min-height:240px;background:{{ $bgColor }};border-radius:8px;">
                                                        @if($plan->is_featured)
                                                            <div class="ribbon ribbon-primary"><span>Oblíbený</span></div>
                                                        @endif
                                                        <div class="plan-icon-inner text-center">
                                                            <i data-feather="{{ $icon }}"
                                                               style="width:72px;height:72px;stroke-width:1.2;"
                                                               class="{{ $type === 'vps' ? 'txt-secondary' : 'txt-primary' }}"></i>
                                                            <p class="f-light mt-2 mb-0">{{ $plan->product?->name }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                {{-- Right: details --}}
                                                <div class="col-span-7 lg:col-span-12 text-start">
                                                    <div class="product-details">
                                                        <h4>{{ $plan->product?->name }} {{ $plan->name }}</h4>
                                                        @if($plan->tagline)
                                                            <p class="f-light f-14 mb-2">{{ $plan->tagline }}</p>
                                                        @endif
                                                        <div class="product-price mb-3">
                                                            {{ number_format($whole, 0, ',', ' ') }}
                                                            {{ $curr === 'CZK' ? 'Kč' : $curr }}
                                                            <span class="f-light f-14">/ {{ $plan->billing_cycle->label() }}</span>
                                                        </div>

                                                        {{-- Features list --}}
                                                        <div class="product-view">
                                                            <h6 class="font-semibold mb-2">Co je zahrnuto:</h6>
                                                            <ul class="list-unstyled mb-0">
                                                                @foreach($plan->resources ?? [] as $key => $value)
                                                                    <li class="d-flex align-items-center gap-2 mb-1">
                                                                        <i data-feather="check-circle" style="width:14px;height:14px;" class="txt-success flex-shrink-0"></i>
                                                                        <span>{{ __("front.resources.$key") }}: <strong>{{ $value }}</strong></span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>

                                                        {{-- CTA --}}
                                                        <div class="mt-3 d-flex gap-2">
                                                            <button type="button"
                                                                    class="btn btn-primary text-white modal-select-plan"
                                                                    data-plan-id="{{ $plan->id }}"
                                                                    data-bs-dismiss="modal">
                                                                <i data-feather="shopping-cart" style="width:14px;height:14px;" class="me-1"></i>
                                                                Objednat
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── product-details ── --}}
                        <div class="product-details">
                            <h4>{{ $plan->name }}</h4>
                            @if($plan->tagline)
                                <p class="f-light f-12">{{ $plan->tagline }}</p>
                            @endif
                            <div class="product-price">
                                {{ number_format($whole, 0, ',', ' ') }}
                                {{ $curr === 'CZK' ? ' Kč' : ' '.$curr }}
                                <span class="f-light f-12">/ {{ $plan->billing_cycle->label() }}</span>
                            </div>
                        </div>

                    </div>{{-- /product-box --}}
                </div>{{-- /card --}}

                {{-- Hidden radio (submitted with form) --}}
                <input type="radio" name="pricing_plan_id" value="{{ $plan->id }}"
                       class="plan-radio d-none" @checked($isSelected)>

            </div>{{-- /col --}}
            @endforeach

          </div>{{-- /grid --}}
        </div>{{-- /product-wrapper-grid --}}

      </div>{{-- /product-grid --}}
    </div>{{-- /product-wrapper --}}

    {{-- ── Doména ── --}}
    <div class="card mt-3">
        <div class="card-header card-no-border pb-0">
            <div class="header-top">
                <h5>{{ __('panel.orders.domain_label') }}</h5>
                <span class="badge badge-light-secondary">Volitelné</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label f-12 f-light">Doménové jméno</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i data-feather="globe" style="width:14px;height:14px;"></i>
                        </span>
                        <input class="form-control" type="text" name="domain"
                               value="{{ old('domain') }}"
                               placeholder="{{ __('front.domains.search_placeholder') }}">
                    </div>
                    <small class="f-light f-12 mt-1 d-block">{{ __('panel.orders.domain_hint') }}</small>
                    @error('domain')<p class="text-danger f-12 mt-1 mb-0">{{ $message }}</p>@enderror
                </div>
                <div class="col-md-5">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="register_domain"
                               id="register_domain" value="1" @checked(old('register_domain'))>
                        <label class="form-check-label" for="register_domain">
                            {{ __('panel.orders.register_domain') }}
                        </label>
                    </div>
                    @if($mockMode)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="simulate_failure"
                                   id="simulate_failure" value="1" @checked(old('simulate_failure'))>
                            <label class="form-check-label f-light f-12" for="simulate_failure">
                                {{ __('panel.orders.simulate_failure') }}
                                <span class="badge badge-light-warning">MOCK</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Submit ── --}}
    <div class="d-flex align-items-center gap-3 mt-3 mb-4 flex-wrap">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i data-feather="check" style="width:16px;height:16px;"></i>
            {{ __('panel.orders.submit') }}
        </button>
        <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-secondary">
            {{ __('panel.nav.back') }}
        </a>
        <small class="f-light ms-2">
            <i data-feather="info" style="width:12px;height:12px;"></i>
            Po odeslání bude vystavena zálohová faktura.
        </small>
    </div>

    </form>

    {{-- ── Compare bar (fixed bottom) ── --}}
    <div id="compare-bar">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="f-w-500 me-2">Porovnat tarify:</span>
            <div id="compare-chips" class="d-flex gap-2 flex-wrap"></div>
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="do-compare">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i>
                Porovnat (<span id="compare-count">0</span>)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-compare">
                Vymazat
            </button>
        </div>
    </div>

    {{-- Compare modal --}}
    <div class="modal fade" id="compareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Porovnání tarifů</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="compare-table-wrap">
                    {{-- Filled by JS --}}
                </div>
            </div>
        </div>
    </div>

    @endif
</div>

<script>
(function () {
    'use strict';

    /* ── Plan data from PHP ── */
    var planData = @json($plans->map(fn($p) => [
        'id'       => $p->id,
        'name'     => $p->product?->name . ' ' . $p->name,
        'product'  => $p->product?->name,
        'price'    => intdiv($p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0, 100),
        'currency' => $p->priceFor($customer->preferred_currency)?->getCurrency()->getCurrencyCode() ?? 'CZK',
        'billing'  => $p->billing_cycle->label(),
        'resources'=> $p->resources ?? [],
        'slug'     => $p->product?->slug,
    ])->values());

    /* ─────────────── Plan selection ─────────────── */
    function selectPlan(planId) {
        document.querySelectorAll('.plan-card-item').forEach(function (item) {
            item.classList.remove('plan-selected');
        });
        document.querySelectorAll('.plan-radio').forEach(function (r) { r.checked = false; });

        var card = document.querySelector('.plan-card-item[data-plan-id="' + planId + '"]');
        if (!card) return;
        card.classList.add('plan-selected');
        var radio = card.querySelector('.plan-radio');
        if (radio) radio.checked = true;
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* Click on card itself selects it */
    document.querySelectorAll('.plan-card-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            if (e.target.closest('.product-hover') || e.target.closest('.modal')) return;
            selectPlan(this.dataset.planId);
        });
    });

    /* Hover "Objednat" button selects plan */
    document.querySelectorAll('.btn-select-plan').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            selectPlan(this.dataset.planId);
        });
    });

    /* Modal "Objednat" button selects and dismisses */
    document.querySelectorAll('.modal-select-plan').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectPlan(this.dataset.planId);
        });
    });

    /* ─────────────── Filter ─────────────── */
    function applyFilters() {
        var checkedCats = Array.from(document.querySelectorAll('.filter-category:checked')).map(function (c) { return c.value; });
        var checkedBill = Array.from(document.querySelectorAll('.filter-billing:checked')).map(function (c) { return c.value; });
        var search      = (document.getElementById('plan-search') || {}).value.toLowerCase().trim();
        var priceMinEl  = document.getElementById('price-min');
        var priceMaxEl  = document.getElementById('price-max');
        var priceMin    = priceMinEl ? parseInt(priceMinEl.value) || 0 : 0;
        var priceMax    = priceMaxEl ? parseInt(priceMaxEl.value) || 999999 : 999999;

        var visible = 0;
        document.querySelectorAll('.plan-card-item').forEach(function (card) {
            var slug    = card.dataset.slug;
            var price   = parseInt(card.dataset.price) || 0;
            var name    = card.dataset.name || '';
            var billing = card.dataset.billing || '';

            var catOk     = checkedCats.length === 0 || checkedCats.indexOf(slug) !== -1;
            var priceOk   = price >= priceMin && price <= priceMax;
            var billOk    = checkedBill.length === 0 || checkedBill.indexOf(billing) !== -1;
            var searchOk  = !search || name.indexOf(search) !== -1;

            if (catOk && priceOk && billOk && searchOk) {
                card.classList.remove('plan-filtered-out');
                visible++;
            } else {
                card.classList.add('plan-filtered-out');
            }
        });

        var countEl = document.getElementById('visible-count');
        if (countEl) countEl.textContent = visible + (visible === 1 ? ' tarif' : visible < 5 ? ' tarify' : ' tarifů');

        var noResults = document.getElementById('no-results');
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
    }

    document.querySelectorAll('.filter-category, .filter-billing').forEach(function (el) {
        el.addEventListener('change', applyFilters);
    });
    document.querySelectorAll('.filter-price').forEach(function (el) {
        el.addEventListener('input', applyFilters);
    });
    var searchInput = document.getElementById('plan-search');
    if (searchInput) searchInput.addEventListener('input', applyFilters);

    function resetFilters() {
        document.querySelectorAll('.filter-category, .filter-billing').forEach(function (cb) { cb.checked = true; });
        var priceMinEl = document.getElementById('price-min');
        var priceMaxEl = document.getElementById('price-max');
        if (priceMinEl) priceMinEl.value = priceMinEl.min;
        if (priceMaxEl) priceMaxEl.value = priceMaxEl.max;
        if (searchInput) searchInput.value = '';
        applyFilters();
    }
    var rfBtn = document.getElementById('reset-filters');
    var rfBtn2 = document.getElementById('reset-filters-2');
    if (rfBtn) rfBtn.addEventListener('click', resetFilters);
    if (rfBtn2) rfBtn2.addEventListener('click', resetFilters);

    /* ─────────────── Sort ─────────────── */
    document.getElementById('sort-plans')?.addEventListener('change', function () {
        var grid = document.getElementById('plans-grid');
        if (!grid) return;
        var items = Array.from(grid.querySelectorAll('.plan-card-item'));
        var sort  = this.value;
        items.sort(function (a, b) {
            var aP = parseInt(a.dataset.price) || 0;
            var bP = parseInt(b.dataset.price) || 0;
            var aN = (a.dataset.name || '');
            var bN = (b.dataset.name || '');
            if (sort === 'price_asc')  return aP - bP;
            if (sort === 'price_desc') return bP - aP;
            if (sort === 'name_asc')   return aN.localeCompare(bN);
            return 0; // default
        });
        items.forEach(function (item) { grid.appendChild(item); });
    });

    /* ─────────────── Grid / List view ─────────────── */
    var gridWrap = document.getElementById('product-grid');
    document.querySelector('.grid-layout-view')?.addEventListener('click', function (e) {
        e.preventDefault();
        gridWrap?.classList.remove('list-view');
        if (typeof feather !== 'undefined') feather.replace();
    });
    document.querySelector('.list-layout-view')?.addEventListener('click', function (e) {
        e.preventDefault();
        gridWrap?.classList.add('list-view');
        if (typeof feather !== 'undefined') feather.replace();
    });

    /* Column count selectors */
    var colMap = { 'product-2-layout-view': 'col-span-6', 'product-3-layout-view': 'col-span-4', 'product-4-layout-view': 'col-span-3' };
    Object.keys(colMap).forEach(function (cls) {
        document.querySelector('.' + cls)?.addEventListener('click', function (e) {
            e.preventDefault();
            var grid = document.getElementById('plans-grid');
            if (!grid) return;
            grid.querySelectorAll('.plan-card-item').forEach(function (card) {
                card.className = card.className.replace(/col-span-\d+(?!\w)/, colMap[cls]);
            });
        });
    });

    /* ─────────────── Filter sidebar toggle ─────────────── */
    document.getElementById('filter-toggle')?.addEventListener('click', function () {
        var panel   = document.getElementById('left-filter-panel');
        var chevron = document.getElementById('filter-chevron');
        if (!panel) return;
        var isOpen = panel.classList.toggle('open');
        if (chevron) chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(-90deg)';
    });

    /* ─────────────── Compare ─────────────── */
    var compareSet = new Set();

    function updateCompareBar() {
        var bar    = document.getElementById('compare-bar');
        var chips  = document.getElementById('compare-chips');
        var count  = document.getElementById('compare-count');
        if (!bar || !chips || !count) return;

        count.textContent = compareSet.size;
        chips.innerHTML   = '';

        compareSet.forEach(function (id) {
            var plan = planData.find(function (p) { return p.id === id; });
            if (!plan) return;
            var chip  = document.createElement('span');
            chip.className = 'compare-plan-chip';
            chip.innerHTML = plan.name + ' <a href="#" class="txt-danger" data-remove="' + id + '">×</a>';
            chip.querySelector('[data-remove]').addEventListener('click', function (e) {
                e.preventDefault(); removeCompare(id);
            });
            chips.appendChild(chip);
        });

        if (compareSet.size > 0) {
            bar.classList.add('show');
        } else {
            bar.classList.remove('show');
        }

        if (typeof feather !== 'undefined') feather.replace();
    }

    function removeCompare(id) {
        compareSet.delete(id);
        var btn = document.querySelector('.btn-compare[data-plan-id="' + id + '"]');
        if (btn) btn.closest('.plan-card-item')?.querySelector('.btn-compare')?.classList.remove('active');
        updateCompareBar();
    }

    document.querySelectorAll('.btn-compare').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = parseInt(this.dataset.planId);
            if (compareSet.has(id)) {
                compareSet.delete(id);
                this.classList.remove('active');
            } else {
                if (compareSet.size >= 3) { alert('Lze porovnat max. 3 tarify.'); return; }
                compareSet.add(id);
                this.classList.add('active');
            }
            updateCompareBar();
        });
    });

    document.getElementById('clear-compare')?.addEventListener('click', function () {
        compareSet.clear();
        document.querySelectorAll('.btn-compare').forEach(function (b) { b.classList.remove('active'); });
        updateCompareBar();
    });

    /* Compare modal table */
    document.getElementById('do-compare')?.addEventListener('click', function () {
        if (compareSet.size < 2) { alert('Vyberte alespoň 2 tarify pro porovnání.'); return; }
        var plans  = Array.from(compareSet).map(function (id) { return planData.find(function (p) { return p.id === id; }); }).filter(Boolean);
        var allKeys = [...new Set(plans.flatMap(function (p) { return Object.keys(p.resources || {}); }))];

        var html = '<div class="table-responsive"><table class="table table-bordered text-center">';
        html += '<thead><tr><th class="text-start">Vlastnost</th>';
        plans.forEach(function (p) { html += '<th>' + p.name + '<br><small class="text-muted">' + p.product + '</small></th>'; });
        html += '</tr></thead><tbody>';

        html += '<tr><td class="text-start f-w-500">Cena</td>';
        plans.forEach(function (p) { html += '<td class="txt-primary f-w-600">' + p.price + ' ' + (p.currency === 'CZK' ? 'Kč' : p.currency) + '/měs</td>'; });
        html += '</tr>';

        allKeys.forEach(function (key) {
            html += '<tr><td class="text-start">' + key + '</td>';
            plans.forEach(function (p) {
                var val = (p.resources || {})[key];
                html += '<td>' + (val !== undefined ? '<strong>' + val + '</strong>' : '<span class="text-muted">—</span>') + '</td>';
            });
            html += '</tr>';
        });

        /* Select buttons */
        html += '<tr><td></td>';
        plans.forEach(function (p) {
            html += '<td><button type="button" class="btn btn-primary btn-sm compare-select" data-plan-id="' + p.id + '" data-bs-dismiss="modal">Objednat</button></td>';
        });
        html += '</tr>';

        html += '</tbody></table></div>';
        document.getElementById('compare-table-wrap').innerHTML = html;

        document.querySelectorAll('.compare-select').forEach(function (btn) {
            btn.addEventListener('click', function () { selectPlan(parseInt(this.dataset.planId)); });
        });

        var modal = new bootstrap.Modal(document.getElementById('compareModal'));
        modal.show();
        if (typeof feather !== 'undefined') setTimeout(function () { feather.replace(); }, 100);
    });

    /* ─────────────── Init feather ─────────────── */
    if (typeof feather !== 'undefined') feather.replace();

}());
</script>
@endsection
