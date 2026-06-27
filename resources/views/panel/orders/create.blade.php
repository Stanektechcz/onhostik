@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.new_order');
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), __('panel.nav.new_order') => ''];
@endphp

@section('title', __('panel.nav.new_order'))

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
                        <span class="badge badge-light-primary rounded-circle f-14" style="width:28px;height:28px;line-height:20px;text-align:center;">1</span>
                        <span class="f-w-500">Vyberte tarif</span>
                    </div>
                    <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light-secondary rounded-circle f-14" style="width:28px;height:28px;line-height:20px;text-align:center;">2</span>
                        <span class="f-light">Doména (volitelně)</span>
                    </div>
                    <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-light-secondary rounded-circle f-14" style="width:28px;height:28px;line-height:20px;text-align:center;">3</span>
                        <span class="f-light">Potvrzení</span>
                    </div>
                    @if($mockMode)
                        <span class="badge badge-light-warning ms-auto">MOCK MODE</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Plan selection using Cuba pricingtable pattern --}}
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

                <div class="grid grid-cols-12 card-gap">
                    @foreach($plans as $plan)
                    @php
                        $selected = (int) old('pricing_plan_id', $selectedPlan) === $plan->id;
                        $price = $plan->priceFor($customer->preferred_currency);
                        // Detect icon per product type
                        $icon = match($plan->product?->type?->value ?? '') {
                            'vps'         => 'cpu',
                            'gamehosting' => 'monitor',
                            default       => 'server',
                        };
                    @endphp
                    <div class="col-span-12 md:col-span-6 xl:col-span-4 xxl:col-span-3">
                        <label class="plan-card w-100 mb-0" style="cursor:pointer;">
                            <input type="radio" name="pricing_plan_id" value="{{ $plan->id }}"
                                   class="d-none plan-radio" @checked($selected)>
                            <div class="pricingtable h-100 {{ $selected ? 'active' : '' }}"
                                 style="border:2px solid {{ $selected ? 'var(--theme-default)' : 'transparent' }};border-radius:10px;transition:all .2s;">
                                {{-- Header --}}
                                <div class="pricingtable-header text-center pb-0">
                                    @if($plan->is_featured)
                                        <div class="ribbon ribbon-primary" style="position:absolute;top:-1px;right:-1px;">
                                            <span>Oblíbený</span>
                                        </div>
                                    @endif
                                    <div class="mb-2 mt-1">
                                        <i data-feather="{{ $icon }}" class="txt-primary" style="width:32px;height:32px;"></i>
                                    </div>
                                    <h4 class="title mergecolor mb-0">{{ $plan->name }}</h4>
                                    <p class="f-light f-12 mb-0">{{ $plan->product?->name }}</p>
                                    @if($plan->tagline)
                                        <p class="f-light f-12 mb-0">{{ $plan->tagline }}</p>
                                    @endif
                                </div>

                                {{-- Price --}}
                                <div class="price-value text-center py-3">
                                    @php
                                        $minor  = $price?->getMinorAmount()->toInt() ?? 0;
                                        $whole  = intdiv($minor, 100);
                                        $curr   = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
                                    @endphp
                                    <span class="currency txt-primary f-16 f-w-600">{{ $curr === 'CZK' ? '' : '$' }}</span>
                                    <span class="amount display-6 f-w-700 mergecolor">{{ number_format($whole, 0, ',', ' ') }}</span>
                                    <span class="f-light f-12">&nbsp;{{ $curr === 'CZK' ? 'Kč' : $curr }}</span>
                                    <span class="duration f-light f-12">&nbsp;/&nbsp;{{ $plan->billing_cycle->label() }}</span>
                                </div>

                                {{-- Features --}}
                                @if(!empty($plan->resources))
                                <ul class="pricing-content">
                                    @foreach($plan->resources as $key => $value)
                                        <li>
                                            <i data-feather="check" style="width:13px;height:13px;" class="txt-success me-1"></i>
                                            <span class="f-light">{{ __("front.resources.$key") }}:</span>
                                            <span class="f-w-500">{{ $value }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @else
                                <ul class="pricing-content">
                                    <li class="f-light f-12">Standardní hosting</li>
                                </ul>
                                @endif

                                {{-- CTA --}}
                                <div class="pricingtable-signup text-center pt-2 pb-3">
                                    <span class="btn {{ $selected ? 'btn-primary' : 'btn-outline-primary' }} w-75 plan-btn">
                                        <i data-feather="{{ $selected ? 'check-circle' : 'shopping-cart' }}" style="width:14px;height:14px;" class="me-1"></i>
                                        {{ $selected ? 'Vybráno' : 'Vybrat' }}
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
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label f-12 f-light">Doménové jméno <span class="f-light">(volitelné)</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light-primary txt-primary">
                                <i data-feather="globe" style="width:14px;height:14px;"></i>
                            </span>
                            <input class="form-control" type="text" name="domain" value="{{ old('domain') }}"
                                   placeholder="{{ __('front.domains.search_placeholder') }}">
                        </div>
                        <small class="f-light f-12">{{ __('panel.orders.domain_hint') }}</small>
                        @error('domain')
                            <p class="text-danger mt-1 mb-0 f-12">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <div class="w-100">
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
        </div>

        {{-- Submit --}}
        <div class="d-flex align-items-center gap-3 mb-4">
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i data-feather="check" style="width:16px;height:16px;"></i>
                {{ __('panel.orders.submit') }}
            </button>
            <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-secondary">
                {{ __('panel.nav.back') }}
            </a>
            <span class="f-light f-12 ms-2">
                <i data-feather="info" style="width:12px;height:12px;"></i>
                Po odeslání bude vystavena zálohová faktura.
            </span>
        </div>
    </form>

    @endif
</div>

<script>
// Plan card selection — Cuba pricingtable style
document.querySelectorAll('.plan-card').forEach(function(label) {
    label.addEventListener('click', function() {
        // Deselect all
        document.querySelectorAll('.pricingtable').forEach(function(card) {
            card.style.border = '2px solid transparent';
            var btn = card.querySelector('.plan-btn');
            if (btn) {
                btn.className = btn.className.replace('btn-primary', 'btn-outline-primary');
                btn.innerHTML = '<i data-feather="shopping-cart" style="width:14px;height:14px;" class="me-1"></i> Vybrat';
            }
        });
        document.querySelectorAll('.plan-radio').forEach(function(r) { r.checked = false; });

        // Select this
        var radio = this.querySelector('.plan-radio');
        var card  = this.querySelector('.pricingtable');
        var btn   = this.querySelector('.plan-btn');
        if (radio) radio.checked = true;
        if (card)  card.style.border = '2px solid var(--theme-default)';
        if (btn) {
            btn.className = btn.className.replace('btn-outline-primary', 'btn-primary');
            btn.innerHTML = '<i data-feather="check-circle" style="width:14px;height:14px;" class="me-1"></i> Vybráno';
        }

        // Re-init feather icons
        if (typeof feather !== 'undefined') feather.replace();
    });
});
</script>
@endsection
