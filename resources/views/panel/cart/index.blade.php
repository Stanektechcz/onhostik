@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Košík';
    $breadcrumbItems = ['Košík' => ''];
@endphp

@section('title', 'Košík | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container common-cart">
        <div class="grid grid-cols-12 card-gap">

            {{-- Cart table --}}
            <div class="col-span-9 xl:col-span-12 xl-100 box-col-8">
                <div class="card">
                    <div class="card-body shopping-cart-table">
                        <div class="recent-table overflow-x-auto custom-scrollbar">
                            <table class="table" id="cart-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th data-orderable="false">
                                            <span class="c-o-light font-semibold">
                                                Košík <span class="badge badge-dark rounded-full text-white">{{ $items->count() }}</span>
                                            </span>
                                        </th>
                                        <th data-orderable="false"><span class="c-o-light font-semibold"></span></th>
                                        <th data-orderable="false"><span class="c-o-light font-semibold"></span></th>
                                        <th data-orderable="false"><span class="c-o-light font-semibold">Celkem</span></th>
                                        <th data-orderable="false">
                                            <form method="POST" action="{{ route('panel.cart.clear') }}" style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn p-0 border-0 c-o-light font-semibold">
                                                    Vymazat vše
                                                    <svg style="width:16px;height:16px;display:inline;">
                                                        <use href="{{ asset('panel/assets/svg/icon-sprite.svg#trash1') }}"></use>
                                                    </svg>
                                                </button>
                                            </form>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $item)
                                    <tr class="inbox-data">
                                        <td></td>
                                        <td>
                                            <div class="product-names">
                                                <div class="light-product-box d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                                                    <i data-feather="package" style="width:28px;height:28px;"></i>
                                                </div>
                                                <ul>
                                                    <li><h6>{{ $item['plan']->product?->name ?? '' }} {{ $item['plan']->name }}</h6></li>
                                                    <li>
                                                        <p>{{ $item['plan']->billing_cycle->label() }}</p>
                                                        <span class="common-dot"></span>
                                                        <span>Cena: <span>{{ number_format($item['price'], 0, ',', ' ') }} {{ $item['currency'] }}</span></span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cart-price">
                                                <h5>{{ number_format($item['price'], 0, ',', ' ') }} {{ $item['currency'] }}</h5>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="touchspin-wrapper">
                                                <span class="badge badge-light-primary f-12">1 × měsíc</span>
                                            </div>
                                        </td>
                                        <td>
                                            <h4 class="txt-primary">{{ number_format($item['subtotal'], 0, ',', ' ') }} {{ $item['currency'] }}</h4>
                                        </td>
                                        <td>
                                            <div class="product-action">
                                                <a class="square-white" href="{{ route('panel.orders.create', ['plan' => $item['plan']->id]) }}"
                                                   data-bs-toggle="tooltip" data-bs-placement="top" data-tooltip="Objednat">
                                                    <i data-feather="shopping-cart" style="width:14px;height:14px;"></i>
                                                </a>
                                                <form method="POST" action="{{ route('panel.cart.remove', $item['plan']->id) }}" style="display:inline;">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="square-white trash-3 border-0 bg-transparent"
                                                            data-bs-toggle="tooltip" data-bs-placement="top" data-tooltip="Odebrat">
                                                        <svg style="width:16px;height:16px;">
                                                            <use href="{{ asset('panel/assets/svg/icon-sprite.svg#trash1') }}"></use>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i data-feather="shopping-cart" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                                            <h5 class="f-light">Košík je prázdný</h5>
                                            <p class="f-light f-12 mb-3">Přidejte tarify z přehledu tarifů.</p>
                                            <a href="{{ route('panel.orders.create') }}" class="btn btn-primary text-white">
                                                Vybrat tarif
                                            </a>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary sidebar --}}
            <div class="col-span-3 xl:col-span-12 xl-100 box-col-4e">
                <div class="grid grid-cols-12 card-gap">

                    {{-- Cart summary --}}
                    <div class="col-span-12 order-1 xl:order-0 xxl:order-2">
                        <div class="card">
                            <div class="card-header">
                                <div class="header-top"><h5>Souhrn</h5></div>
                            </div>
                            <div class="card-body">
                                <ul class="cart-summary">
                                    <li>
                                        <h6>Mezisoučet</h6>
                                        <span>{{ number_format($total, 0, ',', ' ') }} Kč</span>
                                    </li>
                                    <li>
                                        <h6>DPH</h6>
                                        <span class="txt-primary">Bude vypočtena</span>
                                    </li>
                                </ul>
                            </div>
                            <ul class="product-total">
                                <li>
                                    <h4>Celkem</h4>
                                    <h4>~{{ number_format($total, 0, ',', ' ') }} Kč</h4>
                                </li>
                                <li>
                                    <ul class="cart-buttons">
                                        @if($items->isNotEmpty())
                                        <li class="[@media(min-width:1661px)]:!w-[100%]">
                                            <a class="btn btn-primary text-white"
                                               href="{{ route('panel.orders.create', ['plan' => $items->first()['plan']->id]) }}">
                                                Pokračovat k objednávce
                                            </a>
                                        </li>
                                        @endif
                                        <li class="[@media(min-width:1661px)]:!w-[100%]">
                                            <a class="btn btn-hover-effect" href="{{ route('panel.orders.create') }}">
                                                <span><i class="fa-solid fa-caret-left fa-lg"></i></span>
                                                Zpět na tarify
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- Promo / info --}}
                    <div class="col-span-12 order-0 xxl:order-1">
                        <div class="card">
                            <div class="card-body">
                                <div class="coupon-cart">
                                    <span class="pb-1">Přejít na oblíbené tarify</span>
                                    <a href="{{ route('panel.wishlist.index') }}" class="btn btn-outline-primary w-full mt-2">
                                        <i data-feather="heart" style="width:14px;height:14px;"></i>
                                        Zobrazit oblíbené
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
