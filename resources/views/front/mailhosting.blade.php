@extends('layouts.front')

@section('title', __('front.pages.mailhosting.title'))
@section('meta_description', 'Mailhosting Onhost.cz — firemní e-mail na vlastní doméně s antispamem, IMAP/POP3, webmailem a automatickým nastavením SPF/DKIM/DMARC.')

@section('content')
    <x-front.page-banner :title="__('front.pages.mailhosting.title')" :subtitle="__('front.pages.mailhosting.subtitle')" />

    @if($plans->isNotEmpty())
        <section class="pricing bg-colorstyle pb-90">
            <div class="container">
                <div class="sec-main-title text-center pb-5">
                    <h2 class="title mergecolor" data-aos="fade-up">Tarify mailhostingu</h2>
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
            <div class="row">
                @foreach([
                    ['icon' => 'ico-mail', 'title' => 'E-mail na vlastní doméně', 'text' => 'Profesionální schránky ve formátu jmeno@vase-firma.cz — žádné reklamy, žádné limitace.'],
                    ['icon' => 'ico-protection', 'title' => 'Antispam a antivir', 'text' => 'Vícevrstvá ochrana založená na reputačních databázích, Bayesově filtru a skenování příloh.'],
                    ['icon' => 'ico-ssl', 'title' => 'SPF / DKIM / DMARC', 'text' => 'Záznamy pro autentizaci e-mailů nastavíme automaticky — váš e-mail nedopadne do spamu.'],
                    ['icon' => 'ico-speedometer', 'title' => 'Webmail i IMAP/POP3', 'text' => 'Moderní webmail přístupný z prohlížeče plus standardní protokoly pro Outlook, Thunderbird nebo telefon.'],
                    ['icon' => 'ico-backup', 'title' => 'Zálohy schránek', 'text' => 'Denní zálohy s možností obnovy jednotlivých zpráv nebo celých schránek.'],
                    ['icon' => 'ico-globe', 'title' => 'Aliasy a přesměrování', 'text' => 'Neomezený počet aliasů a přesměrování — info@, objednavky@, fakturace@ a kdokoli jiný.'],
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
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up">
                        <h2 class="title mergecolor f-22 pb-2">E-mail je součástí každého webhostingu</h2>
                        <p class="seccolor mb-2">Pokud provozujete web, e-mailové schránky jsou součástí každého webhostingového tarifu — od 5 schránek u tarifu Start po 250 u Managed WordPress.</p>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill btn-sm">Prohlédnout webhosting</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="title mergecolor f-22 pb-2">Migrace schránek zdarma</h2>
                        <p class="seccolor mb-0">Přecházíte od jiného poskytovatele? Přeneseme vaše e-mailové schránky včetně historické pošty bez výpadku.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Potřebujete e-mail pro celou firmu?</h2>
                <p class="text-white-50 pb-3">Pro enterprise nasazení, Microsoft 365 nebo Google Workspace integrace nás kontaktujte.</p>
                <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill">Domluvit řešení</a>
            </div>
        </div>
    </section>
@endsection
