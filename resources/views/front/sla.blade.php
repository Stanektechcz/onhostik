@extends('layouts.front')

@section('title', __('front.pages.sla.title'))
@section('meta_description', 'Garance dostupnosti služeb Onhost.cz (SLA) — dostupnost, reakční doby, kompenzace.')

@section('content')
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.sla.title') }}</h1>
                        <div class="subheading text-center">Garantovaná dostupnost a jasná pravidla kompenzací</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="col-lg-9">
                <div class="wrapper bg-seccolorstyle p-4 rounded seccolor" data-aos="fade-up">
                    <h2 class="mergecolor f-20 pb-2">Garantovaná dostupnost</h2>
                    <ul class="ps-3">
                        <li>Webhosting a e-mail: <strong class="mergecolor">99,9 %</strong> měsíčně</li>
                        <li>VPS a dedikované servery: <strong class="mergecolor">99,9 %</strong> měsíčně (síť a napájení)</li>
                        <li>Do výpadku se nepočítá plánovaná údržba oznámená min. 24 h předem.</li>
                    </ul>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Reakční doby podpory</h2>
                    <ul class="ps-3">
                        <li>Urgentní priorita (výpadek služby): reakce do <strong class="mergecolor">1 hodiny</strong>, řešíme nepřetržitě</li>
                        <li>Vysoká priorita: reakce do 4 hodin</li>
                        <li>Běžná priorita: reakce do 1 pracovního dne</li>
                    </ul>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Kompenzace</h2>
                    <p>Při nedodržení garantované dostupnosti náleží zákazníkovi na vyžádání kredit ve výši 5 % měsíční ceny služby za každou započatou hodinu výpadku nad rámec SLA, maximálně 100 % měsíční ceny. Kompenzace se připisuje do kreditní peněženky.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Měření</h2>
                    <p class="mb-0">Dostupnost měříme vlastním monitoringem (kontroly v minutových intervalech) a záznamy zveřejňujeme v klientské zóně u každé služby.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
