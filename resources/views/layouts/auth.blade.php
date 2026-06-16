<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link href="{{ asset('front/img/favicon.ico') }}" rel="shortcut icon">
    <title>@yield('title', 'Onhost.cz') — Onhost.cz</title>

    <link href="{{ asset('front/fonts/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/fonts/fonts.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/aos.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/vendors.min.css') }}" rel="stylesheet">
    <link href="{{ asset('front/css/theme.min.css') }}" rel="stylesheet">

    <script src="{{ asset('front/js/jquery.min.js') }}"></script>
    <script src="{{ asset('front/js/gdpr-cookie.min.js') }}"></script>
    <script src="{{ asset('front/js/popper.min.js') }}"></script>
    <script defer src="{{ asset('front/js/bootstrap.min.js') }}"></script>
    <script defer src="{{ asset('front/js/aos.min.js') }}"></script>
    <script defer src="{{ asset('front/js/scripts.min.js') }}"></script>
    <script defer src="{{ asset('front/js/settings-init.js') }}"></script>
</head>
<body>
<div class="box-container limit-width">

    <section id="settings"></section>

    <div id="spinner-area">
        <div class="spinner">
            <div class="double-bounce1"></div>
            <div class="double-bounce2"></div>
            <div class="spinner-txt">Onhost…</div>
        </div>
    </div>

    {{-- Antler fullrock auth layout --}}
    <div class="fullrock config sec-bg3 motpath bg-colorstyle">
        <a href="{{ route('front.home') }}" class="closebtn" title="Zpět na úvod">
            <img class="svg closer bg-transparent" src="{{ asset('front/img/closer.svg') }}" alt="Zavřít" width="20" height="20">
        </a>
        <section class="fullrock-content">
            <div class="container">
                <a href="{{ route('front.home') }}" title="Onhost.cz" class="d-inline-block mb-4">
                    <img class="svg logo-menu d-block" src="{{ asset('front/img/logo.svg') }}" alt="Onhost.cz" width="180" height="45">
                    <img class="svg logo-menu d-none" src="{{ asset('front/img/logo-light.svg') }}" alt="Onhost.cz" width="180" height="45">
                </a>
                <div class="sec-main sec-bg1 bg-seccolorstyle noshadow">
                    <div class="randomline">
                        <div class="bigline"></div>
                        <div class="smallline"></div>
                    </div>
                    @yield('content')
                </div>
            </div>
        </section>
    </div>

</div>
</body>
</html>
