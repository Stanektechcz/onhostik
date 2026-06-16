@extends('layouts.front')

@section('title', __('front.pages.gdpr.title'))
@section('meta_description', 'Zásady zpracování osobních údajů ve službách Onhost.cz dle GDPR.')

@section('content')
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.gdpr.title') }}</h1>
                        <div class="subheading text-center">Zásady zpracování osobních údajů dle nařízení GDPR</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="col-lg-9">
                <div class="wrapper bg-seccolorstyle p-4 rounded seccolor" data-aos="fade-up">
                    <h2 class="mergecolor f-20 pb-2">Správce údajů</h2>
                    <p>Správcem osobních údajů je {{ config('billing.supplier.name') }}. V záležitostech ochrany osobních údajů nás kontaktujte prostřednictvím stránky Kontakt.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Jaké údaje zpracováváme</h2>
                    <p>Identifikační a fakturační údaje (jméno, firma, IČ/DIČ, adresa), kontaktní údaje (e-mail, telefon), provozní údaje o službách (objednávky, faktury, platby, podpora) a technické záznamy nezbytné pro bezpečný provoz (IP adresy, auditní log).</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Účel a právní základ</h2>
                    <p>Údaje zpracováváme pro plnění smlouvy (provoz objednaných služeb), plnění právních povinností (účetnictví, daňové doklady) a z oprávněného zájmu (bezpečnost, prevence zneužití, auditní stopa). Marketingová sdělení zasíláme pouze se souhlasem.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Doba uložení</h2>
                    <p>Smluvní a provozní údaje uchováváme po dobu trvání služby a dále po dobu nutnou k plnění zákonných povinností (daňové doklady 10 let). Technické logy uchováváme po dobu nezbytnou pro bezpečnost provozu.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Příjemci údajů</h2>
                    <p>Údaje předáváme pouze zpracovatelům nezbytným pro provoz služeb (platební brána, registrátor domén, datacentrum, účetní systém), a to na základě zpracovatelských smluv. Údaje nepředáváme mimo EU/EHP.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Vaše práva</h2>
                    <p>Máte právo na přístup ke svým údajům, opravu, výmaz, omezení zpracování, přenositelnost a námitku. Máte právo podat stížnost u Úřadu pro ochranu osobních údajů (uoou.gov.cz).</p>

                    <p class="f-14 pt-3 mb-0"><em>Pozn.: Dokument podléhá závěrečné kontrole právním zástupcem před spuštěním ostrého provozu.</em></p>
                </div>
            </div>
        </div>
    </section>
@endsection
