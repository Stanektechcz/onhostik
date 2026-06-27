@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];

    $productTypes  = $plans->map(fn($p) => ['slug' => $p->product?->slug ?? '', 'name' => $p->product?->name ?? ''])
                           ->unique('slug')->filter(fn($t) => $t['slug'] !== '')->values();
    $prices        = $plans->map(fn($p) => $p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0);
    $priceMin      = $prices->isNotEmpty() ? (int) floor($prices->min() / 100) : 0;
    $priceMax      = $prices->isNotEmpty() ? (int) ceil($prices->max() / 100) : 9999;
    $billingCycles = $plans->map(fn($p) => ['value' => $p->billing_cycle->value, 'label' => $p->billing_cycle->label()])
                           ->unique('value')->values();

    /* Pre-build JS plan data — avoids @json + fn() Blade parse conflict */
    $jsPlanData = $plans->map(function ($p) use ($customer) {
        return [
            'id'       => $p->id,
            'name'     => trim(($p->product?->name ?? '') . ' ' . $p->name),
            'product'  => $p->product?->slug ?? '',
            'price'    => intdiv($p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0, 100),
            'currency' => $p->priceFor($customer->preferred_currency)?->getCurrency()->getCurrencyCode() ?? 'CZK',
            'billing'  => $p->billing_cycle->value,
            'resources'=> $p->resources ?? new \stdClass(),
        ];
    })->values();
@endphp

@section('title', __('panel.nav.new_order'))

@push('styles')
<style>
/* ── Plan card ── */
.plan-card-item .card { border: 2px solid transparent; transition: border .2s, box-shadow .2s, transform .15s; }
.plan-card-item:hover .card { border-color: rgba(var(--theme-default),.3); transform: translateY(-3px); box-shadow: 0 6px 20px rgba(var(--theme-default),.1); }
.plan-card-item.plan-selected .card { border-color: rgba(var(--theme-default),1) !important; box-shadow: 0 0 0 4px rgba(var(--theme-default),.15) !important; }
.plan-card-item.plan-filtered-out { display: none !important; }

/* ── Cuba product-hover ── */
.product-hover { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(var(--white),.88); opacity: 0; transition: opacity .2s ease; z-index: 5; }
.plan-card-item:hover .product-hover { opacity: 1; }
.product-hover ul { display: flex; gap: 10px; list-style: none; margin: 0; padding: 0; }
.product-hover .btn { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
    border-radius: 10px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.15);
    border: 1px solid #eee; transition: background .15s, color .15s; padding: 0; }
.product-hover .btn:hover { background: rgba(var(--theme-default),1); color: #fff; border-color: rgba(var(--theme-default),1); }

/* ── Selected badge ── */
.plan-selected-badge { display: none; position: absolute; top: 8px; left: 8px; z-index: 10;
    background: rgba(var(--theme-default),1); color: #fff; border-radius: 20px;
    padding: 2px 10px; font-size: 11px; font-weight: 700; }
.plan-card-item.plan-selected .plan-selected-badge { display: block; }

/* ── Always-visible select button ── */
.btn-select-visible { margin-top: 4px; }
.plan-card-item.plan-selected .btn-select-visible { background: rgba(var(--theme-default),1) !important;
    border-color: rgba(var(--theme-default),1) !important; color: #fff !important; }

/* ── Compare badge on compare cards ── */
.compare-badge { display: none; position: absolute; top: 8px; right: 8px; z-index: 10;
    background: #f39c12; color: #fff; border-radius: 20px; padding: 2px 8px; font-size: 11px; }
.plan-card-item.in-compare .compare-badge { display: block; }

/* ── Selected plan bar (bottom) ── */
#selected-plan-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1050;
    background: rgba(var(--theme-default),1); color: #fff; padding: 12px 24px;
    box-shadow: 0 -4px 20px rgba(0,0,0,.2); display: none; }
#selected-plan-bar.show { display: block; }

/* ── Compare bar (fixed bottom) ── */
#compare-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1049;
    background: #fff; border-top: 2px solid #f39c12; padding: 10px 24px;
    box-shadow: 0 -4px 12px rgba(0,0,0,.1); display: none; }
#compare-bar.show { display: block; }
#compare-bar.bar-above-select { bottom: 56px; }

/* ── Product icon area ── */
.plan-icon-area { height: 160px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg,rgba(var(--theme-default),.06),rgba(var(--theme-default),.01)); }
.plan-icon-area svg, .plan-icon-area i { stroke: rgba(var(--theme-default),1); }

/* Force filter sidebar to always be visible (override Cuba's collapse default) */
.filter-section .left-filter {
    visibility: visible !important;
    height: auto !important;
    overflow: visible !important;
    display: block !important;
}
</style>
@endpush

@section('content')

<form id="order-form" method="POST" action="{{ route('panel.orders.store') }}">
@csrf
<input type="hidden" name="pricing_plan_id" id="selected_plan_id" value="">
@if($mockMode)
    <input type="hidden" name="simulate_failure" value="0">
@endif

<div class="container product-wrapper">
  <div class="product-grid">

    {{-- ─── Feature toolbar ─────────────────────────────────── --}}
    <div class="feature-products">

      {{-- Row 1: View toggles + sort --}}
      <div class="grid grid-cols-12 gap-3">
        <div class="col-span-6 md:col-span-12 products-total">
          <div class="square-product-setting d-inline-block">
            <a class="icon-grid grid-layout-view" href="#" title=""><i data-feather="grid"></i></a>
          </div>
          <div class="square-product-setting d-inline-block">
            <a class="icon-grid m-0 list-layout-view" href="#" title=""><i data-feather="list"></i></a>
          </div>
          <div class="grid-options d-inline-block">
            <ul>
              <li><a class="product-2-layout-view" href="#"><span class="line-grid line-grid-1 bg-primary"></span><span class="line-grid line-grid-2 bg-primary"></span></a></li>
              <li><a class="product-3-layout-view" href="#"><span class="line-grid line-grid-3 bg-primary"></span><span class="line-grid line-grid-4 bg-primary"></span><span class="line-grid line-grid-5 bg-primary"></span></a></li>
              <li><a class="product-4-layout-view" href="#"><span class="line-grid line-grid-6 bg-primary"></span><span class="line-grid line-grid-7 bg-primary"></span><span class="line-grid line-grid-8 bg-primary"></span><span class="line-grid line-grid-9 bg-primary"></span></a></li>
            </ul>
          </div>
        </div>
        <div class="col-span-6 md:col-span-12 text-end md:text-start">
          <span class="font-semibold m-r-5">Zobrazeno <span id="visible-count">{{ $plans->count() }}</span> z {{ $plans->count() }} tarifů</span>
          <div class="inline-block dropdown-product">
            <select class="form-select" id="sort-select" aria-label="Řazení">
              <option value="default">Výchozí pořadí</option>
              <option value="price_asc">Cena (nejnižší)</option>
              <option value="price_desc">Cena (nejvyšší)</option>
              <option value="name_asc">Název (A–Z)</option>
            </select>
          </div>
        </div>
      </div>

      {{-- Row 2: Filter sidebar + Search --}}
      <div class="grid grid-cols-12 gap-3">

        {{-- Left filter: col-span-3 --}}
        <div class="col-span-3 md:col-span-6 sm:col-span-12" id="filter-panel">
          <div class="product-sidebar">
            <div class="filter-section">
              <div class="card">
                <div class="card-header">
                  <h6 class="mb-0 font-semibold">Filtry
                    <span class="pull-right"><i class="fa-solid fa-chevron-down toggle-data"></i></span>
                  </h6>
                </div>
                <div class="left-filter">
                  <div class="card-body filter-cards-view animate-chk">

                    {{-- Category --}}
                    @if($productTypes->count() > 1)
                    <div class="product-filter">
                      <h6 class="font-semibold">Kategorie</h6>
                      <div class="checkbox-animated mt-0">
                        @foreach($productTypes as $i => $pt)
                        <label class="block" for="cat-{{ $i }}">
                          <input class="checkbox_animated filter-cat" id="cat-{{ $i }}" type="checkbox"
                                 value="{{ $pt['slug'] }}" checked>
                          {{ $pt['name'] }}
                        </label>
                        @endforeach
                      </div>
                    </div>
                    @endif

                    {{-- Price range --}}
                    <div class="product-filter pb-0 product-range">
                      <h6 class="font-semibold">Cena (Kč/měs.)</h6>
                      <div class="d-flex gap-2 mt-2">
                        <input type="number" class="form-control form-control-sm" id="price-min"
                               placeholder="Od" value="{{ $priceMin }}" min="{{ $priceMin }}" max="{{ $priceMax }}">
                        <input type="number" class="form-control form-control-sm" id="price-max"
                               placeholder="Do" value="{{ $priceMax }}" min="{{ $priceMin }}" max="{{ $priceMax }}">
                      </div>
                      <button type="button" class="btn btn-primary btn-sm mt-2 w-full" id="apply-price">
                        Použít filtr
                      </button>
                    </div>

                    {{-- Billing cycle --}}
                    @if($billingCycles->count() > 1)
                    <div class="product-filter">
                      <h6 class="font-semibold">Fakturační cyklus</h6>
                      <div class="checkbox-animated mt-0">
                        @foreach($billingCycles as $i => $bc)
                        <label class="block" for="billing-{{ $i }}">
                          <input class="checkbox_animated filter-billing" id="billing-{{ $i }}" type="checkbox"
                                 value="{{ $bc['value'] }}" checked>
                          {{ $bc['label'] }}
                        </label>
                        @endforeach
                      </div>
                    </div>
                    @endif

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Search: col-span-9 --}}
        <div class="col-span-9 md:col-span-12">
          <form onsubmit="return false;">
            <div class="form-group m-0">
              <input class="form-control" type="search" id="plan-search"
                     placeholder="Hledat tarif..." title="">
              <i class="fa-solid fa-magnifying-glass"></i>
            </div>
          </form>
        </div>
      </div>
    </div>{{-- /feature-products --}}

    {{-- ─── Product grid ──────────────────────────────────────── --}}
    <div class="product-wrapper-grid">
      <div class="grid grid-cols-12 card-gap" id="plans-grid">

        @foreach($plans as $plan)
        @php
            $price    = $plan->priceFor($customer->preferred_currency);
            $priceVal = $price ? intdiv($price->getMinorAmount()->toInt(), 100) : 0;
            $currency = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
            $mid      = 'modal-plan-' . $plan->id;
            $slug     = $plan->product?->slug ?? 'unknown';
            $prodName = $plan->product?->name ?? '';
            $fullName = trim($prodName . ' ' . $plan->name);
            $res      = (array)($plan->resources ?? []);
            $icon     = match($slug) {
                'webhosting'      => 'globe',
                'vps'             => 'server',
                'mailhosting'     => 'mail',
                'managed-hosting' => 'shield',
                'gamehosting'     => 'zap',
                'domeny'          => 'at-sign',
                default           => 'package',
            };
            $isPreselected = ($selectedPlan === $plan->id);
        @endphp
        <div class="col-span-3 xxl:col-span-4 md:col-span-6 sm:col-span-12 box-col-4 plan-card-item{{ $isPreselected ? ' plan-selected' : '' }}"
             data-plan-id="{{ $plan->id }}"
             data-product="{{ $slug }}"
             data-billing="{{ $plan->billing_cycle->value }}"
             data-price="{{ $priceVal }}"
             data-name="{{ strtolower($fullName) }}"
             id="plan-item-{{ $plan->id }}">
          <div class="card">
            <div class="product-box">

              {{-- Image / Icon area --}}
              <div class="product-img" style="position:relative;">
                <span class="plan-selected-badge">✓ Vybráno</span>
                <span class="compare-badge">⇌ Porovnat</span>
                <div class="plan-icon-area">
                  <i data-feather="{{ $icon }}" style="width:72px;height:72px;stroke-width:1;"></i>
                </div>

                {{-- Cuba hover overlay --}}
                <div class="product-hover">
                  <ul>
                    <li>
                      <button type="button" class="btn" title="Vybrat tarif"
                              onclick="selectPlan({{ $plan->id }}, {{ json_encode($fullName) }}, {{ $priceVal }}, '{{ $currency }}')">
                        <i class="fa-solid fa-cart-shopping"></i>
                      </button>
                    </li>
                    <li>
                      <a class="btn" href="#" data-bs-toggle="modal" data-bs-target="#{{ $mid }}" title="Náhled">
                        <i class="fa-solid fa-eye"></i>
                      </a>
                    </li>
                    <li>
                      <button type="button" class="btn" title="Porovnat"
                              onclick="toggleCompare({{ $plan->id }}, {{ json_encode($fullName) }}, {{ $priceVal }}, '{{ $currency }}')">
                        <i class="fa-solid fa-code-compare fa-rotate-90"></i>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>

              {{-- Quick-view modal --}}
              <div class="modal fade" id="{{ $mid }}" tabindex="-1" aria-labelledby="{{ $mid }}-label" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <div class="product-box w-100">
                        <div class="grid grid-cols-12 gap-4">
                          <div class="col-span-5 lg:col-span-12">
                            <div class="plan-icon-area" style="border-radius:8px;height:220px;">
                              <i data-feather="{{ $icon }}" style="width:90px;height:90px;stroke-width:1;"></i>
                            </div>
                          </div>
                          <div class="col-span-7 lg:col-span-12 text-start">
                            <div class="product-details">
                              <a href="#"><h4>{{ $fullName }}</h4></a>
                              <div class="product-price">
                                {{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}
                                <small class="f-light f-14"> / {{ $plan->billing_cycle->label() }}</small>
                              </div>
                              <div class="product-view mt-2">
                                <h6 class="font-medium">Parametry tarifu</h6>
                                @if(!empty($res))
                                  <table class="table table-sm table-borderless mb-2">
                                    @foreach($res as $k => $v)
                                    <tr><td class="f-light f-12 py-1">{{ ucfirst($k) }}</td><td class="f-w-600 f-12 py-1">{{ $v }}</td></tr>
                                    @endforeach
                                  </table>
                                @else
                                  <p class="f-light f-12 mb-2">Základní hosting tarif.</p>
                                @endif
                              </div>
                              <div class="product-size">
                                <ul class="common-flex">
                                  <li><span class="badge badge-light-primary">{{ $prodName }}</span></li>
                                  <li><span class="badge badge-light-secondary">{{ $plan->billing_cycle->label() }}</span></li>
                                </ul>
                              </div>
                              <div class="mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-primary text-white"
                                        onclick="selectPlan({{ $plan->id }}, {{ json_encode($fullName) }}, {{ $priceVal }}, '{{ $currency }}');document.querySelector('#{{ $mid }} [data-bs-dismiss=modal]').click();">
                                  <i class="fa-solid fa-cart-shopping me-1"></i>Vybrat tarif
                                </button>
                                <a class="btn btn-primary text-white ms-2" href="{{ route('panel.orders.create', ['plan' => $plan->id]) }}">Objednat</a>
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

              {{-- Product details (visible below icon) --}}
              <div class="product-details">
                <div class="rating">
                  <span class="badge badge-light-primary f-11">{{ $prodName }}</span>
                  <span class="badge badge-light-secondary f-11">{{ $plan->billing_cycle->label() }}</span>
                </div>
                <a href="#" data-bs-toggle="modal" data-bs-target="#{{ $mid }}">
                  <h4>{{ $plan->name }}</h4>
                </a>
                <p>
                  @if(!empty($res))
                    @foreach(array_slice($res, 0, 2) as $k => $v)
                      <strong>{{ $v }}</strong>{{ !$loop->last ? ' · ' : '' }}
                    @endforeach
                  @else
                    Hosting tarif
                  @endif
                </p>
                <div class="product-price">
                  {{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}
                </div>
              </div>

              {{-- Always-visible select button --}}
              <div class="px-3 pb-3">
                <button type="button"
                        class="btn btn-primary w-full btn-select-visible"
                        id="btn-select-{{ $plan->id }}"
                        onclick="selectPlan({{ $plan->id }}, {{ json_encode($fullName) }}, {{ $priceVal }}, '{{ $currency }}')">
                  @if($isPreselected) ✓ Vybráno @else Vybrat tarif @endif
                </button>
              </div>

            </div>{{-- /product-box --}}
          </div>{{-- /card --}}
        </div>{{-- /plan-card-item --}}
        @endforeach

        {{-- No results placeholder --}}
        <div class="col-span-12 text-center py-5" id="no-results" style="display:none;">
          <i class="fa-solid fa-magnifying-glass fa-3x text-muted mb-3 d-block"></i>
          <h5 class="f-light">Žádné tarify neodpovídají filtru</h5>
          <p class="f-light f-12">Zkuste upravit kritéria filtru nebo hledání.</p>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetFilters()">Resetovat filtry</button>
        </div>

      </div>{{-- /plans-grid --}}
    </div>{{-- /product-wrapper-grid --}}

  </div>{{-- /product-grid --}}
</div>{{-- /product-wrapper --}}
</form>

{{-- ─── Compare bar ────────────────────────────────────────────── --}}
<div id="compare-bar">
  <div class="d-flex align-items-center justify-content-between">
    <div class="d-flex gap-3 align-items-center flex-wrap">
      <strong class="f-14">Porovnat:</strong>
      <span id="compare-names" class="f-light f-13"></span>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-warning btn-sm text-white" data-bs-toggle="modal" data-bs-target="#compare-modal" id="btn-open-compare">
        <i class="fa-solid fa-code-compare fa-rotate-90 me-1"></i>Porovnat
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearCompare()">Vymazat</button>
    </div>
  </div>
</div>

{{-- ─── Selected plan sticky bar ───────────────────────────────── --}}
<div id="selected-plan-bar">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <span class="f-light f-12 me-2">Vybraný tarif:</span>
      <strong id="bar-plan-name" class="f-16"></strong>
      <span id="bar-plan-price" class="ms-3 badge badge-light-primary f-14"></span>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);" onclick="clearSelection()">
        Zrušit výběr
      </button>
      <button type="button" class="btn btn-success btn-sm fw-bold" id="btn-submit-order" onclick="goToCheckout()" disabled>
        Pokračovat k objednávce →
      </button>
    </div>
  </div>
</div>

{{-- ─── Compare modal ──────────────────────────────────────────── --}}
<div class="modal fade" id="compare-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Porovnání tarifů</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body overflow-x-auto custom-scrollbar">
        <table class="table table-striped table-bordered" id="compare-table">
          <thead id="compare-thead"></thead>
          <tbody id="compare-tbody"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavřít</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function() {
    /* ── Plan data from PHP ── */
    var planData = {!! json_encode($jsPlanData) !!};
    var planMap  = {};
    planData.forEach(function(p) { planMap[p.id] = p; });

    /* ── State ── */
    var selectedPlanId  = {{ $selectedPlan ? $selectedPlan : 'null' }};
    var compareSet      = [];
    var activeCats      = [];
    var activeBillings  = [];
    var activePriceMin  = {{ $priceMin }};
    var activePriceMax  = {{ $priceMax }};

    /* ── Init: collect checked filters ── */
    document.querySelectorAll('.filter-cat').forEach(function(cb) { activeCats.push(cb.value); });
    document.querySelectorAll('.filter-billing').forEach(function(cb) { activeBillings.push(cb.value); });

    /* ── Plan selection ── */
    window.selectPlan = function(id, name, price, currency) {
        /* Deselect previous */
        document.querySelectorAll('.plan-card-item.plan-selected').forEach(function(el) {
            el.classList.remove('plan-selected');
            var btn = el.querySelector('.btn-select-visible');
            if (btn) btn.textContent = 'Vybrat tarif';
        });

        selectedPlanId = id;
        document.getElementById('selected_plan_id').value = id;

        var card = document.getElementById('plan-item-' + id);
        if (card) {
            card.classList.add('plan-selected');
            var btn = card.querySelector('.btn-select-visible');
            if (btn) btn.textContent = '✓ Vybráno';
        }

        /* Sticky bar */
        var bar = document.getElementById('selected-plan-bar');
        document.getElementById('bar-plan-name').textContent = name;
        document.getElementById('bar-plan-price').textContent = price.toLocaleString('cs-CZ') + ' ' + currency;
        bar.classList.add('show');
        document.getElementById('btn-submit-order').disabled = false;

        /* Push compare bar above if visible */
        var cb = document.getElementById('compare-bar');
        if (cb.classList.contains('show')) cb.classList.add('bar-above-select');
    };

    window.clearSelection = function() {
        selectedPlanId = null;
        document.getElementById('selected_plan_id').value = '';
        document.querySelectorAll('.plan-card-item.plan-selected').forEach(function(el) {
            el.classList.remove('plan-selected');
            var btn = el.querySelector('.btn-select-visible');
            if (btn) btn.textContent = 'Vybrat tarif';
        });
        var bar = document.getElementById('selected-plan-bar');
        bar.classList.remove('show');
        document.getElementById('btn-submit-order').disabled = true;
        document.getElementById('compare-bar').classList.remove('bar-above-select');
    };

    /* ── Compare ── */
    window.toggleCompare = function(id, name, price, currency) {
        var idx = compareSet.findIndex(function(c) { return c.id === id; });
        if (idx >= 0) {
            compareSet.splice(idx, 1);
            document.getElementById('plan-item-' + id).classList.remove('in-compare');
        } else {
            if (compareSet.length >= 3) {
                alert('Lze porovnat nejvýše 3 tarify.');
                return;
            }
            compareSet.push({ id: id, name: name, price: price, currency: currency });
            document.getElementById('plan-item-' + id).classList.add('in-compare');
        }
        updateCompareBar();
    };

    window.clearCompare = function() {
        compareSet.forEach(function(c) {
            var el = document.getElementById('plan-item-' + c.id);
            if (el) el.classList.remove('in-compare');
        });
        compareSet = [];
        updateCompareBar();
    };

    function updateCompareBar() {
        var bar = document.getElementById('compare-bar');
        if (compareSet.length < 2) {
            bar.classList.remove('show', 'bar-above-select');
            return;
        }
        bar.classList.add('show');
        if (document.getElementById('selected-plan-bar').classList.contains('show')) {
            bar.classList.add('bar-above-select');
        }
        var names = compareSet.map(function(c) { return c.name; }).join(' vs. ');
        document.getElementById('compare-names').textContent = names;
        buildCompareTable();
    }

    window.openCompareModal = function() { buildCompareTable(); };

    function buildCompareTable() {
        if (compareSet.length < 2) return;

        /* Collect all resource keys */
        var allKeys = ['price'];
        compareSet.forEach(function(c) {
            var p = planMap[c.id];
            if (p && p.resources) {
                Object.keys(p.resources).forEach(function(k) {
                    if (allKeys.indexOf(k) < 0) allKeys.push(k);
                });
            }
        });

        var thead = '<tr><th>Parametr</th>' +
            compareSet.map(function(c) {
                return '<th>' + c.name + '<br><small class="f-light">' + c.price.toLocaleString('cs-CZ') + ' ' + c.currency + '</small></th>';
            }).join('') + '</tr>';

        var rows = allKeys.map(function(key) {
            var cells = compareSet.map(function(c) {
                var p = planMap[c.id];
                if (key === 'price') return '<td><strong>' + c.price.toLocaleString('cs-CZ') + ' ' + c.currency + '</strong></td>';
                var val = (p && p.resources && p.resources[key] !== undefined) ? p.resources[key] : '—';
                return '<td>' + val + '</td>';
            }).join('');
            return '<tr><td class="f-w-600">' + (key === 'price' ? 'Cena/měs.' : ucfirst(key)) + '</td>' + cells + '</tr>';
        }).join('');

        var actBtns = compareSet.map(function(c) {
            return '<td><button type="button" class="btn btn-primary btn-sm text-white" onclick="selectPlan(' + c.id + ', ' + JSON.stringify(c.name) + ', ' + c.price + ', \'' + c.currency + '\')">Vybrat</button></td>';
        }).join('');

        document.getElementById('compare-thead').innerHTML = thead;
        document.getElementById('compare-tbody').innerHTML = rows + '<tr><td>Vybrat</td>' + actBtns + '</tr>';
    }

    function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

    /* ── Filtering ── */
    function applyFilters() {
        var search  = document.getElementById('plan-search').value.toLowerCase().trim();
        var pMin    = activePriceMin;
        var pMax    = activePriceMax;
        var visible = 0;

        document.querySelectorAll('.plan-card-item').forEach(function(card) {
            var cat     = card.dataset.product;
            var billing = card.dataset.billing;
            var price   = parseInt(card.dataset.price, 10);
            var name    = card.dataset.name;

            var catOk     = activeCats.length === 0 || activeCats.indexOf(cat) >= 0;
            var billingOk = activeBillings.length === 0 || activeBillings.indexOf(billing) >= 0;
            var priceOk   = price >= pMin && price <= pMax;
            var searchOk  = !search || name.indexOf(search) >= 0;

            if (catOk && billingOk && priceOk && searchOk) {
                card.classList.remove('plan-filtered-out');
                visible++;
            } else {
                card.classList.add('plan-filtered-out');
            }
        });

        document.getElementById('visible-count').textContent = visible;
        document.getElementById('no-results').style.display = (visible === 0) ? '' : 'none';
    }

    /* ── Sort ── */
    function applySort(sortVal) {
        var grid  = document.getElementById('plans-grid');
        var cards = Array.from(grid.querySelectorAll('.plan-card-item'));

        cards.sort(function(a, b) {
            if (sortVal === 'price_asc')  return parseInt(a.dataset.price, 10) - parseInt(b.dataset.price, 10);
            if (sortVal === 'price_desc') return parseInt(b.dataset.price, 10) - parseInt(a.dataset.price, 10);
            if (sortVal === 'name_asc')   return a.dataset.name.localeCompare(b.dataset.name);
            return 0; /* default — keep server order */
        });

        /* Re-insert sorted (no-results stays last) */
        var noRes = document.getElementById('no-results').parentElement;
        cards.forEach(function(c) { grid.appendChild(c); });
    }

    /* ── Event listeners ── */
    document.querySelectorAll('.filter-cat').forEach(function(cb) {
        cb.addEventListener('change', function() {
            activeCats = Array.from(document.querySelectorAll('.filter-cat:checked')).map(function(el) { return el.value; });
            applyFilters();
        });
    });

    document.querySelectorAll('.filter-billing').forEach(function(cb) {
        cb.addEventListener('change', function() {
            activeBillings = Array.from(document.querySelectorAll('.filter-billing:checked')).map(function(el) { return el.value; });
            applyFilters();
        });
    });

    document.getElementById('apply-price').addEventListener('click', function() {
        activePriceMin = parseInt(document.getElementById('price-min').value, 10) || 0;
        activePriceMax = parseInt(document.getElementById('price-max').value, 10) || 999999;
        applyFilters();
    });

    document.getElementById('plan-search').addEventListener('input', applyFilters);

    document.getElementById('sort-select').addEventListener('change', function() {
        applySort(this.value);
        applyFilters();
    });

    /* ── Reset ── */
    window.resetFilters = function() {
        document.querySelectorAll('.filter-cat, .filter-billing').forEach(function(cb) { cb.checked = true; });
        activeCats = Array.from(document.querySelectorAll('.filter-cat')).map(function(el) { return el.value; });
        activeBillings = Array.from(document.querySelectorAll('.filter-billing')).map(function(el) { return el.value; });
        activePriceMin = {{ $priceMin }};
        activePriceMax = {{ $priceMax }};
        document.getElementById('price-min').value = {{ $priceMin }};
        document.getElementById('price-max').value = {{ $priceMax }};
        document.getElementById('plan-search').value = '';
        document.getElementById('sort-select').value = 'default';
        applyFilters();
    };

    /* ── Checkout redirect ── */
    window.goToCheckout = function() {
        if (!selectedPlanId) return;
        window.location.href = '{{ route('panel.checkout.index') }}?plan=' + selectedPlanId;
    };

    /* ── Init preselected plan ── */
    if (selectedPlanId) {
        var p = planMap[selectedPlanId];
        if (p) selectPlan(p.id, p.name, p.price, p.currency);
    }

    /* ── Init feather on modal open ── */
    document.addEventListener('shown.bs.modal', function() {
        if (typeof feather !== 'undefined') feather.replace();
    });
})();
</script>
@endpush

@endsection
