@extends('layouts.front')

@section('title', __('front.pages.domains.title'))
@section('meta_description', 'Registrace a správa domén na Onhost.cz — .cz, .com, .eu, .sk a desítky dalších TLD. Přenos domény zdarma.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.domains.title') }}</h1>
                        <div class="subheading text-center mb-4">{{ __('front.pages.domains.subtitle') }}</div>

                        {{-- Domain search --}}
                        <x-front.domain-search />

                        @if(session('domain_check_status'))
                            <div class="alert alert-info mt-3 text-center" role="alert">
                                {{ session('domain_check_status') }}
                            </div>
                        @endif

                        @if(session('domain_check_result'))
                            @php($result = session('domain_check_result'))
                            <div class="alert {{ $result['available'] ? 'alert-success' : 'alert-warning' }} mt-3 text-center" role="alert">
                                @if($result['available'])
                                    {{ __('front.domains.check_available', ['domain' => $result['fqdn']]) }}
                                @else
                                    {{ __('front.domains.check_unavailable', [
                                        'domain' => $result['fqdn'],
                                        'reason' => __('front.domains.reasons.' . ($result['reason'] ?? 'taken')),
                                    ]) }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ DOMAIN PRICING TABLE ============ --}}
    <section id="specs" class="sec-normal sec-bg1 bg-colorstyle pb-80">
        <div class="best-plans pricing">
            <div class="container">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Ceník domén</h2>
                        <p class="section-subheading mergecolor">Ceny jsou bez DPH za rok.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="table-responsive-lg">
                            <table class="table sample mt-5">
                                <thead>
                                    <tr>
                                        <th class="border-start-0 title">Koncovka</th>
                                        <th class="border-start-0 title">Registrace</th>
                                        <th class="border-start-0 title">Obnova</th>
                                        <th class="border-start-0 title">Přenos</th>
                                        <th class="border-start-0 title"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach([
                                        ['.cz',   '199 Kč',   '199 Kč',   '199 Kč'],
                                        ['.com',  '299 Kč',   '349 Kč',   '299 Kč'],
                                        ['.eu',   '199 Kč',   '249 Kč',   '199 Kč'],
                                        ['.sk',   '299 Kč',   '349 Kč',   '299 Kč'],
                                        ['.net',  '349 Kč',   '399 Kč',   '349 Kč'],
                                        ['.org',  '299 Kč',   '349 Kč',   '299 Kč'],
                                        ['.info', '249 Kč',   '299 Kč',   '249 Kč'],
                                        ['.shop', '399 Kč',   '449 Kč',   '399 Kč'],
                                        ['.io',   '1 490 Kč', '1 690 Kč', '1 490 Kč'],
                                        ['.dev',  '499 Kč',   '549 Kč',   '499 Kč'],
                                    ] as $row)
                                        <tr>
                                            <th><strong class="mergecolor">{{ $row[0] }}</strong></th>
                                            <td>{{ $row[1] }}</td>
                                            <td>{{ $row[2] }}</td>
                                            <td>{{ $row[3] }}</td>
                                            <td>
                                                <a href="{{ route('panel.orders.create') }}" class="btn btn-default-yellow-fill btn-sm">Registrovat</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <th class="border-0">
                                            <a href="{{ route('front.contact') }}" class="btn btn-default-purple-fill">Jiná koncovka?</a>
                                        </th>
                                        <td class="border-0" colspan="4">
                                            <span class="seccolor f-14">Nespravujeme pouze tyto TLD — napište nám a zjistíme dostupnost.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Správa domén v klientské zóně</h2>
                        <p class="section-subheading">Vše potřebné pod jednou střechou.</p>
                    </div>
                    @foreach([
                        ['icon' => 'ico-globe',      'badge' => 'DNS',     'title' => 'DNS správa',      'text' => 'Plná správa DNS záznamů z klientské zóny — A, AAAA, CNAME, MX, TXT, SRV a další.'],
                        ['icon' => 'icon-lock',        'badge' => 'Auto',    'title' => 'SSL v ceně',      'text' => "K doméně s hostingem dostanete Let's Encrypt SSL zdarma a automaticky obnovovaný."],
                        ['icon' => 'icon-protection', 'badge' => 'DNSSEC',  'title' => 'DNSSEC',          'text' => 'Zabezpečení domény pomocí DNSSEC — ochrana před DNS spoofingem a man-in-the-middle útoky.'],
                        ['icon' => 'icon-speed',      'badge' => 'Zdarma',  'title' => 'Přenos domény',   'text' => 'Přeneseme doménu od jiného registrátora. Poradíme s AuthCode a celý postup zvládnete sami v klientské zóně.'],
                    ] as $feature)
                        <div class="col-sm-12 col-md-6 col-lg-3" data-aos="fade-up">
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
                            <h2 class="fw-bold mb-3 mergecolor">Doména + hosting v jednom</h2>
                            <p class="seccolor">Objednejte hosting a doménu najednou — DNS záznamy nastavíme automaticky. Žádná ruční konfigurace, žádné čekání na propagaci.</p>
                        </div>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">Vybrat hosting</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="ico-globe purple" style="font-size:100px;opacity:.8"></i>
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
                                    <div class="description seccolor">Sdílený NVMe hosting s SSL a e-mailem od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.mailhosting') }}" class="help-item" title="Mailhosting">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Mailhosting</div>
                                    <div class="description seccolor">Firemní e-mail na vlastní doméně od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Potřebujete poradit?</div>
                                    <div class="description seccolor">Pomůžeme s výběrem domény, přenosem nebo DNS konfigurací.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
