@extends('layouts.front')

@section('title', __('front.pages.ai_feature.title'))
@section('meta_description', 'AI asistent OnHost.cz — automatická doporučení tarifu, vysvětlení DNS, návrhy řešení problémů a podpora 24/7.')

@section('content')
    <x-front.page-banner :title="__('front.pages.ai_feature.title')" :subtitle="__('front.pages.ai_feature.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-chip',         'title' => 'Doporučení tarifu',    'text' => 'Popište svůj projekt a AI doporučí optimální tarif — webhosting, VPS nebo managed hosting.'],
                    ['icon' => 'ico-globe',        'title' => 'Vysvětlení DNS',        'text' => 'Nejasné DNS záznamy? AI vysvětlí rozdíl mezi A, CNAME, MX záznamy a pomůže s konfigurací.'],
                    ['icon' => 'ico-support',      'title' => 'Okamžité odpovědi',     'text' => 'Přímé odpovědi na otázky o PHP, MySQL, SSL a e-mailu bez čekání na operátora.'],
                    ['icon' => 'ico-speedometer',  'title' => 'Diagnostika problémů',  'text' => 'Pomalý web, chyba 500, odmítnutý e-mail — AI analyzuje příznaky a navrhne řešení.'],
                    ['icon' => 'ico-protection',   'title' => 'Bezpečnostní rady',     'text' => 'Doporučení k hardening WordPressu, nastavení firewallu nebo revize .htaccess souboru.'],
                    ['icon' => 'ico-drives',       'title' => 'Součást všech tarifů',  'text' => 'AI asistent je dostupný v klientské zóně bez příplatku u každého aktivního tarifu.'],
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
                <h2 class="title text-white">AI asistent v každém tarifu</h2>
                <p class="text-white-50 pb-3">Vyberte hosting a okamžitě získejte přístup k AI asistentovi v klientské zóně.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill">{{ __('front.home.cta_button') }}</a>
            </div>
        </div>
    </section>
@endsection
