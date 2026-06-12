@extends('layouts.front')

@section('title', __('front.pages.domains.title'))
@section('meta_description', 'Registrace a správa domén na Onhost.cz — .cz, .com, .eu, .sk a desítky dalších TLD. Přenos domény zdarma.')

@section('content')
    <x-front.page-banner
        :title="__('front.pages.domains.title')"
        :subtitle="__('front.pages.domains.subtitle')">
        <x-front.domain-search />

        @if(session('domain_check_status'))
            <div class="alert alert-info mt-3" role="alert">
                {{ session('domain_check_status') }}
            </div>
        @endif

        @if(session('domain_check_result'))
            @php($result = session('domain_check_result'))
            <div class="alert {{ $result['available'] ? 'alert-success' : 'alert-warning' }} mt-3" role="alert">
                @if($result['available'])
                    {{ __('front.domains.check_available', ['domain' => $result['fqdn']]) }}
                @else
                    {{ __('front.domains.check_unavailable', [
                        'domain' => $result['fqdn'],
                        'reason' => __('front.domains.reasons.' . ($result['reason'] ?? 'taken')),
                    ]) }}
                @endif
            </div>
        @endif
    </x-front.page-banner>

    {{-- ── Ceník domén ────────────────────────────────────────────────── --}}
    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Ceník domén</h2>
                <p class="seccolor" data-aos="fade-up">Ceny jsou bez DPH za rok.</p>
            </div>
            <div class="table-responsive" data-aos="fade-up">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th class="mergecolor">Doménová koncovka</th>
                            <th class="mergecolor">Registrace</th>
                            <th class="mergecolor">Obnova</th>
                            <th class="mergecolor">Přenos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="seccolor">
                        @foreach([
                            ['.cz',   '199 Kč', '199 Kč', '199 Kč'],
                            ['.com',  '299 Kč', '349 Kč', '299 Kč'],
                            ['.eu',   '199 Kč', '249 Kč', '199 Kč'],
                            ['.sk',   '299 Kč', '349 Kč', '299 Kč'],
                            ['.net',  '349 Kč', '399 Kč', '349 Kč'],
                            ['.org',  '299 Kč', '349 Kč', '299 Kč'],
                            ['.info', '249 Kč', '299 Kč', '249 Kč'],
                            ['.shop', '399 Kč', '449 Kč', '399 Kč'],
                            ['.io',   '1 490 Kč', '1 690 Kč', '1 490 Kč'],
                            ['.dev',  '499 Kč', '549 Kč', '499 Kč'],
                        ] as $row)
                            <tr>
                                <td><strong class="mergecolor">{{ $row[0] }}</strong></td>
                                <td>{{ $row[1] }}</td>
                                <td>{{ $row[2] }}</td>
                                <td>{{ $row[3] }}</td>
                                <td>
                                    <a href="{{ route('panel.orders.create') }}" class="btn btn-default-yellow-fill btn-sm">
                                        Registrovat
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ── Výhody správy domén ─────────────────────────────────────────── --}}
    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-globe', 'title' => 'DNS správa', 'text' => 'Plná správa DNS záznamů z klientské zóny — A, AAAA, CNAME, MX, TXT, SRV a další.'],
                    ['icon' => 'ico-ssl', 'title' => 'SSL v ceně', 'text' => "K doméně s hostingem dostanete Let's Encrypt SSL zdarma a automaticky obnovovaný."],
                    ['icon' => 'ico-protection', 'title' => 'DNSSEC', 'text' => 'Zabezpečení domény pomocí DNSSEC — ochrana před DNS spoofingem a man-in-the-middle útoky.'],
                    ['icon' => 'ico-speed', 'title' => 'Přenos domény', 'text' => 'Přeneseme doménu od jiného registrátora. Poradíme s AuthCode a celý postup zvládnete sami v klientské zóně.'],
                ] as $i => $f)
                    <div class="col-md-6 col-lg-3">
                        <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="{{ $i * 80 }}">
                            <i class="{{ $f['icon'] }} f-30 purple"></i>
                            <h3 class="title mergecolor pt-3 f-18">{{ $f['title'] }}</h3>
                            <p class="seccolor mb-0">{{ $f['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── CTA ─────────────────────────────────────────────────────────── --}}
    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Doména + hosting v jednom</h2>
                <p class="text-white-50 pb-3">Objednejte hosting a doménu najednou — nastavíme DNS automaticky.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill me-2">Vybrat hosting</a>
                <a href="{{ route('front.contact') }}" class="btn btn-default-grey">Poradit s výběrem</a>
            </div>
        </div>
    </section>
@endsection
