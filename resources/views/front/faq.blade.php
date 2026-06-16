@extends('layouts.front')

@section('title', __('front.pages.faq.title'))
@section('meta_description', 'Časté dotazy k webhostingu, doménám, platbám a podpoře Onhost.cz.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.faq.title') }}</h1>
                        <div class="subheading text-center mb-5">Nejčastější otázky a odpovědi na jednom místě.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ FAQ ACCORDION ============ --}}
    <section class="services sec-normal sec-bg1 bg-colorstyle pb-80">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    @php($faqs = [
                        ['q' => 'Jak rychle bude hosting aktivní?',    'a' => 'Ihned po zaplacení — zřízení probíhá plně automaticky. U platby kartou je web obvykle online do minuty.'],
                        ['q' => 'Můžu přejít z jiného hostingu?',      'a' => 'Ano. S přenosem webu, e-mailů i domény vám pomůže podpora zdarma; výpadek při migraci se blíží nule.'],
                        ['q' => 'Jak funguje kreditní peněženka?',     'a' => 'V klientské zóně si dobijete kredit (100 – 50 000 Kč) a faktury pak hradíte jedním kliknutím. Kredit využijete i pro automatické prodlužování služeb.'],
                        ['q' => 'Dostanu daňový doklad?',              'a' => 'Ano, automaticky po úhradě zálohové faktury. Najdete jej v klientské zóně u faktury, včetně tiskové verze.'],
                        ['q' => 'Co když potřebuji větší tarif?',       'a' => 'Tarif lze kdykoli povýšit; rozdíl ceny se dopočítá poměrně. Downgrade je možný ke konci fakturačního období.'],
                        ['q' => 'Umíte WordPress?',                    'a' => 'Ano — tarif Managed WordPress zahrnuje instalaci na jedno kliknutí, automatické aktualizace a denní zálohy.'],
                        ['q' => 'Jak fungují zálohy?',                 'a' => 'Každý hosting zálohujeme denně s retencí 14 dní. Zálohu spustíte i ručně v detailu služby a o obnovu požádáte podporu.'],
                        ['q' => 'Co hlídá monitoring?',                'a' => 'Dostupnost webu a platnost SSL certifikátu 24/7. Stav vidíte v klientské zóně; při výpadku evidujeme incident.'],
                        ['q' => 'Jak kontaktuji podporu?',             'a' => 'Nejrychleji ticketem v klientské zóně — urgentní výpadky řešíme s reakcí do 1 hodiny. K dispozici je i AI asistent pro okamžité odpovědi.'],
                        ['q' => 'Můžu služby zrušit?',                 'a' => 'Kdykoli ke konci uhrazeného období bez výpovědní lhůty. U webhostingu platí 30denní garance vrácení peněz.'],
                    ])
                    <div class="accordion faq" id="faq-accordion">
                        @foreach($faqs as $i => $faq)
                            <div class="panel-wrap bg-colorstyle">
                                <div class="panel-title seccolor" data-bs-toggle="collapse" data-bs-target="#faq-{{ $i }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                    {{ $faq['q'] }}
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div id="faq-{{ $i }}" class="panel-collapse collapse {{ $i === 0 ? 'show' : '' }}">
                                    <div class="wrapper-collapse seccolor">
                                        {{ $faq['a'] }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ HELP ============ --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Další otázka?</div>
                                    <div class="description seccolor">Otevřete ticket — odpovíme do 1 pracovního dne.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Znalostní báze</div>
                                    <div class="description seccolor">Podrobné návody pro DNS, e-mail, SSL a WordPress.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.webhosting') }}" class="help-item" title="Webhosting">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Vybrat hosting</div>
                                    <div class="description seccolor">Prohlédněte tarify a spusťte web ještě dnes.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
