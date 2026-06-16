@extends('layouts.front')

@section('title', 'DDoS Ochrana — Nepřetržitá ochrana vašich serverů')
@section('meta_description', 'Automatická DDoS ochrana pro webhosting, VPS a dedikované servery. Filtrujeme útoky v reálném čase — váš web zůstane online i při masivních útocích.')

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">DDoS Ochrana</h1>
                        <div class="subheading text-center">Automatická ochrana před distribuovanými útoky — váš web zůstane online 24/7.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- HOW IT WORKS --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">Jak naše DDoS ochrana funguje?</h2>
                            <p class="seccolor">Veškerý příchozí provoz prochází naší síťovou vrstvou, která v reálném čase identifikuje a filtruje podezřelé požadavky. Legitimní uživatelé se k vám dostanou bez zpomalení.</p>
                        </div>
                        <ul class="list-unstyled seccolor mt-3">
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Ochrana L3/L4/L7 — síťová, transportní i aplikační vrstva</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Automatická detekce a mitigace během sekund</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Žádný downtime — provoz se nepřerušuje</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Propustnost 100 Gbps+ — filtrujeme masivní útoky</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Rate limiting a IP reputace</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Zahrnuto bez příplatku v každém tarifu</li>
                        </ul>
                        <a href="{{ route('front.vps') }}" class="btn btn-default-yellow-fill mt-3">VPS s DDoS ochranou</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0">
                        <div class="wrapper bg-seccolorstyle p-4 rounded">
                            <h5 class="mergecolor mb-3">Vrstvy ochrany</h5>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex align-items-center py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3">L3</span>
                                    <span><strong class="mergecolor">Síťová vrstva</strong> — filtruje IP flood a spoofing</span>
                                </li>
                                <li class="d-flex align-items-center py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3">L4</span>
                                    <span><strong class="mergecolor">Transportní vrstva</strong> — blokuje TCP/UDP flood</span>
                                </li>
                                <li class="d-flex align-items-center py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3">L7</span>
                                    <span><strong class="mergecolor">Aplikační vrstva</strong> — HTTP flood, slowloris</span>
                                </li>
                                <li class="d-flex align-items-center py-2 seccolor">
                                    <span class="badge bg-purple me-3">WAF</span>
                                    <span><strong class="mergecolor">ModSecurity WAF</strong> — OWASP ruleset</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES GRID --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Co je zahrnuto v ochraně</h2>
                        <p class="section-subheading">Vícevrstvá ochrana aktivní 24 hodin denně.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-protection', 'badge' => 'Auto',    'title' => 'Automatická mitigace',  'text' => 'Detekce a potlačení útoku proběhne automaticky během sekund bez zásahu admina.'],
                        ['icon' => 'icon-speed', 'badge' => '100 Gbps','title' => 'Vysoká propustnost',   'text' => 'Síťová kapacita 100 Gbps+ — zvládneme i největší volumetrické útoky.'],
                        ['icon' => 'icon-cpu',        'badge' => 'AI',      'title' => 'Strojové učení',       'text' => 'Naše systémy se učí z vzorů útoků a adaptují pravidla v reálném čase.'],
                        ['icon' => 'icon-lock',         'badge' => 'WAF',     'title' => 'Web Application Firewall','text' => 'ModSecurity s OWASP Core Rule Set chrání před SQL injection, XSS a dalšími.'],
                        ['icon' => 'icon-speed', 'badge' => '24/7',    'title' => 'Monitoring & Alerty',  'text' => 'Sledujeme síťový provoz nepřetržitě a upozorňujeme na anomálie okamžitě.'],
                        ['icon' => 'icon-drives',      'badge' => 'Scrubbing','title' => 'Scrubbing centra',    'text' => 'Provoz je přesměrován přes scrubbing centrum, kde se útok odfiltruje.'],
                    ] as $f)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $f['badge'] }}</div>
                                <i class="{{ $f['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $f['title'] }}</div>
                                <p class="subtitle seccolor">{{ $f['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">DDoS ochrana zahrnuta v každém tarifu</h2>
                <p class="text-white-50 pb-3">Nepřiplácíte za bezpečnost — základní DDoS ochrana je součástí všech hostingových, VPS i dedikovaných plánů.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill me-2">Webhosting</a>
                <a href="{{ route('front.vps') }}" class="btn btn-default-grad-purple-fill">Cloud VPS</a>
            </div>
        </div>
    </section>

@endsection
