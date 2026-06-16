@props([
    'plan',                 // App\Domains\Products\Models\PricingPlan
    'currency',             // App\Domains\Shared\Enums\Currency
    'featured' => false,
])
@php($price = $plan->priceFor($currency))
<div class="col-md-12 col-lg-4">
    <div class="wrapper {{ $featured ? 'recommended' : '' }}">
        @if($featured)
            <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
        @endif
        <div class="top-content bg-seccolorstyle topradius">
            <div class="title">{{ $plan->name }}</div>
            @if($plan->tagline)
                <div class="fromer seccolor">{{ $plan->tagline }}</div>
            @endif
            <div class="price mergecolor">
                {{ \App\Domains\Shared\Support\MoneyFormatter::format($price) }}
                <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
            </div>
            <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">
                {{ __('front.pricing.order_now') }}
            </a>
        </div>
        <ul class="list-info bg-purple">
            @foreach($plan->resources ?? [] as $key => $value)
                <li>
                    <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                    <div>{{ __("front.resources.$key") }}<br><span>{{ $value }}</span></div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
