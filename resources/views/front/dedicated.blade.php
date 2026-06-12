@extends('layouts.front')

@section('title', __('front.pages.dedicated.title'))
@section('meta_description', 'Dedikované servery Onhost.cz — výhradní hardware pro náročné projekty. Konfigurace na míru, optionální správa, SLA 99,9 %.')

@section('content')
    <x-front.page-banner :title="__('front.pages.dedicated.title')" :subtitle="__('front.pages.dedicated.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-dedicated', 'title' => 'Výhradní hardware', 'text' => 'Celý fyzický server jen pro vás — bez sdílení CPU, RAM ani disků s ostatními zákazníky.'],
                    ['icon' => 'ico-speedometer', 'title' => 'Konfigurace na míru', 'text' => 'CPU, RAM, disky i síť sestavíme přesně podle projektu. Xeon, EPYC nebo moderní serverové Core.'],
                    ['icon' => 'ico-managed', 'title' => 'Volitelná správa', 'text' => 'Od holého metalu po plně spravované řešení s OS, aktualizacemi, monitoringem a support SLA.'],
                    ['icon' => 'ico-drives', 'title' => 'NVMe RAID pole', 'text' => 'Lokální NVMe v RAID konfiguraci pro maximální propustnost a odolnost. IPMI/iDRAC přístup v ceně.'],
                    ['icon' => 'ico-protection', 'title' => 'Anti-DDoS + BGP', 'text' => 'Pokročilá DDoS ochrana, dedikovaná IPv4 /29 nebo větší, BGP session na vyžádání.'],
                    ['icon' => 'ico-backup', 'title' => 'Offsite zálohy', 'text' => 'Zálohy do oddělené lokace s vlastním retenčním plánem. Testovaná obnova v SLA garantovaném čase.'],
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
                <h2 class="title mergecolor" data-aos="fade-up">Příklady konfigurací</h2>
                <p class="seccolor" data-aos="fade-up">Orientační sestavy — finální cena závisí na aktuální dostupnosti komponent.</p>
            </div>
            <div class="row">
                @foreach([
                    ['name' => 'Entry', 'cpu' => 'Intel Xeon E-2336 (6c/12t)', 'ram' => '32 GB ECC DDR4', 'disk' => '2× 480 GB SSD SATA RAID 1', 'net' => '1 Gbit/s', 'price' => 'od 3 990 Kč/měs.'],
                    ['name' => 'Performance', 'cpu' => 'Intel Xeon Silver 4310 (12c)', 'ram' => '64 GB ECC DDR4', 'disk' => '2× 1,92 TB NVMe RAID 1', 'net' => '10 Gbit/s', 'price' => 'od 7 990 Kč/měs.'],
                    ['name' => 'High-End', 'cpu' => 'AMD EPYC 7282 (16c/32t)', 'ram' => '128 GB ECC DDR4', 'disk' => '4× 3,84 TB NVMe RAID 10', 'net' => '25 Gbit/s', 'price' => 'od 14 990 Kč/měs.'],
                ] as $i => $c)
                    <div class="col-md-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                            <h3 class="title mergecolor f-18 pb-2">{{ $c['name'] }}</h3>
                            <ul class="list-unstyled seccolor f-14 mb-3">
                                <li class="py-1"><i class="fas fa-microchip purple pe-2"></i>{{ $c['cpu'] }}</li>
                                <li class="py-1"><i class="fas fa-memory purple pe-2"></i>{{ $c['ram'] }}</li>
                                <li class="py-1"><i class="fas fa-hdd purple pe-2"></i>{{ $c['disk'] }}</li>
                                <li class="py-1"><i class="fas fa-network-wired purple pe-2"></i>{{ $c['net'] }}</li>
                            </ul>
                            <div class="title mergecolor f-20 pb-3">{{ $c['price'] }}</div>
                            <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill btn-sm">Poptávka</a>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="seccolor f-14 text-center mt-2" data-aos="fade-up">Ceny jsou orientační a bez DPH. Přesnou nabídku sestavíme po upřesnění požadavků.</p>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Připravit nabídku pro váš projekt</h2>
                <p class="text-white-50 pb-3">Napište nám požadavky — výkon, redundanci, lokalitu, SLA. Odpovíme do 24 hodin s konkrétní nabídkou.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Nezávazná poptávka</a>
            </div>
        </div>
    </section>
@endsection
