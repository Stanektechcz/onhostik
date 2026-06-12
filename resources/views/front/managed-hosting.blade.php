@extends('layouts.front')

@section('title', __('front.pages.managed.title'))
@section('meta_description', 'Managed hosting Onhost.cz — hosting, o který se kompletně staráme my. Aktualizace CMS, zálohy, bezpečnostní monitoring a podpora.')

@section('content')
    <x-front.page-banner :title="__('front.pages.managed.title')" :subtitle="__('front.pages.managed.subtitle')" />

    @if($plans->isNotEmpty())
        <section class="pricing bg-colorstyle pb-90">
            <div class="container">
                <div class="sec-main-title text-center pb-5">
                    <h2 class="title mergecolor" data-aos="fade-up">Tarify managed hostingu</h2>
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
                <h2 class="title mergecolor" data-aos="fade-up">Co děláme za vás</h2>
            </div>
            <div class="row">
                @foreach([
                    ['icon' => 'ico-managed', 'title' => 'Správa serveru', 'text' => 'Aktualizace OS, PHP, databází a bezpečnostní záplaty bez vašeho zásahu.'],
                    ['icon' => 'ico-speed', 'title' => 'Aktualizace CMS', 'text' => 'WordPress, Joomla, PrestaShop — jádro, šablony i pluginy aktualizujeme a testujeme na záloze.'],
                    ['icon' => 'ico-backup', 'title' => 'Zálohy a obnova', 'text' => 'Denní zálohy s retencí 30 dní a rychlá obnova kdykoli o to požádáte.'],
                    ['icon' => 'ico-protection', 'title' => 'Bezpečnostní monitoring', 'text' => 'Skenování malware, sledování podezřelých přihlášení a okamžité upozornění na incidenty.'],
                    ['icon' => 'ico-speedometer', 'title' => 'Optimalizace výkonu', 'text' => 'Nastavení cache, CDN a databázových dotazů pro maximálně svižné načítání.'],
                    ['icon' => 'ico-support', 'title' => 'Prioritní podpora', 'text' => 'Vyhrazená fronta ticketů s reakcí do 1 hodiny a přímý kontakt na váš technický tým.'],
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
            <div class="row">
                <div class="col-lg-6">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up">
                        <h2 class="title mergecolor f-22 pb-2">Ideální pro koho?</h2>
                        <ul class="list-unstyled seccolor mb-0">
                            @foreach([
                                'Agentury a freelanceři spravující weby klientů',
                                'E-shopy, kde každý výpadek znamená ztrátu tržeb',
                                'Firmy bez vlastního IT oddělení',
                                'Projekty s přísnými SLA požadavky',
                                'Weby zpracovávající citlivé nebo regulované údaje',
                            ] as $item)
                                <li class="py-1"><i class="fas fa-check purple pe-2"></i>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="title mergecolor f-22 pb-2">Onboarding na míru</h2>
                        <p class="seccolor mb-2">Přeneseme vaše stávající weby a nastavíme vše potřebné — DNS, SSL, cache, e-maily a zálohy. Onboarding probíhá bez výpadku.</p>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill btn-sm">Nezávazná konzultace</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Chcete hosting bez starostí?</h2>
                <p class="text-white-50 pb-3">Ozvěte se a připravíme nabídku přesně pro váš projekt — od ceny po rozsah správy.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill me-2">Standardní webhosting</a>
                <a href="{{ route('front.contact') }}" class="btn btn-default-grey">Kontaktovat nás</a>
            </div>
        </div>
    </section>
@endsection
