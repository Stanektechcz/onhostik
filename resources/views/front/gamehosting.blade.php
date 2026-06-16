@extends('layouts.front')

@section('title', __('front.pages.gamehosting.title'))
@section('meta_description', 'Herní servery Minecraft, Valheim, CS2, ARK, Rust s nízkou latencí, anti-DDoS a Pterodactyl panelem. Gamehosting od 99 Kč/měs.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.gamehosting.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.gamehosting.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">V ceně každého serveru</div>
                            <ul><li><i class="fas fa-check-circle"></i> Pterodactyl panel</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Anti-DDoS ochrana</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> NVMe SSD úložiště</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Zálohy světů</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> České datacentrum</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Podpora v češtině</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING ============ --}}
    <section class="pricing special sec-normal bg-colorstyle pt-5">
        <div class="container">
            @if($plans->isNotEmpty())
                <div class="row justify-content-center">
                    @foreach($plans as $plan)
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="wrapper text-start noshadow">
                                @if($plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price mergecolor">
                                        {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                        <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
                                    </div>
                                    <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">{{ __('front.pricing.order_now') }}</a>
                                </div>
                                <ul class="list-info bg-purple">
                                    @foreach($plan->resources ?? [] as $key => $value)
                                        <li>
                                            <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                                            <div>{{ __("front.resources.$key") }}<br><span>{{ $value }}</span></div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="seccolor f-14 mt-4 text-center pb-5">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>

    {{-- ============ GAME LIST ============ --}}
    <section class="services sec-normal sec-bg3 motpath bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading text-white mergecolor">Nejhranější tituly v ceně</h2>
                        <p class="section-subheading text-muted mergecolor">Instalace jedním kliknutím přímo z Pterodactyl panelu.</p>
                    </div>
                    @foreach([
                        ['name' => 'Minecraft',  'badge' => 'Java + Bedrock', 'icon' => 'icon-speed'],
                        ['name' => 'Valheim',    'badge' => 'Viking survival', 'icon' => 'icon-protection'],
                        ['name' => 'CS2',        'badge' => 'Counter-Strike',  'icon' => 'icon-speed'],
                        ['name' => 'ARK',        'badge' => 'Survival',        'icon' => 'icon-drives'],
                        ['name' => 'Rust',       'badge' => 'Open world',      'icon' => 'icon-protection'],
                        ['name' => 'Terraria',   'badge' => '2D sandbox',      'icon' => 'icon-diskette'],
                        ['name' => 'Palworld',   'badge' => 'Action RPG',      'icon' => 'icon-cpu'],
                        ['name' => 'V Rising',   'badge' => 'Survival RPG',    'icon' => 'ico-globe'],
                        ['name' => 'Stardew Valley','badge' => 'Farming',      'icon' => 'icon-drives'],
                    ] as $game)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-seccolorstyle noshadow">
                                <div class="plans badge feat bg-purple">{{ $game['badge'] }}</div>
                                <i class="{{ $game['icon'] }} f-30 purple mb-2 d-block"></i>
                                <div class="title mergecolor">{{ $game['name'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURE HIGHLIGHTS ============ --}}
    <section id="scroll" class="history-section feat01 sec-normal pb-0 bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-6">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Pterodactyl — moderní herní panel</h2>
                            <p class="seccolor">Webový panel pro správu serveru, instalaci modifikací, sledování výkonu a konzolový přístup v reálném čase. Funguje na mobilu i desktopu.</p>
                        </div>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill mt-3">Spustit server</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-speed purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-protection purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Anti-DDoS ochrana a nízká latence</h2>
                            <p class="seccolor">Filtrace volumetrických i aplikačních útoků na síťové vrstvě. České datacentrum zajišťuje ping pod 5 ms pro české a slovenské hráče.</p>
                        </div>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Podmínky pro klany</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="help pt-4 pb-80 globalpadding bg-colorstyle tophalfpadding">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                            <div class="img"><i class="icon-drives f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Webhosting</div>
                                <div class="description seccolor">Sdílený NVMe webhosting s PHP 8.3 a SSL zdarma od 49 Kč.</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.dedicated') }}" class="help-item" title="Dedikované servery">
                            <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Dedikované servery</div>
                                <div class="description seccolor">Pro velké komunity a esportové organizace — výhradní hardware.</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                            <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Sleva pro klany</div>
                                <div class="description seccolor">Provozujete více serverů? Napište nám pro množstevní nabídku.</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
