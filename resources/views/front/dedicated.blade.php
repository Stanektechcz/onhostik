@extends('layouts.front')

@section('title', __('front.pages.dedicated.title'))
@section('meta_description', 'Dedikované servery Onhost.cz — výhradní hardware pro náročné projekty. Konfigurace na míru, optionální správa, SLA 99,9 %.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.dedicated.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.dedicated.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Každý server obsahuje</div>
                            <ul><li><i class="fas fa-check-circle"></i> Výhradní hardware</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> NVMe RAID pole</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> IPMI / iDRAC přístup</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Anti-DDoS ochrana</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Dedikovaná IPv4 /29</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> SLA 99,9 %</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ FEATURES GRID ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč dedikovaný server?</h2>
                        <p class="section-subheading">Výhradní výkon bez kompromisů pro náročné projekty.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-cpu',         'badge' => 'Výhradní',  'title' => 'Výhradní hardware',    'text' => 'Celý fyzický server jen pro vás — bez sdílení CPU, RAM ani disků s ostatními zákazníky.'],
                        ['icon' => 'icon-speed',  'badge' => 'Na míru',   'title' => 'Konfigurace na míru',  'text' => 'CPU, RAM, disky i síť sestavíme přesně podle projektu. Xeon, EPYC nebo moderní serverové Core.'],
                        ['icon' => 'icon-support',      'badge' => 'Managed',   'title' => 'Volitelná správa',     'text' => 'Od holého metalu po plně spravované řešení s OS, aktualizacemi, monitoringem a support SLA.'],
                        ['icon' => 'icon-drives',       'badge' => 'RAID',      'title' => 'NVMe RAID pole',       'text' => 'Lokální NVMe v RAID konfiguraci pro maximální propustnost a odolnost. IPMI/iDRAC přístup v ceně.'],
                        ['icon' => 'icon-protection',   'badge' => 'BGP',       'title' => 'Anti-DDoS + BGP',      'text' => 'Pokročilá DDoS ochrana, dedikovaná IPv4 /29 nebo větší, BGP session na vyžádání.'],
                        ['icon' => 'icon-diskette',       'badge' => 'Offsite',   'title' => 'Offsite zálohy',       'text' => 'Zálohy do oddělené lokace s vlastním retenčním plánem. Testovaná obnova v SLA garantovaném čase.'],
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

    {{-- ============ EXAMPLE CONFIGS ============ --}}
    <section id="specs" class="sec-normal sec-bg1 bg-colorstyle pb-80">
        <div class="best-plans pricing">
            <div class="container">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Příklady konfigurací</h2>
                        <p class="section-subheading mergecolor">Orientační sestavy — finální cena závisí na aktuální dostupnosti komponent.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="table-responsive-lg">
                            <table class="table sample mt-5">
                                <thead>
                                    <tr>
                                        <th class="border-start-0 title">Konfigurace</th>
                                        <th class="border-start-0 title">CPU</th>
                                        <th class="border-start-0 title">RAM</th>
                                        <th class="border-start-0 title">Disk</th>
                                        <th class="border-start-0 title">Síť</th>
                                        <th class="border-start-0 title">Cena</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach([
                                        ['Entry',       'Intel Xeon E-2336 (6c/12t)', '32 GB ECC DDR4',  '2× 480 GB SSD SATA RAID 1',  '1 Gbit/s',  'od 3 990 Kč/měs.'],
                                        ['Performance', 'Intel Xeon Silver 4310 (12c)', '64 GB ECC DDR4', '2× 1,92 TB NVMe RAID 1',    '10 Gbit/s', 'od 7 990 Kč/měs.'],
                                        ['High-End',    'AMD EPYC 7282 (16c/32t)',    '128 GB ECC DDR4', '4× 3,84 TB NVMe RAID 10',    '25 Gbit/s', 'od 14 990 Kč/měs.'],
                                    ] as $row)
                                        <tr>
                                            <th><strong>{{ $row[0] }}</strong></th>
                                            <td>{{ $row[1] }}</td>
                                            <td>{{ $row[2] }}</td>
                                            <td>{{ $row[3] }}</td>
                                            <td>{{ $row[4] }}</td>
                                            <td class="mergecolor"><strong>{{ $row[5] }}</strong></td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <th class="border-0">
                                            <a href="{{ route('front.contact') }}" class="btn btn-default-purple-fill">Poptávka</a>
                                        </th>
                                        <td class="border-0" colspan="5">
                                            <span class="seccolor f-14">Ceny jsou orientační a bez DPH. Přesnou nabídku sestavíme po upřesnění požadavků.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                            <h2 class="fw-bold mb-3 mergecolor">Remote hands a IPMI přístup</h2>
                            <p class="seccolor">Plný out-of-band přístup přes IPMI nebo iDRAC — restart, reinstalace OS nebo připojení ISO bez nutnosti fyzického přístupu. Remote hands k dispozici na vyžádání.</p>
                        </div>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill mt-3">Nezávazná poptávka</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-cpu purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-protection purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Anti-DDoS a BGP konektivita</h2>
                            <p class="seccolor">Pokročilá filtrace volumetrických i aplikačních útoků na síťové vrstvě. Dedikované IPv4 /29, BGP session a volitelný 10GE uplink pro nejnáročnější provoz.</p>
                        </div>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Dotaz na BGP</a>
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
                            <a href="{{ route('front.vps') }}" class="help-item" title="Cloud VPS">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Cloud VPS</div>
                                    <div class="description seccolor">KVM virtualizace od 149 Kč/měs. — flexibilní alternativa k dedikátu.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.managed') }}" class="help-item" title="Managed hosting">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Managed hosting</div>
                                    <div class="description seccolor">Hosting, o který se kompletně staráme — aktualizace, zálohy, monitoring.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Poptávka na míru">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Poptávka na míru</div>
                                    <div class="description seccolor">Napište požadavky — výkon, redundanci, SLA. Odpovíme do 24 hodin.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
