@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Reseller Dashboard';
@endphp

@section('title', 'Reseller Dashboard')

@section('content')
<div class="container-fluid">

    {{-- Profile status banner --}}
    @if(($profile ?? null) === null)
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="briefcase" style="width:56px;height:56px;" class="text-muted mb-3"></i>
                <h5 class="mt-2">Reseller profil zatím není aktivní</h5>
                <p class="f-light f-14 mb-3 mx-auto" style="max-width:480px;">
                    Váš účet nemá přiřazený reseller profil. Kontaktujte administrátora pro aktivaci.
                </p>
                <a href="{{ route('panel.dashboard') }}" class="btn btn-outline-primary btn-sm me-2">
                    <i data-feather="arrow-left" style="width:13px;height:13px;"></i>
                    Zpět do panelu
                </a>
                <a href="{{ route('panel.support.index') }}" class="btn btn-primary btn-sm">
                    <i data-feather="message-square" style="width:13px;height:13px;"></i>
                    Kontaktovat podporu
                </a>
            </div>
        </div>

    @elseif($profile->status === 'pending')
        <div class="alert alert-light-warning d-flex align-items-start gap-3 mb-4">
            <i data-feather="clock" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;color:#e29d38;"></i>
            <div>
                <strong class="d-block">Žádost čeká na schválení</strong>
                <span class="f-light f-13">
                    Váš reseller profil byl zaregistrován a čeká na schválení administrátorem.
                    Jakmile bude schválen, získáte přístup ke všem funkcím resellera.
                    V případě dotazů nás kontaktujte na
                    <a href="mailto:info@onhost.cz">info@onhost.cz</a>.
                </span>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-no-border">
                <h5>Vaše žádost</h5>
            </div>
            <div class="card-body pt-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Obchodní jméno</span>
                        <strong>{{ $profile->business_name }}</strong>
                    </li>
                    @if($profile->custom_domain)
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Vlastní doména</span>
                        <strong>{{ $profile->custom_domain }}</strong>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Stav</span>
                        <span class="badge badge-light-warning">Čeká na schválení</span>
                    </li>
                </ul>
            </div>
        </div>

    @elseif($profile->status === 'suspended')
        <div class="alert alert-light-danger d-flex align-items-start gap-3 mb-4">
            <i data-feather="alert-triangle" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"></i>
            <div>
                <strong class="d-block">Reseller účet pozastaven</strong>
                <span class="f-light f-13">
                    Váš reseller profil byl dočasně pozastaven. Kontaktujte podporu pro obnovení přístupu.
                </span>
            </div>
        </div>

    @elseif($profile->status === 'rejected')
        <div class="alert alert-light-danger d-flex align-items-start gap-3 mb-4">
            <i data-feather="x-circle" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"></i>
            <div>
                <strong class="d-block">Žádost zamítnuta</strong>
                <span class="f-light f-13">
                    Vaše žádost o reseller program byla zamítnuta. Kontaktujte nás pro více informací.
                </span>
            </div>
        </div>

    @else
        {{-- ACTIVE reseller --}}

        {{-- KPI cards --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card o-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(30,215,96,.12);display:flex;align-items:center;justify-content:center;">
                                <i data-feather="users" style="width:22px;height:22px;color:#1ed760;"></i>
                            </div>
                            <div>
                                <p class="f-light f-12 mb-1">Zákazníci</p>
                                <h5 class="mb-0">{{ number_format($customerCount) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card o-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(var(--theme-default),.1);display:flex;align-items:center;justify-content:center;">
                                <i data-feather="shopping-cart" style="width:22px;height:22px;color:rgba(var(--theme-default),1);"></i>
                            </div>
                            <div>
                                <p class="f-light f-12 mb-1">Objednávky celkem</p>
                                <h5 class="mb-0">{{ number_format($orderCount) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card o-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(var(--theme-default),.1);display:flex;align-items:center;justify-content:center;">
                                <i data-feather="trending-up" style="width:22px;height:22px;color:rgba(var(--theme-default),1);"></i>
                            </div>
                            <div>
                                <p class="f-light f-12 mb-1">Celkové tržby</p>
                                <h5 class="mb-0">{{ number_format($revenueMinor / 100, 0, ',', ' ') }} Kč</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card o-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0" style="width:48px;height:48px;border-radius:12px;background:rgba(var(--theme-default),.1);display:flex;align-items:center;justify-content:center;">
                                <i data-feather="percent" style="width:22px;height:22px;color:rgba(var(--theme-default),1);"></i>
                            </div>
                            <div>
                                <p class="f-light f-12 mb-1">Markup</p>
                                <h5 class="mb-0">{{ number_format($profile->markup_percent, 1) }} %</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue chart + info --}}
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5>Tržby zákazníků — posledních 6 měsíců</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div id="resellerRevenueChart"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header card-no-border">
                        <h5>Reseller profil</h5>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="f-light">Obchodní jméno</span>
                                <strong>{{ $profile->business_name }}</strong>
                            </li>
                            @if($profile->custom_domain)
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="f-light">Vlastní doména</span>
                                <strong>{{ $profile->custom_domain }}</strong>
                            </li>
                            @endif
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="f-light">Markup</span>
                                <strong>{{ number_format($profile->markup_percent, 2) }} %</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="f-light">Dostupné produkty</span>
                                <span>
                                    @if($profile->allowed_products === null)
                                        <span class="badge badge-light-success">Všechny</span>
                                    @else
                                        <span class="badge badge-light-primary">{{ count($profile->allowed_products) }} produktů</span>
                                    @endif
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="f-light">Aktivní od</span>
                                <strong>{{ $profile->approved_at?->format('d. m. Y') ?? '—' }}</strong>
                            </li>
                        </ul>
                        <div class="mt-3 d-flex flex-column gap-2">
                            <a href="{{ route('panel.services.index') }}" class="btn btn-outline-primary btn-sm text-start">
                                <i data-feather="server" style="width:13px;height:13px;"></i> Moje služby
                            </a>
                            <a href="{{ route('panel.support.index') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i data-feather="message-square" style="width:13px;height:13px;"></i> Kontaktovat podporu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent customers table --}}
        @if(isset($recentCustomers) && $recentCustomers->isNotEmpty())
        <div class="card">
            <div class="card-header card-no-border">
                <h5>Nedávní zákazníci</h5>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Jméno / firma</th>
                                <th>Email</th>
                                <th>Typ</th>
                                <th>Registrace</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentCustomers as $customer)
                            <tr>
                                <td class="f-w-500">{{ $customer->company_name ?: $customer->user?->name }}</td>
                                <td class="f-light f-12">{{ $customer->email ?: $customer->user?->email }}</td>
                                <td>
                                    <span class="badge badge-light-{{ $customer->type === 'company' ? 'primary' : 'secondary' }} f-10">
                                        {{ $customer->type === 'company' ? 'Firma' : 'Fyzická osoba' }}
                                    </span>
                                </td>
                                <td class="f-light f-12">{{ $customer->created_at?->format('d. m. Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @push('scripts')
        <script>
        (function() {
            var labels  = @json($chartLabels);
            var revenue = @json($chartRevenue);
            var options = {
                series: [{ name: 'Tržby (Kč)', data: revenue }],
                chart: { type: 'bar', height: 240, toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } },
                dataLabels: { enabled: false },
                xaxis: { categories: labels, labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { formatter: function(v) { return v.toLocaleString('cs-CZ') + ' Kč'; } } },
                colors: ['rgba(var(--theme-default),1)'],
                tooltip: { y: { formatter: function(v) { return v.toLocaleString('cs-CZ') + ' Kč'; } } },
                grid: { borderColor: '#f1f1f1' },
            };
            new ApexCharts(document.getElementById('resellerRevenueChart'), options).render();
        })();
        </script>
        @endpush
    @endif

</div>
@endsection
