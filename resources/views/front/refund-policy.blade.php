@extends('layouts.front')

@section('title', __('front.pages.refund.title'))
@section('meta_description', 'Reklamační řád a podmínky vrácení plateb Onhost.cz — 30denní garance vrácení peněz u webhostingu.')

@section('content')
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.refund.title') }}</h1>
                        <div class="subheading text-center">30denní garance vrácení peněz u webhostingu</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="col-lg-9">
                <div class="wrapper bg-seccolorstyle p-4 rounded seccolor" data-aos="fade-up">
                    <h2 class="mergecolor f-20 pb-2">Garance vrácení peněz</h2>
                    <p>U nových webhostingových tarifů poskytujeme <strong class="mergecolor">30denní garanci vrácení peněz</strong> bez udání důvodu. Garance se nevztahuje na registrace domén (registr je zpoplatňuje nevratně) a na služby zřízené na míru.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Jak reklamovat</h2>
                    <ol class="ps-3">
                        <li>Založte ticket v klientské zóně (sekce Podpora) s popisem problému.</li>
                        <li>Reklamaci vyřídíme nejpozději do 30 dnů; o průběhu vás informujeme v ticketu.</li>
                        <li>Uznané reklamace řešíme přednostně kreditem, na vyžádání vrácením platby na účet.</li>
                    </ol>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Vrácení plateb</h2>
                    <p>Platby vracíme stejnou cestou, jakou byly uhrazeny (platební brána, převod). U částečně vyčerpaných období se vrací poměrná část. Kredit v peněžence je vratný na žádost, s výjimkou bonusových kreditů.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Spotřebitelská ustanovení</h2>
                    <p class="mb-0">Spotřebitel může od smlouvy odstoupit do 14 dnů od uzavření; zřízením služby před uplynutím této lhůty na výslovnou žádost zákazníka vzniká nárok na poměrnou úhradu již poskytnutého plnění. Subjektem mimosoudního řešení sporů je Česká obchodní inspekce (coi.cz).</p>
                </div>
            </div>
        </div>
    </section>
@endsection
