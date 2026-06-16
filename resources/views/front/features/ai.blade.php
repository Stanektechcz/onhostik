@extends('layouts.front')

@section('title', __('front.pages.ai_feature.title'))
@section('meta_description', 'AI asistent OnHost.cz — automatická doporučení tarifu, vysvětlení DNS, návrhy řešení problémů a podpora 24/7.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.ai_feature.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.ai_feature.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Co AI asistent umí</div>
                            <ul><li><i class="fas fa-check-circle"></i> Doporučení tarifu</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Vysvětlení DNS záznamů</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Diagnostika problémů</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Dostupný 24/7 bez příplatku</li></ul>
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
                        <h2 class="section-heading">Možnosti AI asistenta</h2>
                        <p class="section-subheading">Chytrá pomoc vždy po ruce — bez čekání na operátora.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-cpu',         'badge' => 'AI',       'title' => 'Doporučení tarifu',    'text' => 'Popište svůj projekt a AI doporučí optimální tarif — webhosting, VPS nebo managed hosting.'],
                        ['icon' => 'ico-globe',        'badge' => 'DNS',      'title' => 'Vysvětlení DNS',        'text' => 'Nejasné DNS záznamy? AI vysvětlí rozdíl mezi A, CNAME, MX záznamy a pomůže s konfigurací.'],
                        ['icon' => 'icon-support',      'badge' => '24/7',     'title' => 'Okamžité odpovědi',     'text' => 'Přímé odpovědi na otázky o PHP, MySQL, SSL a e-mailu bez čekání na operátora.'],
                        ['icon' => 'icon-speed',  'badge' => 'Analýza',  'title' => 'Diagnostika problémů',  'text' => 'Pomalý web, chyba 500, odmítnutý e-mail — AI analyzuje příznaky a navrhne řešení.'],
                        ['icon' => 'icon-protection',   'badge' => 'Sec',      'title' => 'Bezpečnostní rady',     'text' => 'Doporučení k hardening WordPressu, nastavení firewallu nebo revize .htaccess souboru.'],
                        ['icon' => 'icon-drives',       'badge' => 'Zdarma',   'title' => 'Součást všech tarifů',  'text' => 'AI asistent je dostupný v klientské zóně bez příplatku u každého aktivního tarifu.'],
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
                            <h2 class="fw-bold mb-3 mergecolor">AI asistent v klientské zóně</h2>
                            <p class="seccolor">Ihned po aktivaci tarifu máte k dispozici AI asistenta přímo v dashboardu. Žádná registrace navíc, žádný příplatek — součást každého tarifu OnHost.</p>
                        </div>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">{{ __('front.home.cta_button') }}</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-cpu purple" style="font-size:100px;opacity:.8"></i>
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
                                    <div class="description seccolor">NVMe hosting s AI asistencí od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.features.monitoring') }}" class="help-item" title="Monitoring">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Monitoring 24/7</div>
                                    <div class="description seccolor">Nepřetržité hlídání dostupnosti webu a SSL certifikátů.</div>
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
                                    <div class="description seccolor">Ticketová podpora s reakcí do 1 hodiny pro urgentní výpadky.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
