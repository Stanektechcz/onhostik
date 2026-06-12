@extends('layouts.front')

@section('title', __('front.pages.backups_feature.title'))
@section('meta_description', 'Automatické zálohy OnHost.cz — denní zálohy webu, databáze i e-mailů s retencí 14 dní a obnovou jedním kliknutím.')

@section('content')
    <x-front.page-banner :title="__('front.pages.backups_feature.title')" :subtitle="__('front.pages.backups_feature.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                @foreach([
                    ['icon' => 'ico-backup',      'title' => 'Denní zálohy',              'text' => 'Web, databáze i e-maily se automaticky zálohují každý den. Zálohy se ukládají nezávisle na primárním serveru.'],
                    ['icon' => 'ico-protection',  'title' => 'Retence 14 dní',            'text' => 'Uchovávame zálohy z posledních 14 dní — obnovíte web do libovolného dne v tomto okně.'],
                    ['icon' => 'ico-speed',       'title' => 'Rychlá obnova',             'text' => 'Obnova webu přes klientskou zónu nebo ticketem — obvyklá doba obnovy je do 30 minut.'],
                    ['icon' => 'ico-drives',      'title' => 'Manuální záloha',           'text' => 'Před nasazením nové verze webu spusťte manuální zálohu přímo z detailu služby v klientské zóně.'],
                    ['icon' => 'ico-cloud',       'title' => 'Offsite uložení',           'text' => 'Zálohy ukládáme na fyzicky oddělené úložiště — výpadek primárního serveru se záloh nedotkne.'],
                    ['icon' => 'ico-chip',        'title' => 'V ceně každého tarifu',     'text' => 'Zálohy jsou aktivovány automaticky pro každý webhosting bez příplatku. Žádné skryté poplatky.'],
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
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="wrapper bg-seccolorstyle p-4 rounded" data-aos="fade-up">
                        <h2 class="title mergecolor f-20 pb-3">Jak zálohy fungují</h2>
                        <ol class="seccolor ps-3">
                            <li class="py-2">Každý den v noci se automaticky zazálohují soubory, databáze a e-mailové schránky.</li>
                            <li class="py-2">Zálohy se přenesou na offsite úložiště a zkontroluje se jejich integrita.</li>
                            <li class="py-2">V klientské zóně v detailu služby vidíte seznam dostupných záloh s datem a velikostí.</li>
                            <li class="py-2">Pro obnovu klikněte na tlačítko „Obnovit zálohu" nebo otevřete ticket — postaráme se o zbytek.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Zálohy v ceně každého tarifu</h2>
                <p class="text-white-50 pb-3">Neplaťte extra za bezpečnost — zálohy jsou součástí každého webhostingového tarifu.</p>
                <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill">{{ __('front.home.cta_button') }}</a>
            </div>
        </div>
    </section>
@endsection
