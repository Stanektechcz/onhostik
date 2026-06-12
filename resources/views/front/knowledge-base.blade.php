@extends('layouts.front')

@section('title', __('front.pages.kb.title'))
@section('meta_description', 'Znalostní báze Onhost.cz — návody pro nastavení DNS, e-mailu, SSL, FTP a dalších funkcí hostingu.')

@section('content')
    <x-front.page-banner :title="__('front.pages.kb.title')" :subtitle="__('front.pages.kb.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Oblíbené kategorie</h2>
            </div>
            <div class="row">
                @foreach([
                    ['icon' => 'ico-globe', 'title' => 'DNS a domény', 'count' => 12, 'items' => ['Nastavení A záznamu', 'MX záznamy pro e-mail', 'CNAME a subdoména', 'Přenos domény']],
                    ['icon' => 'ico-mail', 'title' => 'E-mail', 'count' => 8, 'items' => ['Nastavení Outlooku', 'Nastavení Thunderbirdu', 'Webmail RoundCube', 'SPF / DKIM / DMARC']],
                    ['icon' => 'ico-ssl', 'title' => 'SSL a bezpečnost', 'count' => 6, 'items' => ["Let's Encrypt certifikát", 'HTTPS přesměrování', 'HTTP hlavičky', 'Firewall pravidla']],
                    ['icon' => 'ico-speed', 'title' => 'WordPress', 'count' => 10, 'items' => ['Instalace WordPress', 'Migrace WP webu', 'Cache pluginy', 'Obnovení zálohy']],
                    ['icon' => 'ico-drives', 'title' => 'Databáze', 'count' => 5, 'items' => ['phpMyAdmin', 'Import / export SQL', 'Uživatel a práva', 'Vzdálený přístup']],
                    ['icon' => 'ico-backup', 'title' => 'Zálohy a obnova', 'count' => 4, 'items' => ['Jak fungují zálohy', 'Obnova ze zálohy', 'Manuální záloha', 'Plán záloh']],
                ] as $i => $cat)
                    <div class="col-md-6 col-lg-4">
                        <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="{{ ($i % 3) * 100 }}">
                            <i class="{{ $cat['icon'] }} f-30 purple"></i>
                            <h3 class="title mergecolor pt-3 f-18">
                                {{ $cat['title'] }}
                                <span class="badge badge-light-primary f-12 ms-1">{{ $cat['count'] }} článků</span>
                            </h3>
                            <ul class="list-unstyled seccolor f-14 mt-2 mb-0">
                                @foreach($cat['items'] as $item)
                                    <li class="py-1"><i class="fas fa-chevron-right purple pe-2 f-12"></i>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center" data-aos="fade-up">
                    <div class="wrapper bg-seccolorstyle p-4 rounded">
                        <i class="ico-chip f-30 purple"></i>
                        <h3 class="title mergecolor pt-3 f-18">Nenašli jste odpověď?</h3>
                        <p class="seccolor mb-3">Zeptejte se AI asistenta v klientské zóně, nebo otevřete ticket — odpovíme do 1 pracovního dne.</p>
                        <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill btn-sm me-2">Kontaktovat podporu</a>
                        <a href="{{ route('front.faq') }}" class="btn btn-default-grey btn-sm">Časté dotazy</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
