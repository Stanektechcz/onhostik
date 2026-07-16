@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Mapa webu';
    $breadcrumbItems = ['Mapa webu' => ''];
@endphp

@section('title', 'Mapa webu')

@section('content')
<div class="container-fluid">
    <div class="container sitemap-wrapper">
        <div class="grid grid-cols-12 card-gap">

            {{-- Default sitemap --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Mapa systému — přehled stránek</h5></div>
                    </div>
                    <div class="card-body">
                        <div class="default-sitemap">
                            <div class="grid grid-cols-12 gap-3">
                                @foreach([
                                    ['Zákaznický panel', 'primary', [
                                        ['Dashboard',route('panel.dashboard')],
                                        ['Objednávky',route('panel.orders.index')],
                                        ['Nová objednávka',route('panel.orders.create')],
                                        ['Služby',route('panel.services.index')],
                                        ['Domény',route('panel.domains.index')],
                                    ]],
                                    ['Fakturace', 'success', [
                                        ['Faktury',route('panel.billing.invoices')],
                                        ['Platby',route('panel.billing.payments')],
                                        ['Kredit',route('panel.billing.credits')],
                                        ['Košík',route('panel.cart.index')],
                                        ['Pokladna',route('panel.checkout.index')],
                                    ]],
                                    ['Obsah', 'warning', [
                                        ['FAQ',route('panel.faq.index')],
                                        ['Blog',route('panel.blog.index')],
                                        ['Znalostní báze',route('panel.kb.index')],
                                        ['Oblíbené',route('panel.wishlist.index')],
                                    ]],
                                    ['Účet', 'info', [
                                        ['Profil',route('panel.account.profile')],
                                        ['Fakturační údaje',route('panel.account.billing')],
                                        ['Zabezpečení',route('admin.account.security')],
                                        ['Podpora',route('panel.support.index')],
                                        ['AI asistent',route('panel.ai.index')],
                                    ]],
                                    ['Admin', 'danger', [
                                        ['Dashboard',route('admin.dashboard')],
                                        ['Zákazníci',route('admin.customers.index')],
                                        ['Objednávky',route('admin.orders.index')],
                                        ['Faktury',route('admin.invoices.index')],
                                        ['Produkty',route('admin.products.index')],
                                    ]],
                                    ['Systém', 'secondary', [
                                        ['Servery',route('admin.servers.index')],
                                        ['Integrace',route('admin.integrations.index')],
                                        ['Nastavení',route('admin.settings.index')],
                                        ['Audit log',route('admin.logs.audit')],
                                        ['Systém',route('admin.system.index')],
                                    ]],
                                ] as [$section, $color, $links])
                                <div class="col-span-2 xl:col-span-4 sm:col-span-6">
                                    <h6 class="mb-2"><span class="badge badge-{{ $color }} text-white">{{ $section }}</span></h6>
                                    <ul class="common-flex flex-column gap-1 list-unstyled">
                                        @foreach($links as [$label, $url])
                                        <li><a href="{{ $url }}" class="f-12 f-light">→ {{ $label }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tree sitemap --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Stromová struktura</h5></div>
                    </div>
                    <div class="card-body overflow-x-auto custom-scrollbar">
                        <div class="horizontal-sitemap">
                            <ul class="main-site list-unstyled">
                                <li>
                                    <div class="main-root text-center p-3 border rounded mb-3">
                                        <strong>onhost.cz</strong>
                                    </div>
                                    <ul class="tier-1 d-flex flex-wrap gap-3 list-unstyled">
                                        @foreach([
                                            ['/ (Domovská stránka)', ['/login','/register','/blog','/faq','/kontakt']],
                                            ['/panel (Zákaznický panel)', ['/panel/objednavky','/panel/sluzby','/panel/domeny','/panel/fakturace','/panel/faq']],
                                            ['/admin (Administrace)', ['/admin/zakaznici','/admin/objednavky','/admin/faktury','/admin/produkty','/admin/servery']],
                                            ['/partner (Partner portál)', ['/partner/referraly','/partner/provize','/partner/vyplaty','/partner/materialy']],
                                        ] as [$root, $children])
                                        <li class="tier-1-item" style="flex:1;min-width:200px;">
                                            <div class="p-2 border rounded bg-primary text-white text-center f-12 mb-2">{{ $root }}</div>
                                            @foreach($children as $child)
                                            <div class="p-1 border rounded f-11 f-light mb-1 ms-2">{{ $child }}</div>
                                            @endforeach
                                        </li>
                                        @endforeach
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
