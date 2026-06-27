@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];
@endphp

@section('title', __('panel.nav.new_order'))

@push('styles')
<style>
/* Plan card selection highlight */
.plan-card-wrapper { cursor: pointer; height: 100%; }
.plan-card-wrapper input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.plan-card-wrapper .pricingtable {
    border: 2px solid transparent;
    transition: border-color .2s, box-shadow .2s;
}
.plan-card-wrapper:hover .pricingtable {
    border-color: rgba(var(--theme-default), .4);
}
.plan-card-wrapper.selected .pricingtable {
    border-color: rgba(var(--theme-default), 1);
    box-shadow: 0 0 0 4px rgba(var(--theme-default), .12) !important;
}
.plan-card-wrapper .select-btn { transition: all .15s; }
/* Override pricingtable center for feature list */
.pricingtable .pricing-content { text-align: left; padding-left: 1.5rem; }
.pricingtable .pricing-content li { display: flex; align-items: center; gap: .4rem; }
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
                    Zpět na objednávky
                </a>
            </div>
        </div>
    @else

    <form method="POST" action="{{ route('panel.orders.store') }}" id="order-form">
        @csrf

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
                        <span class="f-light">Doména (volitelně)</span>
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

        {{-- Plan selection --}}
        <div class="card">
            <div class="card-header card-no-border pb-0">
                <div class="header-top">
                    <h5>{{ __('panel.orders.choose_plan') }}</h5>
                </div>
            </div>
            <div class="card-body">

                @error('pricing_plan_id')
                    <div class="alert alert-light-danger mb-3 f-12">{{ $message }}</div>
                @enderror

                {{-- 4 per row: row-cols-xl-4, 2 per row on md, 1 on mobile --}}
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
                    @foreach($plans as $plan)
                    @php
                        $isSelected = (int) old('pricing_plan_id', $selectedPlan) === $plan->id;
                        $minor = $plan->priceFor($customer->preferred_currency)?->getMinorAmount()->toInt() ?? 0;
                        $whole = intdiv($minor, 100);
                        $curr  = $plan->priceFor($customer->preferred_currency)?->getCurrency()->getCurrencyCode() ?? 'CZK';
                        $icon  = match($plan->product?->type?->value ?? '') {
                            'vps'  => 'cpu',
                            'vps'  => 'cpu',
                            default => 'server',
                        };
                    @endphp
                    <div class="col">
                        <label class="plan-card-wrapper d-block h-100 {{ $isSelected ? 'selected' : '' }}"
                               data-plan-id="{{ $plan->id }}">
                            <input type="radio" name="pricing_plan_id"
                                   value="{{ $plan->id }}"
                                   @checked($isSelected)>

                            <div class="pricingtable h-100">
                                {{-- Featured ribbon --}}
                                @if($plan->is_featured)
                                    <div class="ribbon ribbon-primary">
                                        <span>Oblíbený</span>
                                    </div>
                                @endif

                                {{-- Header --}}
                                <div class="pricingtable-header">
                                    <div class="mb-2">
                                        <i data-feather="{{ $icon }}" class="txt-primary" style="width:36px;height:36px;"></i>
                                    </div>
                                    <h4 class="title mergecolor mb-1">{{ $plan->name }}</h4>
                                    <p class="f-light f-12 mb-0">{{ $plan->product?->name }}</p>
                                    @if($plan->tagline)
                                        <p class="f-light f-12 mb-0">{{ $plan->tagline }}</p>
                                    @endif
                                </div>

                                {{-- Price --}}
                                <div class="price-value">
                                    <span class="currency">{{ $curr === 'CZK' ? '' : '$' }}</span>
                                    <span class="amount">{{ number_format($whole, 0, ',', ' ') }}</span>
                                    <span class="duration">&nbsp;{{ $curr === 'CZK' ? 'Kč' : $curr }}/{{ $plan->billing_cycle->label() }}</span>
                                </div>

                                {{-- Features --}}
                                <ul class="pricing-content">
                                    @forelse($plan->resources ?? [] as $key => $value)
                                        <li>
                                            <i data-feather="check" style="width:13px;height:13px;" class="txt-success"></i>
                                            {{ __("front.resources.$key") }}: <strong>{{ $value }}</strong>
                                        </li>
                                    @empty
                                        <li>
                                            <i data-feather="check" style="width:13px;height:13px;" class="txt-success"></i>
                                            Standardní hosting
                                        </li>
                                    @endforelse
                                </ul>

                                {{-- CTA --}}
                                <div class="pricingtable-signup">
                                    <span class="btn select-btn {{ $isSelected ? 'btn-primary' : 'btn-outline-primary' }} btn-lg">
                                        @if($isSelected)
                                            <i data-feather="check-circle" style="width:16px;height:16px;"></i>
                                            Vybráno
                                        @else
                                            <i data-feather="shopping-cart" style="width:16px;height:16px;"></i>
                                            Vybrat
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </label>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>

        {{-- Domain --}}
        <div class="card">
            <div class="card-header card-no-border pb-0">
                <div class="header-top">
                    <h5>{{ __('panel.orders.domain_label') }}</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label f-12 f-light">
                            Doménové jméno
                            <span class="badge badge-light-secondary ms-1">volitelné</span>
                        </label>
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

        {{-- Submit --}}
        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
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
    var wrappers = document.querySelectorAll('.plan-card-wrapper');

    wrappers.forEach(function (wrapper) {
        wrapper.addEventListener('click', function (e) {
            // Deselect all
            wrappers.forEach(function (w) {
                w.classList.remove('selected');
                var r = w.querySelector('input[type="radio"]');
                if (r) r.checked = false;
                var btn = w.querySelector('.select-btn');
                if (btn) {
                    btn.className = btn.className.replace('btn-primary', 'btn-outline-primary');
                    btn.innerHTML = '<i data-feather="shopping-cart" style="width:16px;height:16px;"></i> Vybrat';
                }
            });

            // Select this
            this.classList.add('selected');
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            var btn = this.querySelector('.select-btn');
            if (btn) {
                btn.className = btn.className.replace('btn-outline-primary', 'btn-primary');
                btn.innerHTML = '<i data-feather="check-circle" style="width:16px;height:16px;"></i> Vybráno';
            }

            if (typeof feather !== 'undefined') feather.replace();
        });
    });
}());
</script>
@endsection
