@extends('layouts.front')

@section('title', __('front.pages.wordpress.title'))
@section('meta_description', 'WordPress hosting OnHost.cz — spravovaný WordPress s automatickými aktualizacemi, zálohami a SSL zdarma.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.wordpress.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.wordpress.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Každý tarif obsahuje</div>
                            <ul><li><i class="fas fa-check-circle"></i> Instalace WordPress jedním kliknutím</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Automatické aktualizace jádra</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> SSL zdarma (Let's Encrypt)</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Denní zálohy (14 dní)</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> PHP 8.3 + MySQL 8</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> NVMe SSD úložiště</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING ============ --}}
    <section class="pricing special sec-uping pb-5 bg-colorstyle specialposition">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            @if($plans->isNotEmpty())
                <div class="row justify-content-center">
                    @foreach($plans as $plan)
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="wrapper price-container text-start noshadow">
                                @if(str_contains(mb_strtolower((string) $plan->name), 'wordpress') || $plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price-content">
                                        <div class="price mergecolor">
                                            {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                            <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
                                        </div>
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
                <p class="seccolor f-14 mt-4 text-center">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>

    {{-- ============ FEATURES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">WordPress bez technických starostí</h2>
                        <p class="section-subheading">Soustřeďte se na obsah — vše ostatní řeší OnHost.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-speed',       'badge' => '1 klik',   'title' => 'Instalace na 1 klik',    'text' => 'WordPress je připravený během minut — žádná ruční instalace, FTP ani konfigurace databáze.'],
                        ['icon' => 'icon-diskette',      'badge' => '14 dní',   'title' => 'Automatické zálohy',     'text' => 'Denní zálohy webu a databáze s retencí 14 dní. Obnova jedním kliknutím z klientské zóny.'],
                        ['icon' => 'icon-lock',         'badge' => 'SSL',      'title' => 'SSL zdarma',             'text' => "Let's Encrypt SSL certifikát v ceně každého tarifu s automatickým obnovením."],
                        ['icon' => 'icon-drives',      'badge' => 'NVMe',     'title' => 'NVMe SSD úložiště',      'text' => 'Rychlé NVMe diskové pole pro svižné načítání WordPress webu a admin panelu.'],
                        ['icon' => 'icon-speed', 'badge' => 'PHP 8.3',  'title' => 'Nejnovější PHP',         'text' => 'PHP 8.3 s OPcache a MySQL 8 — nejrychlejší prostředí pro moderní WordPress pluginy.'],
                        ['icon' => 'icon-protection',  'badge' => 'Auto',     'title' => 'Aktualizace jádra',      'text' => 'Automatické aktualizace WordPress jádra a volitelné aktualizace pluginů bez vašeho zásahu.'],
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
                            <h2 class="fw-bold mb-3 mergecolor">Migrace WordPress webu zdarma</h2>
                            <p class="seccolor">Přecházíte od jiného hostitele? Přeneseme váš WordPress web včetně databáze, médií a nastavení bez výpadku. Postará se o to náš tým — vy nemusíte dělat nic.</p>
                        </div>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Domluvit migraci</a>
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
                            <h2 class="fw-bold mb-3 mergecolor">Potřebujete plnou správu?</h2>
                            <p class="seccolor">Managed hosting zahrnuje aktualizace pluginů, bezpečnostní monitoring a prioritní podporu. Ideální pro e-shopy a weby, kde si nemůžete dovolit výpadek.</p>
                        </div>
                        <a href="{{ route('front.managed') }}" class="btn btn-default-yellow-fill mt-3">Managed hosting</a>
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
                                    <div class="title mergecolor">Standardní webhosting</div>
                                    <div class="description seccolor">NVMe hosting pro vlastní PHP aplikace od 49 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.managed') }}" class="help-item" title="Managed hosting">
                                <div class="img"><i class="icon-protection f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Managed hosting</div>
                                    <div class="description seccolor">Plná správa — aktualizace, zálohy, monitoring i podpora.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.kb') }}" class="help-item" title="WordPress návody">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">WordPress návody</div>
                                    <div class="description seccolor">Instalace, migrace, cache pluginy a obnova zálohy.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
