@extends('layouts.front')

@section('title', __('front.pages.support.title'))
@section('meta_description', 'Podpora Onhost.cz — tickety, znalostní báze a AI asistent. Reakce na urgentní výpadky do 1 hodiny.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.support.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.support.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Jak nás kontaktovat</div>
                            <ul><li><i class="fas fa-check-circle"></i> Ticketová podpora</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> AI asistent 24/7</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Znalostní báze</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Reakce na urgentní výpadky do 1 hodiny</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ SUPPORT CHANNELS ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Kanály podpory</h2>
                        <p class="section-subheading">Vyberte nejrychlejší cestu k řešení.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-support',      'badge' => 'Ticket',  'title' => 'Ticketová podpora',  'text' => 'Nejrychlejší cesta k řešení — ticket zůstane v historii a sledujete průběh v reálném čase.', 'route' => 'panel.support.index', 'label' => 'Otevřít ticket'],
                        ['icon' => 'icon-cpu',          'badge' => '24/7',    'title' => 'AI asistent',        'text' => 'Okamžité odpovědi na otázky o DNS, PHP, e-mailech nebo tarifu. Dostupný 24/7 v klientské zóně.', 'route' => 'panel.ai.index', 'label' => 'Otevřít AI asistenta'],
                        ['icon' => 'icon-speed',   'badge' => 'Návody',  'title' => 'Znalostní báze',     'text' => 'Návody pro nastavení DNS, e-mailů, SSL, přesměrování a dalších běžných operací.', 'route' => 'front.kb', 'label' => 'Procházet návody'],
                    ] as $channel)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $channel['badge'] }}</div>
                                <i class="{{ $channel['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $channel['title'] }}</div>
                                <p class="subtitle seccolor">{{ $channel['text'] }}</p>
                                <a href="{{ route($channel['route']) }}" class="btn btn-default-yellow-fill btn-sm mt-2">{{ $channel['label'] }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ RESPONSE TIMES TABLE ============ --}}
    <section id="sla" class="sec-normal sec-bg1 bg-colorstyle pb-80">
        <div class="best-plans pricing">
            <div class="container">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Reakční doby podpory</h2>
                        <p class="section-subheading mergecolor">Garantujeme tyto doby reakce pro všechny aktivní zákazníky.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="table-responsive-lg">
                            <table class="table sample mt-5">
                                <thead>
                                    <tr>
                                        <th class="border-start-0 title">Priorita</th>
                                        <th class="border-start-0 title">Příklady</th>
                                        <th class="border-start-0 title">Reakční doba</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Urgentní</th>
                                        <td>Výpadek služby, ztráta dat</td>
                                        <td><strong>Do 1 hodiny, 24/7</strong></td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Vysoká</th>
                                        <td>Výrazné zpomalení, funkční chyba</td>
                                        <td><strong>Do 4 hodin</strong></td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Standardní</th>
                                        <td>Dotazy, nastavení, přesměrování</td>
                                        <td><strong>Do 1 pracovního dne</strong></td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Nízká</th>
                                        <td>Obecné dotazy, konzultace</td>
                                        <td><strong>Do 2 pracovních dnů</strong></td>
                                    </tr>
                                    <tr>
                                        <th class="border-0">
                                            <a href="{{ route('panel.support.index') }}" class="btn btn-default-purple-fill">Otevřít ticket</a>
                                        </th>
                                        <td class="border-0" colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
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
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Znalostní báze</div>
                                    <div class="description seccolor">Návody pro DNS, e-mail, SSL, FTP a další obvyklé operace.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.faq') }}" class="help-item" title="Časté dotazy">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Časté dotazy</div>
                                    <div class="description seccolor">Nejčastější otázky o tarifech, platbách a technickém nastavení.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Přejít na webhosting</div>
                                    <div class="description seccolor">Prohlédněte tarify a vyberte ten správný pro váš projekt.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
