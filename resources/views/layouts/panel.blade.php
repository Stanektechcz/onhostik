<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') | Onhost.cz</title>
    <link rel="icon" href="{{ asset('panel/images/favicon.png') }}" type="image/x-icon">

    {{-- Cuba CSS stack --}}
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/icofont.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/themify.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/animate.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('panel/css/style.css') }}">
</head>
<body>
{{-- Cuba loader --}}
<div class="loader-wrapper">
    <div class="loader-index"><span></span></div>
    <svg><defs></defs><filter id="goo"><fegaussianblur in="SourceGraphic" stddeviation="11" result="blur"></fegaussianblur><fecolormatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"></fecolormatrix></filter></svg>
</div>

<div class="tap-top"><i data-feather="chevrons-up"></i></div>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
    <x-panel.header />

    <div class="page-body-wrapper">
        <x-panel.sidebar />

        <div class="page-body">
            <x-panel.breadcrumb :title="$breadcrumbTitle ?? null" :items="$breadcrumbItems ?? []" />
            @yield('content')
        </div>

        <footer class="footer">
            <div class="container-fluid">
                <div class="grid grid-cols-12">
                    <div class="col-span-12 footer-copyright text-center">
                        <p class="mb-0">© {{ date('Y') }} Onhost.cz</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

{{-- Cuba JS stack --}}
<script src="{{ asset('panel/js/jquery.min.js') }}"></script>
<script src="{{ asset('panel/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('panel/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('panel/js/icons/feather-icon/feather-icon.js') }}"></script>
<script src="{{ asset('panel/js/scrollbar/simplebar.min.js') }}"></script>
<script src="{{ asset('panel/js/scrollbar/custom.js') }}"></script>
<script src="{{ asset('panel/js/config.js') }}"></script>
<script src="{{ asset('panel/js/sidebar-menu.js') }}"></script>
<script src="{{ asset('panel/js/sidebar-pin.js') }}"></script>
@livewireScripts
@stack('scripts')
<script src="{{ asset('panel/js/script.js') }}"></script>
</body>
</html>
