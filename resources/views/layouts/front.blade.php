<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', __('front.meta.default_description'))">
    <link href="{{ asset('front/img/favicon.ico') }}" rel="shortcut icon">
    <title>@yield('title', 'Onhost.cz') — Onhost.cz</title>

    {{-- Antler font + CSS stack --}}
    <link href="{{ asset('front/fonts/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/fonts/fonts.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/aos.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/vendors.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/theme.min.css') }}" rel="stylesheet">
    @stack('styles')

    <script src="{{ asset('front/js/jquery.min.js') }}"></script>
    <script src="{{ asset('front/js/popper.min.js') }}"></script>
    <script defer src="{{ asset('front/js/bootstrap.min.js') }}"></script>
    <script defer src="{{ asset('front/js/slick.min.js') }}"></script>
    <script defer src="{{ asset('front/js/aos.min.js') }}"></script>
    <script defer src="{{ asset('front/js/swiper.min.js') }}"></script>
    <script defer src="{{ asset('front/js/jquery.lazyload-any.min.js') }}"></script>
    <script defer src="{{ asset('front/js/scripts.min.js') }}"></script>
</head>
<body>
<div class="box-container limit-width">

    {{-- Loading spinner (Antler) --}}
    <div id="spinner-area">
        <div class="spinner">
            <div class="double-bounce1"></div>
            <div class="double-bounce2"></div>
            <div class="spinner-txt">Onhost…</div>
        </div>
    </div>

    <x-front.header />

    @yield('content')

    <x-front.footer />
</div>
@stack('scripts')
</body>
</html>
