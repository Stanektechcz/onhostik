@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Oblíbené tarify';
    $breadcrumbItems = ['Oblíbené' => ''];
@endphp

@section('title', 'Oblíbené tarify | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            <div class="col-span-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="flex gap-2">
                            Oblíbené tarify
                            <span class="c-o-light">({{ $items->count() }})</span>
                        </h5>
                    </div>
                </div>
            </div>

            <div class="col-span-12">
                @if($items->isEmpty())
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i data-feather="heart" style="width:48px;height:48px;" class="text-muted mb-3 block mx-auto"></i>
                            <h5 class="f-light">Nemáte žádné oblíbené tarify</h5>
                            <p class="f-light f-12 mb-3">Přidejte tarify pomocí tlačítka ♡ na stránce s výběrem tarifů.</p>
                            <a href="{{ route('panel.orders.create') }}" class="btn btn-primary text-white">
                                Procházet tarify
                            </a>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-12 gap-3 [@media(max-width:1875px)]:!mb-[20px]">
                        @foreach($items as $item)
                        <div class="col-span-4 xxl:col-span-6 sm:col-span-12 box-col-span-6 inbox-data">
                            <div class="card mb-0 h-full">
                                <div class="wishlist-box card-body">
                                    <div>
                                        <div class="wishlist-image">
                                            <a href="{{ route('panel.orders.create', ['plan' => $item['plan']->id]) }}">
                                                <div style="width:80px;height:80px;display:flex;align-items:center;justify-content:center;
                                                    background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.02));
                                                    border-radius:12px;">
                                                    <i data-feather="package" style="width:40px;height:40px;stroke-width:1;stroke:rgba(var(--theme-default),1);"></i>
                                                </div>
                                            </a>
                                            <div class="wishlist-close-btn">
                                                <form method="POST" action="{{ route('panel.wishlist.remove', $item['plan']->id) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn trash-3">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="wishlist-footer">
                                            <span class="brand-name">{{ $item['prodName'] }}</span>
                                            <a href="{{ route('panel.orders.create', ['plan' => $item['plan']->id]) }}">
                                                <h6>{{ $item['fullName'] }}</h6>
                                            </a>
                                            <span class="txt-success mt-1">Dostupný</span>
                                            <h6 class="price">
                                                {{ number_format($item['price'], 0, ',', ' ') }} {{ $item['currency'] }}
                                                <small class="f-light f-12">/ {{ $item['plan']->billing_cycle->label() }}</small>
                                            </h6>
                                            <a class="btn bg-primary btn-hover-effect text-white"
                                               href="{{ route('panel.orders.create', ['plan' => $item['plan']->id]) }}">
                                                <i class="fa-solid fa-cart-shopping me-2"></i>Objednat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection
