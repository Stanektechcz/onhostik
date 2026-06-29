@extends('layouts.panel')

@php
    use App\Domains\Shared\Support\MoneyFormatter;
    $breadcrumbTitle = __('panel.services.change_plan');
    $breadcrumbItems = [
        __('panel.nav.services') => route('panel.services.index'),
        $service->label          => route('panel.services.show', $service),
        __('panel.services.change_plan') => '',
    ];
@endphp

@section('title', __('panel.services.change_plan') . ' — ' . $service->label)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Current plan info --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card :title="__('panel.services.current_plan')">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="bg-light-primary rounded p-2">
                        <i data-feather="server" class="font-primary"></i>
                    </div>
                    <div>
                        <p class="f-w-600 mb-0">{{ $service->label }}</p>
                        <p class="f-light f-12 mb-0">{{ $service->product?->name }}</p>
                    </div>
                </div>
                <x-panel.status-badge :status="$service->status" />

                @if(!empty($service->resources))
                    <hr class="my-3">
                    <p class="f-light f-12 mb-2">{{ __('panel.services.resources') }}</p>
                    @foreach($service->resources as $key => $val)
                        <div class="d-flex justify-content-between f-12 mb-1">
                            <span class="f-light">{{ __("front.resources.$key", [], 'cs') }}</span>
                            <span class="f-w-500">{{ is_array($val) ? json_encode($val) : $val }}</span>
                        </div>
                    @endforeach
                @endif

                <div class="mt-3">
                    <a href="{{ route('panel.services.show', $service) }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px"></i>
                        {{ __('panel.common.back') }}
                    </a>
                </div>
            </x-panel.card>
        </div>

        {{-- Available plans --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card :title="__('panel.services.available_plans')">
                @if($availablePlans->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="package" style="width:36px;height:36px" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">{{ __('panel.services.no_other_plans') }}</p>
                    </div>
                @else
                    <div class="alert alert-light-info d-flex gap-2 align-items-start py-2 px-3 f-12 mb-3">
                        <i data-feather="info" style="width:14px;height:14px;margin-top:2px;flex-shrink:0" class="font-info"></i>
                        <span>{{ __('panel.services.plan_change_note') }}</span>
                    </div>

                    <div class="row g-3">
                        @foreach($availablePlans as $plan)
                            @php
                                $price = $plan->priceFor($currency);
                                $currentPlan = $currentPlanId ? \App\Domains\Products\Models\PricingPlan::find($currentPlanId) : null;
                                $currentPrice = $currentPlan?->supportsCurrency($currency)
                                    ? $currentPlan->priceFor($currency)->getAmount()->toFloat() / max(1, $currentPlan->billing_cycle->months())
                                    : null;
                                $newPrice = $price->getAmount()->toFloat() / max(1, $plan->billing_cycle->months());
                                $isUpgrade = $currentPrice === null || $newPrice > $currentPrice;
                                $upgradeLabel = $isUpgrade ? 'primary' : 'warning';
                                $upgradeText  = $isUpgrade ? __('panel.services.upgrade') : __('panel.services.downgrade');
                            @endphp
                            <div class="col-span-6 md:col-span-12">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="f-w-600">{{ $plan->name }}</span>
                                            <span class="badge badge-light-{{ $upgradeLabel }} ms-2 f-11">{{ $upgradeText }}</span>
                                        </div>
                                        <div class="text-end">
                                            <div class="f-w-600 font-primary">{{ MoneyFormatter::format($price) }}</div>
                                            <div class="f-light f-11">/ {{ $plan->billing_cycle->label() }}</div>
                                        </div>
                                    </div>

                                    @if(!empty($plan->resources))
                                        <ul class="list-unstyled f-12 f-light mb-3">
                                            @foreach($plan->resources as $rk => $rv)
                                                <li class="mb-1">
                                                    <i data-feather="check" style="width:11px;height:11px" class="font-success me-1"></i>
                                                    {{ __("front.resources.$rk", [], 'cs') }}: <strong>{{ is_array($rv) ? json_encode($rv) : $rv }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <form method="POST" action="{{ route('panel.services.apply-change-plan', $service) }}">
                                        @csrf
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                        <button type="submit" class="btn btn-{{ $upgradeLabel }} btn-sm w-100">
                                            {{ $upgradeText }} → {{ $plan->name }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel.card>
        </div>

    </div>
</div>
@endsection
