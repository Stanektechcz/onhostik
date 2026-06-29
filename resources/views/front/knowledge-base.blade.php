@extends('layouts.front')

@section('title', __('front.pages.kb.title'))
@section('meta_description', 'Znalostní báze Onhost.cz — návody pro nastavení DNS, e-mailu, SSL, FTP a dalších funkcí hostingu.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header">
        <div class="total-grad-inverse"></div>
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.kb.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.kb.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Oblíbené kategorie</div>
                            <ul><li><i class="fas fa-check-circle"></i> DNS a domény</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Nastavení e-mailu</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> SSL a bezpečnost</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> WordPress</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ CATEGORIES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Oblíbené kategorie</h2>
                        <p class="section-subheading">Návody a odpovědi na nejčastější otázky.</p>
                    </div>
                    @php
                        $catIcons = [
                            'dns'      => 'ico-globe',
                            'email'    => 'icon-emailopen',
                            'ssl'      => 'icon-lock',
                            'wordpress'=> 'icon-speed',
                            'databaze' => 'icon-drives',
                            'zalohy'   => 'icon-diskette',
                            'general'  => 'icon-drives',
                        ];
                        $placeholder = [
                            ['icon' => 'ico-globe',      'badge' => '12 článků',  'title' => 'DNS a domény',      'items' => ['Nastavení A záznamu', 'MX záznamy pro e-mail', 'CNAME a subdoména', 'Přenos domény']],
                            ['icon' => 'icon-emailopen',  'badge' => '8 článků',   'title' => 'E-mail',            'items' => ['Nastavení Outlooku', 'Nastavení Thunderbirdu', 'Webmail RoundCube', 'SPF / DKIM / DMARC']],
                            ['icon' => 'icon-lock',        'badge' => '6 článků',   'title' => 'SSL a bezpečnost',  'items' => ["Let's Encrypt certifikát", 'HTTPS přesměrování', 'HTTP hlavičky', 'Firewall pravidla']],
                            ['icon' => 'icon-speed',      'badge' => '10 článků',  'title' => 'WordPress',         'items' => ['Instalace WordPress', 'Migrace WP webu', 'Cache pluginy', 'Obnovení zálohy']],
                            ['icon' => 'icon-drives',     'badge' => '5 článků',   'title' => 'Databáze',          'items' => ['phpMyAdmin', 'Import / export SQL', 'Uživatel a práva', 'Vzdálený přístup']],
                            ['icon' => 'icon-diskette',     'badge' => '4 články',   'title' => 'Zálohy a obnova',   'items' => ['Jak fungují zálohy', 'Obnova ze zálohy', 'Manuální záloha', 'Plán záloh']],
                        ];
                    @endphp
                    @if(isset($categories) && $categories->isNotEmpty())
                        @foreach($categories as $catName => $articles)
                            <div class="col-sm-12 col-md-4" data-aos="fade-up">
                                <div class="service-section bg-colorstyle">
                                    <div class="plans badge feat bg-purple">{{ $articles->count() }} {{ $articles->count() === 1 ? 'článek' : 'článků' }}</div>
                                    <i class="{{ $catIcons[strtolower($catName)] ?? 'icon-drives' }} f-30 purple mb-3 d-block"></i>
                                    <div class="title mergecolor">{{ $catName }}</div>
                                    <ul class="list-unstyled seccolor f-14 mt-2 mb-0">
                                        @foreach($articles->take(4) as $a)
                                            <li class="py-1">
                                                <a href="{{ route('front.kb.show', $a->slug) }}" class="seccolor">
                                                    <i class="fas fa-chevron-right purple pe-2 f-12"></i>{{ $a->title }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach($placeholder as $cat)
                            <div class="col-sm-12 col-md-4" data-aos="fade-up">
                                <div class="service-section bg-colorstyle">
                                    <div class="plans badge feat bg-purple">{{ $cat['badge'] }}</div>
                                    <i class="{{ $cat['icon'] }} f-30 purple mb-3 d-block"></i>
                                    <div class="title mergecolor">{{ $cat['title'] }}</div>
                                    <ul class="list-unstyled seccolor f-14 mt-2 mb-0">
                                        @foreach($cat['items'] as $item)
                                            <li class="py-1"><i class="fas fa-chevron-right purple pe-2 f-12"></i>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ============ POPULAR & NEWEST ARTICLES (Antler knowledgebase-list sec-main knowledge) ============ --}}
    @if(isset($categories) && $categories->isNotEmpty())
    @php
        $allArticles = $categories->flatten();
        $newest = $allArticles->sortByDesc('created_at')->take(4);
    @endphp
    @if($newest->isNotEmpty())
    <section class="services sec-normal pt-0 pb-80 bg-colorstyle">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="sec-main sec-bg1 bg-colorstyle noshadow">
                        <div class="heading active mergecolor">Nejnovější články</div>
                        <div class="line active mb-4"></div>
                        <div class="row">
                            @foreach($newest as $article)
                            <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                                <div class="sec-bg4 bg-seccolorstyle noshadow p-3 h-100">
                                    <a href="{{ route('front.kb.show', $article->slug) }}" class="mergecolor">
                                        <i class="far fa-file-alt purple me-2"></i>
                                        <span class="f-14">{{ $article->title }}</span>
                                    </a>
                                    @if($article->category)
                                    <div class="mt-1">
                                        <span class="badge bg-purple text-white f-11">{{ $article->category }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif
    @endif

    {{-- ============ HELP ============ --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Kontaktovat podporu</div>
                                    <div class="description seccolor">Nenašli jste odpověď? Otevřete ticket — odpovíme do 1 prac. dne.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('panel.ai.index') }}" class="help-item" title="AI asistent">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">AI asistent</div>
                                    <div class="description seccolor">Okamžité odpovědi na technické otázky — DNS, PHP, e-mail.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.faq') }}" class="help-item" title="Časté dotazy">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Časté dotazy</div>
                                    <div class="description seccolor">Nejčastější otázky o tarifech, platbách a aktivaci služeb.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
