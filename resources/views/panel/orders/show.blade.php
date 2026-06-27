@extends('layouts.panel')

@php
    $orderNo = '#' . strtoupper(substr($order->uuid, 0, 8));
    $breadcrumbTitle = __('panel.nav.orders') . ' ' . $orderNo;
    $breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), $orderNo => ''];

    /* Status timeline step */
    $step = match($order->status->value) {
        'pending'   => 1,
        'active'    => 3,
        'cancelled' => 0,
        'expired'   => 2,
        default     => 1,
    };
    $latestInvoice = $order->invoices->sortByDesc('id')->first();
    $billingAddress = auth()->user()?->customer?->addresses()->where('type','billing')->first();
@endphp

@section('title', __('panel.nav.orders') . ' ' . $orderNo)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- ── Left (col-span-9): Timeline + Items ─────────────── --}}
            <div class="col-span-9 xxl:col-span-8 xl:col-span-12 box-col-8e">
                <div class="grid grid-cols-12">

                    {{-- Status timeline --}}
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Stav objednávky</h5>
                            </div>
                            <div class="card-body track-order-details">
                                <h6 id="order-status-timeline">
                                    <div class="status-bar progress step-{{ $step }}"></div>
                                    <div class="main-status-line">
                                        <ul class="[@media(max-width:400px)]:items-[unset]">
                                            <li>
                                                <div class="order-process {{ $step >= 1 ? 'active' : '' }}"><span>1</span></div>
                                                <h6>Objednávka přijata</h6>
                                            </li>
                                            <li>
                                                <div class="order-process {{ $step >= 2 ? 'active' : '' }}"><span>2</span></div>
                                                <h6>Čeká na platbu</h6>
                                            </li>
                                            <li>
                                                <div class="order-process {{ $step >= 3 ? 'active' : '' }}"><span>3</span></div>
                                                <h6>Zaplaceno</h6>
                                            </li>
                                            <li>
                                                <div class="order-process {{ $step >= 4 ? 'active' : '' }}"><span>4</span></div>
                                                <h6>Aktivace</h6>
                                            </li>
                                            <li>
                                                <div class="order-process {{ $step >= 5 ? 'active' : '' }}"><span>5</span></div>
                                                <h6>Aktivní</h6>
                                            </li>
                                        </ul>
                                    </div>
                                </h6>
                            </div>
                        </div>
                    </div>

                    {{-- Order items --}}
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header card-no-border">
                                <div class="header-top">
                                    <h5>Objednávka: {{ $orderNo }}</h5>
                                    <div class="card-header-right-icon">
                                        <x-panel.status-badge :status="$order->status" />
                                    </div>
                                </div>
                            </div>
                            <div class="card-body order-details-product pt-0">
                                <div class="overflow-x-auto custom-scrollbar">
                                    <table class="table">
                                        <thead class="border-b">
                                            <tr>
                                                <th scope="col">Ikona</th>
                                                <th scope="col">Tarif / Popis</th>
                                                <th scope="col">Cena bez DPH</th>
                                                <th scope="col">DPH %</th>
                                                <th scope="col">Celkem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->items as $item)
                                            <tr class="border-b">
                                                <td>
                                                    <div class="light-product-box d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                                        <i data-feather="package" style="width:24px;height:24px;"></i>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul>
                                                        <li>
                                                            <h6>{{ $item->description }}</h6>
                                                        </li>
                                                        @if($item->period_from && $item->period_to)
                                                        <li>
                                                            <p class="c-o-light">Období: {{ $item->period_from->format('d.m.Y') }} — {{ $item->period_to->format('d.m.Y') }}</p>
                                                        </li>
                                                        @endif
                                                        @if(!empty($item->config['domain']))
                                                        <li>
                                                            <p class="c-o-light">Doména: <strong>{{ $item->config['domain'] }}</strong></p>
                                                        </li>
                                                        @endif
                                                    </ul>
                                                </td>
                                                <td><x-panel.money :money="$item->subtotal" /></td>
                                                <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                                                <td class="f-w-600"><x-panel.money :money="$item->total" /></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Invoices --}}
                    @if($order->invoices->isNotEmpty())
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header card-no-border">
                                <div class="header-top">
                                    <h5>{{ __('panel.orders.invoice') }}</h5>
                                </div>
                            </div>
                            <div class="card-body pt-0 px-0">
                                <div class="recent-table overflow-x-auto custom-scrollbar">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th><span class="f-light font-semibold">{{ __('panel.billing.number') }}</span></th>
                                                <th><span class="f-light font-semibold">Typ</span></th>
                                                <th><span class="f-light font-semibold">{{ __('panel.common.status') }}</span></th>
                                                <th><span class="f-light font-semibold">Splatnost</span></th>
                                                <th><span class="f-light font-semibold">{{ __('panel.common.total') }}</span></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->invoices as $invoice)
                                            <tr class="inbox-data">
                                                <td class="f-w-600">{{ $invoice->number }}</td>
                                                <td class="f-12">{{ $invoice->type->label() }}</td>
                                                <td><x-panel.status-badge :status="$invoice->status" /></td>
                                                <td class="f-12">{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</td>
                                                <td><x-panel.money :money="$invoice->total" /></td>
                                                <td>
                                                    <div class="common-align gap-2 justify-start">
                                                        <a class="square-white" href="{{ route('panel.billing.invoices.show', $invoice) }}"
                                                           data-bs-toggle="tooltip" data-bs-placement="top" data-tooltip="{{ __('panel.orders.view_invoice') }}">
                                                            <svg><use href="{{ asset('panel/assets/svg/icon-sprite.svg#fill-view') }}"></use></svg>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            {{-- ── Right (col-span-3): Summary + Customer ───────────── --}}
            <div class="col-span-3 xxl:col-span-4 xl:col-span-12 box-col-4">
                <div class="grid grid-cols-12">

                    {{-- Summary card --}}
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header card-no-border">
                                <div class="header-top summary-header">
                                    <h5>Souhrn</h5>
                                    @if($latestInvoice)
                                    <div class="card-header-right-icon">
                                        <a class="btn btn-primary text-white btn-sm"
                                           href="{{ route('panel.billing.invoices.show', $latestInvoice) }}">
                                            <i class="fa-regular fa-file-lines pe-2 f-14"></i>Faktura
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <ul class="tracking-total">
                                    <li>
                                        <h6>{{ __('panel.orders.subtotal') }}</h6>
                                        <span><x-panel.money :money="$order->subtotal" /></span>
                                    </li>
                                    <li>
                                        <h6>{{ __('panel.orders.vat') }}</h6>
                                        <span><x-panel.money :money="$order->tax_amount" /></span>
                                    </li>
                                    <li>
                                        <h6>{{ __('panel.common.total') }}</h6>
                                        <span class="f-w-600"><x-panel.money :money="$order->total" /></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Customer details card --}}
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header card-no-border">
                                <div class="header-top">
                                    <h5>Zákazník</h5>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <ul class="customer-details">
                                    <li>
                                        <h6>Jméno / Firma</h6>
                                        <span>{{ auth()->user()?->customer?->company_name ?? auth()->user()?->name }}</span>
                                    </li>
                                    <li>
                                        <h6>E-mail</h6>
                                        <span>{{ auth()->user()?->email }}</span>
                                    </li>
                                    @if($billingAddress)
                                    <li>
                                        <h6>Fakturační adresa</h6>
                                        <span>{{ $billingAddress->street }}, {{ $billingAddress->city }}, {{ $billingAddress->zip }}</span>
                                    </li>
                                    @endif
                                    <li>
                                        <h6>Datum objednávky</h6>
                                        <span>{{ $order->created_at?->format('d.m.Y H:i') }}</span>
                                    </li>
                                    @if($order->paid_at)
                                    <li>
                                        <h6>Datum platby</h6>
                                        <span>{{ $order->paid_at->format('d.m.Y H:i') }}</span>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-body">
                                <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-secondary w-full mb-2">
                                    <i data-feather="arrow-left" style="width:14px;height:14px;"></i>
                                    Zpět na objednávky
                                </a>
                                @if($latestInvoice && $latestInvoice->status->value === 'unpaid')
                                <a href="{{ route('panel.billing.invoices.show', $latestInvoice) }}"
                                   class="btn btn-primary w-full text-white">
                                    <i data-feather="credit-card" style="width:14px;height:14px;"></i>
                                    Zaplatit fakturu
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
