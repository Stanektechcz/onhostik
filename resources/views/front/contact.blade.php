@extends('layouts.front')

@section('title', __('front.pages.contact.title'))
@section('meta_description', 'Kontakty na Onhost.cz — podpora, fakturace, obchodní dotazy.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header total-grad-inverse">
        <div class="total-grad-inverse"></div>
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.contact.title') }}</h1>
                        <div class="subheading text-center mb-5">Jsme tu pro vás — nejrychleji přes klientskou zónu.</div>
                        <div class="included">
                            <div class="h4 mb-3">Jak nás zastihnout</div>
                            <ul><li><i class="fas fa-check-circle"></i> Ticketová podpora 24/7</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> E-mail: podpora@onhost.cz</li></ul>
                            <ul><li><i class="fas fa-check-circle"></i> Urgentní výpadky — reakce do 1 hodiny</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ OFFICE LOCATIONS (Antler contact.html) ============ --}}
    <section class="services pt-4 sec-normal bg-seccolorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    @foreach([
                        ['Brno — Sídlo',     'Molákova 2145/5, 627 00 Brno-Líšeň', 'Česká republika',    'https://maps.google.com/?q=Molakova+2145/5+Brno', 'icon-pin'],
                        ['Podpora online',   'podpora@onhost.cz',                    'Ticketová podpora 24/7', 'mailto:podpora@onhost.cz',                      'icon-mail'],
                        ['Fakturace',        'info@onhost.cz',                       'IČ: 08094616',       'mailto:info@onhost.cz',                             'icon-wallet'],
                    ] as [$city, $address, $info, $href, $icon])
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-4">
                        <div class="service-section noshadow bg-colorstyle text-center">
                            <i class="{{ $icon }} f-40 font-primary mb-3 d-block"></i>
                            <div class="title mergecolor">{{ $city }}</div>
                            <p class="subtitle seccolor mb-1">{{ $address }}</p>
                            <p class="seccolor f-14">{{ $info }}</p>
                            <a href="{{ $href }}" class="btn btn-default-grad-purple-fill btn-sm" target="_blank">
                                Otevřít
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CONTACT CHANNELS ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Kontaktní kanály</h2>
                        <p class="section-subheading">Vyberte nejrychlejší cestu k odpovědi.</p>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up">
                        <div class="service-section bg-colorstyle">
                            <div class="plans badge feat bg-purple">Ticket</div>
                            <i class="icon-support f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Technická podpora</div>
                            <p class="subtitle seccolor">Tickety řešíme nepřetržitě, urgentní výpadky s reakcí do 1 hodiny.</p>
                            <a href="{{ route('panel.support.index') }}" class="btn btn-default-yellow-fill btn-sm mt-2">Otevřít ticket</a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up">
                        <div class="service-section bg-colorstyle">
                            <div class="plans badge feat bg-purple">E-mail</div>
                            <i class="icon-emailopen f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">E-mailový kontakt</div>
                            <p class="subtitle seccolor mb-1">Podpora: <a href="mailto:podpora@onhost.cz" class="purple">podpora@onhost.cz</a></p>
                            <p class="subtitle seccolor mb-1">Fakturace: <a href="mailto:fakturace@onhost.cz" class="purple">fakturace@onhost.cz</a></p>
                            <p class="subtitle seccolor mb-0">Obchod: <a href="mailto:obchod@onhost.cz" class="purple">obchod@onhost.cz</a></p>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4" data-aos="fade-up">
                        <div class="service-section bg-colorstyle">
                            <div class="plans badge feat bg-purple">IČ / DIČ</div>
                            <i class="icon-drives f-30 purple mb-3 d-block"></i>
                            <div class="title mergecolor">Fakturační údaje</div>
                            <p class="subtitle seccolor mb-1">{{ config('billing.supplier.name') }}</p>
                            @if(config('billing.supplier.street'))
                                <p class="subtitle seccolor mb-1">{{ config('billing.supplier.street') }}, {{ config('billing.supplier.zip') }} {{ config('billing.supplier.city') }}</p>
                            @endif
                            @if(config('billing.supplier.ic'))
                                <p class="subtitle seccolor mb-1">IČ: {{ config('billing.supplier.ic') }}</p>
                            @endif
                            @if(config('billing.supplier.dic'))
                                <p class="subtitle seccolor mb-0">DIČ: {{ config('billing.supplier.dic') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CONTACT FORM ============ --}}
    <section class="exapath pb-80 noimage bg-seccolorstyle toppadding bottomhalfpadding">
        <div class="container">
            <div class="sec-main sec-bg1 bg-colorstyle noshadow">
                <div class="randomline">
                    <div class="bigline"></div>
                    <div class="smallline"></div>
                </div>
                <h2 class="mergecolor"><b>Napište nám</b></h2>
                <p class="mb-5 seccolor">Vyplňte formulář a ozveme se vám co nejdříve. Pro urgentní záležitosti použijte ticketový systém.</p>

                @if(session('contact_success'))
                    <div class="alert alert-success mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Vaše zpráva byla odeslána. Ozveme se vám co nejdříve.
                    </div>
                @endif

                <form id="contactForm" method="POST" action="{{ route('front.contact.send') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 position-relative mb-3">
                            <div class="general-input">
                                <label class="seccolor"><i class="fas fa-user-tie me-1"></i>Jméno a příjmení</label>
                                <input type="text" name="name" class="fill-input w-100 mt-1" placeholder="Jan Novák" required value="{{ old('name') }}">
                                @error('name')<div class="text-danger f-13 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6 position-relative mb-3">
                            <div class="general-input">
                                <label class="seccolor"><i class="fas fa-envelope me-1"></i>E-mail</label>
                                <input type="email" name="email" class="fill-input w-100 mt-1" placeholder="vas@email.cz" required value="{{ old('email') }}">
                                @error('email')<div class="text-danger f-13 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6 position-relative mb-3">
                            <div class="general-input">
                                <label class="seccolor"><i class="fas fa-file-alt me-1"></i>Předmět</label>
                                <input type="text" name="subject" class="fill-input w-100 mt-1" placeholder="Dotaz k webhostingu" required value="{{ old('subject') }}">
                                @error('subject')<div class="text-danger f-13 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6 position-relative mb-3">
                            <div class="general-input">
                                <label class="seccolor"><i class="fas fa-tag me-1"></i>Oddělení</label>
                                <select name="department" class="fill-input w-100 mt-1">
                                    <option value="">Vyberte oddělení</option>
                                    <option value="support">Technická podpora</option>
                                    <option value="billing">Fakturace</option>
                                    <option value="sales">Obchodní oddělení</option>
                                    <option value="abuse">Abuse / DMCA</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12 position-relative mb-3">
                            <div class="general-input">
                                <label class="seccolor"><i class="fas fa-comment me-1"></i>Zpráva</label>
                                <textarea name="message" class="fill-input w-100 mt-1" rows="5" placeholder="Popište váš dotaz nebo problém..." required>{{ old('message') }}</textarea>
                                @error('message')<div class="text-danger f-13 mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-12 mt-3">
                            <ul class="list d-inline me-3">
                                <li>
                                    <input name="gdpr" type="checkbox" id="gdpr_check" class="filter" required>
                                    <label for="gdpr_check" class="checkbox-label c-grey seccolor">
                                        Souhlasím se zpracováním osobních údajů — <a href="{{ route('front.gdpr') }}" class="golink-dark">GDPR</a>
                                    </label>
                                </li>
                            </ul>
                            <button type="submit" class="btn btn-default-yellow-fill mt-3 me-2">Odeslat dotaz</button>
                            <button type="reset" class="btn btn-default-fill mt-3">Vymazat</button>
                        </div>
                        <div id="msgSubmit" class="col-md-12 mt-4" style="display:none">
                            <h3 class="c-pink"><i class="fas fa-check-circle me-2"></i>Zpráva odeslána!</h3>
                        </div>
                    </div>
                </form>
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
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-speed f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Znalostní báze</div>
                                    <div class="description seccolor">Návody pro DNS, e-mail, SSL, WordPress a zálohy.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.faq') }}" class="help-item" title="Časté dotazy">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Časté dotazy</div>
                                    <div class="description seccolor">Nejčastější otázky o tarifech, platbách a aktivaci služeb.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Centrum podpory</div>
                                    <div class="description seccolor">Reakční doby, kanály a otevření prioritního ticketu.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
