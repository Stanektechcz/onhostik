@extends('layouts.front')

@section('title', __('front.pages.managed.title'))
@section('meta_description', 'Managed hosting Onhost.cz — hosting, o který se kompletně staráme my. Aktualizace CMS, zálohy, bezpečnostní monitoring a podpora.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.managed.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.managed.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Co děláme za vás</div>
                            <ul><li><i class="fas fa-check-circle"></i> Aktualizace OS a PHP</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Aktualizace CMS a pluginů</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Denní zálohy (30 dní)</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Bezpečnostní monitoring</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Prioritní podpora</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Onboarding bez výpadku</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING ============ --}}
    <section class="pricing special sec-uping pb-5 bg-colorstyle specialposition">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            @if($plans->isNotEmpty())
                <div class="row justify-content-center">
                    @foreach($plans as $plan)
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="wrapper price-container text-start noshadow">
                                @if($plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price-content">
                                        <div class="price mergecolor">
                                            {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                            <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">{{ __('front.pricing.order_now') }}</a>
                                </div>
                                <ul class="list-info bg-purple">
                                    @foreach($plan->resources ?? [] as $key => $value)
                                        <li>
                                            <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                                            <div>{{ __("front.resources.$key") }}<br><span>{{ \App\Domains\Shared\Support\ResourceFormatter::format($key, $value) }}</span></div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="seccolor f-14 mt-4 text-center">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>

    {{-- ============ FEATURES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Co děláme za vás</h2>
                        <p class="section-subheading">Kompletní péče o váš web — bez vaší technické intervence.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-speed',       'badge' => 'Auto',     'title' => 'Správa serveru',          'text' => 'Aktualizace OS, PHP, databází a bezpečnostní záplaty bez vašeho zásahu.'],
                        ['icon' => 'icon-drives',       'badge' => 'WP / JS',  'title' => 'Aktualizace CMS',         'text' => 'WordPress, Joomla, PrestaShop — jádro, šablony i pluginy aktualizujeme a testujeme na záloze.'],
                        ['icon' => 'icon-diskette',       'badge' => '30 dní',   'title' => 'Zálohy a obnova',         'text' => 'Denní zálohy s retencí 30 dní a rychlá obnova kdykoli o to požádáte.'],
                        ['icon' => 'icon-protection',   'badge' => '24/7',     'title' => 'Bezpečnostní monitoring', 'text' => 'Skenování malware, sledování podezřelých přihlášení a okamžité upozornění na incidenty.'],
                        ['icon' => 'icon-speed',  'badge' => 'Cache',    'title' => 'Optimalizace výkonu',     'text' => 'Nastavení cache, CDN a databázových dotazů pro maximálně svižné načítání.'],
                        ['icon' => 'icon-support',      'badge' => '1h SLA',   'title' => 'Prioritní podpora',       'text' => 'Vyhrazená fronta ticketů s reakcí do 1 hodiny a přímý kontakt na váš technický tým.'],
                    ] as $feature)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $feature['badge'] }}</div>
                                <i class="{{ $feature['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $feature['title'] }}</div>
                                <p class="subtitle seccolor">{{ $feature['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURE HIGHLIGHTS ============ --}}
    <section class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-6">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Ideální pro koho?</h2>
                            <ul class="list-unstyled seccolor">
                                <li class="py-2"><i class="fas fa-check purple pe-2"></i>Agentury a freelanceři spravující weby klientů</li>
                                <li class="py-2"><i class="fas fa-check purple pe-2"></i>E-shopy, kde každý výpadek znamená ztrátu tržeb</li>
                                <li class="py-2"><i class="fas fa-check purple pe-2"></i>Firmy bez vlastního IT oddělení</li>
                                <li class="py-2"><i class="fas fa-check purple pe-2"></i>Projekty s přísnými SLA požadavky</li>
                                <li class="py-2"><i class="fas fa-check purple pe-2"></i>Weby zpracovávající citlivé nebo regulované údaje</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-protection purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-drives purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Onboarding na míru</h2>
                            <p class="seccolor">Přeneseme vaše stávající weby a nastavíme vše potřebné — DNS, SSL, cache, e-maily a zálohy. Onboarding probíhá bez výpadku.</p>
                        </div>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill mt-3">Nezávazná konzultace</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Standardní webhosting">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Standardní webhosting</div>
                                    <div class="description seccolor">Vlastní správa, nižší cena — NVMe hosting od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.vps') }}" class="help-item" title="Cloud VPS">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Cloud VPS</div>
                                    <div class="description seccolor">Plný root přístup, KVM virtualizace, NVMe SSD.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Poradit se">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Nevíte si rady?</div>
                                    <div class="description seccolor">Napište nám — poradíme s výběrem tarifu a rozsahem správy.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
