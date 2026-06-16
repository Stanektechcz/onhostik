@extends('layouts.front')

@section('title', 'Naše datacenter — Tier III infrastruktura v Praze | Onhost.cz')
@section('meta_description', 'Vlastní Tier III datacenter v Praze — redundantní napájení, 10 Gbps konektivita, 99,9% SLA, fyzická bezpečnost 24/7.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading mb-0">Naše datacenter</h1>
                        <div class="subheading mb-0">Spolehlivá a výkonná infrastruktura v srdci Evropy.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ DATACENTER GALLERY ============ --}}
    <section class="sec-normal history-section custom-flip sec-bg3 bg-colorstyle">
        <div class="container">
            <div class="row">
                <div class="col-md-12 col-sm-12 text-center">
                    <h2 class="section-heading text-white mergecolor">Naše datacenter v obrazech</h2>
                    <p class="section-subheading c-grey-light mergecolor">Moderní Tier III infrastruktura v Praze — redundantní napájení, klimatizace a konektivita.</p>
                </div>
                <div class="col-sm-12 text-center pt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle text-center py-5">
                                <i class="icon-drives f-50 purple mb-3 d-block"></i>
                                <div class="title mergecolor">Server haly</div>
                                <p class="subtitle seccolor">Tisíce serverů ve standardizovaných rack skříních s hot-swap komponentami.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle text-center py-5">
                                <i class="icon-speed f-50 purple mb-3 d-block"></i>
                                <div class="title mergecolor">Síťová infrastruktura</div>
                                <p class="subtitle seccolor">10 Gbps redundantní uplinky od více nezávislých ISP pro maximální konektivitu.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle text-center py-5">
                                <i class="icon-support f-50 purple mb-3 d-block"></i>
                                <div class="title mergecolor">NOC centrum</div>
                                <p class="subtitle seccolor">Nepřetržitý monitoring a okamžitá reakce na incidenty z naše síťové kontrolní místnosti.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURES ============ --}}
    <section class="history-section pb-80 sec-bg2 motpath noimage bg-seccolorstyle nobottompadding toppadding">
        <div class="container">
            <div class="sec-main sec-up mb-0 sec-bg1 bg-seccolorstyle noshadow">
                <div class="row">
                    <div class="col-md-10 offset-md-1">
                        <div class="info-content">
                            <h2 class="mb-4 mergecolor"><b>Maximální fyzická bezpečnost</b></h2>
                            <p class="seccolor">Naše datacenter splňuje standard Tier III — to znamená N+1 redundanci pro všechny kritické systémy. Přístup do serverové haly je řízen biometrickými čtečkami, kamerami a hlídanou recepcí. Budova je chráněna 24 hodin denně, 7 dní v týdnu.</p>
                            <p class="seccolor">Systémy detekce úniku plynu, hasicí zařízení FM-200, nepřetržité video monitorování a kontrola vstupu každé osoby jsou standardní součástí našeho bezpečnostního protokolu.</p>
                        </div>
                    </div>
                </div>
                <div class="row mt-5">
                    <div class="col-md-10 offset-md-1">
                        <div class="info-content">
                            <h2 class="mb-4 mergecolor"><b>Vysoká dostupnost a redundantní systémy</b></h2>
                            <p class="seccolor">Napájení je zajištěno dvojitými UPS systémy a dieselovými generátory s palivem na 72 hodin provozu. Redundantní chlazení udržuje optimální teplotu i při výpadku jednoho okruhu. Konektivita je vedena přes fyzicky oddělené trasy od více operátorů.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ SECURITY FEATURES ============ --}}
    <section class="services sec-normal sec-bg2 bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Bezpečnost a důvěrnost vašich dat</h2>
                        <p class="section-subheading mergecolor">Vícevrstvá ochrana na fyzické i síťové úrovni — vždy aktivní.</p>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up" data-aos-duration="1000">
                        <div class="service-section bg-seccolorstyle">
                            <div class="plans badge feat bg-pink">auto</div>
                            <i class="icon-virus f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Detekce toxických plynů</div>
                            <p class="subtitle seccolor">Automatický systém detekce úniku plynu a požáru s okamžitou reakcí hasicích systémů FM-200.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up" data-aos-duration="800">
                        <div class="service-section bg-seccolorstyle">
                            <i class="ico-lock f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Řízený přístup</div>
                            <p class="subtitle seccolor">Biometrické čtečky, kamerový systém a nepřetržitá ostraha — vstup pouze pro autorizované osoby.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up" data-aos-duration="900">
                        <div class="service-section bg-seccolorstyle">
                            <div class="plans badge feat bg-grey">pro</div>
                            <i class="icon-security f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Anti-DDoS ochrana</div>
                            <p class="subtitle seccolor">Síťová ochrana proti DDoS útokům přímo na peering úrovni — bez latence, bez výpadku vaší služby.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CTA SECTION ============ --}}
    <section class="getready sec-bg1 bg-seccolorstyle">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="column-support-txt">
                        <div class="column-support-title text-dark mergecolor">Zájem o kolokaci nebo prohlídku?</div>
                        <div class="column-support-subtitle text-dark mergecolor">Umístěte svůj server do našeho Tier III datacentra nebo si domluvte osobní prohlídku.</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="btn-floats">
                        <a href="{{ route('front.colocation') }}" class="btn btn-default-yellow-fill me-3">Kolokace</a>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-purple-fill">Kontaktovat nás</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ MAP ============ --}}
    <section class="services maping sec-normal sec-grad-grey-to-grey bg-colorstyle pb-5">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor" data-aos="fade-up">Naše datacenter je v srdci Evropy</h2>
                        <p class="section-subheading mergecolor" data-aos="fade-up">Vlastní infrastruktura v <span class="golink">Tier III datacentru</span> v Praze — 99,9% SLA, redundantní napájení a konektivita.</p>
                    </div>
                    <div class="col-md-12 pt-5 position-relative">
                        <img src="{{ asset('front/patterns/map.svg') }}" class="img-fluid w-100" alt="Mapa datacenter OnHost">
                        <span class="datacenters portugal"
                              data-bs-toggle="popover"
                              data-bs-container="body"
                              data-bs-trigger="hover"
                              data-bs-placement="top"
                              title="Praha, CZ"
                              data-bs-content="Primární datacenter — Tier III, redundantní napájení, 10 Gbps konektivita."
                              role="button"
                              aria-label="Praha datacenter"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
