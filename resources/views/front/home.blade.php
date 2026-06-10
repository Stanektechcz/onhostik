@extends('layouts.front')

@section('title', __('front.pages.home.title'))

@section('content')
    <x-front.page-banner
        :title="__('front.pages.home.title')"
        :subtitle="__('front.pages.home.subtitle')">
        <x-front.domain-search />
    </x-front.page-banner>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <a href="{{ route('front.webhosting') }}" class="d-block mb-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded" data-aos="fade-up">
                            <i class="ico-drives f-30 purple"></i>
                            <h3 class="title mergecolor pt-3">{{ __('front.nav.webhosting') }}</h3>
                            <p class="seccolor mb-0">{{ __('front.pages.webhosting.subtitle') }}</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('front.gamehosting') }}" class="d-block mb-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded" data-aos="fade-up" data-aos-delay="100">
                            <i class="ico-game f-30 purple"></i>
                            <h3 class="title mergecolor pt-3">{{ __('front.nav.gamehosting') }}</h3>
                            <p class="seccolor mb-0">{{ __('front.pages.gamehosting.subtitle') }}</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('front.vps') }}" class="d-block mb-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded" data-aos="fade-up" data-aos-delay="200">
                            <i class="ico-cloud f-30 purple"></i>
                            <h3 class="title mergecolor pt-3">{{ __('front.nav.vps') }}</h3>
                            <p class="seccolor mb-0">{{ __('front.pages.vps.subtitle') }}</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
