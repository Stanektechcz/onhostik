@extends('layouts.front')

@section('title', 'Databáze jako služba (DBaaS) — MySQL & MariaDB | Onhost.cz')
@section('meta_description', 'Výkonné databáze jako služba — MySQL a MariaDB. Automatické zálohy, vysoká dostupnost, SSD úložiště.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading mb-0">Databáze jako služba</h1>
                        <div class="subheading mb-0">Výkonná databáze pro vaši aplikaci — spuštěna za minuty.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING TABS ============ --}}
    <section class="sec-normal tabs motpath bg-colorstyle nobottompadding">
        <div class="best-plans pricing">
            <div class="container">
                <div class="randomline">
                    <div class="bigline"></div>
                    <div class="smallline"></div>
                </div>
                <div class="sec-main sec-bg1">
                    <div class="tabs-header btn-select-plan">
                        <ul class="btn-group">
                            <li class="btn btn-secondary active mb-2">DBaaS MySQL</li>
                            <li class="btn btn-secondary">DBaaS MariaDB</li>
                        </ul>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div id="table-container" class="table-responsive-lg tabs-content">
                                {{-- MySQL Table --}}
                                <table id="maintable" class="table tabs-item active">
                                    <thead>
                                        <tr class="thead-clone">
                                            <th></th>
                                            <th>
                                                <div class="title">MySQL Starter</div>
                                                <div class="price mergecolor"><sup>Kč</sup>99<span class="period">/rok</span></div>
                                                <div class="info seccolor">Ideální pro malé projekty a aplikace</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                            <th>
                                                <div class="title">MySQL Pro</div>
                                                <div class="price mergecolor"><sup>Kč</sup>299<span class="period">/rok</span></div>
                                                <div class="info seccolor">Pro produkční aplikace s vysokým výkonem</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                            <th>
                                                <div class="title">MySQL Enterprise</div>
                                                <div class="price mergecolor"><sup>Kč</sup>699<span class="period">/rok</span></div>
                                                <div class="info seccolor">Maximální výkon pro náročné systémy</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th><div>Verze MySQL</div></th>
                                            <td>MySQL 8.0</td>
                                            <td>MySQL 8.0</td>
                                            <td>MySQL 8.0</td>
                                        </tr>
                                        <tr>
                                            <th><div>SSD Úložiště</div></th>
                                            <td>2 GB SSD</td>
                                            <td>10 GB SSD</td>
                                            <td>30 GB SSD</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Databáze</th>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Provoz</th>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">zdarma</span> SSL Certifikát</th>
                                            <td>Let's Encrypt</td>
                                            <td>Let's Encrypt</td>
                                            <td>Wildcard SSL</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-grey">auto</span> Denní zálohy</th>
                                            <td>7 dní historie</td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">zdarma</span> Kontrolní panel</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Správa databáze</th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-grey">auto</span> Aktualizace</th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Vysoká dostupnost</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">premium</span> Podpora 24/7</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Garancia vrácení peněz</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="border-0 sticky-stopper"></th>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                        </tr>
                                    </tbody>
                                </table>
                                {{-- MariaDB Table --}}
                                <table id="mariadbserver" class="table tabs-item">
                                    <thead>
                                        <tr class="thead-clone">
                                            <th></th>
                                            <th class="price-container">
                                                <div class="title">MariaDB Starter</div>
                                                <div class="price mergecolor"><sup>Kč</sup>89<span class="period">/rok</span></div>
                                                <div class="info seccolor">Ideální pro malé projekty a aplikace</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                            <th class="price-container">
                                                <div class="title">MariaDB Pro</div>
                                                <div class="price mergecolor"><sup>Kč</sup>269<span class="period">/rok</span></div>
                                                <div class="info seccolor">Pro produkční aplikace s vysokým výkonem</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                            <th class="price-container">
                                                <div class="title">MariaDB Enterprise</div>
                                                <div class="price mergecolor"><sup>Kč</sup>629<span class="period">/rok</span></div>
                                                <div class="info seccolor">Maximální výkon pro náročné systémy</div>
                                                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th><div>Verze MariaDB</div></th>
                                            <td>MariaDB 10.11</td>
                                            <td>MariaDB 10.11</td>
                                            <td>MariaDB 10.11</td>
                                        </tr>
                                        <tr>
                                            <th><div>SSD Úložiště</div></th>
                                            <td>2 GB SSD</td>
                                            <td>10 GB SSD</td>
                                            <td>30 GB SSD</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Databáze</th>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Provoz</th>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">zdarma</span> SSL Certifikát</th>
                                            <td>Let's Encrypt</td>
                                            <td>Let's Encrypt</td>
                                            <td>Wildcard SSL</td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-grey">auto</span> Denní zálohy</th>
                                            <td>7 dní historie</td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">zdarma</span> Kontrolní panel</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Správa databáze</th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-grey">auto</span> Aktualizace</th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Vysoká dostupnost</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table"><span class="badge bg-purple">premium</span> Podpora 24/7</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="title-table">Garancia vrácení peněz</th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th class="border-0 sticky-stopper"></th>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                            <td><a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Poptat řešení</a></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="bottom_anchor"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FEATURES ============ --}}
    <section class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="sec-main sec-bg1 bg-colorstyle noshadow">
                <div class="row">
                    <div class="col-md-12 col-lg-6 offset-lg-1">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Výkon a škálovatelnost na prvním místě</h2>
                            <p class="seccolor">Naše databáze jako služba běží na NVMe SSD úložišti a dedikovaných serverech v pražském datacentru. Horizontální i vertikální škálování zvládnete přes webový panel — bez výpadku a bez nutnosti migrace dat.</p>
                        </div>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill mt-3">Konzultovat řešení</a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12 col-lg-5">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Maximální bezpečnost dat</h2>
                            <p class="seccolor">Přístup k databázi je šifrován SSL/TLS. Automatické denní zálohy jsou ukládány na oddělená úložiště. Přísná kontrola přístupu a izolace dat mezi zákazníky je standardem.</p>
                            <p class="seccolor">Garanujeme 99,9% dostupnost s kompenzací dle SLA. Monitorujeme databáze nepřetržitě a reagujeme na výpadky do 15 minut.</p>
                        </div>
                        <a href="{{ route('front.sla') }}" class="btn btn-default-yellow-fill mt-3">Přečíst SLA</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ WHY CHOOSE ============ --}}
    <section class="services sec-normal sec-bg2 bg-seccolorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Proč zvolit DBaaS od Onhost?</h2>
                        <p class="section-subheading mergecolor">Databáze, která se stará sama o sebe — vy se starejte o aplikaci.</p>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle noshadow">
                            <div class="plans badge feat bg-purple">Panel</div>
                            <i class="icon-window f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Jednoduché ovládání</div>
                            <p class="subtitle seccolor">Webový panel pro správu databází, uživatelů a oprávnění. Žádné příkazové řádky.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle noshadow">
                            <i class="icon-support f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Plně spravováno</div>
                            <p class="subtitle seccolor">Aktualizace, zálohy a monitoring zajišťujeme my. Vy nemusíte řešit infrastrukturu.</p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4">
                        <div class="service-section bg-colorstyle noshadow">
                            <div class="plans badge feat bg-purple">SSD</div>
                            <i class="icon-speed f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Optimalizovaný výkon</div>
                            <p class="subtitle seccolor">NVMe SSD úložiště a dedikované databázové servery pro maximální rychlost dotazů.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ PANEL SECTION ============ --}}
    <section class="sec-normal history-section custom-flip sec-bg3 motpath bg-colorstyle">
        <div class="container">
            <div class="row">
                <div class="col-md-12 col-sm-12 text-center">
                    <h2 class="section-heading text-white mergecolor">Kontrolní panel — síla pro škálování a růst</h2>
                    <p class="section-subheading c-grey-light mergecolor">Spravujte databáze, uživatele a zálohy přes přehledný webový panel bez technických znalostí.</p>
                </div>
                <div class="col-sm-12 text-center pt-5">
                    <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill me-3">Konzultovat nasazení</a>
                    <a href="{{ route('front.support') }}" class="btn btn-default-purple-fill">Technická podpora</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="help pt-80 pb-80 bg-seccolorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <div class="help-container bg-colorstyle noshadow">
                            <div class="plans badge feat left bg-grey"><i class="fas fa-long-arrow-alt-left"></i></div>
                            <a href="{{ route('front.reseller') }}" class="help-item" title="Reseller hosting">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Přejít na Reseller hosting</div>
                                    <div class="description seccolor">Potřebujete hostovat více webů najednou?</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <div class="help-container bg-colorstyle noshadow">
                            <div class="plans badge feat bg-grey"><i class="fas fa-long-arrow-alt-right"></i></div>
                            <a href="{{ route('front.vps') }}" class="help-item" title="VPS server">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Přejít na VPS server</div>
                                    <div class="description seccolor">Potřebujete škálovatelné prostředky a plnou kontrolu?</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
