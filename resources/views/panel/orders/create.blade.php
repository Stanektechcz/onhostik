@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];

    // Filter facets, built from the actual catalogue.
    $productTypes = $plans->map(fn ($p) => ['slug' => $p->product?->slug ?? '', 'name' => $p->product?->name ?? ''])
                          ->unique('slug')->filter(fn ($t) => $t['slug'] !== '')->values();
    $prices       = $plans->map(fn ($p) => $p->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0);
    $priceMin     = $prices->isNotEmpty() ? (int) floor($prices->min() / 100) : 0;
    $priceMax     = $prices->isNotEmpty() ? (int) ceil($prices->max() / 100) : 9999;

    $iconFor = fn (string $slug) => match ($slug) {
        'webhosting'      => 'globe',
        'vps'             => 'server',
        'mailhosting'     => 'mail',
        'managed-hosting' => 'shield',
        'gamehosting'     => 'zap',
        'domeny'          => 'at-sign',
        default           => 'package',
    };
@endphp

@section('title', __('panel.nav.new_order'))

@section('content')
<div class="container-fluid product-wrapper">

    {{-- ── Toolbar: search, sort, compare + cart access ────────────── --}}
    <x-panel.card>
        <div class="grid grid-cols-12 gap-3 items-center">
            <div class="col-span-5 md:col-span-12">
                <div class="input-group">
                    <span class="input-group-text bg-transparent"><i data-feather="search"></i></span>
                    <input type="search" id="plan-search" class="form-control" placeholder="Hledat tarif…" aria-label="Hledat tarif">
                </div>
            </div>
            <div class="col-span-3 md:col-span-6">
                <select class="form-select" id="sort-select" aria-label="Řazení">
                    <option value="default">Výchozí pořadí</option>
                    <option value="price_asc">Cena (nejnižší)</option>
                    <option value="price_desc">Cena (nejvyšší)</option>
                    <option value="name_asc">Název (A–Z)</option>
                </select>
            </div>
            <div class="col-span-4 md:col-span-6 text-end md:text-start flex items-center justify-end gap-2">
                <span class="f-light f-12"><span id="visible-count">{{ $plans->count() }}</span> tarifů</span>
                <a href="{{ route('panel.cart.index') }}" class="btn btn-outline-primary btn-sm" id="cart-link">
                    <i data-feather="shopping-bag"></i>
                    Košík <span class="badge badge-primary" id="cart-count">{{ $cartCount }}</span>
                </a>
            </div>
        </div>
    </x-panel.card>

    <div class="grid grid-cols-12 card-gap">

        {{-- ── Filters ──────────────────────────────────────────────── --}}
        <div class="col-span-3 lg:col-span-12">
            <x-panel.card title="Filtry">
                @if($productTypes->count() > 1)
                    <div class="mb-4">
                        <h6 class="f-w-600 f-14 mb-2">Kategorie</h6>
                        <div class="checkbox-animated">
                            @foreach($productTypes as $i => $pt)
                                <label class="d-block mb-1" for="cat-{{ $i }}">
                                    <input class="checkbox_animated filter-cat" id="cat-{{ $i }}" type="checkbox" value="{{ $pt['slug'] }}" checked>
                                    {{ $pt['name'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mb-2">
                    <h6 class="f-w-600 f-14 mb-2">Cena ({{ $customer->preferred_currency->value }} / měs.)</h6>
                    <div class="flex gap-2">
                        <input type="number" class="form-control form-control-sm" id="price-min" placeholder="Od"
                               value="{{ $priceMin }}" min="{{ $priceMin }}" max="{{ $priceMax }}" aria-label="Cena od">
                        <input type="number" class="form-control form-control-sm" id="price-max" placeholder="Do"
                               value="{{ $priceMax }}" min="{{ $priceMin }}" max="{{ $priceMax }}" aria-label="Cena do">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm w-full mt-2" id="apply-price">Použít filtr</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-full mt-2" id="reset-filters">Zrušit filtry</button>
                </div>
            </x-panel.card>
        </div>

        {{-- ── Plan grid ────────────────────────────────────────────── --}}
        <div class="col-span-9 lg:col-span-12">
            <div class="grid grid-cols-12 card-gap" id="plans-grid">
                @foreach($plans as $plan)
                    @php
                        $price     = $plan->priceFor($customer->preferred_currency);
                        $priceVal  = $price ? intdiv($price->getMinorAmount()->toInt(), 100) : 0;
                        $slug      = $plan->product?->slug ?? 'unknown';
                        $prodName  = $plan->product?->name ?? '';
                        $fullName  = trim($prodName . ' ' . $plan->name);
                        $res       = (array) ($plan->resources ?? []);
                        $inCart    = in_array($plan->id, $cartPlanIds, true);
                    @endphp
                    <div class="col-span-4 xl:col-span-6 md:col-span-12 plan-card"
                         data-plan-id="{{ $plan->id }}"
                         data-product="{{ $slug }}"
                         data-price="{{ $priceVal }}"
                         data-name="{{ strtolower($fullName) }}">
                        <x-panel.card class="h-100 plan-card-inner">
                            {{-- Header: icon + product badge --}}
                            <div class="flex items-center justify-between mb-3">
                                <div class="plan-icon">
                                    <i data-feather="{{ $iconFor($slug) }}"></i>
                                </div>
                                <span class="badge badge-light-primary">{{ $prodName }}</span>
                            </div>

                            <h5 class="mb-1">{{ $plan->name }}</h5>
                            @if($plan->tagline)
                                <p class="f-light f-12 mb-2">{{ $plan->tagline }}</p>
                            @endif

                            <div class="plan-price mb-3">
                                <span class="plan-price-amount">{{ \App\Domains\Shared\Support\MoneyFormatter::format($price) }}</span>
                                <span class="f-light f-12">/ {{ $plan->billing_cycle->label() }}</span>
                            </div>

                            {{-- Parameters — human labels + formatted values --}}
                            @if(!empty($res))
                                <ul class="plan-params list-unstyled mb-3">
                                    @foreach($res as $key => $value)
                                        <li>
                                            <i data-feather="check" class="plan-param-check"></i>
                                            <span class="f-light">{{ __("front.resources.$key") }}</span>
                                            <strong>{{ \App\Domains\Shared\Support\ResourceFormatter::format($key, $value) }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="f-light f-12 mb-3">Základní hostingový tarif.</p>
                            @endif

                            {{-- Actions — two independent, valid forms/links --}}
                            <div class="plan-actions mt-auto">
                                <a href="{{ route('panel.checkout.index', ['plan' => $plan->id]) }}" class="btn btn-primary w-full text-white">
                                    <i data-feather="shopping-cart"></i> Objednat
                                </a>
                                <form method="POST" action="{{ route('panel.cart.add', $plan->id) }}" class="add-to-cart-form">
                                    @csrf
                                    <button type="submit" class="btn {{ $inCart ? 'btn-success' : 'btn-outline-primary' }} w-full">
                                        <i data-feather="{{ $inCart ? 'check' : 'shopping-bag' }}"></i>
                                        {{ $inCart ? 'V košíku' : 'Do košíku' }}
                                    </button>
                                </form>
                                <label class="compare-check d-block">
                                    <input type="checkbox" class="compare-toggle" value="{{ $plan->id }}"
                                           data-name="{{ $fullName }}">
                                    Přidat k porovnání
                                </label>
                            </div>
                        </x-panel.card>
                    </div>
                @endforeach

                <div class="col-span-12 text-center py-5" id="no-results" hidden>
                    <i data-feather="search" class="empty-state-icon mb-3"></i>
                    <h6 class="f-light">Žádné tarify neodpovídají filtru</h6>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="reset-filters-2">Resetovat filtry</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Compare bar (fixed, Cuba card) ───────────────────────────── --}}
    <div id="compare-bar" class="compare-bar" hidden>
        <div class="flex items-center gap-3">
            <span class="f-w-600"><span id="compare-count">0</span> tarifů k porovnání</span>
            <button type="button" class="btn btn-primary btn-sm" id="compare-open" disabled>Porovnat</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="compare-clear">Zrušit</button>
        </div>
    </div>

    {{-- ── Compare modal (Cuba modal + table) ───────────────────────── --}}
    <div class="modal fade" id="compare-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Porovnání tarifů</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="table table-bordered" id="compare-table"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    var grid  = document.getElementById('plans-grid');
    var cards = [].slice.call(grid.querySelectorAll('.plan-card'));

    // ── Add to cart via fetch, so the count updates without a full reload ──
    grid.querySelectorAll('.add-to-cart-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: new FormData(form),
            }).then(function () {
                var btn = form.querySelector('button');
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-success');
                btn.innerHTML = '<i data-feather="check"></i> V košíku';
                var counter = document.getElementById('cart-count');
                counter.textContent = parseInt(counter.textContent || '0', 10) + 1;
                if (window.feather) feather.replace();
            }).catch(function () { form.submit(); });
        });
    });

    // ── Search + category + price filters ──────────────────────────────
    var search   = document.getElementById('plan-search');
    var catBoxes = [].slice.call(document.querySelectorAll('.filter-cat'));
    var priceMin = document.getElementById('price-min');
    var priceMax = document.getElementById('price-max');

    function applyFilters() {
        var q    = (search.value || '').trim().toLowerCase();
        var cats = catBoxes.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        var min  = parseInt(priceMin.value || '0', 10);
        var max  = parseInt(priceMax.value || '999999', 10);
        var shown = 0;

        cards.forEach(function (card) {
            var okText = !q || card.dataset.name.indexOf(q) !== -1;
            var okCat  = !catBoxes.length || cats.indexOf(card.dataset.product) !== -1;
            var price  = parseInt(card.dataset.price, 10);
            var okPrice = price >= min && price <= max;
            var visible = okText && okCat && okPrice;
            card.hidden = !visible;
            if (visible) shown++;
        });

        document.getElementById('visible-count').textContent = shown;
        document.getElementById('no-results').hidden = shown !== 0;
    }

    search.addEventListener('input', applyFilters);
    catBoxes.forEach(function (c) { c.addEventListener('change', applyFilters); });
    document.getElementById('apply-price').addEventListener('click', applyFilters);

    function resetFilters() {
        search.value = '';
        catBoxes.forEach(function (c) { c.checked = true; });
        priceMin.value = priceMin.min;
        priceMax.value = priceMax.max;
        applyFilters();
    }
    document.getElementById('reset-filters').addEventListener('click', resetFilters);
    var reset2 = document.getElementById('reset-filters-2');
    if (reset2) reset2.addEventListener('click', resetFilters);

    // ── Sort ───────────────────────────────────────────────────────────
    document.getElementById('sort-select').addEventListener('change', function () {
        var mode = this.value;
        var sorted = cards.slice().sort(function (a, b) {
            if (mode === 'price_asc')  return a.dataset.price - b.dataset.price;
            if (mode === 'price_desc') return b.dataset.price - a.dataset.price;
            if (mode === 'name_asc')   return a.dataset.name.localeCompare(b.dataset.name);
            return 0;
        });
        sorted.forEach(function (c) { grid.appendChild(c); });
        grid.appendChild(document.getElementById('no-results'));
    });

    // ── Compare ────────────────────────────────────────────────────────
    var compare = {}; // planId -> name
    var bar   = document.getElementById('compare-bar');
    var count = document.getElementById('compare-count');
    var openBtn = document.getElementById('compare-open');

    function refreshCompareBar() {
        var ids = Object.keys(compare);
        count.textContent = ids.length;
        bar.hidden = ids.length === 0;
        openBtn.disabled = ids.length < 2;
    }

    grid.querySelectorAll('.compare-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked) {
                if (Object.keys(compare).length >= 4) { cb.checked = false; return; }
                compare[cb.value] = cb.dataset.name;
            } else {
                delete compare[cb.value];
            }
            refreshCompareBar();
        });
    });

    document.getElementById('compare-clear').addEventListener('click', function () {
        compare = {};
        grid.querySelectorAll('.compare-toggle').forEach(function (cb) { cb.checked = false; });
        refreshCompareBar();
    });

    openBtn.addEventListener('click', function () {
        var ids = Object.keys(compare);
        // Collect the union of parameter labels across the compared cards.
        var labels = [];
        var data = ids.map(function (id) {
            var card = grid.querySelector('.plan-card[data-plan-id="' + id + '"]');
            var params = {};
            card.querySelectorAll('.plan-params li').forEach(function (li) {
                var k = li.querySelector('.f-light').textContent.trim();
                var v = li.querySelector('strong').textContent.trim();
                params[k] = v;
                if (labels.indexOf(k) === -1) labels.push(k);
            });
            return {
                name:  compare[id],
                price: card.querySelector('.plan-price-amount').textContent.trim(),
                cycle: card.querySelector('.plan-price .f-light').textContent.trim(),
                params: params,
            };
        });

        var thead = '<thead><tr><th>Parametr</th>' + data.map(function (d) {
            return '<th>' + d.name + '</th>';
        }).join('') + '</tr></thead>';

        var rows = '<tr><td class="f-w-600">Cena</td>' + data.map(function (d) {
            return '<td class="f-w-600 txt-primary">' + d.price + ' ' + d.cycle + '</td>';
        }).join('') + '</tr>';

        labels.forEach(function (label) {
            rows += '<tr><td class="f-light">' + label + '</td>' + data.map(function (d) {
                return '<td>' + (d.params[label] || '—') + '</td>';
            }).join('') + '</tr>';
        });

        document.getElementById('compare-table').innerHTML = thead + '<tbody>' + rows + '</tbody>';
        new bootstrap.Modal(document.getElementById('compare-modal')).show();
    });

    if (window.feather) feather.replace();
});
</script>
@endpush
