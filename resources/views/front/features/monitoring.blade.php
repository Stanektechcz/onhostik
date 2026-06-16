@extends('layouts.front')

@section('title', __('front.pages.monitoring_feature.title'))
@section('meta_description', 'Monitoring 24/7 OnHost.cz — nepřetržité sledování dostupnosti webu, SSL certifikátů a výkonu s okamžitými notifikacemi.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.monitoring_feature.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.monitoring_feature.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Monitoring v ceně tarifu</div>
                            <ul><li><i class="fas fa-check-circle"></i> Kontrola každou minutu</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Sledování SSL certifikátu</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> E-mailové notifikace</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Historie incidentů</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ FEATURES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Co monitoring hlídá</h2>
                        <p class="section-subheading">Výpadek zachytíme dříve, než vám zavolá zákazník.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-speed',  'badge' => '1 min',   'title' => 'Kontroly každou minutu',   'text' => 'HTTP/HTTPS dostupnost ověřujeme z monitorovacích bodů každou minutu — výpadek zachytíme dříve, než vám zavolá zákazník.'],
                        ['icon' => 'icon-lock',          'badge' => 'SSL',     'title' => 'Platnost SSL',              'text' => "Hlídáme expiraci SSL certifikátu a upozorníme vás 30, 14 a 7 dní předem. Certifikáty Let's Encrypt obnovujeme automaticky."],
                        ['icon' => 'icon-protection',   'badge' => 'Alert',   'title' => 'Notifikace',                'text' => 'E-mail a notifikace do klientské zóny ihned při výpadku a při obnovení dostupnosti.'],
                        ['icon' => 'icon-diskette',       'badge' => 'Log',     'title' => 'Historie incidentů',        'text' => 'Přehledná timeline výpadků s příčinami a dobou výpadku — vidíte vše ve svém dashboardu.'],
                        ['icon' => 'icon-drives',       'badge' => 'SLA',     'title' => 'SLA podklady',              'text' => 'Záznamy monitoringu slouží jako podklad pro výpočet kompenzací dle SLA. Transparentně a automaticky.'],
                        ['icon' => 'icon-cpu',         'badge' => 'Zdarma',  'title' => 'V každém tarifu',           'text' => 'Monitoring dostupnosti a SSL je aktivní automaticky pro každou provozovanou službu bez příplatku.'],
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

    {{-- ============ HIGHLIGHT ============ --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">Monitoring se aktivuje automaticky</h2>
                            <p class="seccolor">Po aktivaci webhostingového tarifu se monitorig spouští bez jakékoli konfigurace. Stav webu a SSL sledujeme od první minuty — 24 hodin denně, 7 dní v týdnu.</p>
                        </div>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">{{ __('front.home.cta_button') }}</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-speed purple" style="font-size:100px;opacity:.8"></i>
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
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Webhosting</div>
                                    <div class="description seccolor">NVMe hosting s monitoringem v ceně od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.features.backups') }}" class="help-item" title="Zálohy">
                                <div class="img"><i class="icon-diskette f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Automatické zálohy</div>
                                    <div class="description seccolor">Denní zálohy s retencí 14 dní a obnovou jedním kliknutím.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Podpora</div>
                                    <div class="description seccolor">Reakce na urgentní výpadky do 1 hodiny, 24/7.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
