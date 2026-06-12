@extends('layouts.front')

@section('title', __('front.pages.gamehosting.title'))
@section('meta_description', 'Gamehosting Onhost.cz — herní servery Minecraft, Valheim, CS2 s nízkou latencí, anti-DDoS ochranou a instalací na jedno kliknutí.')

@section('content')
    <x-front.page-banner :title="__('front.pages.gamehosting.title')" :subtitle="__('front.pages.gamehosting.subtitle')" />

    @if($plans->isNotEmpty())
        <section class="pricing bg-colorstyle pb-90">
            <div class="container">
                <div class="sec-main-title text-center pb-5">
                    <h2 class="title mergecolor" data-aos="fade-up">Herní server za minutu</h2>
                    <p class="seccolor" data-aos="fade-up">{{ __('front.pricing.vat_note') }}</p>
                </div>
                <div class="row justify-content-md-center" data-aos="fade-up">
                    @foreach($plans as $plan)
                        <x-front.pricing-card :plan="$plan" :currency="$currency" :featured="(bool) $plan->is_featured" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Nejhranější tituly v ceně</h2>
            </div>
            <div class="row justify-content-center">
                @foreach(['Minecraft', 'Valheim', 'CS2', 'ARK', 'Rust', 'Terraria', 'Palworld', 'V Rising'] as $i => $game)
                    <div class="col-6 col-md-3 col-lg-2 text-center mb-4" data-aos="fade-up" data-aos-delay="{{ ($i % 4) * 80 }}">
                        <div class="wrapper bg-seccolorstyle p-3 rounded">
                            <i class="ico-game f-24 purple pb-2"></i>
                            <p class="seccolor f-14 mb-0">{{ $game }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-speed', 'title' => 'Vysoký takt CPU', 'text' => 'Herní servery potřebují rychlá jádra, ne jejich počet — nasazujeme procesory s co nejvyšším jednovláknovým výkonem.'],
                    ['icon' => 'ico-protection', 'title' => 'Anti-DDoS ochrana', 'text' => 'Filtrace volumetrických i aplikačních útoků na síťové vrstvě, v ceně každého herního serveru.'],
                    ['icon' => 'ico-drives', 'title' => 'NVMe + rychlý spawn', 'text' => 'Herní světy i mapy se načítají z NVMe disků — konec čekání při respawnu nebo startu serveru.'],
                    ['icon' => 'ico-chip', 'title' => 'Panel Pterodactyl', 'text' => 'Moderní webový panel pro správu serveru, instalaci modifikací a sledování výkonu v reálném čase.'],
                    ['icon' => 'ico-backup', 'title' => 'Zálohy světů', 'text' => 'Automatické zálohy herních světů a konfigurací s jednoduchou obnovou na požadovaný bod.'],
                    ['icon' => 'ico-globe', 'title' => 'České datacentrum', 'text' => 'Nízká latence pro české a slovenské hráče — ping pod 5 ms v rámci ČR.'],
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
                <h2 class="title text-white">Máte klana nebo komunitu?</h2>
                <p class="text-white-50 pb-3">Pro větší komunity a provozovatele více serverů nabízíme množstevní slevy a prioritní podporu.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Domluvit podmínky</a>
            </div>
        </div>
    </section>
@endsection
