@extends('layouts.front')

@section('title', 'Kolokace — Umístěte svůj server do našeho datacentra')
@section('meta_description', 'Profesionální kolokace serverů v moderním datacentru v ČR. Redundantní napájení, chlazení a konektivita. SLA 99,99%, fyzická bezpečnost a 24/7 technická podpora.')

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">Kolokace serverů</h1>
                        <div class="subheading text-center">Umístěte svůj hardware do našeho moderního datacentra — my zajistíme napájení, chlazení a konektivitu.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SPECS --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">Tier III datacenter v srdci ČR</h2>
                            <p class="seccolor">Naše datacentry splňují standardy Tier III s dostupností 99,982 %. Redundantní napájení (2N UPS), chlazení N+1 a konektivita s víceúrovňovým BGP routingem.</p>
                        </div>
                        <ul class="list-unstyled seccolor mt-3">
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Tier III — SLA 99,982% dostupnost</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Napájení 2N UPS — bez výpadků</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Konektivita 1 Gbit/s + — BGP multihome</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>IPv4 a IPv6 — vlastní /24 bloky</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>IPMI/KVM přístup — vzdálená správa</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Fyzická bezpečnost — biometrika + CCTV</li>
                        </ul>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill mt-3">Nezávazná poptávka</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0">
                        <div class="wrapper bg-seccolorstyle p-4 rounded">
                            <h5 class="mergecolor mb-3">Parametry prostorů</h5>
                            <ul class="list-unstyled mb-0">
                                @foreach([
                                    ['label' => 'PUE',             'value' => '1,35 (energetická efektivita)'],
                                    ['label' => 'Napájení',        'value' => 'Redundantní 2N, diesel agregáty'],
                                    ['label' => 'Chlazení',        'value' => 'N+1, 22°C ± 2°C'],
                                    ['label' => 'Konektivita',     'value' => '100 Gbit/s uplink, multihome BGP'],
                                    ['label' => 'Bezpečnost',      'value' => 'Biometrika, CCTV, 24/7 ostraha'],
                                    ['label' => 'Certifikace',     'value' => 'ISO 27001, ISO 9001'],
                                ] as $spec)
                                    <li class="d-flex justify-content-between py-2 border-bottom seccolor">
                                        <span class="fw-bold mergecolor">{{ $spec['label'] }}</span>
                                        <span>{{ $spec['value'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SERVICES GRID --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Doplňkové služby kolokace</h2>
                        <p class="section-subheading">Vše co potřebujete pro provoz serveru v našem datacentru.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-drives',      'badge' => 'Rack',    'title' => 'Rack space',            'text' => 'Pronájem celých racků (42U), půl-racků nebo jednotkových prostorů (U).'],
                        ['icon' => 'icon-cpu',        'badge' => 'KVM',     'title' => 'Remote KVM + IPMI',     'text' => 'Vzdálený přístup ke konzole a správa napájení i bez fyzického přístupu.'],
                        ['icon' => 'icon-support',     'badge' => 'Hands',   'title' => 'Remote hands',          'text' => 'Výměna disků, karet a kabelů — naši technici zasáhnou na vaši žádost.'],
                        ['icon' => 'icon-speed',       'badge' => '10G',     'title' => 'Dedikovaný uplink',     'text' => 'Garantovaný 1G nebo 10G port — žádné sdílení, žádné překročení.'],
                        ['icon' => 'icon-protection',  'badge' => 'DDoS',    'title' => 'DDoS ochrana',          'text' => 'Volitelná DDoS filtrační vrstva na síťovém hraničním routeru.'],
                        ['icon' => 'icon-diskette',      'badge' => '99,99%',  'title' => 'SLA záruka',            'text' => 'Garantujeme dostupnost napájení, chlazení a konektivity — nebo vracíme kredity.'],
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
                <h2 class="title text-white">Máte zájem o kolokaci?</h2>
                <p class="text-white-50 pb-3">Kontaktujte náš obchodní tým — připravíme nabídku na míru vašim požadavkům na prostor, konektivitu a SLA.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptejte kolokaci</a>
            </div>
        </div>
    </section>

@endsection
