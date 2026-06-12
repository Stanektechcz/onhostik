@extends('layouts.front')

@section('title', __('front.pages.support.title'))
@section('meta_description', 'Podpora Onhost.cz — tickety, znalostní báze a AI asistent. Reakce na urgentní výpadky do 1 hodiny.')

@section('content')
    <x-front.page-banner :title="__('front.pages.support.title')" :subtitle="__('front.pages.support.subtitle')" />

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up">
                        <i class="ico-support f-30 purple"></i>
                        <h2 class="title mergecolor pt-3 f-18">Ticketová podpora</h2>
                        <p class="seccolor mb-3">Nejrychlejší cesta k řešení — ticket zůstane v historii a sledujete průběh v reálném čase.</p>
                        <a href="{{ route('panel.support.index') }}" class="btn btn-default-yellow-fill btn-sm">Otevřít ticket</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="100">
                        <i class="ico-chip f-30 purple"></i>
                        <h2 class="title mergecolor pt-3 f-18">AI asistent</h2>
                        <p class="seccolor mb-3">Okamžité odpovědi na otázky o DNS, PHP, e-mailech nebo tarifu. Dostupný 24/7 v klientské zóně.</p>
                        <a href="{{ route('panel.ai.index') }}" class="btn btn-default-grey btn-sm">Otevřít AI asistenta</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="wrapper bg-seccolorstyle p-4 rounded mb-4" data-aos="fade-up" data-aos-delay="200">
                        <i class="ico-speedometer f-30 purple"></i>
                        <h2 class="title mergecolor pt-3 f-18">Znalostní báze</h2>
                        <p class="seccolor mb-3">Návody pro nastavení DNS, e-mailů, SSL, přesměrování a dalších běžných operací.</p>
                        <a href="{{ route('front.kb') }}" class="btn btn-default-grey btn-sm">Procházet návody</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-90">
        <div class="container">
            <div class="sec-main-title text-center pb-5">
                <h2 class="title mergecolor" data-aos="fade-up">Reakční doby podpory</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="wrapper bg-seccolorstyle p-4 rounded" data-aos="fade-up">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody class="seccolor">
                                    <tr>
                                        <td><span class="badge bg-danger">Urgentní</span></td>
                                        <td>Výpadek služby, ztráta dat</td>
                                        <td class="mergecolor f-w-600">Reakce do 1 hodiny, 24/7</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-warning">Vysoká</span></td>
                                        <td>Výrazné zpomalení, funkční chyba</td>
                                        <td class="mergecolor f-w-600">Reakce do 4 hodin</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-info">Standardní</span></td>
                                        <td>Dotazy, nastavení, přesměrování</td>
                                        <td class="mergecolor f-w-600">Reakce do 1 pracovního dne</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-secondary">Nízká</span></td>
                                        <td>Obecné dotazy, konzultace</td>
                                        <td class="mergecolor f-w-600">Reakce do 2 pracovních dnů</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="wrapper bg-purple p-5 rounded text-center" data-aos="fade-up">
                <h2 class="title text-white">Potřebujete okamžitou pomoc?</h2>
                <p class="text-white-50 pb-3">Přihlaste se do klientské zóny a otevřete ticket s prioritou Urgentní.</p>
                <a href="{{ config('app.customer_panel_url') }}" class="btn btn-default-yellow-fill">Klientská zóna</a>
            </div>
        </div>
    </section>
@endsection
