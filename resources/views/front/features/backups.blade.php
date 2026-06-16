@extends('layouts.front')

@section('title', __('front.pages.backups_feature.title'))
@section('meta_description', 'Automatické zálohy OnHost.cz — denní zálohy webu, databáze i e-mailů s retencí 14 dní a obnovou jedním kliknutím.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.backups_feature.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.backups_feature.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Zálohy v ceně tarifu</div>
                            <ul><li><i class="fas fa-check-circle"></i> Denní zálohy automaticky</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Retence 14 dní</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Offsite úložiště</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Obnova jedním kliknutím</li></ul>
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
                        <h2 class="section-heading">Jak zálohy fungují</h2>
                        <p class="section-subheading">Žádná konfigurace — zálohy se spouštějí automaticky.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-diskette',      'badge' => 'Denně',    'title' => 'Denní zálohy',              'text' => 'Web, databáze i e-maily se automaticky zálohují každý den. Zálohy se ukládají nezávisle na primárním serveru.'],
                        ['icon' => 'icon-protection',  'badge' => '14 dní',   'title' => 'Retence 14 dní',            'text' => 'Uchovávame zálohy z posledních 14 dní — obnovíte web do libovolného dne v tomto okně.'],
                        ['icon' => 'icon-speed',       'badge' => '30 min',   'title' => 'Rychlá obnova',             'text' => 'Obnova webu přes klientskou zónu nebo ticketem — obvyklá doba obnovy je do 30 minut.'],
                        ['icon' => 'icon-drives',      'badge' => 'Manuál',   'title' => 'Manuální záloha',           'text' => 'Před nasazením nové verze webu spusťte manuální zálohu přímo z detailu služby v klientské zóně.'],
                        ['icon' => 'ico-globe',       'badge' => 'Offsite',  'title' => 'Offsite uložení',           'text' => 'Zálohy ukládáme na fyzicky oddělené úložiště — výpadek primárního serveru se záloh nedotkne.'],
                        ['icon' => 'icon-cpu',        'badge' => 'Zdarma',   'title' => 'V ceně každého tarifu',     'text' => 'Zálohy jsou aktivovány automaticky pro každý webhosting bez příplatku. Žádné skryté poplatky.'],
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
                            <h2 class="fw-bold mb-3 mergecolor">Krok za krokem: jak obnovit zálohu</h2>
                            <ol class="seccolor ps-3">
                                <li class="py-2">Každý den v noci se automaticky zazálohují soubory, databáze a e-mailové schránky.</li>
                                <li class="py-2">Zálohy se přenesou na offsite úložiště a zkontroluje se jejich integrita.</li>
                                <li class="py-2">V klientské zóně v detailu služby vidíte seznam dostupných záloh s datem a velikostí.</li>
                                <li class="py-2">Pro obnovu klikněte na tlačítko „Obnovit zálohu" nebo otevřete ticket — postaráme se o zbytek.</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-diskette purple" style="font-size:100px;opacity:.8"></i>
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
                                    <div class="description seccolor">NVMe hosting se zálohami v ceně od 49 Kč/měs.</div>
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
                            <a href="{{ route('front.kb') }}" class="help-item" title="Zálohy – návody">
                                <div class="img"><i class="icon-diskette f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Návody k zálohám</div>
                                    <div class="description seccolor">Podrobné návody pro obnovu webu, databáze i e-mailů.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
