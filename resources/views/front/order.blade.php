@extends('layouts.front')

@section('title', __('front.pages.order.title'))

@section('content')
    <x-front.page-banner :title="__('front.pages.order.title')" :subtitle="$plan->name">
        <p class="seccolor" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
        <a href="{{ config('app.customer_panel_url') }}" class="btn btn-default-yellow-fill" data-aos="fade-up">
            {{ __('front.nav.client_login') }}
        </a>
    </x-front.page-banner>
@endsection
