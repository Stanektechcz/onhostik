@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];
@endphp

@section('title', __('panel.nav.new_order'))

@push('styles')
<style>
/* ── Plan card selection ── */
.plan-label { cursor: pointer; display: block; height: 100%; }
.plan-label input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }

/* Selected state — border on the card */
.plan-label.plan-selected .card {
    border: 2px solid rgba(var(--theme-default), 1);
    box-shadow: 0 0 0 4px rgba(var(--theme-default), .12) !important;
}

/* Visual area background (replaces product image) */
.plan-visual {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 160px;
    overflow: hidden;
    background: linear-gradient(135deg, rgba(var(--theme-default), .08) 0%, rgba(var(--theme-default), .04) 100%);
    border-bottom: 1px solid rgba(var(--light-background), 1);
}
.plan-visual .plan-icon { transition: transform .3s ease; }
.plan-label:hover .plan-visual .plan-icon { transform: scale(1.08); }

/* product-price override — use Cuba's class */
.product-price { font-size: 1.2rem; font-weight: 700; color: rgba(var(--theme-default), 1); }
.product-price .duration { font-size: .8rem; font-weight: 400; color: rgba(var(--font-color), .6); }

/* Features list */
.plan-features li { display: flex; align-items: center; gap: .35rem; font-size: 12px; color: rgba(var(--font-color), .7); padding: 2px 0; }

/* Hover overlay button — "Vybrat" */
.product-hover .btn-select-plan { white-space: nowrap; padding: 8px 16px; font-size: .85rem; }
.plan-selected .product-hover .btn-select-plan { background-color: rgba(var(--theme-default), 1) !important; color: rgba(var(--white), 1) !important; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($plans->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="package" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h5 class="f-light mt-2">Žádné dostupné tarify</h5>
                <p class="f-light f-14 mb-3">Aktuálně nejsou dostupné žádné aktivní tarify.</p>
                <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-primary btn-sm">
                    <i data-feather="arrow-left" style="width:13px;height:13px;"></i>
                    Zpět
                </a>
            </div>
        </div>
    @else

    <form method="POST" action="{{ route('panel.orders.store') }}" id="order-form">
        @csrf

        {{-- ── Krok indikátor ─────────────────────────────────────── --}}
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

        {{-- ── Cuba product-grid pattern ──────────────────────────── --}}
        @error('pricing_plan_id')
            <div class="alert alert-light-danger mb-3">{{ $message }}</div>
        @enderror

        <div class="container product-wrapper px-0">
            <div class="product-grid">

                {{-- Feature bar (product count + sort) --}}
                <div class="feature-products mb-3">
                    <div class="grid grid-cols-12 gap-3 align-items-center">
                        <div class="col-span-6 md:col-span-12 products-total">
                            <span class="f-light f-14">
                                <strong>{{ $plans->count() }}</strong> tarify jsou k dispozici
                            </span>
                        </div>
                        <div class="col-span-6 md:col-span-12 text-end md:text-start">
                            <span class="f-light f-12">Vyberte tarif kliknutím</span>
                        </div>
                    </div>
                </div>

                {{-- Product grid: 4 per row (col-span-3) --}}
                <div class="product-wrapper-grid">
                    <div class="grid grid-cols-12 card-gap">

                        @foreach($plans as $plan)
                        @php
                            $isSelected = (int) old('pricing_plan_id', $selectedPlan) === $plan->id;
                            $price  = $plan->priceFor($customer->preferred_currency);
                            $minor  = $price?->getMinorAmount()->toInt() ?? 0;
                            $whole  = intdiv($minor, 100);
                            $curr   = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
                            $type   = $plan->product?->type?->value ?? 'webhosting';
                            $icon   = match($type) {
                                'vps'  => 'cpu',
                                default => 'server',
                            };
                            // Icon color per product type
                            $iconColor = match($type) {
                                'vps'  => 'txt-secondary',
                                default => 'txt-primary',
                            };
                        @endphp

                        <div class="col-span-3 xxl:col-span-4 md:col-span-6 sm:col-span-12 box-col-4">
                            <label class="plan-label {{ $isSelected ? 'plan-selected' : '' }}"
                                   data-plan-id="{{ $plan->id }}">

                                <input type="radio"
                                       name="pricing_plan_id"
                                       value="{{ $plan->id }}"
                                       @checked($isSelected)>

                                <div class="card h-100">
                                    <div class="product-box">

                                        {{-- ── product-img (visual area) ── --}}
                                        <div class="plan-visual">
                                            {{-- Featured ribbon --}}
                                            @if($plan->is_featured)
                                                <div class="ribbon ribbon-primary">
                                                    <span>Oblíbený</span>
                                                </div>
                                            @endif

                                            {{-- Service icon --}}
                                            <div class="plan-icon text-center">
                                                <i data-feather="{{ $icon }}"
                                                   class="{{ $iconColor }}"
                                                   style="width:52px;height:52px;stroke-width:1.5;"></i>
                                                <p class="f-light f-12 mb-0 mt-1">{{ $plan->product?->name }}</p>
                                            </div>

                                            {{-- Hover overlay (Cuba product-hover) --}}
                                            <div class="product-hover">
                                                <ul>
                                                    <li>
                                                        <span class="btn btn-select-plan {{ $isSelected ? 'btn-primary' : 'btn-outline-primary' }}">
                                                            @if($isSelected)
                                                                <i data-feather="check-circle" style="width:14px;height:14px;" class="me-1"></i>Vybráno
                                                            @else
                                                                <i data-feather="shopping-cart" style="width:14px;height:14px;" class="me-1"></i>Vybrat
                                                            @endif
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        {{-- ── product-details ── --}}
                                        <div class="product-details">
                                            <h4>{{ $plan->name }}</h4>

                                            @if($plan->tagline)
                                                <p class="f-light f-12 mb-1">{{ $plan->tagline }}</p>
                                            @endif

                                            <div class="product-price">
                                                {{ number_format($whole, 0, ',', ' ') }}
                                                {{ $curr === 'CZK' ? 'Kč' : $curr }}
                                                <span class="duration">/ {{ $plan->billing_cycle->label() }}</span>
                                            </div>

                                            @if(!empty($plan->resources))
                                                <ul class="plan-features mt-2 ps-0 list-unstyled">
                                                    @foreach(array_slice($plan->resources, 0, 4) as $key => $value)
                                                        <li>
                                                            <i data-feather="check" style="width:12px;height:12px;" class="txt-success"></i>
                                                            {{ __("front.resources.$key") }}: <strong>{{ $value }}</strong>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>

                                    </div>{{-- /product-box --}}
                                </div>{{-- /card --}}
                            </label>
                        </div>{{-- /col --}}
                        @endforeach

                    </div>{{-- /grid --}}
                </div>{{-- /product-wrapper-grid --}}

            </div>{{-- /product-grid --}}
        </div>{{-- /product-wrapper --}}

        {{-- ── Doména ──────────────────────────────────────────────── --}}
        <div class="card mt-3">
            <div class="card-header card-no-border pb-0">
                <div class="header-top">
                    <h5>{{ __('panel.orders.domain_label') }}</h5>
                    <span class="badge badge-light-secondary ms-2">Volitelné</span>
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
                        @error('domain')
                            <p class="text-danger mt-1 mb-0 f-12">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-md-5">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="register_domain" id="register_domain"
                                   value="1" @checked(old('register_domain'))>
                            <label class="form-check-label" for="register_domain">
                                {{ __('panel.orders.register_domain') }}
                            </label>
                        </div>
                        @if($mockMode)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="simulate_failure" id="simulate_failure"
                                       value="1" @checked(old('simulate_failure'))>
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

        {{-- ── Submit ───────────────────────────────────────────────── --}}
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
    @endif
</div>

<script>
(function () {
    var labels = document.querySelectorAll('.plan-label');

    labels.forEach(function (label) {
        label.addEventListener('click', function () {
            // Deselect all
            labels.forEach(function (l) {
                l.classList.remove('plan-selected');
                var r = l.querySelector('input[type="radio"]');
                if (r) r.checked = false;
                var btn = l.querySelector('.btn-select-plan');
                if (btn) {
                    btn.className = btn.className.replace('btn-primary', 'btn-outline-primary');
                    btn.innerHTML = '<i data-feather="shopping-cart" style="width:14px;height:14px;" class="me-1"></i>Vybrat';
                }
            });

            // Select this
            this.classList.add('plan-selected');
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            var btn = this.querySelector('.btn-select-plan');
            if (btn) {
                btn.className = btn.className.replace('btn-outline-primary', 'btn-primary');
                btn.innerHTML = '<i data-feather="check-circle" style="width:14px;height:14px;" class="me-1"></i>Vybráno';
            }

            if (typeof feather !== 'undefined') feather.replace();
        });
    });
}());
</script>
@endsection
