@extends('layouts.front')

@section('title', __('front.pages.monitoring_feature.title'))
@section('meta_description', 'Monitoring 24/7 OnHost.cz — nepřetržité sledování dostupnosti webu, SSL certifikátů a výkonu s okamžitými notifikacemi.')

@section('content')
    <x-front.page-banner :title="__('front.pages.monitoring_feature.title')" :subtitle="__('front.pages.monitoring_feature.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-speedometer', 'title' => 'Kontroly každou minutu',   'text' => 'HTTP/HTTPS dostupnost ověřujeme z monitorovacích bodů každou minutu — výpadek zachytíme dříve, než vám zavolá zákazník.'],
                    ['icon' => 'ico-ssl',         'title' => 'Platnost SSL',              'text' => 'Hlídáme expiraci SSL certifikátu a upozorníme vás 30, 14 a 7 dní předem. Certifikáty Let\'s Encrypt obnovujeme automaticky.'],
                    ['icon' => 'ico-protection',  'title' => 'Notifikace',                'text' => 'E-mail a notifikace do klientské zóny ihned při výpadku a při obnovení dostupnosti.'],
                    ['icon' => 'ico-backup',      'title' => 'Historie incidentů',        'text' => 'Přehledná timeline výpadků s příčinami a dobou výpadku — vidíte vše ve svém dashboardu.'],
                    ['icon' => 'ico-drives',      'title' => 'SLA podklady',              'text' => 'Záznamy monitoringu slouží jako podklad pro výpočet kompenzací dle SLA. Transparentně a automaticky.'],
                    ['icon' => 'ico-chip',        'title' => 'V každém tarifu',           'text' => 'Monitoring dostupnosti a SSL je aktivní automaticky pro každou provozovanou službu bez příplatku.'],
                ] as $i => $f)
                    <div class="col-md-6 col-lg-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="{{ ($i % 3) * 100 }}">
                            <i class="{{ $f['icon'] }} f-30 purple"></i>
                            <h3 class="title mergecolor pt-3 f-18">{{ $f['title'] }}</h3>
                            <p class="seccolor mb-0">{{ $f['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Monitoring v ceně hostingu</h2>
                <p class="text-white-50 pb-3">Vyberte tarif a monitoring se aktivuje automaticky.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill">{{ __('front.home.cta_button') }}</a>
            </div>
        </div>
    </section>
@endsection
