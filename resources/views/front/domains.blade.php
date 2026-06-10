@extends('layouts.front')

@section('title', __('front.pages.domains.title'))

@section('content')
    <x-front.page-banner
        :title="__('front.pages.domains.title')"
        :subtitle="__('front.pages.domains.subtitle')">
        <x-front.domain-search />

        @if(session('domain_check_status'))
            <div class="alert alert-info mt-3" role="alert">
                {{ session('domain_check_status') }}
            </div>
        @endif
    </x-front.page-banner>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <p class="seccolor mb-0" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
        </div>
    </section>
@endsection
