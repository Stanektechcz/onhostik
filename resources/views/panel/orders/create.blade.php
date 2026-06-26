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
                    <p class="f-light f-14 mb-3">Aktuálně nejsou dostupné žádné aktivní tarify. Zkuste to prosím za chvíli.</p>
                    <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-primary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px;"></i>
                        Zpět na objednávky
                    </a>
                </div>
            </div>
        @else
        <form method="POST" action="{{ route('panel.orders.store') }}">
            @csrf

            {{-- Step indicator --}}
            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-light-primary rounded-circle f-14 px-2 py-1">1</span>
                            <span class="f-w-500">Vyberte tarif</span>
                        </div>
                        <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-light-secondary rounded-circle f-14 px-2 py-1">2</span>
                            <span class="f-light">Doména (volitelně)</span>
                        </div>
                        <i data-feather="chevron-right" style="width:14px;height:14px;" class="text-muted"></i>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-light-secondary rounded-circle f-14 px-2 py-1">3</span>
                            <span class="f-light">Potvrzení a faktura</span>
                        </div>
                        @if($mockMode)
                            <span class="badge badge-light-warning ms-auto">MOCK MODE</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Step 1: Plan selection --}}
            <x-panel.card :title="__('panel.orders.choose_plan')">
                <div class="row g-3">
                    @foreach($plans as $plan)
                        <div class="col-md-6 col-xl-4">
                            <label class="card h-100 mb-0 p-3 b-light border hover-border-primary {{ (int) old('pricing_plan_id', $selectedPlan) === $plan->id ? 'border-primary bg-light-primary' : '' }}"
                                   style="cursor:pointer; transition: border-color 0.15s;">
                                <div class="d-flex align-items-start">
                                    <input class="form-check-input me-2 mt-1 flex-shrink-0" type="radio"
                                           name="pricing_plan_id" value="{{ $plan->id }}"
                                           @checked((int) old('pricing_plan_id', $selectedPlan) === $plan->id)>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1">{{ $plan->product?->name }} {{ $plan->name }}</h5>
                                        @if($plan->tagline)
                                            <p class="f-light f-12 mb-2">{{ $plan->tagline }}</p>
                                        @endif
                                        <p class="f-w-600 mb-2 txt-primary">
                                            <x-panel.money :money="$plan->priceFor($customer->preferred_currency)" />
                                            <span class="f-light f-12 txt-secondary">/ {{ $plan->billing_cycle->label() }}</span>
                                        </p>
                                        @if(!empty($plan->resources))
                                        <ul class="f-light f-12 mb-0 ps-3">
                                            @foreach($plan->resources as $key => $value)
                                                <li>{{ __("front.resources.$key") }}: <span class="f-w-500">{{ $value }}</span></li>
                                            @endforeach
                                        </ul>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('pricing_plan_id')
                    <p class="text-danger mt-2 mb-0 f-12">{{ $message }}</p>
                @enderror
            </x-panel.card>

            {{-- Step 2: Domain --}}
            <x-panel.card :title="__('panel.orders.domain_label')">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label f-12 f-light">Doménové jméno</label>
                        <input class="form-control" type="text" name="domain" value="{{ old('domain') }}"
                               placeholder="{{ __('front.domains.search_placeholder') }}">
                        <small class="f-light f-12">{{ __('panel.orders.domain_hint') }}</small>
                        @error('domain')
                            <p class="text-danger mt-1 mb-0 f-12">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div>
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
            </x-panel.card>

            {{-- Submit --}}
            <div class="d-flex align-items-center gap-3 mb-4">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="check" style="width:14px;height:14px;"></i>
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
@endsection
