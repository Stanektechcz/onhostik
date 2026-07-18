@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Pokladna';
    $breadcrumbItems = ['Objednávky' => route('panel.orders.index'), 'Výběr tarifu' => route('panel.orders.create'), 'Pokladna' => ''];
@endphp

@section('title', 'Pokladna | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(!$plan)
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-feather="shopping-cart" class="empty-state-icon mb-3 block mx-auto"></i>
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
                                            <i data-feather="alert-circle"></i>
                                            Nemáte nastavenou fakturační adresu.
                                            <a href="{{ route('panel.account.billing') }}">Přidat adresu</a>
                                        </div>
                                    </div>
                                    @endif
                                    {{-- Discount code --}}
                                    <div class="col-span-12">
                                        <label class="form-label">Slevový kód <small class="f-light">(volitelné)</small></label>
                                        <div class="input-group">
                                            <input class="form-control uppercase" type="text" id="discount-input"
                                                   placeholder="PROMO2026" maxlength="32">
                                            <button class="btn btn-outline-primary" type="button" onclick="applyDiscount()">
                                                Použít
                                            </button>
                                        </div>
                                        <div id="discount-msg" class="mt-1 f-12"></div>
                                    </div>

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
                                        <div class="card-wrapper custom-border rounded light-card">
                                            <div class="flex items-start gap-3">
                                                <div class="plan-thumb plan-thumb-lg">
                                                    <i data-feather="package"></i>
                                                </div>
                                                <div class="grow">
                                                    <h6 class="mb-1">{{ $plan->product?->name ?? '' }} {{ $plan->name }}</h6>
                                                    <p class="f-light f-12 mb-1">{{ $plan->billing_cycle->label() }}</p>
                                                    @php $res = (array)($plan->resources ?? []); @endphp
                                                    @if(!empty($res))
                                                    <div class="flex gap-2 flex-wrap">
                                                        @foreach(array_slice($res, 0, 4) as $k => $v)
                                                        <span class="badge badge-light-primary">{{ ucfirst($k) }}: {{ $v }}</span>
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <h5 class="txt-primary mb-0">{{ number_format($priceVal, 0, ',', ' ') }} {{ $currency }}</h5>
                                                    <small class="f-light">/ {{ $plan->billing_cycle->label() }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-12">
                                        <a href="{{ route('panel.orders.create') }}" class="btn btn-outline-secondary btn-sm">
                                            <i data-feather="arrow-left"></i> Změnit tarif
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 3: Platba --}}
                            <div class="stepper-three shipping-wizard" id="wizard-step-3" style="display:none;">
                                <div class="grid grid-cols-12 shipping-method gap-3">
                                    @php
                                        $creditBalance = app(\App\Domains\Billing\Services\CreditLedger::class)
                                            ->getBalance(auth()->user()->customer);
                                        $paymentMethods = [
                                            ['id' => 'pay-comgate', 'value' => 'comgate', 'icon' => 'credit-card',
                                             'title' => 'Comgate — platební karta / QR',
                                             'desc'  => 'Zabezpečená platba kartou přes Comgate.'],
                                            ['id' => 'pay-credit', 'value' => 'credit', 'icon' => 'dollar-sign',
                                             'title' => 'Kredit na účtu',
                                             'desc'  => 'Ihned uhrazeno z kreditu. Zůstatek: '
                                                        . \App\Domains\Shared\Support\MoneyFormatter::format($creditBalance)],
                                            ['id' => 'pay-bank', 'value' => 'bank', 'icon' => 'home',
                                             'title' => 'Bankovní převod',
                                             'desc'  => 'Objednávka bude aktivována po přijetí platby.'],
                                        ];
                                    @endphp
                                    @foreach($paymentMethods as $i => $method)
                                        <div class="col-span-12">
                                            <div class="card-wrapper custom-border rounded light-card payment-method-option {{ $i === 0 ? 'selected' : '' }}"
                                                 data-radio="{{ $method['id'] }}">
                                                <div class="grow">
                                                    <div class="form-check radio radio-primary">
                                                        <input class="form-check-input" type="radio" id="{{ $method['id'] }}"
                                                               name="payment_method" value="{{ $method['value'] }}" {{ $i === 0 ? 'checked' : '' }}>
                                                        <label class="form-check-label mb-0 font-medium" for="{{ $method['id'] }}">{{ $method['title'] }}</label>
                                                    </div>
                                                    <p class="f-light f-12 mb-0">{{ $method['desc'] }}</p>
                                                </div>
                                                <i data-feather="{{ $method['icon'] }}" class="payment-method-icon"></i>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Step 4: Dokončeno --}}
                            <div class="stepper-four shipping-wizard" id="wizard-step-4" style="display:none;">
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="col-span-12">
                                        <div class="text-center py-4">
                                            <i data-feather="check-circle" class="checkout-success-icon font-success mb-3 block mx-auto"></i>
                                            <h5>Objednávka odeslána!</h5>
                                            <p class="f-light">Vaše objednávka bude zpracována. Potvrzení dostanete e-mailem.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>{{-- /shipping-content --}}

                        {{-- Wizard nav --}}
                        <div class="wizard-footer flex gap-2 justify-end mt-3">
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
                                <div class="plan-thumb">
                                    <i data-feather="package"></i>
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
        <input type="hidden" name="discount_code" id="final-discount-code" value="">
        {{-- Mirrors the step-3 radio: the radios live outside this form, so
             without this the chosen method would never reach the server. --}}
        <input type="hidden" name="payment_method" id="final-payment-method" value="comgate">
        @if($mockMode)
        <input type="hidden" name="simulate_failure" value="0">
        @endif
    </form>

    @endif
</div>
@endsection

@push('scripts')
<script>
/* Discount code AJAX validation */
function applyDiscount() {
    var code = document.getElementById('discount-input').value.trim().toUpperCase();
    var msg  = document.getElementById('discount-msg');
    if (!code) { msg.innerHTML = ''; return; }

    fetch('{{ route('panel.discount.validate') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({code: code})
    })
    .then(function(r) { return r.json().then(function(d) { return {ok:r.ok, data:d}; }); })
    .then(function(result) {
        var d = result.data;
        if (result.ok && d.valid) {
            document.getElementById('final-discount-code').value = d.code;
            msg.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + d.message + '</span>';
        } else {
            document.getElementById('final-discount-code').value = '';
            msg.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + (d.message || 'Neplatný kód') + '</span>';
        }
    })
    .catch(function() {
        msg.innerHTML = '<span class="text-danger">Chyba při ověřování kódu.</span>';
    });
}

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

    /* Payment method: make the whole Cuba card a hit target and keep the
       hidden field in the order form in sync with the chosen radio. */
    function syncPaymentMethod() {
        var picked = document.querySelector('input[name="payment_method"]:checked');
        var hidden = document.getElementById('final-payment-method');
        if (picked && hidden) hidden.value = picked.value;

        document.querySelectorAll('.payment-method-option').forEach(function (card) {
            var radio = document.getElementById(card.dataset.radio);
            card.classList.toggle('selected', !!(radio && radio.checked));
        });
    }

    document.querySelectorAll('.payment-method-option').forEach(function (card) {
        card.addEventListener('click', function (e) {
            var radio = document.getElementById(card.dataset.radio);
            if (!radio) return;
            // Let the native label/radio handle their own clicks.
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                radio.checked = true;
            }
            syncPaymentMethod();
        });
    });

    document.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
        radio.addEventListener('change', syncPaymentMethod);
    });

    syncPaymentMethod();

    window.wizardNext = function() {
        if (currentStep === 3) {
            /* Submit order */
            var domain = document.getElementById('domain-input');
            if (domain && domain.value) {
                document.getElementById('final-domain').value = domain.value;
            }
            syncPaymentMethod();
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
