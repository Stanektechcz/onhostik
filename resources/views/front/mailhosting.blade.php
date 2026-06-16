@extends('layouts.front')

@section('title', __('front.pages.mailhosting.title'))
@section('meta_description', 'Firemní e-mail na vlastní doméně. Antispam, SPF/DKIM/DMARC, webmail, IMAP, zálohy schránek. Mailhosting od 49 Kč/měs.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.mailhosting.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.mailhosting.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Každý tarif obsahuje</div>
                            <ul><li><i class="fas fa-check-circle"></i> E-mail na vlastní doméně</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Antispam + antivir</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> SPF / DKIM / DMARC</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Webmail + IMAP/POP3</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Zálohy schránek</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Aliasy a přesměrování</li></ul>
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
                                @if($plan->is_featured)
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
                                            <i class="{{ config("resources.icons.$key", 'icon-emailopen') }}"></i>
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
                        <h2 class="section-heading">Proč mailhosting OnHost?</h2>
                        <p class="section-subheading">Profesionální e-mail bez technických starostí.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-emailopen',  'badge' => 'Doména',  'title' => 'E-mail na vlastní doméně',  'text' => 'Schránky ve formátu jmeno@vase-firma.cz — žádné reklamy, žádné omezení formátu.'],
                        ['icon' => 'icon-protection',  'badge' => 'Spam 0',  'title' => 'Antispam a antivir',        'text' => 'Vícevrstvá ochrana: reputační databáze, Bayesův filtr a skenování příloh v reálném čase.'],
                        ['icon' => 'icon-lock',         'badge' => 'Auto',    'title' => 'SPF / DKIM / DMARC',        'text' => 'Záznamy pro autentizaci e-mailů nastavíme automaticky — váš e-mail nedopadne do spamu.'],
                        ['icon' => 'ico-globe',       'badge' => 'IMAP',    'title' => 'Webmail i IMAP/POP3',       'text' => 'Moderní webmail z prohlížeče plus standardní protokoly pro Outlook, Thunderbird nebo telefon.'],
                        ['icon' => 'icon-diskette',      'badge' => '14 dní',  'title' => 'Zálohy schránek',           'text' => 'Denní zálohy s možností obnovy jednotlivých zpráv nebo celých schránek.'],
                        ['icon' => 'icon-drives',      'badge' => '∞',       'title' => 'Aliasy a přesměrování',     'text' => 'Neomezený počet aliasů — info@, objednavky@, fakturace@ na jednom tarifu.'],
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

    {{-- ============ FEATURE HIGHLIGHTS ============ --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">E-mail je součástí každého webhostingu</h2>
                            <p class="seccolor">Pokud provozujete web, e-mailové schránky jsou součástí každého webhostingového tarifu — od 5 schránek u tarifu Start po 250 u Managed WordPress. Bez příplatku.</p>
                        </div>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">Prohlédnout webhosting</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-emailopen purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-drives purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Migrace schránek zdarma</h2>
                            <p class="seccolor">Přecházíte od jiného poskytovatele? Přeneseme vaše e-mailové schránky včetně historické pošty bez výpadku. Postará se o to náš tým.</p>
                        </div>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Domluvit migraci</a>
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
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting s e-mailem">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Webhosting + e-mail</div>
                                    <div class="description seccolor">E-mail je součástí každého webhostingového tarifu. Bez příplatku.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Nastavení e-mailu</div>
                                    <div class="description seccolor">Návody pro Outlook, Thunderbird, webmail a nastavení mobilního zařízení.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Enterprise e-mail">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Enterprise řešení</div>
                                    <div class="description seccolor">Microsoft 365, Google Workspace nebo vlastní poštovní server na míru.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
