@extends('layouts.front')

@section('title', __('front.pages.vps.title'))
@section('meta_description', 'Cloud VPS Onhost.cz — KVM virtualizace, NVMe disky, root přístup, IPv4 a IPv6 v ceně. Výkonné virtuální servery pro vývojáře i produkci.')

@section('content')
    <x-front.page-banner :title="__('front.pages.vps.title')" :subtitle="__('front.pages.vps.subtitle')" />

    @if($plans->isNotEmpty())
        <section class="pricing bg-colorstyle pb-90">
            <div class="container">
                <div class="sec-main-title text-center pb-5">
                    <h2 class="title mergecolor" data-aos="fade-up">Vyberte svůj VPS</h2>
                    <p class="seccolor" data-aos="fade-up">{{ __('front.pricing.vat_note') }}</p>
                </div>
                <div class="row justify-content-md-center" data-aos="fade-up">
                    @foreach($plans as $plan)
                        <x-front.pricing-card :plan="$plan" :currency="$currency" :featured="(bool) $plan->is_featured" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Proč Cloud VPS od OnHost?</h2>
            </div>
            <div class="row">
                @foreach([
                    ['icon' => 'ico-cloud', 'title' => 'KVM virtualizace', 'text' => 'Plný root přístup, vlastní ISO, dedikované CPU vlákna. Žádné sdílení výkonu.'],
                    ['icon' => 'ico-drives', 'title' => 'NVMe úložiště', 'text' => 'Lokální NVMe pole s vysokým IOPS pro databáze, cache i náročné aplikace.'],
                    ['icon' => 'ico-backup', 'title' => 'Snapshoty a zálohy', 'text' => 'Denní zálohy s retencí 14 dní a manuální snapshoty před každým zásahem.'],
                    ['icon' => 'ico-speedometer', 'title' => 'Rychlá síť', 'text' => 'Gigabitové uplinky v českém datacentru s nízkou latencí a DDoS ochranou.'],
                    ['icon' => 'ico-ssl', 'title' => 'IPv4 + IPv6', 'text' => 'Dedicated IPv4 a celý /64 IPv6 prefix v ceně každého VPS tarifu.'],
                    ['icon' => 'ico-managed', 'title' => 'Volitelná správa', 'text' => 'Od holého serveru po plně spravované řešení s OS, aktualizacemi a monitoringem.'],
                ] as $i => $f)
                    <div class="col-md-6 col-lg-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="{{ ($i % 3) * 100 }}">
                            <i class="{{ $f['icon'] }} f-30 purple"></i>
                            <h3 class="title mergecolor pt-3 f-18">{{ $f['title'] }}</h3>
                            <p class="seccolor mb-0">{{ $f['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Podporované operační systémy</h2>
            </div>
            <div class="row justify-content-center">
                @foreach(['Debian 12', 'Ubuntu 24.04 LTS', 'AlmaLinux 9', 'Rocky Linux 9', 'Windows Server 2022'] as $i => $os)
                    <div class="col-6 col-md-4 col-lg-2 text-center mb-4" data-aos="fade-up" data-aos-delay="{{ $i * 80 }}">
                        <div class="wrapper bg-seccolorstyle p-3 rounded">
                            <i class="fas fa-server f-24 purple pb-2"></i>
                            <p class="seccolor f-14 mb-0">{{ $os }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Potřebujete výkonnější konfiguraci?</h2>
                <p class="text-white-50 pb-3">Sestavíme VPS přesně na míru vašeho projektu — od extra RAM po dedikované GPU.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Nezávazně se zeptat</a>
            </div>
        </div>
    </section>
@endsection
