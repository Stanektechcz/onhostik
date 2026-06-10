{{-- Antler top info bar + main navigation, converted from header.html --}}
<div class="sec-bg3 infonews">
    <div class="container-fluid">
        <div class="row">
            <div class="col-6 col-md-7 news">
                <span class="badge bg-purple me-2">{{ __('front.nav.news_badge') }}</span>
                <span>@yield('news_text', __('front.nav.news_default'))</span>
            </div>
            <div class="col-6 col-md-5 link">
                <div class="infonews-nav float-end">
                    <x-front.locale-switcher />
                    <a href="{{ route('front.contact') }}" class="iconews" title="{{ __('front.nav.contact') }}"><i class="ico-bell f-18 w-icon"></i></a>
                    <a href="{{ config('app.customer_panel_url') }}" class="iconews" title="{{ __('front.nav.client_login') }}"><i class="ico-shopping-cart f-18 w-icon"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="menu-wrap">
    <div class="nav-menu">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-2 col-md-2">
                    <a href="{{ route('front.home') }}" title="Onhost.cz">
                        <img class="svg logo-menu d-block" src="{{ asset('front/img/logo.svg') }}" alt="Onhost.cz" width="200" height="50">
                        <img class="svg logo-menu d-none" src="{{ asset('front/img/logo-light.svg') }}" alt="Onhost.cz" width="200" height="50">
                    </a>
                </div>
                <nav id="menu" class="col-10 col-md-10">
                    <div class="navigation float-end">
                        <button class="menu-toggle" title="Menu">
                            <span class="icon"></span><span class="icon"></span><span class="icon"></span>
                        </button>
                        <div class="main-menu nav navbar-nav navbar-right">
                            <x-front.nav-item :href="route('front.home')" :label="__('front.nav.home')" />
                            <x-front.nav-item :href="route('front.webhosting')" :label="__('front.nav.webhosting')" />
                            <x-front.nav-item :href="route('front.gamehosting')" :label="__('front.nav.gamehosting')" badge="new" />
                            <x-front.nav-item :href="route('front.vps')" :label="__('front.nav.vps')" />
                            <x-front.nav-item :href="route('front.domains')" :label="__('front.nav.domains')" />
                            <x-front.nav-item :href="route('front.contact')" :label="__('front.nav.contact')" />
                            <div class="menu-item">
                                <a href="{{ config('app.customer_panel_url') }}" class="btn btn-default-yellow-fill" title="{{ __('front.nav.client_login') }}">
                                    {{ __('front.nav.client_login') }} <i class="fas fa-user ps-1 f-15"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</div>
