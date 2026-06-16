@extends('layouts.front')

@section('title', 'SSL Certifikáty — Zabezpečte svůj web')
@section('meta_description', 'SSL certifikáty pro každý web. Let\'s Encrypt zdarma, komerční DV/OV/Wildcard certifikáty. Zabezpečení, důvěra a lepší pozice ve vyhledávačích.')

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">SSL Certifikáty</h1>
                        <div class="subheading text-center">Zabezpečení, důvěryhodnost a lepší pozice ve vyhledávačích — vše v jednom.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PRICING CARDS --}}
    <section class="pricing special sec-normal pt-80 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-sm-12 text-center pb-5">
                    <h2 class="section-heading mergecolor" data-aos="fade-up">Vyberte správný certifikát</h2>
                    <p class="section-subheading mergecolor" data-aos="fade-up">Od bezplatného Let's Encrypt až po EV certifikáty pro e-shopy.</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 col-lg-4">
                    <div class="wrapper first text-start noshadow">
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Let's Encrypt</div>
                            <div class="fromer seccolor">Zdarma s každým hostingem</div>
                            <div class="price seccolor"><sup>Kč</sup>0 <span class="period">/měs.</span></div>
                            <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill">Objednat hosting</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-ssl"></i> <div>DV certifikát<br><span>Domain Validation</span></div></li>
                            <li><i class="icon-speed"></i> <div>Aktivace<br><span>Automatická</span></div></li>
                            <li><i class="icon-backup"></i> <div>Obnova<br><span>Automatická</span></div></li>
                            <li><i class="icon-drives"></i> <div>Platnost<br><span>90 dní</span></div></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-12 col-lg-4">
                    <div class="wrapper">
                        <div class="plans badge feat bg-purple">doporučujeme</div>
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Positive SSL</div>
                            <div class="fromer seccolor">Pro firemní weby a e-shopy</div>
                            <div class="price seccolor"><sup>Kč</sup>499 <span class="period">/rok</span></div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-ssl"></i> <div>DV certifikát<br><span>Domain Validation</span></div></li>
                            <li><i class="icon-protection"></i> <div>Záruka<br><span>500 000 USD</span></div></li>
                            <li><i class="icon-speed"></i> <div>Vystavení<br><span>Do 15 minut</span></div></li>
                            <li><i class="icon-drives"></i> <div>Platnost<br><span>1 rok</span></div></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-12 col-lg-4">
                    <div class="wrapper third text-start noshadow">
                        <div class="top-content bg-seccolorstyle topradius">
                            <div class="title">Wildcard SSL</div>
                            <div class="fromer seccolor">Pokrytí celé domény + subdomén</div>
                            <div class="price seccolor"><sup>Kč</sup>1 990 <span class="period">/rok</span></div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                        </div>
                        <ul class="list-info bg-purple">
                            <li><i class="icon-ssl"></i> <div>DV Wildcard<br><span>*.vase-domena.cz</span></div></li>
                            <li><i class="icon-protection"></i> <div>Záruka<br><span>1 000 000 USD</span></div></li>
                            <li><i class="icon-domains"></i> <div>Subdomény<br><span>Neomezeno</span></div></li>
                            <li><i class="icon-drives"></i> <div>Platnost<br><span>1 rok</span></div></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- WHY SSL --}}
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
                            <h2 class="fw-bold mb-3 mergecolor">Proč potřebujete SSL certifikát?</h2>
                            <p class="seccolor">SSL certifikát šifruje veškerá data přenášená mezi vaším webem a návštěvníky. Bez něj Chrome i další prohlížeče zobrazují varování "Nezabezpečeno".</p>
                        </div>
                        <ul class="list-unstyled seccolor mt-3">
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Šifrování dat — ochrana citlivých informací</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Google Ranking — SSL je součástí hodnocení</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>Zámek v adresním řádku — důvěra návštěvníků</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>GDPR compliance — zabezpečení osobních dat</li>
                            <li class="py-1"><i class="fas fa-check-circle purple me-2"></i>PCI DSS — povinné pro přijímání plateb online</li>
                        </ul>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">Zahrnuto zdarma v hostingu</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0">
                        <div class="wrapper bg-seccolorstyle p-4 rounded">
                            <h5 class="mergecolor mb-3">Jak SSL certifikát funguje?</h5>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex align-items-start py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3 mt-1">1</span>
                                    <span>Prohlížeč požádá server o SSL certifikát</span>
                                </li>
                                <li class="d-flex align-items-start py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3 mt-1">2</span>
                                    <span>Server odešle certifikát podepsaný důvěryhodnou CA</span>
                                </li>
                                <li class="d-flex align-items-start py-2 border-bottom seccolor">
                                    <span class="badge bg-purple me-3 mt-1">3</span>
                                    <span>Prohlížeč ověří platnost a zahájí šifrované spojení</span>
                                </li>
                                <li class="d-flex align-items-start py-2 seccolor">
                                    <span class="badge bg-purple me-3 mt-1">4</span>
                                    <span>Veškerá komunikace je šifrována pomocí TLS 1.3</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES GRID --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Zahrnuto v každém certifikátu</h2>
                        <p class="section-subheading">Standardní funkce, které získáte bez příplatku.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-lock',         'badge' => 'TLS 1.3', 'title' => 'Moderní šifrování',    'text' => 'Certifikáty používají nejnovější TLS 1.3 protokol s 256bitovým šifrováním.'],
                        ['icon' => 'icon-speed',  'badge' => 'HTTPS',   'title' => 'HTTP/2 a HTTP/3',       'text' => 'Šifrované spojení je rychlé — HTTP/2 a HTTP/3 jsou plně podporované.'],
                        ['icon' => 'icon-protection',   'badge' => 'Auto',    'title' => 'Automatická obnova',    'text' => 'Let\'s Encrypt certifikáty se obnovují automaticky — bez zásahu z vaší strany.'],
                        ['icon' => 'icon-cpu',         'badge' => 'SEO',     'title' => 'Lepší pozice v Google', 'text' => 'HTTPS je jedním z hodnotících faktorů Google. Bez SSL ztrácíte pozice.'],
                        ['icon' => 'icon-emailopen',    'badge' => 'E-mail',  'title' => 'SSL pro e-mailové servery', 'text' => 'Zabezpečení SMTP, IMAP a POP3 serverů — šifrovaná e-mailová komunikace.'],
                        ['icon' => 'icon-support',      'badge' => '24/7',    'title' => 'Technická podpora',     'text' => 'Pomůžeme s instalací, nastavením přesměrování a řešením problémů s SSL.'],
                    ] as $f)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $f['badge'] }}</div>
                                <i class="{{ $f['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $f['title'] }}</div>
                                <p class="subtitle seccolor">{{ $f['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- HELP --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting se SSL zdarma">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Hosting se SSL zdarma</div>
                                    <div class="description seccolor">Let's Encrypt je součástí každého hostingového tarifu.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Návody SSL">
                                <div class="img"><i class="icon-lock f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Návody k SSL</div>
                                    <div class="description seccolor">Instalace, nastavení HTTPS přesměrování a řešení chyb.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Kontakt">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Potřebujete poradit?</div>
                                    <div class="description seccolor">Napište nám a vybereme správný certifikát pro váš projekt.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
