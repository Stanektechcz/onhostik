@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.new_order'))

@section('title', __('panel.nav.new_order'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.orders.store') }}">
            @csrf

            <x-panel.card :title="__('panel.orders.choose_plan')">
                <div class="row g-3">
                    @foreach($plans as $plan)
                        <div class="col-md-6 col-xl-4">
                            <label class="card h-100 mb-0 p-3 b-light border {{ (int) old('pricing_plan_id', $selectedPlan) === $plan->id ? 'border-primary' : '' }}">
                                <div class="d-flex align-items-start">
                                    <input class="form-check-input me-2 mt-1" type="radio" name="pricing_plan_id"
                                           value="{{ $plan->id }}"
                                           @checked((int) old('pricing_plan_id', $selectedPlan) === $plan->id)>
                                    <div>
                                        <h5 class="mb-1">{{ $plan->product?->name }} {{ $plan->name }}</h5>
                                        @if($plan->tagline)
                                            <p class="f-light f-12 mb-1">{{ $plan->tagline }}</p>
                                        @endif
                                        <p class="f-w-600 mb-1">
                                            <x-panel.money :money="$plan->priceFor($customer->preferred_currency)" />
                                            <span class="f-light f-12">/ {{ $plan->billing_cycle->label() }}</span>
                                        </p>
                                        <ul class="f-light f-12 mb-0 ps-3">
                                            @foreach($plan->resources ?? [] as $key => $value)
                                                <li>{{ __("front.resources.$key") }}: {{ $value }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('pricing_plan_id')
                    <p class="text-danger mt-2 mb-0">{{ $message }}</p>
                @enderror
            </x-panel.card>

            <x-panel.card :title="__('panel.orders.domain_label')">
                <div class="mb-3">
                    <input class="form-control" type="text" name="domain" value="{{ old('domain') }}"
                           placeholder="{{ __('front.domains.search_placeholder') }}">
                    <small class="f-light">{{ __('panel.orders.domain_hint') }}</small>
                    @error('domain')
                        <p class="text-danger mt-1 mb-0">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="register_domain" id="register_domain"
                           value="1" @checked(old('register_domain'))>
                    <label class="form-check-label" for="register_domain">{{ __('panel.orders.register_domain') }}</label>
                </div>

                @if($mockMode)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="simulate_failure" id="simulate_failure"
                               value="1" @checked(old('simulate_failure'))>
                        <label class="form-check-label f-light" for="simulate_failure">
                            {{ __('panel.orders.simulate_failure') }}
                            <span class="badge badge-light-warning">{{ __('panel.admin.mock_badge') }}</span>
                        </label>
                    </div>
                @endif
            </x-panel.card>

            <button type="submit" class="btn btn-primary mb-4">{{ __('panel.orders.submit') }}</button>
        </form>
    </div>
@endsection
