@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Košík';
    $breadcrumbItems = ['Objednávky a nákup' => '', 'Košík' => ''];
@endphp

@section('title', 'Košík | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    @error('cart')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

    @if($items->isEmpty())
        <x-panel.card title="Košík">
            <x-panel.empty-state icon="shopping-bag" title="Váš košík je prázdný"
                subtitle="Vyberte tarif a přidejte ho do košíku — objednat můžete i více služeb najednou.">
                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary text-white">
                    <i data-feather="plus-circle"></i> Vybrat tarif
                </a>
            </x-panel.empty-state>
        </x-panel.card>
    @else
        <div class="grid grid-cols-12 card-gap">

            {{-- Cart lines --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card title="Položky v košíku" :subtitle="$items->sum('qty') . ' ks'">
                    <div class="table-responsive theme-scrollbar">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Služba</th>
                                    <th>Cena / období</th>
                                    <th>Množství</th>
                                    <th>Celkem</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="cart-item-thumb">
                                                    <i data-feather="server"></i>
                                                </div>
                                                <div>
                                                    <span class="f-w-500">{{ $item['plan']->product?->name }} — {{ $item['plan']->name }}</span>
                                                    <p class="f-light f-12 mb-0">{{ $item['plan']->billing_cycle->label() }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="f-w-500">{{ \App\Domains\Shared\Support\MoneyFormatter::format($item['plan']->priceFor($customer->preferred_currency)) }}</span>
                                        </td>
                                        <td>
                                            @if($item['plan']->product?->provisionsInstance())
                                                <span class="badge badge-light-secondary">1 instance</span>
                                            @else
                                                <form method="POST" action="{{ route('panel.cart.update', $item['plan']->id) }}" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="number" name="qty" value="{{ $item['qty'] }}" min="1" max="20"
                                                           class="form-control form-control-sm cart-qty-input" aria-label="Množství">
                                                    <button type="submit" class="btn btn-outline-primary btn-xs" title="Přepočítat">
                                                        <i data-feather="refresh-cw"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="f-w-600">
                                                {{ \App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($item['subtotal'], $currency)) }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('panel.cart.remove', $item['plan']->id) }}"
                                                  data-confirm="Odebrat položku z košíku?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-xs" title="Odebrat">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-3">
                        <a href="{{ route('panel.orders.create') }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="plus"></i> Přidat další službu
                        </a>
                        <form method="POST" action="{{ route('panel.cart.clear') }}"
                              data-confirm="Opravdu vyprázdnit celý košík?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i data-feather="trash-2"></i> Vyprázdnit košík
                            </button>
                        </form>
                    </div>
                </x-panel.card>
            </div>

            {{-- Summary + checkout --}}
            <div class="col-span-4 xl:col-span-12">
                <form method="POST" action="{{ route('panel.cart.checkout') }}" id="cart-checkout-form">
                    @csrf

                    @php
                        // Products provisioned by a web/domain backend need a domain.
                        $domainDrivers = ['aapanel', 'wedos'];
                        $domainItems = $items->filter(fn ($i) => in_array($i['plan']->product?->provisioning_driver?->value, $domainDrivers, true));
                    @endphp
                    @if($domainItems->isNotEmpty())
                        <x-panel.card title="Konfigurace služeb">
                            <p class="f-light f-12 mb-3">Zadejte doménu pro služby, které ji vyžadují. Nepovinné — lze doplnit i po objednání.</p>
                            @foreach($domainItems as $i)
                                <div class="mb-3">
                                    <label class="form-label f-12" for="domain-{{ $i['plan']->id }}">
                                        <i data-feather="globe" style="width:12px;height:12px;"></i>
                                        {{ $i['plan']->product?->name }} — {{ $i['plan']->name }}
                                    </label>
                                    <input type="text" id="domain-{{ $i['plan']->id }}" name="domains[{{ $i['plan']->id }}]"
                                           class="form-control form-control-sm" placeholder="mujweb.cz"
                                           value="{{ old('domains.' . $i['plan']->id) }}"
                                           pattern="^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$">
                                    <div class="form-check checkbox checkbox-primary mt-1">
                                        <input class="form-check-input" type="checkbox" id="regdom-{{ $i['plan']->id }}"
                                               name="register_domains[{{ $i['plan']->id }}]" value="1"
                                               @checked(old('register_domains.' . $i['plan']->id))>
                                        <label class="form-check-label f-12 f-light" for="regdom-{{ $i['plan']->id }}">
                                            Zaregistrovat tuto doménu jako novou (ověříme dostupnost)
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                            @error('domains.*')<div class="text-danger f-12">{{ $message }}</div>@enderror
                            @error('register_domains')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </x-panel.card>
                    @endif

                    <x-panel.card title="Souhrn objednávky">
                        <table class="table table-borderless mb-3">
                            <tr>
                                <td class="f-light ps-0">Mezisoučet (bez DPH)</td>
                                <td class="text-right f-w-500">
                                    {{ \App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($subtotal, $currency)) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">DPH ({{ rtrim(rtrim(number_format($vatRate, 2, ',', ' '), '0'), ',') }} %)</td>
                                <td class="text-right f-w-500">
                                    {{ \App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($tax, $currency)) }}
                                </td>
                            </tr>
                            <tr class="border-top">
                                <td class="f-w-600 ps-0">Celkem k úhradě</td>
                                <td class="text-right f-w-600 txt-primary">
                                    {{ \App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($total, $currency)) }}
                                </td>
                            </tr>
                        </table>

                        <div class="p-2 bg-light-primary rounded mb-3">
                            <p class="f-12 f-w-600 mb-1"><i data-feather="repeat" style="width:12px;height:12px;"></i> Opakovaná platba (obnovení)</p>
                            @foreach($recurring as $r)
                                <div class="flex items-center justify-between f-12">
                                    <span class="f-light">{{ $r['label'] }}</span>
                                    <span class="f-w-500">{{ \App\Domains\Shared\Support\MoneyFormatter::format(\Brick\Money\Money::ofMinor($r['amount'], $currency)) }} bez DPH</span>
                                </div>
                            @endforeach
                            <p class="f-light f-11 mb-0 mt-1">Služby se automaticky obnovují za tuto cenu na konci období.</p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label f-12" for="cart-discount">Slevový kód</label>
                            <div class="input-group">
                                <input type="text" name="discount_code" id="cart-discount" class="form-control form-control-sm uppercase"
                                       placeholder="Nepovinné" maxlength="50">
                                <button class="btn btn-outline-primary btn-sm" type="button" id="cart-discount-btn">Ověřit</button>
                            </div>
                            <div id="cart-discount-msg" class="f-11 mt-1"></div>
                        </div>

                        <p class="f-light f-11 mb-0">Konečná DPH je dle vašich fakturačních údajů; slevu zohledníme na zálohové faktuře.</p>
                    </x-panel.card>

                    <x-panel.card title="Způsob platby">
                        @php
                            // C44: when the customer's credit covers the whole order, credit is
                            // the default (instant activation); otherwise Comgate leads.
                            $cartMethods = [
                                ['id' => 'cart-pay-credit', 'value' => 'credit', 'icon' => 'dollar-sign',
                                 'title' => 'Kredit na účtu',
                                 'desc'  => 'Ihned uhrazeno a zřízeno. Zůstatek: ' . \App\Domains\Shared\Support\MoneyFormatter::format($creditBalance)],
                                ['id' => 'cart-pay-comgate', 'value' => 'comgate', 'icon' => 'credit-card',
                                 'title' => 'Comgate — karta / QR', 'desc' => 'Přesměrujeme vás na zabezpečenou platbu kartou.'],
                                ['id' => 'cart-pay-bank', 'value' => 'bank', 'icon' => 'home',
                                 'title' => 'Bankovní převod', 'desc' => 'Aktivace po přijetí platby na účet.'],
                            ];

                            // Credit first only if it actually covers the order.
                            if (! $creditCovers) {
                                $credit        = array_shift($cartMethods);
                                $credit['desc'] = 'Nedostatečný zůstatek. Zůstatek: ' . \App\Domains\Shared\Support\MoneyFormatter::format($creditBalance);
                                array_splice($cartMethods, 1, 0, [$credit]);
                            }
                            $defaultIndex = 0;
                        @endphp
                        <div class="grid grid-cols-12 shipping-method gap-3">
                            @foreach($cartMethods as $i => $method)
                                @php($disabled = $method['value'] === 'credit' && ! $creditCovers)
                                <div class="col-span-12">
                                    <div class="card-wrapper custom-border rounded light-card payment-method-option {{ $i === $defaultIndex ? 'selected' : '' }} {{ $disabled ? 'opacity-50' : '' }}"
                                         data-radio="{{ $method['id'] }}">
                                        <div class="grow">
                                            <div class="form-check radio radio-primary">
                                                <input class="form-check-input" type="radio" id="{{ $method['id'] }}"
                                                       name="payment_method" value="{{ $method['value'] }}"
                                                       {{ $i === $defaultIndex ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }}>
                                                <label class="form-check-label mb-0 font-medium" for="{{ $method['id'] }}">{{ $method['title'] }}</label>
                                            </div>
                                            <p class="f-light f-12 mb-0">{{ $method['desc'] }}</p>
                                        </div>
                                        <i data-feather="{{ $method['icon'] }}" class="payment-method-icon"></i>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- C49: how quickly the order is provisioned --}}
                        <div class="flex items-center gap-2 mt-3 f-12 f-light">
                            <i data-feather="clock" style="width:13px;height:13px;"></i>
                            <span>Zřízení služby obvykle do několika minut po připsání platby (u bankovního převodu po přijetí na účet).</span>
                        </div>

                        @if($resellerMarkup > 0)
                            {{-- C43: reseller margin preview --}}
                            <div class="p-2 bg-light-success rounded mt-3 f-12">
                                <i data-feather="trending-up" style="width:12px;height:12px;"></i>
                                Ceny zahrnují vaši reseller marži {{ rtrim(rtrim(number_format($resellerMarkup, 2, ',', ' '), '0'), ',') }} %.
                            </div>
                        @endif

                        {{-- C50: mandatory terms consent --}}
                        <div class="form-check checkbox checkbox-primary mt-3">
                            <input class="form-check-input" type="checkbox" id="cart-terms" name="terms" value="1"
                                   @checked(old('terms')) required>
                            <label class="form-check-label f-12" for="cart-terms">
                                Souhlasím s <a href="{{ route('front.legal') }}" target="_blank" rel="noopener">obchodními podmínkami</a>
                                a <a href="{{ route('front.gdpr') }}" target="_blank" rel="noopener">zpracováním osobních údajů</a>.
                            </label>
                            @error('terms')<div class="text-danger f-11 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary text-white w-full mt-3">
                            <i data-feather="check-circle"></i> Objednat vše ({{ $items->sum('qty') }})
                        </button>
                    </x-panel.card>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    // Same interaction contract as the checkout wizard: the whole Cuba
    // card is the hit target; the Cuba radio component stays untouched.
    function sync() {
        document.querySelectorAll('.payment-method-option').forEach(function (card) {
            var radio = document.getElementById(card.dataset.radio);
            card.classList.toggle('selected', !!(radio && radio.checked));
        });
    }

    document.querySelectorAll('.payment-method-option').forEach(function (card) {
        card.addEventListener('click', function (e) {
            var radio = document.getElementById(card.dataset.radio);
            if (!radio || radio.disabled) return;
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                radio.checked = true;
            }
            sync();
        });
    });

    document.querySelectorAll('input[name="payment_method"]').forEach(function (r) {
        r.addEventListener('change', sync);
    });

    sync();

    // Discount code — live AJAX validation (same endpoint as the wizard).
    var dBtn = document.getElementById('cart-discount-btn'),
        dInput = document.getElementById('cart-discount'),
        dMsg = document.getElementById('cart-discount-msg');
    if (dBtn && dInput) {
        dBtn.addEventListener('click', function () {
            var code = (dInput.value || '').trim().toUpperCase();
            if (!code) { dMsg.innerHTML = ''; return; }
            fetch('{{ route('panel.discount.validate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ code: code })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (res.ok && res.d.valid) {
                    dMsg.innerHTML = '<span class="txt-success">✓ ' + (res.d.message || 'Kód je platný') + '</span>';
                } else {
                    dMsg.innerHTML = '<span class="text-danger">✗ ' + (res.d.message || 'Neplatný kód') + '</span>';
                }
            }).catch(function () { dMsg.innerHTML = '<span class="text-danger">Chyba ověření.</span>'; });
        });
    }
});
</script>
@endpush
