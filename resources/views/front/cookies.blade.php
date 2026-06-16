@extends('layouts.front')

@section('title', __('front.pages.cookies.title'))
@section('meta_description', 'Zásady používání cookies na webu Onhost.cz.')

@section('content')
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.cookies.title') }}</h1>
                        <div class="subheading text-center">Informace o souborech cookies na webu Onhost.cz</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <div class="col-lg-9">
                <div class="wrapper bg-seccolorstyle p-4 rounded seccolor" data-aos="fade-up">
                    <h2 class="mergecolor f-20 pb-2">Jaké cookies používáme</h2>
                    <p><strong class="mergecolor">Nezbytné (technické):</strong> relace přihlášení, CSRF ochrana, volba jazyka. Bez nich web a klientská zóna nefungují; nelze je vypnout.</p>
                    <p><strong class="mergecolor">Analytické a marketingové:</strong> v současnosti nenasazujeme žádné cookies třetích stran. Pokud je v budoucnu nasadíme, vyžádáme si předem souhlas prostřednictvím cookie lišty.</p>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Přehled</h2>
                    <ul class="ps-3">
                        <li><code>onhost_session</code> — relace přihlášení (nezbytné, po dobu relace)</li>
                        <li><code>XSRF-TOKEN</code> — ochrana formulářů (nezbytné, po dobu relace)</li>
                        <li><code>locale</code> — zvolený jazyk (nezbytné, 1 rok)</li>
                    </ul>

                    <h2 class="mergecolor f-20 pb-2 pt-3">Správa cookies</h2>
                    <p class="mb-0">Technické cookies můžete smazat v nastavení prohlížeče; web poté může vyžadovat opětovné přihlášení. Postup naleznete v nápovědě svého prohlížeče.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
