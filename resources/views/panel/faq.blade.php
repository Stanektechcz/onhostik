@extends('layouts.panel')

@php
    $breadcrumbTitle = 'FAQ';
    $breadcrumbItems = ['FAQ' => ''];

    /* Static OnHost FAQ data grouped by category */
    $faqGroups = [
        'Základní dotazy' => [
            ['q' => 'Jaké jsou rozdíly mezi tarify webhostingu?',
             'a' => 'Každý tarif se liší diskové kapacitou, počtem databází, e-mailových schránek a povolenou ší&shy;řkou pásma. Start tarify jsou ideální pro osobní weby a blogy, Pro tarify pro menší podnikání a Business tarify pro náročné projekty a e-shopy. Kompletní srovnání najdete na stránce Výběr tarifu.'],
            ['q' => 'Co je sdílený hosting?',
             'a' => 'Sdílený hosting znamená, že váš web běží na serveru společně s dalšími weby. Je to nejdostupnější a nejjednodušší forma hostingu vhodná pro začátečníky a weby s průměrnou návštěvností. Server, procesor, paměť i diskový prostor jsou sdíleny, přičemž každý zákazník má svůj vlastní oddělený prostor.'],
            ['q' => 'Jak rychle bude moje služba aktivována?',
             'a' => 'Aktivace probíhá automaticky ihned po přijetí platby. U bankovního převodu dochází k aktivaci do 24 hodin od připsání platby na náš účet. Platba kartou přes Comgate je zpracována okamžitě.'],
            ['q' => 'Mohu kdykoli přejít na vyšší/nižší tarif?',
             'a' => 'Ano, tarif lze upgradovat nebo downgradovat kdykoli. Při upgradu se dopočítá poměrná část. Při downgradu platíte novou cenu od dalšího fakturačního období. Kontaktujte naši podporu a domluvíme změnu tarif.'],
        ],
        'Domény a DNS' => [
            ['q' => 'Jak dlouho trvá přenos domény k OnHost?',
             'a' => 'Přenos (transfer) .cz domény trvá obvykle 24–48 hodin. Mezinárodní domény (.com, .net, .org) trvají 5–7 pracovních dní. Před transferem je nutné odemknout doménu u současného registrátora a získat AuthCode (EPP kód).'],
            ['q' => 'Co je DNS propagace a jak dlouho trvá?',
             'a' => 'DNS propagace je proces šíření změn DNS záznamů po celém internetu. Obvykle trvá 24–48 hodin, v některých případech až 72 hodin. Během propagace může web fungovat správně jen z některých míst světa.'],
            ['q' => 'Mohu mít doménu u jiného registrátora a hosting u OnHost?',
             'a' => 'Ano, hosting a doménu nemusíte mít u stejného poskytovatele. Stačí ve správci domény nastavit nameservery (NS záznamy) na servery OnHost: ns1.onhost.cz a ns2.onhost.cz.'],
        ],
        'Fakturace a platby' => [
            ['q' => 'Jaké platební metody přijímáte?',
             'a' => 'Akceptujeme platby kartou (Visa, Mastercard) přes platební bránu Comgate, bankovní převod na účet 318 000 2153/0800 a platbu z kreditního zůstatku na účtu. Veškeré platby jsou zabezpečeny SSL šifrováním.'],
            ['q' => 'Kdy dostanu fakturu?',
             'a' => 'Proforma faktura (zálohová faktura) je vystavena okamžitě po vytvoření objednávky. Daňový doklad (řádná faktura) je vystaven po přijetí platby. Faktury najdete v sekci Fakturace ve vašem zákaznickém panelu.'],
            ['q' => 'Jak funguje obnova služby?',
             'a' => 'Před koncem platebního období vám zašleme upozornění e-mailem. Proforma faktura je vystavena automaticky 14 dní před koncem období. Pokud nebude uhrazena do splatnosti, služba bude pozastavena. Po uhrazení je aktivace okamžitá.'],
            ['q' => 'Nabízíte zkušební dobu nebo vrácení peněz?',
             'a' => 'Nabízíme 30denní garanc vrácení peněz pro nové zákazníky. Pokud nejste spokojeni se službou, kontaktujte nás do 30 dnů od aktivace a vrátíme vám celou platbu. Platí pro hostingové balíčky, nevztahuje se na domény.'],
        ],
        'Správa účtu a zabezpečení' => [
            ['q' => 'Jak mohu resetovat heslo do panelu?',
             'a' => 'Na přihlašovací stránce klikněte na odkaz "Zapomenuté heslo". Zadejte e-mail spojený s účtem a obdržíte odkaz pro reset hesla. Odkaz je platný 60 minut.'],
            ['q' => 'Jsou moje data pravidelně zálohována?',
             'a' => 'Ano, veškerá data jsou zálohována denně. Zálohy jsou uchovávány po dobu 7 dní zpětně. Na vyžádání vám zálohu obnovíme. Pro kritické projekty doporučujeme vlastní zálohu dat (databáze, soubory).'],
            ['q' => 'Jak mohu přidat dalšího uživatele nebo správce k mému účtu?',
             'a' => 'Správa přístupů (sub-uživatelé) je ve vývoji a bude dostupná v dalším vydání. Prozatím kontaktujte podporu, pokud potřebujete přístup pro třetí osobu.'],
            ['q' => 'Jak změním fakturační adresu nebo IČO?',
             'a' => 'Fakturační údaje lze upravit v sekci Nastavení → Fakturační údaje. Změna se projeví na nových dokladech. Stávající vystavené faktury změnit nelze.'],
            ['q' => 'Je možné provozovat více webů na jednom hostingovém balíčku?',
             'a' => 'Závisí na tarifu. Tarify Pro a Business podporují více domén (addon domains). Na tarifu Start je možná pouze jedna primární doména. Přesný počet povolených domén je uveden v detailu tarifu.'],
        ],
    ];
@endphp

@section('title', 'FAQ — Časté dotazy')

@section('content')
<div class="container-fluid">

<div class="container">
<div class="faq-wrap">
<div class="grid grid-cols-12 card-gap">

{{-- ── Widget cards ────────────────────────────────────────────── --}}
<div class="col-span-4 xl:col-span-12 box-col-span-6">
    <div class="card bg-primary">
        <div class="card-body">
            <div class="flex faq-widgets">
                <div class="grow faq-flex">
                    <h5>Články a návody</h5>
                    <p>Procházejte naši znalostní bázi s návody na správu hostingu, domén, e-mailu a WordPress. Odpovědi na nejčastější technické otázky.</p>
                </div>
                <i data-feather="file-text"></i>
            </div>
        </div>
    </div>
</div>
<div class="col-span-4 xl:col-span-6 sm:col-span-12 box-col-span-6">
    <div class="card bg-primary">
        <div class="card-body">
            <div class="flex faq-widgets">
                <div class="grow faq-flex">
                    <h5>Znalostní báze</h5>
                    <p>Centrální repozitář znalostí pro správu hostingových služeb OnHost. Rychlé řešení problémů, konfigurační návody a tipy.</p>
                </div>
                <i data-feather="book-open"></i>
            </div>
        </div>
    </div>
</div>
<div class="col-span-4 xl:col-span-6 sm:col-span-12 box-col-span-12">
    <div class="card bg-primary">
        <div class="card-body">
            <div class="flex faq-widgets">
                <div class="grow faq-flex">
                    <h5>Technická podpora</h5>
                    <p>E-mail, ticket systém — naši technici jsou k dispozici pro řešení vašich dotazů a problémů se službami.</p>
                </div>
                <i data-feather="aperture"></i>
            </div>
        </div>
    </div>
</div>

{{-- ── Main FAQ: accordion + sidebar ──────────────────────────── --}}
<div class="col-span-12">
    <div class="header-faq">
        <h5>Časté dotazy</h5>
    </div>
    <div class="grid grid-cols-12 card-gap default-according style-1 faq-accordion" id="accordionoc">

        {{-- Left: accordions (col-span-8) --}}
        <div class="col-span-8 xl-80 xl:col-span-6 lg:col-span-7 md:col-span-12">
            @php $idx = 1; @endphp
            @foreach($faqGroups as $groupTitle => $items)
                @if(!$loop->first)
                <div class="faq-title">
                    <h6>{{ $groupTitle }}</h6>
                </div>
                @endif
                @foreach($items as $item)
                @php $collapseId = 'collapseicon' . $idx; $idx++; @endphp
                <div class="card accordion">
                    <div class="card-header accordion-item">
                        <h5 class="accordion-header relative">
                            <button class="accordion-button btn btn-link collapsed"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $collapseId }}">
                                <i class="!ml-0 !rotate-0" data-feather="help-circle"></i>
                                {{ $item['q'] }}
                            </button>
                        </h5>
                        <div class="accordion-collapse collapse"
                             id="{{ $collapseId }}"
                             data-bs-parent="#accordionoc">
                            <div class="card-body">{{ $item['a'] }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            @endforeach
        </div>

        {{-- Right: search + navigation + updates (col-span-4) --}}
        <div class="col-span-4 xl:col-span-6 lg:col-span-5 md:col-span-12 xl-40">
            <div class="grid grid-cols-12 card-gap">

                {{-- Search --}}
                <div class="col-span-12">
                    <div class="card card-mb-faq xs-mt-search">
                        <div class="card-header faq-header pb-0">
                            <h5>Hledat v FAQ</h5>
                            <i data-feather="help-circle"></i>
                        </div>
                        <div class="card-body faq-body">
                            <form method="GET" action="{{ route('panel.faq.index') }}">
                                <div class="faq-form">
                                    <input class="form-control" type="text" name="q"
                                           value="{{ $search }}"
                                           placeholder="Hledat otázku…">
                                    <i class="search-icon" data-feather="search"></i>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="col-span-12">
                    <div class="card card-mb-faq">
                        <div class="card-header faq-header !pb-0">
                            <h5>Navigace</h5>
                            <i data-feather="settings"></i>
                        </div>
                        <div class="card-body faq-body">
                            <div class="navigation-btn">
                                <a class="btn btn-primary text-white hover:text-white"
                                   href="{{ route('panel.support.store') }}"
                                   data-submit-form="quick-ticket-form">
                                    <i class="m-r-10" data-feather="message-square"></i>Otevřít ticket
                                </a>
                                <form id="quick-ticket-form" method="GET" action="{{ route('panel.support.index') }}" style="display:none;"></form>
                            </div>
                            <div class="navigation-option">
                                <ul>
                                    <li>
                                        <a href="{{ route('panel.kb.index') }}">
                                            <i data-feather="edit"></i>Návody
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.support.index') }}">
                                            <i data-feather="globe"></i>Centrum podpory
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.kb.index') }}">
                                            <i data-feather="book-open"></i>Znalostní báze
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.blog.index') }}">
                                            <i data-feather="file-text"></i>Články a blog
                                        </a>
                                        <span class="badge badge-primary rounded-full pull-right text-white">
                                            {{ \App\Models\BlogPost::where('is_published', true)->count() }}
                                        </span>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.kb.index') }}">
                                            <i data-feather="message-circle"></i>Znalostní báze OnHost
                                        </a>
                                        <span class="badge badge-primary rounded-full pull-right text-white">
                                            {{ \App\Models\KbArticle::where('is_published', true)->count() }}
                                        </span>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.support.index') }}">
                                            <i data-feather="mail"></i>Kontaktujte nás
                                        </a>
                                    </li>
                                </ul>
                                <hr>
                                <ul>
                                    <li>
                                        <a href="{{ route('panel.support.index') }}">
                                            <i data-feather="message-circle"></i>Podpor komunita
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('panel.support.index') }}">
                                            <i data-feather="mail"></i>Kontaktujte nás
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Latest updates --}}
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header faq-header !pb-0">
                            <h5 class="inline-block">Poslední aktivity</h5>
                            <a class="pull-right inline-block f-12 text-primary" href="{{ route('panel.support.index') }}">Zobrazit vše</a>
                        </div>
                        <div class="card-body faq-body">
                            @if($recentTickets && count($recentTickets) > 0)
                                @foreach($recentTickets as $ticket)
                                <div class="flex updates-faq-main">
                                    <div class="updates-faq">
                                        <i class="font-primary" data-feather="message-square"></i>
                                    </div>
                                    <div class="grow updates-bottom-time w-[calc(100%_-_40px_-_20px)]">
                                        <p><a href="{{ route('panel.support.show', $ticket) }}">{{ Str::limit($ticket->subject ?? 'Ticket #' . $ticket->id, 50) }}</a></p>
                                        <p>{{ $ticket->updated_at?->diffForHumans() }}</p>
                                    </div>
                                </div>
                                @endforeach
                            @else
                                {{-- Static fallback updates --}}
                                <div class="flex updates-faq-main">
                                    <div class="updates-faq">
                                        <i class="font-primary" data-feather="check-circle"></i>
                                    </div>
                                    <div class="grow updates-bottom-time w-[calc(100%_-_40px_-_20px)]">
                                        <p>Vítejte v zákaznickém panelu OnHost</p>
                                        <p>Správa hostingu, domén a fakturace na jednom místě.</p>
                                    </div>
                                </div>
                                <div class="flex updates-faq-main">
                                    <div class="updates-faq">
                                        <i class="font-primary" data-feather="book-open"></i>
                                    </div>
                                    <div class="grow updates-bottom-time w-[calc(100%_-_40px_-_20px)]">
                                        <p>Prohlédněte si <a href="{{ route('panel.kb.index') }}">znalostní bázi</a></p>
                                        <p>Návody a tipy pro správu vašich služeb.</p>
                                    </div>
                                </div>
                                <div class="flex updates-faq-main">
                                    <div class="updates-faq">
                                        <i class="font-primary" data-feather="dollar-sign"></i>
                                    </div>
                                    <div class="grow updates-bottom-time w-[calc(100%_-_40px_-_20px)]">
                                        <p>Faktury a platby spravujte v sekci <a href="{{ route('panel.billing.invoices') }}">Fakturace</a></p>
                                        <p>Přehled plateb, faktur a kreditního zůstatku.</p>
                                    </div>
                                </div>
                                <div class="flex updates-faq-main">
                                    <div class="updates-faq">
                                        <i class="font-primary" data-feather="message-square"></i>
                                    </div>
                                    <div class="grow updates-bottom-time w-[calc(100%_-_40px_-_20px)]">
                                        <p>Potřebujete pomoc? <a href="{{ route('panel.support.index') }}">Otevřete ticket</a></p>
                                        <p>Naši technici vám odpoví co nejdříve.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>{{-- /faq-accordion grid --}}
</div>

{{-- ── Featured Tutorials (from KB articles) ──────────────────── --}}
<div class="col-span-12">
    <div class="header-faq">
        <h5 class="mb-0">Doporučené návody</h5>
    </div>
    <div class="grid grid-cols-12 card-gap">
        @forelse($kbArticles as $article)
        <div class="col-span-3 xxl:col-span-6 md:col-span-12 box-col-span-6">
            <div class="card features-faq product-box">
                <div class="faq-image product-img">
                    <div style="height:160px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                        <i data-feather="book-open" style="width:48px;height:48px;stroke-width:1;color:rgba(var(--theme-default),1);opacity:.5;"></i>
                    </div>
                    <div class="product-hover">
                        <ul>
                            <li><a href="{{ route('panel.kb.show', $article->slug) }}"><i class="icon-link"></i></a></li>
                            <li><i class="icon-import"></i></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="pb-1">{{ $article->category ?? 'Návod' }}</h6>
                    <p class="c-light">{{ Str::limit($article->excerpt ?? $article->title, 120) }}</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('panel.kb.show', $article->slug) }}" class="f-12">
                        {{ $article->title }}
                    </a>
                    <span class="pull-right">
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                    </span>
                </div>
            </div>
        </div>
        @empty
        {{-- Static fallback tutorials --}}
        @foreach([
            ['icon' => 'globe', 'cat' => 'Webhosting', 'title' => 'Jak nahrát web přes FTP / SFTP?', 'desc' => 'Průvodce nahráváním souborů na hosting pomocí FileZilla a dalších FTP klientů. Včetně nastavení připojení.'],
            ['icon' => 'mail', 'cat' => 'E-mail', 'title' => 'Nastavení e-mailu v Outlook a Gmail', 'desc' => 'Kompletní postup konfigurace IMAP/POP3 a SMTP pro správu firemní e-mailové schránky.'],
            ['icon' => 'server', 'cat' => 'DNS', 'title' => 'Jak správně nastavit DNS záznamy?', 'desc' => 'Vysvětlení typů DNS záznamů (A, CNAME, MX, TXT) a praktický postup jejich nastavení.'],
            ['icon' => 'shield', 'cat' => 'Zabezpečení', 'title' => 'SSL certifikát a HTTPS — průvodce', 'desc' => 'Jak nainstalovat bezplatný Let\'s Encrypt SSL certifikát a přesměrovat HTTP na HTTPS.'],
        ] as $t)
        <div class="col-span-3 xxl:col-span-6 md:col-span-12 box-col-span-6">
            <div class="card features-faq product-box">
                <div class="faq-image product-img">
                    <div style="height:160px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                        <i data-feather="{{ $t['icon'] }}" style="width:48px;height:48px;stroke-width:1;color:rgba(var(--theme-default),1);opacity:.5;"></i>
                    </div>
                    <div class="product-hover">
                        <ul>
                            <li><a href="{{ route('panel.kb.index') }}"><i class="icon-link"></i></a></li>
                            <li><i class="icon-import"></i></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="pb-1">{{ $t['cat'] }}</h6>
                    <p class="c-light">{{ $t['desc'] }}</p>
                </div>
                <div class="card-footer">
                    <span>{{ $t['title'] }}</span>
                    <span class="pull-right">
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                        <i class="fa-solid fa-star font-warning"></i>
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endforelse
    </div>
</div>

{{-- ── Latest Articles and Videos ──────────────────────────────── --}}
<div class="col-span-12">
    <div class="header-faq">
        <h5>Nejnovější články</h5>
    </div>
    <div class="grid grid-cols-12 card-gap faq-wrapper">

        {{-- Blog posts col 1 --}}
        <div class="col-span-4 xl:col-span-6 md:col-span-12">
            <div class="grid grid-cols-12 card-gap">
                @foreach($blogPosts->take(3) as $post)
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex">
                                <i class="m-r-20" data-feather="codepen"></i>
                                <div class="grow flex-1">
                                    <h6 class="pb-2">
                                        <a href="{{ route('panel.blog.show', $post->slug) }}">{{ $post->title }}</a>
                                    </h6>
                                    <p class="c-light">{{ Str::limit($post->excerpt, 100) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                @if($blogPosts->count() === 0)
                {{-- Static fallback --}}
                @foreach([
                    ['t' => 'Jak nastavit WordPress na webhostingu OnHost', 'd' => 'Krok za krokem od instalace po základní konfiguraci WordPress na vašem hostingovém účtu.'],
                    ['t' => 'Optimalizace rychlosti webu: caching a CDN', 'd' => 'Jak efektivně nastavit cachování a využít CDN pro rychlejší načítání stránek.'],
                    ['t' => '5 nejdůležitějších bezpečnostních nastavení hostingu', 'd' => 'Zabezpečte svůj web: správa hesel, SSL, firewall a pravidelné zálohy.'],
                ] as $a)
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex">
                                <i class="m-r-20" data-feather="codepen"></i>
                                <div class="grow flex-1">
                                    <h6 class="pb-2">{{ $a['t'] }}</h6>
                                    <p class="c-light">{{ $a['d'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>

        {{-- KB articles col 2 --}}
        <div class="col-span-4 xl:col-span-6 md:col-span-12">
            <div class="grid grid-cols-12 card-gap">
                @foreach($kbArticles->skip(0)->take(3) as $article)
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex">
                                <i class="m-r-20" data-feather="file-text"></i>
                                <div class="grow flex-1">
                                    <h6 class="pb-2">
                                        <a href="{{ route('panel.kb.show', $article->slug) }}">{{ $article->title }}</a>
                                    </h6>
                                    <p class="c-light">{{ Str::limit($article->excerpt, 100) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                @if($kbArticles->count() === 0)
                @foreach([
                    ['t' => 'Přesměrování domény pomocí .htaccess', 'd' => 'Jak přesměrovat www na non-www, HTTP na HTTPS nebo celou doménu na jinou URL.'],
                    ['t' => 'Správa databáze MySQL přes phpMyAdmin', 'd' => 'Základy práce s databázemi: import, export, správa uživatelů a zálohování.'],
                    ['t' => 'Nastavení e-mailových schránek a aliasů', 'd' => 'Jak vytvořit firemní e-mail, nastavit přeposílání a konfigurovat antispam.'],
                ] as $a)
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex">
                                <i class="m-r-20" data-feather="file-text"></i>
                                <div class="grow flex-1">
                                    <h6 class="pb-2">{{ $a['t'] }}</h6>
                                    <p class="c-light">{{ $a['d'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>

        {{-- Video / Tips col 3 --}}
        <div class="col-span-4 xl:col-span-12">
            <div class="grid grid-cols-12 card-gap">
                @foreach([
                    ['icon' => 'youtube', 'title' => 'Zákaznický panel OnHost — rychlý přehled', 'desc' => 'Video průvodce základními funkcemi zákaznického panelu: objednávky, faktury, správa domén.'],
                    ['icon' => 'youtube', 'title' => 'DNS a domény do 5 minut', 'desc' => 'Jak správně nastavit DNS záznamy pro váš web, e-mail a subdomény.'],
                    ['icon' => 'youtube', 'title' => 'Zálohy a obnova dat', 'desc' => 'Jak spravovat zálohy vašeho webu a databáze a jak provést jejich obnovení.'],
                ] as $v)
                <div class="col-span-12 xl:col-span-6 md:col-span-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex">
                                <i class="m-r-20" data-feather="{{ $v['icon'] }}"></i>
                                <div class="grow flex-1">
                                    <h6 class="pb-2">{{ $v['title'] }}</h6>
                                    <p class="c-light">{{ $v['desc'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

</div>{{-- /faq-wrap grid --}}
</div>{{-- /faq-wrap --}}
</div>{{-- /container --}}
</div>{{-- /container-fluid --}}
@endsection

@php use Illuminate\Support\Str; @endphp
