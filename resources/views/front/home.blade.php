@extends('layouts.front')

@section('title', __('front.pages.home.title'))
@section('meta_description', 'Webhosting, domény a cloud hosting v ČR. Rychlé NVMe servery, SSL zdarma, denní zálohy a AI asistent. Moderní hosting s férovými cenami.')

@push('jsonld')
@php
$websiteSchema = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'Onhost.cz',
    'url'      => url('/'),
    'description' => 'Webhosting, domény a cloud hosting v ČR — NVMe SSD, SSL zdarma, AI asistent, denní zálohy.',
    'inLanguage'  => 'cs',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => url('/znalostni-baze') . '?q={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$orgSchema = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => 'Onhost.cz',
    'url'      => url('/'),
    'logo'     => asset('front/img/logo.png'),
    'contactPoint' => [
        '@type'             => 'ContactPoint',
        'contactType'       => 'customer support',
        'availableLanguage' => ['Czech', 'English'],
        'url'               => route('front.support'),
    ],
    'sameAs' => [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo '<script type="application/ld+json">' . $websiteSchema . '</script>';
echo '<script type="application/ld+json">' . $orgSchema . '</script>';
@endphp
@endpush

@section('content')
    {{-- ============ ANNOUNCEMENT BAR (editable via /admin/obsah) ============ --}}
    @php($announcementActive = \App\Models\SiteContent::get('homepage.announcement.active', '0'))
    @if($announcementActive === '1')
        @php($announcementText = \App\Models\SiteContent::get('homepage.announcement.text'))
        @php($announcementLink = \App\Models\SiteContent::get('homepage.announcement.link', '#'))
        @if($announcementText)
            <div class="announcement-bar bg-purple text-white text-center py-2 px-3" style="font-size:.9rem;">
                <i class="fas fa-bolt me-1"></i>
                @if($announcementLink && $announcementLink !== '#')
                    <a href="{{ $announcementLink }}" class="text-white text-decoration-underline">{{ $announcementText }}</a>
                @else
                    {{ $announcementText }}
                @endif
            </div>
        @endif
    @endif

    {{-- ============ HERO ============ --}}
    <section class="top-header sec-bg6 pb-150 bg-colorstyle">
        <div class="container">
            <div class="row">
                <div class="col-md-7">
                    <div class="wrapper">
                        <h1 class="heading mergecolor pb-2" data-aos="fade-up" data-aos-duration="800">{{ __('front.pages.home.title') }}</h1>
                        <h2 class="subheading fw-normal lh-42 text-muted mb-5" data-aos="fade-up" data-aos-duration="1200">{{ __('front.pages.home.subtitle') }}</h2>

                        <x-front.domain-search />

                        @if(session('domain_check_result'))
                            @php($result = session('domain_check_result'))
                            <div class="alert {{ $result['available'] ? 'alert-success' : 'alert-warning' }} mt-3" role="alert">
                                @if($result['available'])
                                    {{ __('front.domains.check_available', ['domain' => $result['fqdn']]) }}
                                @else
                                    {{ __('front.domains.check_unavailable', ['domain' => $result['fqdn'], 'reason' => __('front.domains.reasons.' . ($result['reason'] ?? 'taken'))]) }}
                                @endif
                            </div>
                        @endif

                        <div class="mt-4" data-aos="fade-up">
                            <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill me-2">{{ __('front.home.hero_cta') }} <i class="fas fa-cart-plus ps-1 f-15"></i></a>
                            <a href="{{ route('front.domains') }}" class="btn btn-default-grad-purple-fill">{{ __('front.home.hero_cta2') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ PRODUCT TRIO (overlaps hero) ============ --}}
    <section class="pricing special sec-bg2 bg-colorstyle specialposition">
        <div class="container">
            <div class="sec-up-slider nopadding">
                <div class="row">
                    <div class="col-md-12 col-lg-4">
                        <div class="wrapper first">
                            <div class="top-content bg-seccolorstyle topradius">
                                <div class="title">{{ __('front.nav.webhosting') }}</div>
                                <div class="fromer seccolor">{{ __('front.pages.webhosting.subtitle') }}</div>
                                @if($plans->isNotEmpty())
                                    @php($first = $plans->first())
                                    <div class="price seccolor">
                                        <sup>{{ $currency === \App\Domains\Shared\Enums\Currency::EUR ? '€' : 'Kč' }}</sup>
                                        {{ \App\Domains\Shared\Support\MoneyFormatter::format($first->priceFor($currency)) }}
                                        <span class="period">/{{ $first->billing_cycle->label() }}</span>
                                    </div>
                                @endif
                                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill" title="{{ __('front.nav.webhosting') }}">{{ __('front.home.all_plans') }}</a>
                            </div>
                            <ul class="list-info bg-purple">
                                <li><i class="icon-drives"></i> <div>{{ __('front.resources.disk') }}<br><span>NVMe SSD</span></div></li>
                                <li><i class="icon-speed"></i> <div>{{ __('front.resources.data') }}<br><span>50–1000 GB</span></div></li>
                                <li><i class="icon-emailopen"></i> <div>{{ __('front.resources.emails') }}<br><span>5–250</span></div></li>
                                <li><i class="icon-domains"></i> <div>SSL<br><span>Zdarma</span></div></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-4">
                        <div class="wrapper">
                            <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                            <div class="top-content bg-seccolorstyle topradius">
                                <div class="title">{{ __('front.nav.vps') }}</div>
                                <div class="fromer seccolor">{{ __('front.pages.vps.subtitle') }}</div>
                                <div class="price seccolor"><sup>Kč</sup>149 <span class="period">/měs.</span></div>
                                <a href="{{ route('front.vps') }}" class="btn btn-default-yellow-fill" title="{{ __('front.nav.vps') }}">{{ __('front.home.all_plans') }}</a>
                            </div>
                            <ul class="list-info bg-purple">
                                <li><i class="icon-cpu"></i> <div>CPU<br><span>1–8 jader</span></div></li>
                                <li><i class="icon-ram"></i> <div>RAM<br><span>1–8 GB</span></div></li>
                                <li><i class="icon-drives"></i> <div>DISK<br><span>NVMe SSD</span></div></li>
                                <li><i class="icon-git"></i> <div>ROOT<br><span>Plný přístup</span></div></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-4">
                        <div class="wrapper third">
                            <div class="top-content bg-seccolorstyle topradius">
                                <div class="title">{{ __('front.nav.domains') }}</div>
                                <div class="fromer seccolor">{{ __('front.pages.domains.subtitle') }}</div>
                                <div class="price seccolor"><sup>Kč</sup>199 <span class="period">/rok</span></div>
                                <a href="{{ route('front.domains') }}" class="btn btn-default-yellow-fill" title="{{ __('front.nav.domains') }}">{{ __('front.home.all_plans') }}</a>
                            </div>
                            <ul class="list-info bg-purple">
                                <li><i class="icon-domains"></i> <div>.cz<br><span>od 199 Kč</span></div></li>
                                <li><i class="icon-domains"></i> <div>.com<br><span>od 299 Kč</span></div></li>
                                <li><i class="icon-ssl"></i> <div>DNS<br><span>Zdarma</span></div></li>
                                <li><i class="icon-protection"></i> <div>DNSSEC<br><span>V ceně</span></div></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ DATACENTER MAP ============ --}}
    <section class="services maping sec-normal sec-grad-grey-to-grey bg-colorstyle pb-5">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor" data-aos="fade-up">Naše datacentry jsou v srdci Evropy</h2>
                        <p class="section-subheading mergecolor" data-aos="fade-up">Vlastní infrastruktura v <span class="golink">Tier III datacentru</span> v Praze — 99,9% SLA, redundantní napájení a konektivita.</p>
                    </div>
                    <div class="col-md-12 pt-5 position-relative">
                        <img src="{{ asset('front/patterns/map.svg') }}" class="img-fluid w-100" alt="Mapa datacenter OnHost">
                        <span class="datacenters portugal"
                              data-bs-toggle="popover"
                              data-bs-container="body"
                              data-bs-trigger="hover"
                              data-bs-placement="top"
                              title="Praha, CZ"
                              data-bs-content="Primární datacenter — Tier III, redundantní napájení, 10 Gbps konektivita."
                              role="button"
                              aria-label="Praha datacenter"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ WEBHOSTING PLANS ============ --}}
    @if($plans->isNotEmpty())
        <section class="pricing sec-normal sec-bg1 bg-colorstyle pb-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-sm-12 text-center pb-5">
                        <h2 class="section-heading mergecolor" data-aos="fade-up">{{ __('front.home.plans_title') }}</h2>
                        <p class="section-subheading mergecolor" data-aos="fade-up">{{ __('front.home.plans_subtitle') }}</p>
                    </div>
                </div>
                <div class="row">
                    @foreach($plans as $plan)
                        <x-front.pricing-card :plan="$plan" :currency="$currency" :featured="$plan->is_featured" />
                    @endforeach
                </div>
                <p class="seccolor f-14 mt-3 text-center">{{ __('front.pricing.vat_note') }}</p>
            </div>
        </section>
    @endif

    {{-- ============ WHY ONHOST — FEATURE GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">{{ __('front.home.features_title') }}</h2>
                        <p class="section-subheading">{{ __('front.home.features_subtitle') }}</p>
                    </div>
                    @foreach([
                        ['key' => 'feature_ai',         'icon' => 'icon-cpu',        'badge' => 'AI',    'route' => route('front.features.ai')],
                        ['key' => 'feature_monitoring',  'icon' => 'icon-speed', 'badge' => '24/7',  'route' => route('front.features.monitoring')],
                        ['key' => 'feature_backups',     'icon' => 'icon-diskette',      'badge' => '14 dní','route' => route('front.features.backups')],
                        ['key' => 'feature_wp',          'icon' => 'icon-speed',       'badge' => '1-click','route' => route('front.wordpress')],
                        ['key' => 'feature_ssl',         'icon' => 'icon-lock',         'badge' => 'Free',  'route' => null],
                        ['key' => 'feature_nvme',        'icon' => 'icon-drives',      'badge' => 'NVMe',  'route' => null],
                    ] as $feature)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $feature['badge'] }}</div>
                                <i class="{{ $feature['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ __("front.home.{$feature['key']}.title") }}</div>
                                <p class="subtitle seccolor">{{ __("front.home.{$feature['key']}.text") }}</p>
                                @if($feature['route'])
                                    <a href="{{ $feature['route'] }}" class="btn btn-default-yellow-fill" title="{{ __("front.home.{$feature['key']}.title") }}">{{ __('panel.common.detail') }} →</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURE HIGHLIGHTS (2-column) ============ --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">{{ __('front.home.trust_title') }}</h2>
                            <p class="seccolor">{{ __('front.home.trust_text') }}</p>
                        </div>
                        <ul class="list-unstyled seccolor mt-3">
                            @foreach((array) __('front.home.compare_items') as $item)
                                <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">{{ __('front.home.hero_cta') }}</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0">
                        <div class="wrapper bg-seccolorstyle p-4 rounded">
                            <ul class="list-unstyled mb-0">
                                @foreach([
                                    ['icon' => 'icon-lock',        'text' => 'SSL certifikát zdarma'],
                                    ['icon' => 'icon-diskette',     'text' => 'Denní zálohy (retence 14 dní)'],
                                    ['icon' => 'icon-speed','text' => 'Monitoring dostupnosti 24/7'],
                                    ['icon' => 'icon-emailopen',  'text' => 'E-mailové schránky v ceně'],
                                    ['icon' => 'icon-cpu',       'text' => 'PHP 8.3 / MySQL / MariaDB'],
                                    ['icon' => 'icon-support',    'text' => 'Podpora v češtině'],
                                ] as $item)
                                    <li class="d-flex align-items-center py-2 border-bottom seccolor">
                                        <i class="{{ $item['icon'] }} purple me-3 f-18"></i>{{ $item['text'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CUSTOMER TESTIMONIALS ============ --}}
    <section class="casestudy sec-bg2 bg-colorstyle pt-150">
        <div class="container">
            <div class="sec-main sec-up bg-white mb-0 nomargin p-80 br-12">
                <div class="row">
                    <div class="col-sm-12 text-center pb-4">
                        <h2 class="section-heading mergecolor">Co říkají naši zákazníci</h2>
                        <p class="section-subheading seccolor">Tisíce spokojených zákazníků — od startupů po etablované české firmy.</p>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-9">
                        <div class="slider-container slider-filter">
                            <div class="slider-wrap">
                                <div class="swiper-container main-slider"
                                     data-autoplay="5000"
                                     data-touch="1"
                                     data-mouse="0"
                                     data-slides-per-view="responsive"
                                     data-loop="1"
                                     data-speed="1200"
                                     data-mode="horizontal"
                                     data-xs-slides="1"
                                     data-sm-slides="1"
                                     data-md-slides="1"
                                     data-lg-slides="1">
                                    <div class="swiper-wrapper">
                                        <div class="swiper-slide">
                                            <h3 class="author mergecolor">ACME s.r.o.</h3>
                                            <div class="content-info text-muted">
                                                <p>„Přešli jsme na OnHost před rokem a výkon webů se znatelně zlepšil. NVMe SSD a PHP 8.3 dělají velký rozdíl. Zálohy jsou vždy po ruce a podpora reaguje během hodiny."</p>
                                                <div class="mb-3 seccolor">Tomáš Novák — technický ředitel</div>
                                                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mb-2" title="Webhosting">Vyzkoušet webhosting</a>
                                            </div>
                                        </div>
                                        <div class="swiper-slide">
                                            <h3 class="author mergecolor">GameClan CZ</h3>
                                            <div class="content-info text-muted">
                                                <p>„Herní servery na OnHost mají stabilní ping pod 20 ms a panel Pterodactyl je přehledný. Za cenu, kterou platíme, jinde tohle nenajdete."</p>
                                                <div class="mb-3 seccolor">Jakub Dvořák — správce komunity</div>
                                                <a href="{{ route('front.gamehosting') }}" class="btn btn-default-yellow-fill mb-2" title="Herní hosting">Herní servery</a>
                                            </div>
                                        </div>
                                        <div class="swiper-slide">
                                            <h3 class="author mergecolor">Startup Factory</h3>
                                            <div class="content-info text-muted">
                                                <p>„VPS od OnHost nám dal plnou kontrolu — root přístup, snapshot zálohy a KVM virtualizace. Nasazení přes deploy.sh trvá minuty."</p>
                                                <div class="mb-3 seccolor">Eva Procházková — CTO</div>
                                                <a href="{{ route('front.vps') }}" class="btn btn-default-yellow-fill mb-2" title="VPS">Cloud VPS</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pagination vertical-mode pagination-index"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-lg-3 d-none d-lg-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <div class="display-4 fw-bold purple">4.9</div>
                            <div class="seccolor small">průměrné hodnocení</div>
                            <div class="mt-2">
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                            </div>
                            <div class="seccolor small mt-1">z 500+ recenzí</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FAQ — ACCORDION ============ --}}
    <section class="sec-normal sec-bg2 bg-colorstyle">
        <div class="faq">
            <div class="container">
                <div class="row">
                    <div class="col-md-12 col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">{{ __('front.home.faq_title') }}</h2>
                        <p class="section-subheading mergecolor">Nejčastější otázky o hostingových službách OnHost.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="accordion faq pt-5">
                            @foreach((array) __('front.home.faq_items') as $i => $faq)
                                <div class="panel-wrap">
                                    <div class="panel-title seccolor {{ $i === 0 ? 'active' : '' }}">
                                        <span>{{ $faq['q'] }}</span>
                                        <div class="float-end">
                                            <i class="fa fa-plus"></i>
                                            <i class="fa fa-minus c-pink"></i>
                                        </div>
                                    </div>
                                    <div class="panel-collapse" @if($i === 0) style="display:block" @endif>
                                        <div class="wrapper-collapse">
                                            <div class="info">
                                                <ul class="list seccolor"><li><p>{{ $faq['a'] }}</p></li></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP / CONTACT ============ --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('panel.support.index') }}" class="help-item" title="Ticket">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Ticketová podpora</div>
                                    <div class="description seccolor">Nejrychlejší cesta k řešení — sledujete průběh v reálném čase.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Kontakt">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Napište nám</div>
                                    <div class="description seccolor">Kontaktní formulář nebo e-mail — odpovíme do 1 pracovního dne.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Znalostní báze</div>
                                    <div class="description seccolor">Návody pro DNS, SSL, e-mail a WordPress — vždy po ruce.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FINAL CTA ============ --}}
    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">{{ __('front.home.cta_title') }}</h2>
                <p class="text-white-50 pb-3">{{ __('front.home.cta_text') }}</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill">{{ __('front.home.cta_button') }}</a>
            </div>
        </div>
    </section>
@endsection
