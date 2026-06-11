@extends('layouts.front')

@section('title', __('front.pages.webhosting.title'))

@section('content')
    <x-front.page-banner :title="__('front.pages.webhosting.title')" :subtitle="__('front.pages.webhosting.subtitle')" />

    <section class="pricing bg-colorstyle pb-150">
        <div class="container">
            @if($product !== null)
                <div class="randomline mb-4" data-aos="fade-up">
                    <div class="big mergecolor">{{ $product->name }}</div>
                    <p class="seccolor">{{ $product->description }}</p>
                </div>

                <div class="row justify-content-md-center" data-aos="fade-up">
                    @foreach($plans as $plan)
                        <x-front.pricing-card
                            :plan="$plan"
                            :currency="$currency"
                            :featured="(bool) $plan->is_featured"
                        />
                    @endforeach
                </div>

                <p class="seccolor f-14 mt-4 mb-0" data-aos="fade-up">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor mb-0" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>
@endsection
