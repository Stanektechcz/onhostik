@extends('layouts.front')

@section('title', __('front.pages.vps.title'))
@section('meta_description', 'KVM virtuální servery s NVMe SSD, plným root přístupem a IPv4/IPv6 v ceně. VPS od 149 Kč/měs. bez DPH. České datacentrum.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.vps.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.vps.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">Každý VPS obsahuje</div>
                            <ul><li><i class="fas fa-check-circle"></i> KVM virtualizace</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> NVMe SSD úložiště</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Plný root přístup</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Dedicated IPv4 + /64 IPv6</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Denní zálohy (14 dní)</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Anti-DDoS ochrana</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING ============ --}}
    <section class="pricing special sec-normal pt-80 pb-0 bg-colorstyle">
        <div class="container">
            @if($product !== null && $plans->isNotEmpty())
                <div class="row">
                    @foreach($plans as $plan)
                        <div class="col-sm-12 col-md-6 col-lg-3">
                            <div class="wrapper text-start noshadow">
                                @if($plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price mergecolor">
                                        {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                        <span class="period">/ {{ $plan->billing_cycle->label() }}</span>
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
                <p class="seccolor f-14 mt-4 text-center pb-4">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>

    {{-- ============ FEATURE HIGHLIGHTS ============ --}}
    <section id="features" class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-6">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Snapshoty a zálohy</h2>
                            <p class="seccolor">Denní zálohy s retencí 14 dní a manuální snapshoty před každým nasazením. Obnova probíhá z klientské zóny bez čekání na podporu.</p>
                        </div>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Poradit se</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-diskette purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-cpu purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">KVM virtualizace s NVMe</h2>
                            <p class="seccolor">Plná izolace vCPU, RAM i I/O mezi zákazníky. Lokální NVMe pole s vysokým IOPS — žádný "noisy neighbor" efekt sdíleného úložiště.</p>
                        </div>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill mt-3">Porovnat s hostingem</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ WHY VPS ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč Cloud VPS od OnHost?</h2>
                        <p class="section-subheading">Výkon bez sdílení, kontrola bez kompromisů.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-cpu',    'badge' => 'KVM',     'title' => 'KVM virtualizace',    'text' => 'Plný root přístup, vlastní ISO, dedikované CPU vlákna. Žádné sdílení výkonu s ostatními zákazníky.'],
                        ['icon' => 'icon-drives',  'badge' => 'NVMe',    'title' => 'NVMe úložiště',       'text' => 'Lokální NVMe pole s vysokým IOPS pro databáze, cache i náročné aplikace.'],
                        ['icon' => 'icon-diskette',  'badge' => '14 dní',  'title' => 'Snapshoty a zálohy',  'text' => 'Denní zálohy s retencí 14 dní a manuální snapshoty před každým zásahem do systému.'],
                        ['icon' => 'icon-speed',   'badge' => '1 Gbit',  'title' => 'Rychlá síť',          'text' => 'Gigabitové uplinky v českém datacentru s nízkou latencí a DDoS ochranou v ceně.'],
                        ['icon' => 'ico-globe',   'badge' => 'IPv6',    'title' => 'IPv4 + IPv6',          'text' => 'Dedicated IPv4 a celý /64 IPv6 prefix v ceně každého VPS tarifu bez příplatku.'],
                        ['icon' => 'icon-support', 'badge' => 'Managed', 'title' => 'Volitelná správa',    'text' => 'Od holého serveru po plně spravované řešení s OS, aktualizacemi a monitoringem.'],
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

    {{-- ============ OS LIST ============ --}}
    <section id="specs" class="sec-normal sec-bg1 bg-colorstyle pb-80">
        <div class="best-plans pricing">
            <div class="container">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Podporované operační systémy</h2>
                        <p class="section-subheading mergecolor">Instalace probíhá automaticky během zřizování VPS.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="table-responsive-lg">
                            <table class="table sample mt-5">
                                <thead>
                                    <tr>
                                        <th class="border-start-0 title">Linux</th>
                                        <th class="border-start-0 title">Windows</th>
                                        <th class="border-start-0 title">Doplňky</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Debian 12</th>
                                        <td><span class="fas fa-check-circle me-2"></span> Windows Server 2022</td>
                                        <td><span class="fas fa-check-circle me-2"></span> cPanel / Plesk (volitelně)</td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Ubuntu 24.04 LTS</th>
                                        <td><span class="fas fa-check-circle me-2"></span> Windows Server 2019</td>
                                        <td><span class="fas fa-check-circle me-2"></span> DirectAdmin (volitelně)</td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> AlmaLinux 9</th>
                                        <td><span class="fas fa-check-circle me-2"></span> Vlastní ISO na vyžádání</td>
                                        <td><span class="fas fa-check-circle me-2"></span> IPMI / KVM konzole</td>
                                    </tr>
                                    <tr>
                                        <th><span class="fas fa-check-circle me-2"></span> Rocky Linux 9</th>
                                        <td></td>
                                        <td><span class="fas fa-check-circle me-2"></span> rDNS záznam</td>
                                    </tr>
                                    <tr>
                                        <th class="border-0"><a href="{{ route('front.support') }}" class="btn btn-default-purple-fill">Ptejte se</a></th>
                                        <td class="border-0"></td>
                                        <td class="border-0"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="help pt-4 notoppadding pb-80 bg-colorstyle">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                            <div class="img"><i class="icon-drives f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Sdílený webhosting</div>
                                <div class="description seccolor">Rychlý NVMe hosting od 49 Kč/měs. bez správy serveru.</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.dedicated') }}" class="help-item" title="Dedikované servery">
                            <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Dedikované servery</div>
                                <div class="description seccolor">Výhradní hardware, plný výkon a IPMI přístup.</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-4">
                    <div class="help-container bg-seccolorstyle noshadow">
                        <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                            <div class="img"><i class="icon-support f-40 purple"></i></div>
                            <div class="inform">
                                <div class="title mergecolor">Potřebujete poradit?</div>
                                <div class="description seccolor">Odpovíme do 1 hodiny — pomůžeme s výběrem i konfigurací.</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
