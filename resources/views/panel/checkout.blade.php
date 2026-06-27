@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Pokladna';
    $breadcrumbItems = ['Objednávky' => route('panel.orders.index'), 'Výběr tarifu' => route('panel.orders.create'), 'Pokladna' => ''];
@endphp

@section('title', 'Pokladna | OnHost')

@push('styles')
<style>
.stepper-horizontal { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.stepper-horizontal .step { flex: 1; position: relative; text-align: center; }
.stepper-horizontal .step-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 6px; font-weight: 600; font-size: 15px; background: rgba(var(--light-background),1); color: var(--body-font-color); border: 2px solid rgba(var(--light-background),1); }
.stepper-horizontal .step.active .step-circle, .stepper-horizontal .step.editing .step-circle { background: rgba(var(--theme-default),1); color: #fff; border-color: rgba(var(--theme-default),1); }
.stepper-horizontal .step.done .step-circle { background: #54ba4a; color: #fff; border-color: #54ba4a; }
.stepper-horizontal .step-title { font-size: 13px; color: var(--body-font-color); }
.stepper-horizontal .step-bar-right { position: absolute; top: 20px; right: 0; width: 50%; height: 2px; background: rgba(var(--light-background),1); }
.stepper-horizontal .step-bar-left { position: absolute; top: 20px; left: 0; width: 50%; height: 2px; background: rgba(var(--light-background),1); }
.stepper-horizontal .step:first-child .step-bar-left { display: none; }
.stepper-horizontal .step:last-child .step-bar-right { display: none; }

/* Shipping form sections */
.card-wrapper { border: 1px solid rgba(var(--light-background),1); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.card-wrapper.light-card { background: rgba(var(--light-background),.4); }
.collect-address { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.shipping-address span { display: block; font-size: 12px; color: var(--body-font-color); margin-bottom: 4px; }

/* Summary */
.summery-contain li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(var(--light-background),1); }
.summery-contain li h6 { flex: 1; margin: 0; }
.summery-contain li h6 span { display: block; font-size: 11px; font-weight: 400; color: var(--body-font-color); opacity: .7; }
.summery-contain li h6.price { flex: 0; white-space: nowrap; color: rgba(var(--theme-default),1); }
.summary-total li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(var(--light-background),1); }
.summary-total li:last-child { border: none; }
.summary-total h6.price { color: rgba(var(--theme-default),1); font-weight: 600; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(!$plan)
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-feather="shopping-cart" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
            <h5 class="f-light">Nebyl vybrán žádný tarif</h5>
            <a href="{{ route('panel.orders.create') }}" class="btn btn-primary mt-3">Vybrat tarif</a>
        </div>
    </div>
    @else

    <div class="container">
        <div class="grid grid-cols-12 shipping-form card-gap">

            {{-- ── Left: Checkout wizard ───────────────────── --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card checkout-cart">
                    <div class="card-body basic-wizard important-validation">

                        {{-- Stepper --}}
                        <div class="stepper-horizontal custom-scrollbar" id="stepper1">
                            <div class="stepper-one stepper step editing active" id="step1">
                                <div class="step-circle"><span>1</span></div>
                                <div class="step-title">Fakturační údaje</div>
                                <div class="step-bar-left"></div>
                                <div class="step-bar-right"></div>
                            </div>
                            <div class="stepper-two step" id="step2">
                                <div class="step-circle"><span>2</span></div>
                                <div class="step-title">Potvrzení tarifu</div>
                                <div class="step-bar-left"></div>
                                <div class="step-bar-right"></div>
                            </div>
                            <div class="stepper-three step" id="step3">
                                <div class="step-circle"><span>3</span></div>
                                <div class="step-title">Platba</div>
                                <div class="step-bar-left"></div>
                                <div class="step-bar-right"></div>
                            </div>
                            <div class="stepper-four step" id="step4">
                                <div class="step-circle"><span>4</span></div>
                                <div class="step-title">Dokončeno</div>
                                <div class="step-bar-left"></div>
                                <div class="step-bar-right"></div>
                            </div>
                        </div>

                        <div class="shipping-content" id="msform">

                            {{-- Step 1: Fakturační údaje --}}
                            <div class="stepper-one shipping-wizard" id="wizard-step-1">
                                <div class="grid grid-cols-12 gap-3 custom-input">
                                    <div class="col-span-6 sm:col-span-12">
                                        <label class="form-label">Jméno / Firma</label>
                                        <input class="form-control" type="text" value="{{ $customer->company_name ?? $customer->user?->name }}" readonly>
                                    </div>
                                    <div class="col-span-6 sm:col-span-12">
                                        <label class="form-label">E-mail</label>
                                        <input class="form-control" type="email" value="{{ $customer->email }}" readonly>
                                    </div>
                                    @if($customer->registration_number)
                                    <div class="col-span-6 sm:col-span-12">
                                        <label class="form-label">IČO</label>
                                        <input class="form-control" type="text" value="{{ $customer->registration_number }}" readonly>
                                    </div>
                                    @endif
                                    @if($billingAddress)
                                    <div class="col-span-12">
                                        <div class="card-wrapper light-card">
                                            <div class="collect-address">
                                                <strong>Fakturační adresa</strong>
                                                <a href="{{ route('panel.account.billing') }}" class="btn btn-outline-primary btn-xs">Upravit</a>
                                            </div>
                                            <div class="shipping-address">
                                                <span><strong>Ulice:</strong> {{ $billingAddress->street }}</span>
                                                <span><strong>Město:</strong> {{ $billingAddress->zip }} {{ $billingAddress->city }}</span>
                                                <span><strong>Stát:</strong> {{ $billingAddress->country_code }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @else
                                    <div class="col-span-12">
                                        <div class="alert alert-light-warning">
                                            <i data-feather="alert-circle" style="width:14px;height:14px;"></i>
                                            Nemáte nastavenou fakturační adresu.
                                            <a href="{{ route('panel.account.billing') }}">Přidat adresu</a>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-span-12">
                                        <label class="form-label">Doména (volitelné)</label>
                                        <input class="form-control" type="text" id="domain-input" name="domain_preview"
                                               placeholder="mujweb.cz" pattern="^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$">
                                        <div class="form-text f-12 f-light">Zadejte doménu pro hosting. Pole je nepovinné.</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 2: Potvrzení tarifu --}}
                            <div class="stepper-two shipping-wizard" id="wizard-step-2" style="display:none;">
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-12">
                                        <div class="card-wrapper custom-border rounded-3 light-card">
                                            <div class="d-flex align-items-start gap-3">
                                                <div style="width:60px;height:60px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                                    <i data-feather="package" style="width:28px;height:28px;color:rgba(var(--theme-default),1);"></i>
                                                </div>
                                                <div class="grow">
                                                    <h6 class="mb-1">{{ $plan->product?->name ?? '' }} {{ $plan->name }}</h6>
                                                    <p class="f-light f-12 mb-1">{{ $plan->billing_cycle->label() }}</p>
                                                    @php $res = (array)($plan->resources ?? []); @endphp
                                                    @if(!empty($res))
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        @foreach(array_slice($res, 0, 4) as $k => $v)
                                                        <span class="badge badge-light-primary">{{ ucfirst($k) }}: {{ $v }}</span>
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    <h5 class="txt-primary mb-0">{{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}</h5>
                                                    <small class="f-light">/ {{ $plan->billing_cycle->label() }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-12">
                                        <a href="{{ route('panel.orders.create') }}" class="btn btn-outline-secondary btn-sm">
                                            <i data-feather="arrow-left" style="width:13px;height:13px;"></i> Změnit tarif
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 3: Platba --}}
                            <div class="stepper-three shipping-wizard" id="wizard-step-3" style="display:none;">
                                <div class="grid grid-cols-12 shipping-method gap-3">
                                    <div class="col-span-12">
                                        <div class="card-wrapper custom-border rounded-3 light-card d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="form-check radio radio-primary">
                                                    <input class="form-check-input" type="radio" id="pay-comgate" name="payment_method" value="comgate" checked>
                                                    <label class="form-check-label mb-0 font-medium" for="pay-comgate">Comgate — platební karta / QR</label>
                                                </div>
                                                <p class="f-light f-12 mb-0">Zabezpečená platba kartou přes Comgate.</p>
                                            </div>
                                            <i data-feather="credit-card" style="width:32px;height:32px;opacity:.4;"></i>
                                        </div>
                                    </div>
                                    <div class="col-span-12">
                                        <div class="card-wrapper custom-border rounded-3 light-card d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="form-check radio radio-primary">
                                                    <input class="form-check-input" type="radio" id="pay-credit" name="payment_method" value="credit">
                                                    <label class="form-check-label mb-0 font-medium" for="pay-credit">Kredit na účtu</label>
                                                </div>
                                                <p class="f-light f-12 mb-0">Platba z kreditu na účtu OnHost.</p>
                                            </div>
                                            <i data-feather="dollar-sign" style="width:32px;height:32px;opacity:.4;"></i>
                                        </div>
                                    </div>
                                    <div class="col-span-12">
                                        <div class="card-wrapper custom-border rounded-3 light-card d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="form-check radio radio-primary">
                                                    <input class="form-check-input" type="radio" id="pay-bank" name="payment_method" value="bank">
                                                    <label class="form-check-label mb-0 font-medium" for="pay-bank">Bankovní převod</label>
                                                </div>
                                                <p class="f-light f-12 mb-0">Objednávka bude aktivována po přijetí platby.</p>
                                            </div>
                                            <i data-feather="landmark" style="width:32px;height:32px;opacity:.4;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 4: Dokončeno --}}
                            <div class="stepper-four shipping-wizard" id="wizard-step-4" style="display:none;">
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-12">
                                        <div class="text-center py-4">
                                            <i data-feather="check-circle" style="width:64px;height:64px;color:#54ba4a;margin-bottom:16px;display:block;" class="d-block mx-auto"></i>
                                            <h5>Objednávka odeslána!</h5>
                                            <p class="f-light">Vaše objednávka bude zpracována. Potvrzení dostanete e-mailem.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>{{-- /shipping-content --}}

                        {{-- Wizard nav --}}
                        <div class="wizard-footer d-flex gap-2 justify-content-end mt-3">
                            <button class="btn button-light-primary" id="backbtn" onclick="wizardBack()" style="display:none;">
                                Zpět
                            </button>
                            <button class="btn btn-primary text-white" id="nextbtn" onclick="wizardNext()">
                                Další
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── Right: Order summary ────────────────────── --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Souhrn objednávky</h5>
                    </div>
                    <div class="card-body">
                        <ul class="summery-contain">
                            <li>
                                <div style="width:44px;height:44px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i data-feather="package" style="width:22px;height:22px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <h6>
                                    {{ $plan->product?->name ?? '' }} {{ $plan->name }}
                                    <span>{{ $plan->billing_cycle->label() }}</span>
                                </h6>
                                <h6 class="price">{{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}</h6>
                            </li>
                        </ul>

                        <ul class="summary-total mt-3">
                            <li>
                                <h6>Základ</h6>
                                <h6 class="price">{{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}</h6>
                            </li>
                            <li>
                                <h6>DPH</h6>
                                <h6 class="price f-light">Bude vypočtena</h6>
                            </li>
                            <li>
                                <h6 class="f-w-600">Celkem</h6>
                                <h6 class="price f-w-600">~{{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}</h6>
                            </li>
                        </ul>

                        <div class="mt-3">
                            <a href="{{ route('panel.orders.create') }}" class="btn btn-hover-effect w-full">
                                <span><i class="fa-solid fa-caret-left fa-lg"></i></span>
                                Zpět na tarify
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Hidden order form --}}
    <form id="final-order-form" method="POST" action="{{ route('panel.orders.store') }}" style="display:none;">
        @csrf
        <input type="hidden" name="pricing_plan_id" value="{{ $plan->id }}">
        <input type="hidden" name="domain" id="final-domain" value="">
        @if($mockMode)
        <input type="hidden" name="simulate_failure" value="0">
        @endif
    </form>

    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    var currentStep = 1;
    var totalSteps  = 4;

    function showStep(n) {
        for (var i = 1; i <= totalSteps; i++) {
            var el = document.getElementById('wizard-step-' + i);
            if (el) el.style.display = (i === n) ? '' : 'none';
            var dot = document.getElementById('step' + i);
            if (dot) {
                dot.classList.remove('active', 'editing', 'done');
                if (i < n)  dot.classList.add('done');
                if (i === n) dot.classList.add('active', 'editing');
            }
        }
        document.getElementById('backbtn').style.display = (n > 1) ? '' : 'none';
        var nextBtn = document.getElementById('nextbtn');
        if (n === 3) {
            nextBtn.textContent = 'Objednat';
        } else if (n === 4) {
            nextBtn.style.display = 'none';
        } else {
            nextBtn.textContent = 'Další';
        }
    }

    window.wizardNext = function() {
        if (currentStep === 3) {
            /* Submit order */
            var domain = document.getElementById('domain-input');
            if (domain && domain.value) {
                document.getElementById('final-domain').value = domain.value;
            }
            currentStep = 4;
            showStep(4);
            document.getElementById('final-order-form').submit();
            return;
        }
        if (currentStep < totalSteps - 1) {
            currentStep++;
            showStep(currentStep);
        }
    };

    window.wizardBack = function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    };

    showStep(1);
})();
</script>
@endpush
