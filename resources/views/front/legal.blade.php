@extends('layouts.front')

@section('title', __('front.pages.legal.title'))
@section('meta_description', 'Obchodní podmínky služeb Onhost.cz — webhosting, domény, VPS a související služby.')

@section('content')
    <div class="top-header">
        <div class="total-grad-inverse"></div>
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.legal.title') }}</h1>
                        <div class="subheading text-center">Podmínky pro používání služeb Onhost.cz</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="col-lg-9">
                <div class="wrapper bg-seccolorstyle p-4 rounded seccolor" data-aos="fade-up">
                    <h2 class="mergecolor f-20 pb-2">1. Úvodní ustanovení</h2>
                    <p>Tyto obchodní podmínky upravují vztah mezi poskytovatelem služeb, společností {{ config('billing.supplier.name') }} (dále „poskytovatel“), a objednatelem služeb (dále „zákazník“). Objednáním služby zákazník potvrzuje, že se s podmínkami seznámil a souhlasí s nimi.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">2. Předmět služeb</h2>
                    <p>Poskytovatel zajišťuje provoz webhostingu, registraci a správu domén, pronájem virtuálních a dedikovaných serverů a souvisejících služeb (monitoring, zálohy, e-mail). Konkrétní parametry určuje objednaný tarif.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">3. Objednávka a platba</h2>
                    <p>Služby se objednávají v klientské zóně. Na základě objednávky je vystavena zálohová faktura; služba je zřízena po jejím uhrazení. Daňový doklad je vystaven automaticky po přijetí platby. Ceny jsou uvedeny bez DPH, není-li uvedeno jinak.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">4. Trvání a prodloužení služby</h2>
                    <p>Služba je sjednána na fakturační období dle tarifu. Před koncem období poskytovatel zašle výzvu k úhradě dalšího období. Neuhrazením služba expiruje; postup pozastavení a ukončení popisuje SLA a reklamační řád.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">5. Práva a povinnosti zákazníka</h2>
                    <p>Zákazník se zavazuje nevyužívat služby v rozporu s právním řádem ČR a EU, zejména k šíření nelegálního obsahu, spamu, malware nebo k přetěžování infrastruktury. Zákazník odpovídá za obsah svých dat a za udržování přístupových údajů v tajnosti.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">6. Odpovědnost poskytovatele</h2>
                    <p>Poskytovatel odpovídá za dostupnost služeb v rozsahu definovaném SLA. Poskytovatel neodpovídá za škody způsobené vyšší mocí, zásahem třetích stran ani obsahem dat zákazníka. Náhrada škody je omezena do výše ceny služby za poslední fakturační období.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">7. Ukončení smlouvy</h2>
                    <p>Zákazník může službu kdykoli vypovědět ke konci uhrazeného období. Poskytovatel může službu pozastavit či ukončit při závažném porušení podmínek; o tom zákazníka informuje. Postup vrácení plateb popisuje reklamační řád.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">8. Závěrečná ustanovení</h2>
                    <p>Vztahy neupravené těmito podmínkami se řídí právem České republiky. Poskytovatel je oprávněn podmínky přiměřeně měnit; o změně informuje nejméně 30 dní předem. Tyto podmínky nabývají účinnosti dnem zveřejnění.</p>

                    <p class="f-14 pt-3 mb-0"><em>Pozn.: Návrh podmínek podléhá závěrečné kontrole právním zástupcem před spuštěním ostrého prodeje.</em></p>
                </div>
            </div>
        </div>
    </section>
@endsection
