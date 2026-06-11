@extends('layouts.front')

@section('title', __('front.pages.order.title'))

@section('content')
    <x-front.page-banner :title="__('front.pages.order.title')" :subtitle="$plan->name" />

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="row justify-content-md-center">
                <div class="col-md-8 col-lg-6" data-aos="fade-up">
                    <div class="wrapper">
                        <div class="top-content bg-seccolorstyle topradius bottomradius">
                            <div class="title">{{ __('front.order.plan_summary') }}</div>
                            <div class="fromer seccolor">{{ $plan->product?->name }} — {{ $plan->name }}</div>
                            @if($plan->tagline)
                                <div class="seccolor f-14 mb-2">{{ $plan->tagline }}</div>
                            @endif
                            <div class="price mergecolor">
                                {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
                            </div>
                            <p class="seccolor f-14">{{ __('front.order.vat_note') }}</p>

                            @auth
                                <a href="{{ route('panel.orders.create', ['plan' => $plan->id]) }}"
                                   class="btn btn-default-yellow-fill">
                                    {{ __('front.order.continue') }}
                                </a>
                            @else
                                <p class="seccolor f-14">{{ __('front.order.login_first') }}</p>
                                <a href="{{ route('login') }}" class="btn btn-default-yellow-fill me-2">
                                    {{ __('front.nav.client_login') }}
                                </a>
                                <a href="{{ route('register') }}" class="btn btn-default-yellow">
                                    {{ __('front.auth.register_button') }}
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
