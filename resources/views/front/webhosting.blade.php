@extends('layouts.front')

@section('title', __('front.pages.webhosting.title'))

@push('scripts')
<script>
function moveScroll() {
    var scroll = $(window).scrollTop();
    if (typeof $('#maintable').offset() === 'undefined') return;
    var anchor_top = $('#maintable').offset().top;
    var anchor_bottom = $('#bottom_anchor').offset().top;
    if (scroll > anchor_top && scroll < anchor_bottom) {
        var clone_table = $('#clone');
        if (clone_table.length === 0) {
            clone_table = $('#maintable').clone();
            clone_table.attr('id', 'clone');
            clone_table.css({ position: 'fixed', top: 75 });
            clone_table.width($('#maintable').width());
            $('#table-container').append(clone_table);
            $('#clone').css({ visibility: 'hidden' });
        }
    } else {
        $('#clone').remove();
    }
}
$(window).scroll(moveScroll);
$(function() { $('[data-bs-toggle="tooltip"]').tooltip(); });
</script>
@endpush
@section('meta_description', 'Rychlý NVMe webhosting s PHP 8.3, MySQL, SSL zdarma a denními zálohami. Tarify od 49 Kč/měs. bez DPH. Česká technická podpora.')

@section('content')
    {{-- ============ HERO ============ --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <h1 class="heading text-center">{{ __('front.pages.webhosting.title') }}</h1>
                        <div class="subheading text-center mb-5">{{ __('front.pages.webhosting.subtitle') }}</div>
                        <div class="included">
                            <div class="h4 mb-3">V ceně každého tarifu</div>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> SSL certifikát zdarma</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Denní zálohy (14 dní)</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> E-mailové schránky</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> PHP 8.3 / MySQL</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Monitoring dostupnosti</li>
                            </ul>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Podpora v češtině</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PRICING — OVERLAP ============ --}}
    <section class="pricing special sec-uping pb-5 bg-colorstyle specialposition">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            @if($product !== null)
                @php
                    $monthlyPlans = $plans->filter(fn($p) => $p->billing_cycle === \App\Domains\Products\Enums\BillingCycle::Monthly);
                    $annualPlans  = $plans->filter(fn($p) => $p->billing_cycle === \App\Domains\Products\Enums\BillingCycle::Annually);
                    $hasAnnual    = $annualPlans->isNotEmpty();
                    // Calc avg savings % for annual vs monthly (per matching plan name)
                    $savePercent  = 0;
                    if ($hasAnnual && $monthlyPlans->isNotEmpty()) {
                        $savings = [];
                        foreach ($annualPlans as $ap) {
                            $mp = $monthlyPlans->firstWhere('name', $ap->name);
                            if ($mp && $mp->supportsCurrency($currency) && $ap->supportsCurrency($currency)) {
                                $mMonthly = $mp->priceFor($currency)->getAmount()->toFloat();
                                $aMonthly = $ap->priceFor($currency)->getAmount()->toFloat() / 12;
                                if ($mMonthly > 0) {
                                    $savings[] = round(($mMonthly - $aMonthly) / $mMonthly * 100);
                                }
                            }
                        }
                        $savePercent = !empty($savings) ? (int) round(array_sum($savings) / count($savings)) : 0;
                    }
                    // When no monthly plans exist, show all plans without toggle
                    $displayPlans = $monthlyPlans->isNotEmpty() ? $monthlyPlans : $plans;
                @endphp

                @if($hasAnnual)
                    <x-front.billing-toggle :has-annual="$hasAnnual" :save-percent="$savePercent" />
                @endif

                <div class="row justify-content-center" data-billing-wrapper data-billing="monthly">
                    {{-- Monthly plans --}}
                    @foreach($monthlyPlans as $plan)
                        @if($plan->supportsCurrency($currency))
                        <div class="col-sm-12 col-md-6 col-lg-4 plan-monthly">
                            <div class="wrapper price-container text-start noshadow">
                                @if($plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price-content">
                                        <div class="price mergecolor">
                                            {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                            <span class="period mergecolor">/ {{ __('front.pricing.month') }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">{{ __('front.pricing.order_now') }}</a>
                                </div>
                                <ul class="list-info bg-purple">
                                    @foreach($plan->resources ?? [] as $key => $value)
                                        <li>
                                            <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                                            <div>{{ __("front.resources.$key") }}<br><span>{{ $value }}</span></div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    @endforeach

                    {{-- Annual plans --}}
                    @foreach($annualPlans as $plan)
                        @if($plan->supportsCurrency($currency))
                        @php
                            $mp = $monthlyPlans->firstWhere('name', $plan->name);
                            $annualSave = 0;
                            if ($mp && $mp->supportsCurrency($currency)) {
                                $mAmt = $mp->priceFor($currency)->getAmount()->toFloat();
                                $aAmt = $plan->priceFor($currency)->getAmount()->toFloat() / 12;
                                $annualSave = $mAmt > 0 ? (int) round(($mAmt - $aAmt) / $mAmt * 100) : 0;
                            }
                        @endphp
                        <div class="col-sm-12 col-md-6 col-lg-4 plan-annual">
                            <div class="wrapper price-container text-start noshadow">
                                @if($plan->is_featured)
                                    <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                @endif
                                @if($annualSave > 0)
                                    <div class="plans badge bg-success" style="top:48px">-{{ $annualSave }} %</div>
                                @endif
                                <div class="top-content bg-seccolorstyle topradius">
                                    <div class="title">{{ $plan->name }}</div>
                                    @if($plan->tagline)
                                        <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                    @endif
                                    <div class="price-content">
                                        <div class="price mergecolor">
                                            {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)->dividedBy(12, \RoundingMode::HALF_UP)) }}
                                            <span class="period mergecolor">/ {{ __('front.pricing.month') }}</span>
                                        </div>
                                        <div class="f-12 seccolor mt-1">
                                            {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }} / {{ __('front.pricing.year') }}
                                        </div>
                                    </div>
                                    <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">{{ __('front.pricing.order_now') }}</a>
                                </div>
                                <ul class="list-info bg-purple">
                                    @foreach($plan->resources ?? [] as $key => $value)
                                        <li>
                                            <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                                            <div>{{ __("front.resources.$key") }}<br><span>{{ $value }}</span></div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    @endforeach

                    {{-- Fallback: plans that are neither monthly nor annual --}}
                    @if($monthlyPlans->isEmpty() && $annualPlans->isEmpty())
                        @foreach($plans as $plan)
                            @if($plan->supportsCurrency($currency))
                            <div class="col-sm-12 col-md-6 col-lg-4">
                                <div class="wrapper price-container text-start noshadow">
                                    @if($plan->is_featured)
                                        <div class="plans badge feat bg-purple">{{ __('front.pricing.most_popular') }}</div>
                                    @endif
                                    <div class="top-content bg-seccolorstyle topradius">
                                        <div class="title">{{ $plan->name }}</div>
                                        @if($plan->tagline)
                                            <div class="fromer seccolor">{{ $plan->tagline }}</div>
                                        @endif
                                        <div class="price-content">
                                            <div class="price mergecolor">
                                                {{ \App\Domains\Shared\Support\MoneyFormatter::format($plan->priceFor($currency)) }}
                                                <span class="period mergecolor">/ {{ $plan->billing_cycle->label() }}</span>
                                            </div>
                                        </div>
                                        <a href="{{ route('front.order', $plan) }}" class="btn btn-default-yellow-fill">{{ __('front.pricing.order_now') }}</a>
                                    </div>
                                    <ul class="list-info bg-purple">
                                        @foreach($plan->resources ?? [] as $key => $value)
                                            <li>
                                                <i class="{{ config("resources.icons.$key", 'icon-drives') }}"></i>
                                                <div>{{ __("front.resources.$key") }}<br><span>{{ $value }}</span></div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    @endif
                </div>
                <p class="seccolor f-14 mt-4 text-center">{{ __('front.pricing.vat_note') }}</p>
            @else
                <p class="seccolor" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
            @endif
        </div>
    </section>

    {{-- ============ FEATURE HIGHLIGHTS ============ --}}
    <section id="scroll" class="history-section feat01 sec-normal bg-colorstyle">
        <div class="container">
            <div class="randomline">
                <div class="bigline"></div>
                <div class="smallline"></div>
            </div>
            <div class="sec-main sec-bg1 bg-colorstyle noshadow nopadding">
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-6">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Automatické denní zálohy</h2>
                            <p class="seccolor">Web, databáze i e-maily se každou noc automaticky zálohují na oddělené úložiště. Zálohy uchováváme 14 dní a obnova probíhá do 30 minut.</p>
                        </div>
                        <a href="{{ route('front.features.backups') }}" class="btn btn-default-yellow-fill mt-3">Detail záloh</a>
                    </div>
                    <div class="col-md-12 col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-diskette purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-12 col-lg-5 order-lg-1 mt-4 mt-lg-0 text-center">
                        <i class="icon-speed purple" style="font-size:100px;opacity:.8"></i>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 order-lg-2">
                        <div class="info-content">
                            <h2 class="fw-bold mb-3 mergecolor">Monitoring dostupnosti 24/7</h2>
                            <p class="seccolor">HTTP/HTTPS dostupnost ověřujeme každou minutu. Výpadek zachytíme dříve, než vám zavolá zákazník. SSL certifikáty hlídáme a automaticky obnovujeme.</p>
                        </div>
                        <a href="{{ route('front.features.monitoring') }}" class="btn btn-default-yellow-fill mt-3">Detail monitoringu</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ WHY ONHOST WEBHOSTING ============ --}}
    <section class="services sec-normal motpath sec-bg4">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 text-center">
                        <h2 class="section-heading">Proč zvolit webhosting OnHost?</h2>
                        <p class="section-subheading">Rychlá infrastruktura, bezpečnost a česká podpora v jednom balíčku.</p>
                    </div>
                    @foreach([
                        ['icon' => 'icon-speed',       'badge' => 'Softaculous', 'title' => 'WordPress na 1 klik',      'text' => 'Instalace WordPressu, Joomly a dalších CMS systémů za méně než minutu přímo z klientské zóny.'],
                        ['icon' => 'icon-lock',          'badge' => 'Free',        'title' => 'SSL certifikát zdarma',    'text' => "Let's Encrypt certifikáty pro každou doménu. HTTPS je aktivní automaticky a obnovuje se samo."],
                        ['icon' => 'icon-drives',       'badge' => 'NVMe',        'title' => 'Rychlé NVMe úložiště',    'text' => 'Lokální NVMe SSD pole s vysokým IOPS pro rychlé načítání PHP aplikací a databázových dotazů.'],
                        ['icon' => 'icon-protection',   'badge' => 'WAF',         'title' => 'Web Application Firewall','text' => 'ModSecurity pravidla, DDoS ochrana a automatická blokace podezřelých požadavků.'],
                        ['icon' => 'icon-cpu',         'badge' => 'PHP 8.3',     'title' => 'Nejnovější PHP',          'text' => 'Podpora PHP 8.3 s OPcache, ionCube loaderem a rozšířeními jako imagick, gd, redis a dalšími.'],
                        ['icon' => 'icon-support',      'badge' => 'CZ',          'title' => 'Podpora v češtině',       'text' => 'Ticketová podpora a AI asistent v češtině. Reakční doby: Urgentní do 1 h, Standardní do 1 prac. dne.'],
                    ] as $feature)
                        <div class="col-sm-12 col-md-4" data-aos="fade-up">
                            <div class="service-section bg-colorstyle">
                                <div class="plans badge feat bg-purple">{{ $feature['badge'] }}</div>
                                <i class="{{ $feature['icon'] }} f-30 purple mb-3 d-block"></i>
                                <div class="title mergecolor">{{ $feature['title'] }}</div>
                                <p class="subtitle seccolor">{{ $feature['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ PLANS COMPARISON TABLE ============ --}}
    <section class="sec-normal bg-colorstyle">
        <div class="best-plans pricing">
            <div class="container">
                <div class="randomline">
                    <div class="bigline"></div>
                    <div class="smallline"></div>
                </div>
                <div class="col-sm-12 mb-4 text-center">
                    <h2 class="section-heading mergecolor">Porovnání tarifů webhostingu</h2>
                    <p class="section-subheading mergecolor">Přehled všech funkcí a limitů na jednom místě.</p>
                </div>
                <div class="sec-main sec-bg1">
                    <div class="row">
                        <div class="col-sm-12">
                            <div id="table-container" class="table-responsive-lg">
                                <table id="maintable" class="table">
                                    <thead>
                                        <tr class="thead-clone">
                                            <th></th>
                                            <th>
                                                <div class="title">Start</div>
                                                <div class="price mergecolor"><sup>Kč</sup>49<span class="period">/měs.</span></div>
                                                <div class="info seccolor">Ideální začátek pro osobní web</div>
                                                <a href="{{ route('front.webhosting') }}#pricing" class="btn btn-default-yellow-fill">Objednat</a>
                                            </th>
                                            <th>
                                                <div class="plans badge feat bg-purple">nejoblíbenější</div>
                                                <div class="title">Business</div>
                                                <div class="price mergecolor"><sup>Kč</sup>99<span class="period">/měs.</span></div>
                                                <div class="info seccolor">Pro firmy a e-shopy</div>
                                                <a href="{{ route('front.webhosting') }}#pricing" class="btn btn-default-yellow-fill">Objednat</a>
                                            </th>
                                            <th>
                                                <div class="title">Agency</div>
                                                <div class="price mergecolor"><sup>Kč</sup>249<span class="period">/měs.</span></div>
                                                <div class="info seccolor">Pro agentury a náročné projekty</div>
                                                <a href="{{ route('front.webhosting') }}#pricing" class="btn btn-default-yellow-fill">Objednat</a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th><div class="title-table" data-bs-toggle="tooltip" data-bs-placement="top" title="Počet webů / domén na jednom tarifu">Domény</div></th>
                                            <td>1 doména</td>
                                            <td>5 domén</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">NVMe SSD úložiště</div></th>
                                            <td>10 GB</td>
                                            <td>30 GB</td>
                                            <td>100 GB</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">E-mailové schránky</div></th>
                                            <td>5</td>
                                            <td>50</td>
                                            <td>250</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">MySQL databáze</div></th>
                                            <td>5</td>
                                            <td>25</td>
                                            <td>Neomezeno</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table"><span class="badge bg-purple">Free</span> SSL certifikát</div></th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">Verze PHP</div></th>
                                            <td>8.0 – 8.3</td>
                                            <td>8.0 – 8.3</td>
                                            <td>8.0 – 8.3</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table"><span class="badge bg-purple">Auto</span> Denní zálohy</div></th>
                                            <td>7 dní</td>
                                            <td>14 dní</td>
                                            <td>14 dní</td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">Monitoring dostupnosti</div></th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">Softaculous (1-click install)</div></th>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">ModSecurity WAF</div></th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">Redis / Memcached</div></th>
                                            <td><i class="fas fa-times seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                            <td><i class="fas fa-check seccolor"></i></td>
                                        </tr>
                                        <tr>
                                            <th><div class="title-table">Podpora (CZ)</div></th>
                                            <td>Standardní</td>
                                            <td>Standardní</td>
                                            <td>Prioritní</td>
                                        </tr>
                                        <tr>
                                            <th class="border-0"><a href="{{ route('front.support') }}" class="btn btn-default-purple-fill">Máte otázky?</a></th>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="bottom_anchor"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ FAQ ============ --}}
    <section class="sec-normal sec-bg2 bg-colorstyle">
        <div class="faq">
            <div class="container">
                <div class="row">
                    <div class="col-md-12 col-sm-12 text-center">
                        <h2 class="section-heading mergecolor">Časté dotazy o webhostingu</h2>
                        <p class="section-subheading mergecolor">Nejčastější otázky zákazníků před objednávkou.</p>
                    </div>
                    <div class="col-sm-12">
                        <div class="accordion faq pt-5">
                            @foreach([
                                ['q' => 'Jak rychle bude hosting aktivní?',       'a' => 'Ihned po zaplacení — zřízení probíhá automaticky. Přihlašovací údaje dostanete e-mailem do pár minut.'],
                                ['q' => 'Mohu mít více domén na jednom účtu?',     'a' => 'Ano, hosting umožňuje přidání addon domén. Každá doména má vlastní adresář, databázi i SSL certifikát.'],
                                ['q' => 'Je SSL certifikát opravdu zdarma?',       'a' => "Ano. Let's Encrypt certifikát vystavujeme automaticky ke každé doméně a obnovujeme ho bez vašeho zásahu."],
                                ['q' => 'Mohu kdykoliv tarif upgradovat?',         'a' => 'Ano, změnu tarifu provedete v klientské zóně okamžitě. Přeplatek za aktuální měsíc se přičte jako kredit.'],
                                ['q' => 'Jaká je záruka dostupnosti (SLA)?',       'a' => 'Garantujeme 99.9% dostupnost. Záznamy monitoringu jsou podkladem pro SLA kompenzace.'],
                            ] as $i => $faq)
                                <div class="panel-wrap">
                                    <div class="panel-title seccolor {{ $i === 0 ? 'active' : '' }}">
                                        <span>{{ $faq['q'] }}</span>
                                        <div class="float-end">
                                            <i class="fa fa-plus"></i>
                                            <i class="fa fa-minus c-pink"></i>
                                        </div>
                                    </div>
                                    <div class="panel-collapse" @if($i === 0) style="display:block" @endif>
                                        <div class="wrapper-collapse">
                                            <div class="info">
                                                <ul class="list seccolor"><li><p>{{ $faq['a'] }}</p></li></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                            <a href="{{ route('front.vps') }}" class="help-item" title="Cloud VPS">
                                <div class="img"><i class="icon-cpu f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Cloud VPS</div>
                                    <div class="description seccolor">Plný root přístup, KVM virtualizace, NVMe SSD od 149 Kč/měs.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.managed') }}" class="help-item" title="Managed Hosting">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Managed Hosting</div>
                                    <div class="description seccolor">Hosting, o který se kompletně staráme my — aktualizace, zálohy, bezpečnost.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle noshadow">
                            <a href="{{ route('front.support') }}" class="help-item" title="Podpora">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Máte otázky?</div>
                                    <div class="description seccolor">Napište nám ticket nebo použijte AI asistenta — odpovíme do 1 prac. dne.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
