{{-- Antler top info bar + main navigation --}}
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

                            {{-- Home --}}
                            <x-front.nav-item :href="route('front.home')" :label="__('front.nav.home')" />

                            {{-- Hosting mega-menu --}}
                            <div class="menu-item menu-item-has-children">
                                <a href="{{ route('front.webhosting') }}" class="mergecolor" title="Hosting">{{ __('front.nav.hosting') }}</a>
                                <div class="sub-menu menu-large bg-colorstyle">
                                    <div class="service-list">
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.webhosting') }}" class="menu-item mergecolor" title="{{ __('front.nav.webhosting') }}">{{ __('front.nav.webhosting') }}</a>
                                                <p class="text-muted">NVMe SSD, PHP 8.3, SSL zdarma</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.wordpress') }}" class="menu-item mergecolor" title="{{ __('front.pages.wordpress.title') }}">{{ __('front.pages.wordpress.title') }}</a>
                                                <p class="text-muted">Automatické aktualizace a zálohy</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.managed') }}" class="menu-item mergecolor" title="{{ __('front.nav.managed') }}">{{ __('front.nav.managed') }}</a>
                                                <p class="text-muted">Plná správa serveru naším týmem</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.vps') }}" class="menu-item mergecolor" title="{{ __('front.nav.vps') }}">{{ __('front.nav.vps') }}</a>
                                                <div class="menu badge feat bg-purple">KVM</div>
                                                <p class="text-muted">Root přístup, NVMe SSD od 149 Kč</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.dedicated') }}" class="menu-item mergecolor" title="{{ __('front.nav.dedicated') }}">{{ __('front.nav.dedicated') }}</a>
                                                <p class="text-muted">Výhradní hardware, IPMI přístup</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.gamehosting') }}" class="menu-item mergecolor" title="{{ __('front.nav.gamehosting') }}">{{ __('front.nav.gamehosting') }}</a>
                                                <div class="menu badge feat bg-purple">new</div>
                                                <p class="text-muted">Minecraft, CS2, ARK — Pterodactyl</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.mailhosting') }}" class="menu-item mergecolor" title="{{ __('front.nav.mailhosting') }}">{{ __('front.nav.mailhosting') }}</a>
                                                <p class="text-muted">Firemní e-mail, SPF/DKIM/DMARC</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.ssl') }}" class="menu-item mergecolor" title="{{ __('front.pages.ssl.title') }}">{{ __('front.pages.ssl.title') }}</a>
                                                <p class="text-muted">Let's Encrypt, DV, Wildcard</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.reseller') }}" class="menu-item mergecolor" title="{{ __('front.pages.reseller.title') }}">{{ __('front.pages.reseller.title') }}</a>
                                                <p class="text-muted">White-label, vlastní ceny</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.email-security') }}" class="menu-item mergecolor" title="E-mailová bezpečnost">E-mailová bezpečnost</a>
                                                <p class="text-muted">Anti-spam, MX záloha, karanténa</p>
                                            </div>
                                        </div>
                                        <div class="service">
                                            <div class="media-body">
                                                <a href="{{ route('front.database') }}" class="menu-item mergecolor" title="Databáze (DBaaS)">Databáze (DBaaS)</a>
                                                <p class="text-muted">MySQL, MariaDB, plně spravováno</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Domains --}}
                            <x-front.nav-item :href="route('front.domains')" :label="__('front.nav.domains')" />

                            {{-- Support dropdown --}}
                            <div class="menu-item menu-item-has-children">
                                <a href="{{ route('front.support') }}" class="mergecolor" title="{{ __('front.nav.support') }}">{{ __('front.nav.support') }}</a>
                                <ul class="sub-menu dropdown bg-colorstyle">
                                    <li class="menu-item">
                                        <a href="{{ route('front.support') }}" class="mergecolor" title="{{ __('front.nav.contact') }}">{{ __('front.nav.contact') }}</a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('front.kb') }}" class="mergecolor" title="{{ __('front.pages.kb.title') }}">{{ __('front.pages.kb.title') }}</a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('front.faq') }}" class="mergecolor" title="{{ __('front.pages.faq.title') }}">{{ __('front.pages.faq.title') }}</a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('front.blog.index') }}" class="mergecolor" title="{{ __('front.pages.blog.title') }}">{{ __('front.pages.blog.title') }}</a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('front.about') }}" class="mergecolor" title="{{ __('front.nav.about') }}">{{ __('front.nav.about') }}</a>
                                    </li>
                                </ul>
                            </div>

                            {{-- Client login CTA --}}
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
