@extends('layouts.front')

@section('title', 'E-mailová bezpečnost — anti-spam ochrana | Onhost.cz')
@section('meta_description', 'Pokročilá ochrana e-mailů před spamem a phishingem. 99,98% přesnost detekce, MX záloha, karanténní schránka.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay-video">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading">E-mailová bezpečnost</h1>
                        <div class="subheading mb-0">Výkonná ochrana e-mailů s inteligentním filtrem — <b class="c-pink">99,98% přesnost detekce spamu.</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING PLANS ============ --}}
    <section class="pricing special bg-colorstyle specialposition pt-5">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-4 col-lg-4">
                    <div class="wrapper first text-start noshadow" data-aos="fade-up" data-aos-duration="1000">
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Anti-Spam Essential</div>
                            <div class="fromer seccolor">Základní ochrana pro malé firmy</div>
                            <div class="price mergecolor"><sup>Kč</sup>49 <span class="period">/měs.</span></div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-drives"></i> <div>DISK<br><span>20 GB prostoru</span></div></li>
                            <li><i class="icon-virus"></i> <div>SPAM<br><span>Anti-Spam Premium</span></div></li>
                            <li><i class="icon-inverse"></i> <div>MX<br><span>MX záloha</span></div></li>
                            <li><i class="icon-window"></i> <div>PANEL<br><span>Webový panel</span></div></li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-12 col-md-4 col-lg-4">
                    <div class="wrapper text-start noshadow" data-aos="fade-up" data-aos-duration="700">
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Anti-Spam Business</div>
                            <div class="fromer seccolor">Pro rostoucí firmy s větším objemem</div>
                            <div class="price mergecolor"><sup>Kč</sup>199 <span class="period">/měs.</span></div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-drives"></i> <div>DISK<br><span>40 GB prostoru</span></div></li>
                            <li><i class="icon-virus"></i> <div>SPAM<br><span>Anti-Spam Premium</span></div></li>
                            <li><i class="icon-inverse"></i> <div>MX<br><span>MX záloha</span></div></li>
                            <li><i class="icon-window"></i> <div>PANEL<br><span>Webový panel</span></div></li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-12 col-md-4 col-lg-4">
                    <div class="wrapper third text-start noshadow" data-aos="fade-up" data-aos-duration="900">
                        <div class="plans badge feat bg-purple">doporučujeme</div>
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Anti-Spam Enterprise</div>
                            <div class="fromer seccolor">Maximální ochrana pro velké organizace</div>
                            <div class="price mergecolor"><sup>Kč</sup>499 <span class="period">/měs.</span></div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-drives"></i> <div>DISK<br><span>60 GB prostoru</span></div></li>
                            <li><i class="icon-virus"></i> <div>SPAM<br><span>Anti-Spam Premium</span></div></li>
                            <li><i class="icon-inverse"></i> <div>MX<br><span>MX záloha</span></div></li>
                            <li><i class="icon-window"></i> <div>PANEL<br><span>Webový panel</span></div></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURES ============ --}}
    <section class="history-section sec-normal exapath bg-colorstyle">
        <div class="container">
            <div class="sec-main sec-bg1 bg-colorstyle noshadow">
                <div class="row">
                    <div class="col-md-10 offset-md-1">
                        <div class="info-content">
                            <h2 class="mb-4 mergecolor"><b>Nejvyšší úroveň zabezpečení</b></h2>
                            <p class="seccolor">Naše e-mailová bezpečnostní řešení používají vícevrstvou inteligentní ochranu, která detekuje spam, phishing a malware ještě před doručením do vaší schránky. Kombinace strojového učení a reputačních databází zajišťuje minimální počet falešně pozitivních výsledků.</p>
                        </div>
                    </div>
                </div>
                <div class="row mt-5">
                    <div class="col-md-10 offset-md-1">
                        <div class="info-content">
                            <h2 class="mb-4 mergecolor"><b>Karanténní schránka a přehled</b></h2>
                            <p class="seccolor">Každá zachycená zpráva je uložena do karanténní schránky, kde ji můžete zkontrolovat a rozhodnout o jejím osudu. Přehledný webový panel vám dává plnou kontrolu nad e-mailovým provozem — vidíte statistiky, whitelist/blacklist a historii zpráv.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ WHY CHOOSE ============ --}}
    <section class="services sec-normal exapath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč zvolit e-mailovou bezpečnost od Onhost?</h2>
                        <p class="section-subheading">Ochrana, která funguje — bez kompromisů a bez zbytečné složitosti.</p>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle" data-aos="fade-up" data-aos-duration="1000">
                            <i class="icon-emailopen f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Čistá schránka</div>
                            <p class="subtitle seccolor">Žádný spam, žádný phishing. Pouze skutečná pošta, kterou chcete přijímat. 99,98% přesnost detekce.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle" data-aos="fade-up" data-aos-duration="900">
                            <div class="plans badge feat bg-grey">panel</div>
                            <i class="icon-window f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Snadná správa</div>
                            <p class="subtitle seccolor">Webový kontrolní panel umožňuje spravovat filtry, karanténu a nastavení bez technických znalostí.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle" data-aos="fade-up" data-aos-duration="1000">
                            <div class="plans badge feat bg-pink">MX záloha</div>
                            <i class="icon-inverse f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Profesionální záloha MX</div>
                            <p class="subtitle seccolor">Při výpadku vašeho mailserveru MX záloha přijme všechny zprávy a bezpečně je doručí, jakmile server opět běží.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FAQ ============ --}}
    <section class="sec-normal sec-bg2 bg-colorstyle bottomhalfpadding">
        <div class="faq">
            <div class="container">
                <div class="row">
                    <div class="col-md-12 col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Časté dotazy — e-mailová bezpečnost</h2>
                        <p class="section-subheading mergecolor">Nejčastější otázky o naší anti-spam ochraně.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="accordion faq pt-5">
                            <div class="panel-wrap">
                                <div class="panel-title seccolor active">
                                    <span>Jak funguje anti-spam filtrování?</span>
                                    <div class="float-end"><i class="fa fa-plus"></i><i class="fa fa-minus c-pink"></i></div>
                                </div>
                                <div class="panel-collapse" style="display:block;">
                                    <div class="wrapper-collapse"><div class="info"><ul class="list seccolor"><li>
                                        <p>Příchozí e-maily procházejí vícevrstvou analýzou: kontrola IP reputace odesílatele, analýza obsahu pomocí strojového učení, porovnání s globálními databázemi spamu a phishingu.</p>
                                        <p>Zachycené zprávy jsou umístěny do karantény — nedostanou se do vaší schránky, ale jsou dostupné pro kontrolu.</p>
                                    </li></ul></div></div>
                                </div>
                            </div>
                            <div class="panel-wrap">
                                <div class="panel-title seccolor">
                                    <span>Mohu spravovat whitelist a blacklist?</span>
                                    <div class="float-end"><i class="fa fa-plus"></i><i class="fa fa-minus c-pink"></i></div>
                                </div>
                                <div class="panel-collapse">
                                    <div class="wrapper-collapse"><div class="info"><ul class="list seccolor"><li>
                                        <p>Ano, přes webový panel máte plnou kontrolu. Můžete přidat konkrétní e-mailové adresy nebo domény na whitelist (vždy doručit) nebo blacklist (vždy blokovat).</p>
                                    </li></ul></div></div>
                                </div>
                            </div>
                            <div class="panel-wrap">
                                <div class="panel-title seccolor">
                                    <span>Co je MX záloha a kdy se aktivuje?</span>
                                    <div class="float-end"><i class="fa fa-plus"></i><i class="fa fa-minus c-pink"></i></div>
                                </div>
                                <div class="panel-collapse">
                                    <div class="wrapper-collapse"><div class="info"><ul class="list seccolor"><li>
                                        <p>MX záloha je záložní poštovní server, který přijímá e-maily v případě nedostupnosti vašeho primárního mailserveru. Aktivuje se automaticky a zprávy uchová po dobu výpadku.</p>
                                    </li></ul></div></div>
                                </div>
                            </div>
                            <div class="panel-wrap">
                                <div class="panel-title seccolor">
                                    <span>Jak rychle lze službu aktivovat?</span>
                                    <div class="float-end"><i class="fa fa-plus"></i><i class="fa fa-minus c-pink"></i></div>
                                </div>
                                <div class="panel-collapse">
                                    <div class="wrapper-collapse"><div class="info"><ul class="list seccolor"><li>
                                        <p>Po objednání je služba aktivována do 24 hodin. Naše technická podpora vám pomozte s nastavením MX záznamů v DNS. Přechod je plynulý a bez výpadku.</p>
                                    </li></ul></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="help pt-4 pb-80 notoppadding bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <div class="plans badge feat left bg-grey"><i class="fas fa-long-arrow-alt-left"></i></div>
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Přejít na webhosting</div>
                                    <div class="description seccolor">Hledáte kompletní řešení pro váš web?</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <div class="plans badge feat bg-grey"><i class="fas fa-long-arrow-alt-right"></i></div>
                            <a href="{{ route('front.mailhosting') }}" class="help-item" title="Mailhosting">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Přejít na mailhosting</div>
                                    <div class="description seccolor">Profesionální e-mail s vlastní doménou.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
