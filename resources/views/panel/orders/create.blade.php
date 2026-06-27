@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];

    $productTypes  = $plans->map(fn($p) => ['slug' => $p->product?->slug, 'name' => $p->product?->name])
                           ->unique('slug')->values();
    $prices        = $plans->map(fn($p) => $p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0);
    $priceMin      = $prices->isNotEmpty() ? (int) floor($prices->min() / 100) : 0;
    $priceMax      = $prices->isNotEmpty() ? (int) ceil($prices->max() / 100) : 9999;
    $billingCycles = $plans->map(fn($p) => ['value' => $p->billing_cycle->value, 'label' => $p->billing_cycle->label()])
                           ->unique('value')->values();

    // Pre-build JS data (avoid @json + fn() Blade parse conflict)
    $jsPlanData = $plans->map(function ($p) use ($customer) {
        return [
            'id'        => $p->id,
            'name'      => trim(($p->product?->name ?? '') . ' ' . $p->name),
            'product'   => $p->product?->name ?? '',
            'price'     => intdiv($p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0, 100),
            'currency'  => $p->priceFor($customer->preferred_currency)?->getCurrency()->getCurrencyCode() ?? 'CZK',
            'billing'   => $p->billing_cycle->label(),
            'resources' => $p->resources ?? (object)[],
            'slug'      => $p->product?->slug ?? '',
        ];
    })->values();
@endphp

@section('title', __('panel.nav.new_order'))

@push('styles')
<style>
/* ─────────────────────────── Plan card ─────────────────────────── */
.plan-card-item { transition: transform .15s ease; cursor: pointer; }
.plan-card-item:hover { transform: translateY(-3px); }
.plan-card-item .card { transition: border .2s, box-shadow .2s; border: 2px solid transparent; }
.plan-card-item:hover .card { border-color: rgba(var(--theme-default),.3); }
.plan-card-item.plan-selected .card {
    border-color: rgba(var(--theme-default),1) !important;
    box-shadow: 0 0 0 4px rgba(var(--theme-default),.15) !important;
}
.plan-card-item.plan-filtered-out { display: none !important; }

/* Selected badge */
.plan-selected-badge {
    display: none; position: absolute; top: 8px; left: 8px; z-index: 10;
    background: rgba(var(--theme-default),1); color: #fff;
    border-radius: 20px; padding: 2px 10px; font-size: 11px; font-weight: 600;
}
.plan-card-item.plan-selected .plan-selected-badge { display: block; }

/* ─────────────────────────── Visual area ─────────────────────────── */
.plan-visual {
    position: relative; min-height: 140px;
    display: flex; align-items: center; justify-content: center;
    border-bottom: 1px solid rgba(var(--light-background),1);
    overflow: hidden;
}
.plan-visual .plan-icon { z-index: 1; text-align: center; transition: transform .25s; }
.plan-card-item:hover .plan-visual .plan-icon { transform: scale(1.1); }

/* ─── Cuba product-hover: always slightly visible, full on hover ─── */
.product-hover {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(var(--white),.85);
    opacity: 0; transition: opacity .2s ease;
}
.plan-card-item:hover .product-hover,
.product-hover.always-show { opacity: 1; }
.product-hover ul { display: flex; gap: 8px; list-style: none; margin: 0; padding: 0; }
.product-hover .btn {
    width: 44px; height: 44px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; background: rgba(var(--white),1);
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
    transition: background .15s, color .15s;
    border: 1px solid rgba(var(--light-background),1);
}
.product-hover .btn:hover, .product-hover .btn.active-btn {
    background: rgba(var(--theme-default),1); color: #fff;
    border-color: rgba(var(--theme-default),1);
}
.product-hover .btn i { width: 18px; height: 18px; color: inherit; }

/* ─────────────────────────── Product details ─────────────────────────── */
.product-details { padding: 16px; }
.product-details h4 { font-size: 16px; margin-bottom: 4px; white-space: normal; }
.product-price { font-size: 20px; font-weight: 700; color: rgba(var(--theme-default),1); margin: 8px 0; }
.product-price .duration { font-size: 12px; font-weight: 400; color: rgba(var(--font-color),.55); }
.plan-features { list-style: none; padding: 0; margin: 8px 0 12px; }
.plan-features li { display: flex; align-items: center; gap: 6px; font-size: 12px; color: rgba(var(--font-color),.7); padding: 2px 0; }
.plan-features li i { flex-shrink: 0; color: rgba(var(--success-color),1); }

/* ─────────────────────────── CTA button in card ─────────────────────────── */
.btn-select-visible {
    width: 100%; font-size: 13px; font-weight: 600;
    padding: 8px; border-radius: 8px;
    transition: all .15s;
}
.plan-card-item.plan-selected .btn-select-visible {
    background: rgba(var(--theme-default),1) !important;
    border-color: rgba(var(--theme-default),1) !important;
    color: #fff !important;
}

/* ─────────────────────────── Filter panel ─────────────────────────── */
.left-filter { overflow: hidden; transition: max-height .3s ease, opacity .3s; max-height: 0; opacity: 0; }
.left-filter.filter-open { max-height: 1000px; opacity: 1; }
#filter-chevron { transition: transform .3s; }
.filter-open-state #filter-chevron { transform: rotate(180deg); }

/* ─────────────────────────── Submit bar ─────────────────────────── */
#selected-plan-bar {
    position: sticky; bottom: 0; z-index: 100;
    background: rgba(var(--white),1);
    border-top: 2px solid rgba(var(--theme-default),1);
    box-shadow: 0 -4px 20px rgba(0,0,0,.1);
    padding: 12px 20px;
    display: none;
}
#selected-plan-bar.bar-visible { display: block; }

/* ─────────────────────────── Compare bar ─────────────────────────── */
#compare-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1050;
    background: rgba(var(--white),1);
    box-shadow: 0 -4px 20px rgba(0,0,0,.15);
    transform: translateY(100%); transition: transform .25s ease;
    padding: 10px 24px;
}
#compare-bar.bar-show { transform: translateY(0); }
.compare-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(var(--light-background),1); border-radius: 20px;
    padding: 3px 12px; font-size: 12px;
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($plans->isEmpty())
        <div class="card"><div class="card-body text-center py-5">
            <i data-feather="package" style="width:48px;height:48px;" class="text-muted mb-3"></i>
            <h5 class="f-light">Žádné dostupné tarify</h5>
            <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-primary btn-sm mt-3">
                <i data-feather="arrow-left" style="width:13px;height:13px;"></i> Zpět
            </a>
        </div></div>
    @else

    {{-- Step indicator --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light-primary rounded-circle text-center" style="width:28px;height:28px;line-height:20px;">1</span>
                    <span class="f-w-600">Vyberte tarif</span>
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
                @if($mockMode)<span class="badge badge-light-warning ms-auto">MOCK MODE</span>@endif
            </div>
        </div>
    </div>

    @error('pricing_plan_id')
        <div class="alert alert-light-danger mb-3">
            <i data-feather="alert-circle" style="width:16px;height:16px;" class="me-2"></i>
            {{ $message }}
        </div>
    @enderror

    <form method="POST" action="{{ route('panel.orders.store') }}" id="order-form">
    @csrf

    {{-- ══════════════════════════════════════════════════════════
         CUBA product-wrapper
    ══════════════════════════════════════════════════════════════ --}}
    <div class="container product-wrapper px-0">
      <div class="product-grid">

        {{-- ── Feature bar ── --}}
        <div class="feature-products">
          <div class="grid grid-cols-12 gap-3 mb-3 align-items-center">
            {{-- View switchers + columns --}}
            <div class="col-span-6 md:col-span-12 products-total">
              <div class="square-product-setting d-inline-block">
                <a class="icon-grid grid-layout-view" href="#" title="Mřížka">
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
                    <span class="line-grid line-grid-1 bg-primary"></span><span class="line-grid line-grid-2 bg-primary"></span>
                  </a></li>
                  <li><a class="product-3-layout-view" href="#" title="3 sloupce">
                    <span class="line-grid line-grid-3 bg-primary"></span><span class="line-grid line-grid-4 bg-primary"></span><span class="line-grid line-grid-5 bg-primary"></span>
                  </a></li>
                  <li><a class="product-4-layout-view active-col" href="#" title="4 sloupce">
                    <span class="line-grid line-grid-6 bg-primary"></span><span class="line-grid line-grid-7 bg-primary"></span><span class="line-grid line-grid-8 bg-primary"></span><span class="line-grid line-grid-9 bg-primary"></span>
                  </a></li>
                </ul>
              </div>
            </div>
            {{-- Result count + sort --}}
            <div class="col-span-6 md:col-span-12 text-end md:text-start">
              <span class="f-w-500 me-3 f-14" id="visible-count">{{ $plans->count() }} tarify</span>
              <div class="d-inline-block dropdown-product">
                <select class="form-select form-select-sm" id="sort-plans" style="min-width:185px;">
                  <option value="default">Doporučené</option>
                  <option value="price_asc">Cena: od nejnižší</option>
                  <option value="price_desc">Cena: od nejvyšší</option>
                  <option value="name_asc">Název A–Z</option>
                </select>
              </div>
            </div>
          </div>

          {{-- ── Filter row ── --}}
          <div class="grid grid-cols-12 gap-3 mb-3">
            {{-- Sidebar --}}
            <div class="col-span-3 md:col-span-12">
              <div class="product-sidebar">
                <div class="filter-section">
                  <div class="card">
                    <div class="card-header" id="filter-header" style="cursor:pointer;">
                      <h6 class="mb-0 font-semibold d-flex align-items-center justify-content-between">
                        <span><i data-feather="sliders" style="width:14px;height:14px;" class="me-2"></i>Filtry</span>
                        <i data-feather="chevron-down" id="filter-chevron" style="width:16px;height:16px;"></i>
                      </h6>
                    </div>
                    <div class="left-filter filter-open" id="filter-panel">
                      <div class="card-body filter-cards-view animate-chk pt-2">

                        {{-- Typ služby --}}
                        <div class="product-filter">
                          <h6 class="font-semibold">Typ služby</h6>
                          <div class="checkbox-animated mt-0">
                            @foreach($productTypes as $pt)
                              <label class="block" for="cat-{{ $pt['slug'] }}">
                                <input class="checkbox_animated filter-cat"
                                       id="cat-{{ $pt['slug'] }}"
                                       type="checkbox"
                                       value="{{ $pt['slug'] }}" checked>
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
                            <span class="f-light f-12">–</span>
                            <input type="number" class="form-control form-control-sm filter-price"
                                   id="price-max" placeholder="Do"
                                   value="{{ $priceMax }}" min="{{ $priceMin }}" max="{{ $priceMax }}">
                          </div>
                        </div>

                        {{-- Cyklus --}}
                        @if($billingCycles->count() > 1)
                        <div class="product-filter">
                          <h6 class="font-semibold">Fakturační cyklus</h6>
                          <div class="checkbox-animated mt-0">
                            @foreach($billingCycles as $bc)
                              <label class="block" for="bc-{{ $bc['value'] }}">
                                <input class="checkbox_animated filter-billing"
                                       id="bc-{{ $bc['value'] }}"
                                       type="checkbox"
                                       value="{{ $bc['value'] }}" checked>
                                {{ $bc['label'] }}
                              </label>
                            @endforeach
                          </div>
                        </div>
                        @endif

                        <div class="product-filter pb-0">
                          <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-reset-filters">
                            <i data-feather="refresh-cw" style="width:12px;height:12px;" class="me-1"></i>
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
            <div class="col-span-9 md:col-span-12" style="position:relative;">
              <input class="form-control" type="search" id="plan-search"
                     placeholder="Hledat tarif (název, typ…)" autocomplete="off">
              <i data-feather="search" style="width:16px;height:16px;position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;" class="text-muted"></i>
            </div>
          </div>
        </div>{{-- /feature-products --}}

        {{-- ══ product-wrapper-grid ══ --}}
        <div class="product-wrapper-grid" id="product-grid">
          <div class="grid grid-cols-12 card-gap" id="plans-grid">

            {{-- No results --}}
            <div class="col-span-12 d-none" id="no-results">
              <div class="card"><div class="card-body text-center py-5">
                <i data-feather="search" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <h6 class="f-light">Žádné tarify neodpovídají filtru</h6>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btn-reset-2">Zrušit filtry</button>
              </div></div>
            </div>

            @foreach($plans as $plan)
            @php
                $sel    = (int) old('pricing_plan_id', $selectedPlan) === $plan->id;
                $price  = $plan->priceFor($customer->preferred_currency);
                $minor  = $price?->getMinorAmount()->toInt() ?? 0;
                $whole  = intdiv($minor, 100);
                $curr   = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
                $ptype  = $plan->product?->type?->value ?? 'webhosting';
                $slug   = $plan->product?->slug ?? '';
                $icon   = match($ptype) { 'vps' => 'cpu', default => 'server' };
                $bg     = match($ptype) {
                    'vps'  => 'linear-gradient(135deg,rgba(var(--theme-secondary),.08) 0%,rgba(var(--white),1) 100%)',
                    default=> 'linear-gradient(135deg,rgba(var(--theme-default),.08) 0%,rgba(var(--white),1) 100%)',
                };
                $mid    = 'modal-plan-' . $plan->id;
            @endphp

            <div class="col-span-3 xxl:col-span-4 md:col-span-6 sm:col-span-12 box-col-4 plan-card-item {{ $sel ? 'plan-selected' : '' }}"
                 data-plan-id="{{ $plan->id }}"
                 data-slug="{{ $slug }}"
                 data-price="{{ $whole }}"
                 data-sort="{{ $plan->id }}"
                 data-name="{{ strtolower($plan->product?->name ?? '') }} {{ strtolower($plan->name) }}"
                 data-billing="{{ $plan->billing_cycle->value }}">

                {{-- Hidden radio --}}
                <input type="radio" name="pricing_plan_id" value="{{ $plan->id }}"
                       class="plan-radio d-none" @checked($sel)>

                <div class="card h-100">
                    <div class="product-box">

                        {{-- ── product-img (visual area) ── --}}
                        <div class="plan-visual product-img" style="background:{{ $bg }};">
                            <span class="plan-selected-badge">✓ Vybráno</span>

                            @if($plan->is_featured)
                                <div class="ribbon ribbon-primary"><span>Oblíbený</span></div>
                            @endif

                            <div class="plan-icon">
                                <i data-feather="{{ $icon }}"
                                   style="width:52px;height:52px;stroke-width:1.4;"
                                   class="{{ $ptype === 'vps' ? 'txt-secondary' : 'txt-primary' }}"></i>
                                <p class="f-light f-12 mt-1 mb-0">{{ $plan->product?->name }}</p>
                            </div>

                            {{-- ── product-hover (3 Cuba buttons) ── --}}
                            <div class="product-hover">
                                <ul>
                                    <li title="Vybrat tarif">
                                        <a class="btn btn-cart" href="#" data-plan-id="{{ $plan->id }}">
                                            <i data-feather="shopping-cart"></i>
                                        </a>
                                    </li>
                                    <li title="Detail tarifu">
                                        <a class="btn btn-eye" href="#"
                                           data-bs-toggle="modal" data-bs-target="#{{ $mid }}">
                                            <i data-feather="eye"></i>
                                        </a>
                                    </li>
                                    <li title="Porovnat tarify">
                                        <a class="btn btn-cmp" href="#" data-plan-id="{{ $plan->id }}"
                                           data-plan-name="{{ $plan->product?->name }} {{ $plan->name }}">
                                            <i data-feather="bar-chart-2"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- ── Quick-view modal ── --}}
                        <div class="modal fade" id="{{ $mid }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header border-0 pb-0">
                                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body pt-0">
                                        <div class="grid grid-cols-12 gap-4">
                                            <div class="col-span-5 lg:col-span-12">
                                                <div class="plan-visual" style="min-height:220px;background:{{ $bg }};border-radius:10px;">
                                                    @if($plan->is_featured)
                                                        <div class="ribbon ribbon-primary"><span>Oblíbený</span></div>
                                                    @endif
                                                    <div class="plan-icon">
                                                        <i data-feather="{{ $icon }}"
                                                           style="width:64px;height:64px;stroke-width:1.2;"
                                                           class="{{ $ptype === 'vps' ? 'txt-secondary' : 'txt-primary' }}"></i>
                                                        <p class="f-light mt-2 mb-0">{{ $plan->product?->name }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-span-7 lg:col-span-12">
                                                <div class="product-details text-start ps-0">
                                                    <h4>{{ $plan->product?->name }} — {{ $plan->name }}</h4>
                                                    @if($plan->tagline)
                                                        <p class="f-light f-14 mb-2">{{ $plan->tagline }}</p>
                                                    @endif
                                                    <div class="product-price mb-3">
                                                        {{ number_format($whole, 0, ',', ' ') }} {{ $curr === 'CZK' ? 'Kč' : $curr }}
                                                        <span class="duration">/ {{ $plan->billing_cycle->label() }}</span>
                                                    </div>
                                                    @if(!empty($plan->resources))
                                                    <div class="product-view">
                                                        <h6 class="font-semibold mb-2">Co je zahrnuto:</h6>
                                                        <ul class="plan-features">
                                                            @foreach($plan->resources as $key => $value)
                                                                <li>
                                                                    <i data-feather="check-circle" style="width:14px;height:14px;"></i>
                                                                    {{ __("front.resources.$key") }}: <strong>{{ $value }}</strong>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                    @endif
                                                    <div class="d-flex gap-2 mt-3">
                                                        <button type="button" class="btn btn-primary modal-select-btn"
                                                                data-plan-id="{{ $plan->id }}"
                                                                data-bs-dismiss="modal">
                                                            <i data-feather="shopping-cart" style="width:14px;height:14px;" class="me-1"></i>
                                                            Vybrat tento tarif
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── product-details ── --}}
                        <div class="product-details">
                            <h4>{{ $plan->name }}</h4>
                            @if($plan->tagline)
                                <p class="f-light f-12 mb-1">{{ $plan->tagline }}</p>
                            @endif
                            <div class="product-price">
                                {{ number_format($whole, 0, ',', ' ') }} {{ $curr === 'CZK' ? 'Kč' : $curr }}
                                <span class="duration">/ {{ $plan->billing_cycle->label() }}</span>
                            </div>
                            @if(!empty($plan->resources))
                            <ul class="plan-features">
                                @foreach(array_slice($plan->resources, 0, 3, true) as $key => $value)
                                    <li>
                                        <i data-feather="check" style="width:12px;height:12px;"></i>
                                        {{ __("front.resources.$key") }}: <strong>{{ $value }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                            @endif
                            {{-- Always-visible select button --}}
                            <button type="button"
                                    class="btn btn-outline-primary btn-select-visible w-100"
                                    data-plan-id="{{ $plan->id }}">
                                <i data-feather="{{ $sel ? 'check-circle' : 'shopping-cart' }}"
                                   class="btn-sel-icon" style="width:14px;height:14px;" class="me-1"></i>
                                <span class="btn-sel-text">{{ $sel ? 'Vybráno ✓' : 'Vybrat tarif' }}</span>
                            </button>
                        </div>

                    </div>{{-- /product-box --}}
                </div>{{-- /card --}}
            </div>{{-- /col --}}
            @endforeach

          </div>{{-- /plans-grid --}}
        </div>{{-- /product-wrapper-grid --}}

      </div>{{-- /product-grid --}}
    </div>{{-- /product-wrapper --}}

    {{-- ─── Sticky selected plan bar ─────────────────────────────── --}}
    <div id="selected-plan-bar">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <span class="f-light f-12 d-block">Vybraný tarif:</span>
                <span class="f-w-600 txt-primary" id="bar-plan-name">—</span>
                <span class="f-w-600 ms-2" id="bar-plan-price"></span>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="bar-clear-plan">Zrušit výběr</button>
                <button type="button" class="btn btn-primary btn-lg px-4" id="bar-continue">
                    Pokračovat →
                </button>
            </div>
        </div>
    </div>

    {{-- ─── Doména ──────────────────────────────────────────────── --}}
    <div class="card mt-3" id="domain-section">
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

    {{-- ─── Submit ─────────────────────────────────────────────── --}}
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

    {{-- ─── Compare floating bar ──────────────────────────────── --}}
    <div id="compare-bar">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="f-w-500">Porovnat:</span>
            <div id="compare-chips" class="d-flex gap-2 flex-wrap"></div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-clear-compare">Vymazat</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-do-compare">
                    Porovnat (<span id="cmp-count">0</span>)
                </button>
            </div>
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
                <div class="modal-body" id="compare-table"></div>
            </div>
        </div>
    </div>

    @endif
</div>

<script>
(function () {
'use strict';

// ──────────────── Plan data ────────────────
var planData = {!! json_encode($jsPlanData) !!};

// ──────────────── Helpers ────────────────
function fmt(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

// ──────────────── Select plan ────────────────
function selectPlan(id) {
    id = parseInt(id);

    document.querySelectorAll('.plan-card-item').forEach(function(card) {
        var isThis = parseInt(card.dataset.planId) === id;
        card.classList.toggle('plan-selected', isThis);

        var radio = card.querySelector('.plan-radio');
        if (radio) radio.checked = isThis;

        var btn = card.querySelector('.btn-select-visible');
        if (btn) {
            var icon = btn.querySelector('.btn-sel-icon');
            var text = btn.querySelector('.btn-sel-text');
            if (isThis) {
                btn.classList.replace('btn-outline-primary', 'btn-primary');
                if (icon) { icon.setAttribute('data-feather', 'check-circle'); }
                if (text) text.textContent = 'Vybráno ✓';
            } else {
                btn.classList.replace('btn-primary', 'btn-outline-primary');
                if (icon) { icon.setAttribute('data-feather', 'shopping-cart'); }
                if (text) text.textContent = 'Vybrat tarif';
            }
        }

        // Cart hover button state
        var cartBtn = card.querySelector('.btn-cart');
        if (cartBtn) cartBtn.classList.toggle('active-btn', isThis);
    });

    // Update sticky bar
    var plan = planData.find(function(p) { return p.id === id; });
    var bar  = document.getElementById('selected-plan-bar');
    if (plan && bar) {
        document.getElementById('bar-plan-name').textContent  = plan.name;
        document.getElementById('bar-plan-price').textContent = fmt(plan.price) + ' ' + (plan.currency === 'CZK' ? 'Kč' : plan.currency) + '/měs';
        bar.classList.add('bar-visible');
    }

    if (typeof feather !== 'undefined') feather.replace();
}

// Card click
document.querySelectorAll('.plan-card-item').forEach(function(card) {
    card.addEventListener('click', function(e) {
        if (e.target.closest('.product-hover') || e.target.closest('.modal') || e.target.closest('.btn-select-visible')) return;
        selectPlan(this.dataset.planId);
    });
});

// Cart hover button
document.querySelectorAll('.btn-cart').forEach(function(btn) {
    btn.addEventListener('click', function(e) { e.preventDefault(); selectPlan(this.dataset.planId); });
});

// Always-visible select button
document.querySelectorAll('.btn-select-visible').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        selectPlan(this.dataset.planId);
    });
});

// Modal select button
document.querySelectorAll('.modal-select-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { selectPlan(this.dataset.planId); });
});

// Sticky bar clear
document.getElementById('bar-clear-plan')?.addEventListener('click', function() {
    document.querySelectorAll('.plan-card-item').forEach(function(c) { c.classList.remove('plan-selected'); });
    document.querySelectorAll('.plan-radio').forEach(function(r) { r.checked = false; });
    document.getElementById('selected-plan-bar')?.classList.remove('bar-visible');
    document.querySelectorAll('.btn-select-visible').forEach(function(btn) {
        btn.classList.replace('btn-primary', 'btn-outline-primary');
        var text = btn.querySelector('.btn-sel-text');
        if (text) text.textContent = 'Vybrat tarif';
        var icon = btn.querySelector('.btn-sel-icon');
        if (icon) icon.setAttribute('data-feather', 'shopping-cart');
    });
    if (typeof feather !== 'undefined') feather.replace();
});

// Sticky bar continue → scroll to domain
document.getElementById('bar-continue')?.addEventListener('click', function() {
    var domain = document.getElementById('domain-section');
    if (domain) domain.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// Init pre-selected plan bar
(function() {
    var checked = document.querySelector('input[name="pricing_plan_id"]:checked');
    if (checked) selectPlan(checked.value);
})();

// ──────────────── Filter ────────────────
function applyFilters() {
    var cats    = Array.from(document.querySelectorAll('.filter-cat:checked')).map(function(c) { return c.value; });
    var bills   = Array.from(document.querySelectorAll('.filter-billing:checked')).map(function(c) { return c.value; });
    var search  = (document.getElementById('plan-search')?.value || '').toLowerCase().trim();
    var pMinEl  = document.getElementById('price-min');
    var pMaxEl  = document.getElementById('price-max');
    var pMin    = pMinEl ? (parseInt(pMinEl.value) || 0) : 0;
    var pMax    = pMaxEl ? (parseInt(pMaxEl.value) || 99999) : 99999;

    var visible = 0;
    document.querySelectorAll('.plan-card-item').forEach(function(card) {
        var ok = true;
        if (cats.length  && cats.indexOf(card.dataset.slug) === -1) ok = false;
        if (bills.length && bills.indexOf(card.dataset.billing) === -1) ok = false;
        var p = parseInt(card.dataset.price) || 0;
        if (p < pMin || p > pMax) ok = false;
        if (search && (card.dataset.name || '').indexOf(search) === -1) ok = false;

        card.classList.toggle('plan-filtered-out', !ok);
        if (ok) visible++;
    });

    var noRes = document.getElementById('no-results');
    if (noRes) noRes.classList.toggle('d-none', visible > 0);

    var countEl = document.getElementById('visible-count');
    if (countEl) {
        countEl.textContent = visible + (visible === 1 ? ' tarif' : (visible < 5 ? ' tarify' : ' tarifů'));
    }
}

document.querySelectorAll('.filter-cat, .filter-billing').forEach(function(el) {
    el.addEventListener('change', applyFilters);
});
document.querySelectorAll('.filter-price').forEach(function(el) {
    el.addEventListener('input', applyFilters);
});
document.getElementById('plan-search')?.addEventListener('input', applyFilters);

function resetFilters() {
    document.querySelectorAll('.filter-cat, .filter-billing').forEach(function(cb) { cb.checked = true; });
    var pMin = document.getElementById('price-min');
    var pMax = document.getElementById('price-max');
    if (pMin) pMin.value = pMin.min;
    if (pMax) pMax.value = pMax.max;
    var s = document.getElementById('plan-search');
    if (s) s.value = '';
    applyFilters();
}
document.getElementById('btn-reset-filters')?.addEventListener('click', resetFilters);
document.getElementById('btn-reset-2')?.addEventListener('click', resetFilters);

// ──────────────── Sort ────────────────
document.getElementById('sort-plans')?.addEventListener('change', function() {
    var grid  = document.getElementById('plans-grid');
    if (!grid) return;
    var items = Array.from(grid.querySelectorAll('.plan-card-item'));
    var val   = this.value;

    items.sort(function(a, b) {
        var aP = parseInt(a.dataset.price) || 0, bP = parseInt(b.dataset.price) || 0;
        var aN = a.dataset.name || '', bN = b.dataset.name || '';
        var aS = parseInt(a.dataset.sort) || 0, bS = parseInt(b.dataset.sort) || 0;
        if (val === 'price_asc')  return aP - bP;
        if (val === 'price_desc') return bP - aP;
        if (val === 'name_asc')   return aN.localeCompare(bN, 'cs');
        return aS - bS; // default = original DB order
    });

    var noRes = document.getElementById('no-results');
    if (noRes) grid.insertBefore(noRes, grid.firstChild);
    items.forEach(function(item) { grid.appendChild(item); });
});

// ──────────────── Grid/List view ────────────────
var gridWrap = document.getElementById('product-grid');
document.querySelector('.grid-layout-view')?.addEventListener('click', function(e) {
    e.preventDefault(); gridWrap?.classList.remove('list-view');
    if (typeof feather !== 'undefined') feather.replace();
});
document.querySelector('.list-layout-view')?.addEventListener('click', function(e) {
    e.preventDefault(); gridWrap?.classList.add('list-view');
    if (typeof feather !== 'undefined') feather.replace();
});

// Column count
var colClasses = { 'product-2-layout-view': 'col-span-6', 'product-3-layout-view': 'col-span-4', 'product-4-layout-view': 'col-span-3' };
Object.keys(colClasses).forEach(function(cls) {
    document.querySelector('.' + cls)?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.plan-card-item').forEach(function(card) {
            var cn = card.className;
            cn = cn.replace(/\bcol-span-\d+(?!\w)/, colClasses[cls]);
            card.className = cn;
        });
    });
});

// ──────────────── Filter panel toggle ────────────────
var filterHeader = document.getElementById('filter-header');
var filterPanel  = document.getElementById('filter-panel');
var filterChevron= document.getElementById('filter-chevron');
if (filterHeader && filterPanel) {
    filterHeader.addEventListener('click', function() {
        var open = filterPanel.classList.toggle('filter-open');
        if (filterChevron) filterChevron.style.transform = open ? 'rotate(0)' : 'rotate(-90deg)';
    });
}

// ──────────────── Compare ────────────────
var compareSet = new Set();

function updateCompare() {
    var bar   = document.getElementById('compare-bar');
    var chips = document.getElementById('compare-chips');
    var count = document.getElementById('cmp-count');
    if (!bar || !chips || !count) return;

    count.textContent = compareSet.size;
    chips.innerHTML = '';
    compareSet.forEach(function(id) {
        var plan = planData.find(function(p) { return p.id === id; });
        if (!plan) return;
        var chip = document.createElement('span');
        chip.className = 'compare-chip';
        chip.innerHTML = plan.name + ' <a href="#" data-rem="' + id + '" style="color:var(--danger);">×</a>';
        chip.querySelector('[data-rem]').addEventListener('click', function(e) {
            e.preventDefault(); removeFromCompare(id);
        });
        chips.appendChild(chip);
    });
    bar.classList.toggle('bar-show', compareSet.size > 0);
    if (typeof feather !== 'undefined') setTimeout(function() { feather.replace(); }, 50);
}

function removeFromCompare(id) {
    compareSet.delete(id);
    var btn = document.querySelector('.btn-cmp[data-plan-id="' + id + '"]');
    if (btn) btn.classList.remove('active-btn');
    updateCompare();
}

document.querySelectorAll('.btn-cmp').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = parseInt(this.dataset.planId);
        if (compareSet.has(id)) {
            compareSet.delete(id); this.classList.remove('active-btn');
        } else {
            if (compareSet.size >= 3) { alert('Lze porovnat max. 3 tarify.'); return; }
            compareSet.add(id); this.classList.add('active-btn');
        }
        updateCompare();
    });
});

document.getElementById('btn-clear-compare')?.addEventListener('click', function() {
    compareSet.clear();
    document.querySelectorAll('.btn-cmp').forEach(function(b) { b.classList.remove('active-btn'); });
    updateCompare();
});

document.getElementById('btn-do-compare')?.addEventListener('click', function() {
    if (compareSet.size < 2) { alert('Vyberte alespoň 2 tarify.'); return; }
    var plans   = Array.from(compareSet).map(function(id) { return planData.find(function(p) { return p.id === id; }); }).filter(Boolean);
    var allKeys = [];
    plans.forEach(function(p) { Object.keys(p.resources || {}).forEach(function(k) { if (allKeys.indexOf(k) === -1) allKeys.push(k); }); });

    var html = '<div class="table-responsive"><table class="table table-bordered text-center align-middle">';
    html += '<thead class="table-light"><tr><th class="text-start" style="min-width:140px">Vlastnost</th>';
    plans.forEach(function(p) { html += '<th>' + p.name + '<br><small class="text-muted">' + p.product + '</small></th>'; });
    html += '</tr></thead><tbody>';

    html += '<tr><td class="text-start f-w-500">Cena / měs</td>';
    plans.forEach(function(p) { html += '<td class="txt-primary f-w-700 f-18">' + fmt(p.price) + ' ' + (p.currency === 'CZK' ? 'Kč' : p.currency) + '</td>'; });
    html += '</tr>';

    allKeys.forEach(function(key) {
        html += '<tr><td class="text-start">' + key + '</td>';
        plans.forEach(function(p) {
            var v = (p.resources || {})[key];
            html += '<td>' + (v !== undefined ? '<strong>' + v + '</strong>' : '<span class="text-muted">—</span>') + '</td>';
        });
        html += '</tr>';
    });

    html += '<tr><td class="text-start">Akce</td>';
    plans.forEach(function(p) {
        html += '<td><button type="button" class="btn btn-primary btn-sm cmp-order" data-plan-id="' + p.id + '" data-bs-dismiss="modal">Objednat</button></td>';
    });
    html += '</tr></tbody></table></div>';

    var wrap = document.getElementById('compare-table');
    if (wrap) wrap.innerHTML = html;
    wrap?.querySelectorAll('.cmp-order').forEach(function(btn) {
        btn.addEventListener('click', function() { selectPlan(this.dataset.planId); });
    });

    var modal = new bootstrap.Modal(document.getElementById('compareModal'));
    modal.show();
    setTimeout(function() { if (typeof feather !== 'undefined') feather.replace(); }, 100);
});

// ──────────────── Init ────────────────
if (typeof feather !== 'undefined') feather.replace();

}());
</script>
@endsection
