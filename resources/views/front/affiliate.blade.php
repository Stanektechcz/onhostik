@extends('layouts.front')

@section('title', 'Speciální nabídka od ' . $partnerName . ' — Onhost.cz')
@section('meta_description', 'Vyzkoušejte Onhost.cz s doporučením od ' . $partnerName . '. Rychlý hosting s AI asistencí, garantovanou dostupností a českou podporou.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper text-center">
                        <p class="f-light mb-2" style="font-size:0.9rem;letter-spacing:.05em;">DOPORUČENO VAŠÍM PARTNEREM</p>
                        <h1 class="heading">Hosting, který oceníte</h1>
                        <div class="subheading">{{ $partnerName }} vás pozval na Onhost.cz — český hosting s AI, monitoringem a férovým přístupem.</div>
                        <div class="buttons">
                            <a href="{{ route('register') }}" class="btn btn-default-yellow-fill me-2">
                                Začít zdarma
                            </a>
                            <a href="{{ route('front.webhosting') }}" class="btn btn-default-grad-purple-fill">
                                Prohlédnout tarify
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ WHY ONHOST ============ --}}
    <section class="sec-normal sec-bg1 bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="row justify-content-center mb-4">
                <div class="col-sm-12 text-center">
                    <h2 class="section-heading">Proč Onhost?</h2>
                    <p class="section-subheading">Hosting navržený tak, abyste se mohli soustředit na svůj web, ne na infrastrukturu.</p>
                </div>
            </div>
            <div class="row g-4">
                @foreach([
                    ['fas fa-bolt', 'Bleskový výkon', 'NVMe SSD disky a LiteSpeed server. Vaše stránky se načtou dřív, než stihnete mrknout.'],
                    ['fas fa-robot', 'AI asistent', 'Zabudovaný AI asistent odpovídá na vaše dotazy a pomáhá s webem 24/7.'],
                    ['fas fa-shield-alt', 'Monitoring zdarma', '5minutový monitoring s uptime reportem a SSL hlídáním — bez příplatku.'],
                    ['fas fa-headset', 'Česká podpora', 'Tým v ČR, odpověď do 1 hodiny. Žádné boty, žádné čekání.'],
                    ['fas fa-undo', 'Denní zálohy', 'Automatické zálohy každý den. Obnova na klik, bez stresu.'],
                    ['fas fa-tag', 'Férové ceny', 'Tarify od 49 Kč/měs bez skrytých poplatků. Vždy víte, co platíte.'],
                ] as [$icon, $title, $text])
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="service-section bg-colorstyle text-center" data-aos="fade-up">
                            <i class="{{ $icon }} f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">{{ $title }}</div>
                            <p class="subtitle seccolor">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ CTA ============ --}}
    <section class="services sec-normal sec-bg2 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-md-8">
                    <h2 class="mergecolor mb-3">Připraveni začít?</h2>
                    <p class="seccolor mb-4">Registrace je zdarma. Tarif si zvolíte, až budete připraveni. Žádná kreditní karta předem.</p>
                    <a href="{{ route('register') }}" class="btn btn-default-yellow-fill me-2">
                        Vytvořit účet zdarma
                    </a>
                    <a href="{{ route('front.webhosting') }}" class="btn btn-default-grad-purple-fill">
                        Porovnat tarify
                    </a>
                    <p class="f-light f-12 mt-3">
                        Váš partner: <strong>{{ $partnerName }}</strong> &bull; Kód: <code>{{ $profile->referral_code }}</code>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
