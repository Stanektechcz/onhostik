@extends('layouts.front')

@section('title', __('front.pages.about.title'))

@push('scripts')
    <script defer src="{{ asset('front/js/jquery.circliful.min.js') }}"></script>
@endpush
@section('meta_description', 'Onhost.cz — český hosting s AI asistencí, monitoringem a férovým přístupem.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.about.title') }}</h1>
                        <div class="subheading text-center mb-5">Český hosting postavený pro rok 2026 — automatizace, AI a férovost.</div>
                        <div class="included">
                            <div class="h4 mb-3">V číslech</div>
                            <ul><li><i class="fas fa-check-circle"></i> 99,9% garantovaná dostupnost</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Aktivace služby do 1 minuty</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Podpora s reakcí do 1 hodiny</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> AI asistent v ceně všech tarifů</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ ABOUT CONTENT ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč OnHost?</h2>
                        <p class="section-subheading">Hosting, jaký bychom sami chtěli používat.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-speed',       'badge' => 'Auto',    'title' => 'Automatizace od A do Z',    'text' => 'Od objednávky přes platbu po aktivaci služby — vše probíhá automaticky s plnou auditní stopou v klientské zóně.'],
                        ['icon' => 'icon-cpu',        'badge' => 'AI',      'title' => 'AI asistent v ceně',        'text' => 'Okamžité odpovědi na otázky o DNS, PHP, e-mailu a tarifu. Dostupný 24/7 bez příplatku u každého aktivního tarifu.'],
                        ['icon' => 'icon-diskette',      'badge' => '14 dní',  'title' => 'Denní zálohy',              'text' => 'Každý hosting zálohujeme denně s retencí 14 dní. Obnova probíhá přes klientskou zónu bez čekání na podporu.'],
                        ['icon' => 'icon-protection',  'badge' => '24/7',    'title' => 'Monitoring dostupnosti',    'text' => 'Nepřetržité sledování webu a SSL certifikátu s notifikacemi při výpadku. Záznamy slouží i jako SLA podklad.'],
                        ['icon' => 'icon-drives',      'badge' => 'NVMe',    'title' => 'Moderní infrastruktura',    'text' => 'Lokální NVMe pole, KVM virtualizace a gigabitové uplinky v českém datacentru s nízkou latencí.'],
                        ['icon' => 'ico-globe',       'badge' => 'CZ',      'title' => 'Podpora v češtině',         'text' => 'Celý tým komunikuje česky. České datacentrum, česká fakturace a férové ceny bez skrytých poplatků.'],
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

    {{-- ============ STORY ============ --}}
    <section class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-7">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Náš přístup k hostingu</h2>
                            <p class="seccolor">Onhost.cz stavíme jako hosting, jaký bychom sami chtěli používat: rychlý NVMe webhosting, domény bez skrytých poplatků a klientskou zónu, která hraje za vás — od objednávky přes platbu po automatické zřízení služby během minuty.</p>
                            <p class="seccolor">Co nás odlišuje, je důsledná automatizace s plnou auditní stopou. Každá operace — platba, zřízení hostingu, registrace domény, záloha — běží jako sledovatelná úloha, kterou vidíte v klientské zóně. Když se cokoli pokazí, víme přesně kde, a náš tým i AI asistent vám okamžitě pomohou.</p>
                            <p class="seccolor mb-0">Začínáme webhostingem a doménami; gamehosting, VPS, mailhosting a dedikované servery přidáváme postupně na stejných základech: bezpečnost, transparentnost a podpora, která skutečně odpovídá.</p>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-4 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-drives purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ STATISTICS ============ --}}
    <section class="circle-section sec-normal sec-bg1 bg-seccolorstyle bottomhalfpadding">
        <div class="container">
            <div class="row">
                <div class="col-md-12 col-sm-12 text-center">
                    <h2 class="section-heading mergecolor">OnHost v číslech</h2>
                    <p class="section-subheading mergecolor">Měřitelné závazky — ne jen slova.</p>
                </div>
                <div class="col-sm-12 col-md-12 col-lg-12 text-center pt-5">
                    <div class="col-sm-12 col-md-4 col-lg-4 float-start">
                        <div class="skill-section">
                            <div class="circle-wrapper">
                                <div class="circle-entry clearfix">
                                    <div class="circle center-block color-dark-2"
                                         data-startdegree="0" data-dimension="180"
                                         data-text="<strong class='number seccolor'>99.9%</strong><div class='title-round'>SLA dostupnost</div>"
                                         data-width="5" data-fontsize="17"
                                         data-percent="99" data-fgcolor="#9b59b6"
                                         data-bgcolor="transparent" data-bordersize="1">
                                    </div>
                                </div>
                                <p class="seccolor">Garantovaná dostupnost webhostingu a VPS serverů měřená nepřetržitým monitoringem.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4 col-lg-4 float-start">
                        <div class="skill-section">
                            <div class="circle-wrapper">
                                <div class="circle-entry clearfix">
                                    <div class="circle center-block color-dark-2 seccolor"
                                         data-startdegree="0" data-dimension="180"
                                         data-text="<strong class='number seccolor'>&lt;1 min</strong><div class='title-round'>Aktivace</div>"
                                         data-width="5" data-fontsize="17"
                                         data-percent="95" data-fgcolor="#9b59b6"
                                         data-bgcolor="transparent" data-bordersize="1">
                                    </div>
                                </div>
                                <p class="seccolor">Automatické zřízení každé služby po zaplacení — bez čekání na manuální zpracování.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4 col-lg-4 float-start">
                        <div class="skill-section">
                            <div class="circle-wrapper">
                                <div class="circle-entry clearfix">
                                    <div class="circle center-block color-dark-2 seccolor"
                                         data-startdegree="0" data-dimension="180"
                                         data-text="<strong class='number seccolor'>1 h</strong><div class='title-round'>Reakční doba</div>"
                                         data-width="5" data-fontsize="17"
                                         data-percent="80" data-fgcolor="#9b59b6"
                                         data-bgcolor="transparent" data-bordersize="1">
                                    </div>
                                </div>
                                <p class="seccolor">Maximální reakční doba pro urgentní výpadky — technická podpora v češtině nepřetržitě.</p>
                            </div>
                        </div>
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
                                    <div class="description seccolor">NVMe hosting s SSL a monitoringem od 49 Kč/měs.</div>
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
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Kontakt">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Kontakt</div>
                                    <div class="description seccolor">Napište nám na podpora@onhost.cz nebo otevřete ticket.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
