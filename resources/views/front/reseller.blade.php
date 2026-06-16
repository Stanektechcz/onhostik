@extends('layouts.front')

@section('title', 'Reseller Hosting — Prodávejte hosting pod vlastní značkou')
@section('meta_description', 'Reseller hostingové balíčky pro webdesignéry, agentury a IT firmy. Prodávejte hosting pod vlastní značkou (white-label) s plnou správou a technickou podporou.')

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">Reseller Hosting</h1>
                        <div class="subheading text-center">Prodávejte hosting pod vlastní značkou — my se staráme o infrastrukturu, vy o zákazníky.</div>
                        <div class="included mt-4">
                            <div class="mb-3 h5 text-white">Zahrnuto ve všech plánech</div>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> White-label — vaše značka, vaše ceny</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Neomezený počet zákazníků</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Denní zálohy automaticky</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> SSL certifikáty zdarma</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> PHP 8.3 + MySQL + MariaDB</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PRICING --}}
    <section class="pricing special sec-normal pt-80 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-sm-12 text-center pb-5">
                    <h2 class="section-heading mergecolor">Reseller tarify</h2>
                    <p class="section-subheading mergecolor">Začněte malý, rostěte bez limitů. Tarif změníte kdykoli.</p>
                </div>
            </div>
            <div class="row">
                @foreach([
                    ['name' => 'Starter',    'price' => '499',   'disk' => '20 GB',  'clients' => '10',   'domains' => '20',   'color' => 'first'],
                    ['name' => 'Business',   'price' => '999',   'disk' => '50 GB',  'clients' => '30',   'domains' => '60',   'color' => '', 'popular' => true],
                    ['name' => 'Enterprise', 'price' => '1 999', 'disk' => '150 GB', 'clients' => '100',  'domains' => '250',  'color' => 'third'],
                ] as $plan)
                    <div class="col-md-12 col-lg-4">
                        <div class="wrapper {{ $plan['color'] }} {{ isset($plan['popular']) ? '' : '' }} text-start {{ $plan['color'] ? 'noshadow' : '' }}">
                            @isset($plan['popular'])
                                <div class="plans badge feat bg-purple">nejoblíbenější</div>
                            @endisset
                            <div class="top-content bg-seccolorstyle topradius">
                                <div class="title">{{ $plan['name'] }}</div>
                                <div class="fromer seccolor">White-label reseller</div>
                                <div class="price seccolor"><sup>Kč</sup>{{ $plan['price'] }} <span class="period">/měs.</span></div>
                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Objednat</a>
                            </div>
                            <ul class="list-info bg-purple">
                                <li><i class="icon-drives"></i> <div>DISK<br><span>{{ $plan['disk'] }} NVMe</span></div></li>
                                <li><i class="icon-domains"></i> <div>ZÁKAZNÍCI<br><span>{{ $plan['clients'] }}</span></div></li>
                                <li><i class="icon-domains"></i> <div>DOMÉNY<br><span>{{ $plan['domains'] }}</span></div></li>
                                <li><i class="icon-ssl"></i> <div>SSL<br><span>Zdarma</span></div></li>
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč resellovat s OnHost?</h2>
                        <p class="section-subheading">Kompletní infrastruktura, abyste se mohli soustředit na zákazníky.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-drives',     'badge' => 'White-label', 'title' => 'Vlastní značka',        'text' => 'Prodávejte hosting pod svým logem a jménem — zákazníci netuší, že jedete na OnHost.'],
                        ['icon' => 'icon-support',    'badge' => 'Support',     'title' => 'Technická podpora',     'text' => 'Technické problémy řešíme za vás — vaši zákazníci mají vždy komu zavolat.'],
                        ['icon' => 'icon-cpu',       'badge' => 'API',         'title' => 'Plné API rozhraní',     'text' => 'Automatizujte správu zákazníků a účtů pomocí REST API a webhooků.'],
                        ['icon' => 'icon-diskette',     'badge' => 'Zálohy',      'title' => 'Zálohy pro zákazníky',  'text' => 'Denní zálohy všech zákaznických dat — bez příplatku, bez starostí.'],
                        ['icon' => 'icon-speed','badge' => 'Monitoring',  'title' => 'Monitoring dostupnosti', 'text' => 'Hlídáme dostupnost serverů zákazníků a upozorníme vás při problémech.'],
                        ['icon' => 'icon-lock',        'badge' => 'SSL',         'title' => 'SSL pro každého',       'text' => 'Automatické Let\'s Encrypt certifikáty pro všechny zákaznické domény.'],
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

    {{-- CTA --}}
    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Začněte resellovat ještě dnes</h2>
                <p class="text-white-50 pb-3">Kontaktujte nás a připravíme vám reseller účet na míru. První měsíc zdarma.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Kontaktovat obchodní tým</a>
            </div>
        </div>
    </section>

@endsection
