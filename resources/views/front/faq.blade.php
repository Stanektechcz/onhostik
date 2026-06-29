@extends('layouts.front')

@section('title', __('front.pages.faq.title'))
@section('meta_description', 'Časté dotazy k webhostingu, doménám, platbám a podpoře Onhost.cz.')

@section('content')
    {{-- ============ HERO — Antler total-grad-inverse ============ --}}
    <div class="top-header">
        <div class="total-grad-inverse"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.faq.title') }}</h1>
                        <div class="subheading text-center">Nejčastější otázky a odpovědi na jednom místě.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ FAQ — Antler blog motpath + sidebar layout ============ --}}
    @php
    $faqGroups = [
        '1' => ['title' => 'Hosting & Aktivace', 'items' => [
            ['q' => 'Jak rychle bude hosting aktivní?',
             'a' => 'Ihned po zaplacení — zřízení probíhá plně automaticky. U platby kartou je web obvykle online do minuty.'],
            ['q' => 'Můžu přejít z jiného hostingu?',
             'a' => 'Ano. S přenosem webu, e-mailů i domény vám pomůže podpora zdarma; výpadek při migraci se blíží nule.'],
            ['q' => 'Co když potřebuji větší tarif?',
             'a' => 'Tarif lze kdykoli povýšit; rozdíl ceny se dopočítá poměrně. Downgrade je možný ke konci fakturačního období.'],
            ['q' => 'Umíte WordPress?',
             'a' => 'Ano — tarif Managed WordPress zahrnuje instalaci na jedno kliknutí, automatické aktualizace a denní zálohy.'],
        ]],
        '2' => ['title' => 'Platby & Faktury', 'items' => [
            ['q' => 'Jak funguje kreditní peněženka?',
             'a' => 'V klientské zóně si dobijete kredit (100 – 50 000 Kč) a faktury pak hradíte jedním kliknutím. Kredit využijete i pro automatické prodlužování služeb.'],
            ['q' => 'Dostanu daňový doklad?',
             'a' => 'Ano, automaticky po úhradě zálohové faktury. Najdete jej v klientské zóně u faktury, včetně tiskové verze.'],
            ['q' => 'Jaké platební metody přijímáte?',
             'a' => 'Platbu kartou přes Comgate (Visa, Mastercard), bankovní převod a platbu z kreditního zůstatku v klientské zóně.'],
        ]],
        '3' => ['title' => 'Funkce & Nástroje', 'items' => [
            ['q' => 'Jak fungují zálohy?',
             'a' => 'Každý hosting zálohujeme denně s retencí 14 dní. Zálohu spustíte i ručně v detailu služby a o obnovu požádáte podporu.'],
            ['q' => 'Co hlídá monitoring?',
             'a' => 'Dostupnost webu a platnost SSL certifikátu 24/7. Stav vidíte v klientské zóně; při výpadku evidujeme incident.'],
            ['q' => 'Mám přístup k PHP, MySQL, SSH?',
             'a' => 'Ano — všechny tarify zahrnují PHP 8.x, MySQL databáze a dle tarifu FTP/SFTP/SSH přístup.'],
        ]],
        '4' => ['title' => 'Podpora & Zrušení', 'items' => [
            ['q' => 'Jak kontaktuji podporu?',
             'a' => 'Nejrychleji ticketem v klientské zóně — urgentní výpadky řešíme s reakcí do 1 hodiny. K dispozici je i AI asistent pro okamžité odpovědi.'],
            ['q' => 'Můžu služby zrušit?',
             'a' => 'Kdykoli ke konci uhrazeného období bez výpovědní lhůty. U webhostingu platí 30denní garance vrácení peněz.'],
        ]],
    ];
    @endphp

    <section id="gotop" class="blog motpath pb-80 bg-colorstyle">
        <div class="container">
            <div class="row">

                {{-- LEFT SIDEBAR — Antler gocheck/showSingle category filter --}}
                <div class="col-md-12 col-lg-4">
                    <aside id="sidebar" class="sidebar mt-80 sec-bg1 bg-seccolorstyle noshadow">
                        <div class="menu categories clear">
                            <h4 class="mergecolor"><b>FAQ.</b></h4>
                            <hr>
                            <div class="heading pt-2">
                                <a href="#gotop" id="showall" class="gocheck active seccolor">
                                    Všechny otázky
                                </a>
                            </div>
                            @foreach([
                                ['1', 'Hosting & Aktivace',  'icon-cloud'],
                                ['2', 'Platby & Faktury',    'icon-wallet'],
                                ['3', 'Funkce & Nástroje',   'icon-settings'],
                                ['4', 'Podpora & Zrušení',   'icon-headphone'],
                            ] as [$target, $label, $icon])
                            <div class="heading pt-2">
                                <a href="#gotop" class="gocheck showSingle seccolor" target="{{ $target }}">
                                    <i class="{{ $icon }} me-2"></i>{{ $label }}
                                </a>
                            </div>
                            @endforeach
                        </div>
                        <hr>
                        <div class="categories">
                            <div class="heading active">Rychlý kontakt</div>
                            <div class="line active"></div>
                            <div class="heading">
                                <a href="{{ route('front.support') }}" class="mergecolor">Otevřít ticket</a>
                            </div>
                            <div class="line"></div>
                            <div class="heading">
                                <a href="{{ route('front.kb') }}" class="mergecolor">Znalostní báze</a>
                            </div>
                            <div class="line"></div>
                            <div class="heading">
                                <a href="{{ route('front.contact') }}" class="mergecolor">Kontaktujte nás</a>
                            </div>
                            <div class="line"></div>
                        </div>
                    </aside>
                </div>

                {{-- RIGHT CONTENT — Q&A grouped by category with targetDiv --}}
                <div class="pt-35 col-md-12 col-lg-8">
                    <div id="sidebar_content" class="wrap-blog">
                        @foreach($faqGroups as $target => $group)
                        <div id="div{{ $target }}" class="wrapper targetDiv mt-5 bg-seccolorstyle noshadow">
                            <a href="#" class="category h4"><b>{{ $group['title'] }}</b></a>
                            <span class="float-end c-grey seccolor">[{{ count($group['items']) }} otázek]</span>
                            <hr>
                            @foreach($group['items'] as $j => $faq)
                            <a class="h5 mergecolor" href="#">{{ ($j + 1) }}. {{ $faq['q'] }}</a>
                            <div class="blog-info">
                                <p class="seccolor">{{ $faq['a'] }}</p>
                            </div>
                            @if(!$loop->last)<br>@endif
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </div>{{-- /col-lg-8 --}}

            </div>{{-- /row --}}
        </div>{{-- /container --}}
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

@push('scripts')
<script>
/* Antler showSingle/gocheck sidebar category filter */
(function() {
    var allLinks = document.querySelectorAll('.gocheck');
    var targets  = document.querySelectorAll('.targetDiv');

    function showAll() {
        targets.forEach(function(t) { t.style.display = ''; });
        allLinks.forEach(function(l) { l.classList.remove('active'); });
        var all = document.getElementById('showall');
        if (all) all.classList.add('active');
    }

    function showOne(id) {
        targets.forEach(function(t) {
            t.style.display = (t.id === 'div' + id) ? '' : 'none';
        });
        allLinks.forEach(function(l) { l.classList.remove('active'); });
        document.querySelectorAll('.showSingle[target="' + id + '"]').forEach(function(l) {
            l.classList.add('active');
        });
    }

    document.querySelectorAll('.showSingle').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showOne(this.getAttribute('target'));
        });
    });

    var allBtn = document.getElementById('showall');
    if (allBtn) allBtn.addEventListener('click', function(e) {
        e.preventDefault(); showAll();
    });
})();
</script>
@endpush
