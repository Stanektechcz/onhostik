@extends('layouts.front')

@section('title', __('front.pages.builder.title'))
@section('meta_description', 'Průvodce tvorbou webu od Onhost.cz — nástroj pro rychlé vytvoření webu bez kódování, již brzy.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.builder.title') }}</h1>
                        <div class="subheading text-center">{{ __('front.pages.builder.subtitle') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ COMING SOON ============ --}}
    <section class="sec-normal sec-bg1 bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="row align-items-center">
                <div class="col-md-12 col-lg-5 text-center" data-aos="fade-right">
                    <img class="svg soon" src="{{ asset('front/patterns/soon.svg') }}" alt="Připravujeme" width="100%" height="100%">
                </div>
                <div class="col-md-12 col-lg-6 offset-lg-1 mt-5 mt-lg-0" data-aos="fade-left">
                    <h2 class="fw-bold mergecolor mb-3">Průvodce tvorbou webu — již brzy</h2>
                    <p class="seccolor">Pracujeme na jednoduchém nástroji, který vám umožní vytvořit profesionální web bez kódování. Stačí vybrat šablonu, upravit obsah a zveřejnit.</p>
                    <ul class="seccolor ps-3 mt-3">
                        <li class="mb-2"><i class="fas fa-check-circle purple me-2"></i> Vizuální editor drag & drop</li>
                        <li class="mb-2"><i class="fas fa-check-circle purple me-2"></i> Desítky profesionálních šablon</li>
                        <li class="mb-2"><i class="fas fa-check-circle purple me-2"></i> Vlastní doména a SSL zdarma</li>
                        <li class="mb-2"><i class="fas fa-check-circle purple me-2"></i> Plná integrace s hostingovým účtem</li>
                    </ul>
                    <div class="mt-4">
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill me-2">Webhostingové tarify</a>
                        <a href="{{ route('front.support') }}" class="btn btn-default-grad-purple-fill">Kontaktovat podporu</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURES PREVIEW ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Co bude průvodce umět?</h2>
                        <p class="section-subheading">Přehled plánovaných funkcí webového builderu.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-sync',    'badge' => 'Drag & Drop', 'title' => 'Vizuální editor',         'text' => 'Přetahujte bloky, upravujte texty a obrázky přímo na stránce. Žádné kódování, žádná technická znalost.'],
                        ['icon' => 'icon-speed',        'badge' => 'Šablony',     'title' => 'Prémiové šablony',        'text' => 'Desítky moderních šablon pro různé typy webů — portfolio, firemní prezentace, e-shop, blog.'],
                        ['icon' => 'icon-lock',          'badge' => 'Free',        'title' => 'SSL a doména zdarma',     'text' => "Každý web získá automaticky Let's Encrypt SSL certifikát a vlastní doménu nebo subdoménu."],
                        ['icon' => 'icon-drives',       'badge' => 'NVMe',        'title' => 'Rychlý hosting v ceně',  'text' => 'Builder je plně integrován s NVMe hostingem. Vše na jednom místě — správa webu i hosting.'],
                        ['icon' => 'icon-diskette',       'badge' => 'Auto',        'title' => 'Automatické zálohy',      'text' => 'Vaše stránky se každý den automaticky zálohují. Vrátit web do předchozí verze trvá jen klik.'],
                        ['icon' => 'icon-support',      'badge' => 'CZ',          'title' => 'Podpora v češtině',       'text' => 'Celý builder a dokumentace jsou v češtině. Naše podpora vám pomůže s každým krokem.'],
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
                                    <div class="description seccolor">NVMe hosting s PHP 8.3, SSL zdarma a denními zálohami od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.wordpress') }}" class="help-item" title="WordPress hosting">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">WordPress hosting</div>
                                    <div class="description seccolor">Optimalizovaný hosting pro WordPress s automatickými aktualizacemi.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Zeptejte se nás</div>
                                    <div class="description seccolor">Máte zájem o builder nebo chcete být informováni o spuštění? Napište nám.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
