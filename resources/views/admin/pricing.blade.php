@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Ceník';
    $breadcrumbItems = ['Ceník' => ''];
@endphp

@section('title', 'Ceník')

@section('content')
<div class="container-fluid">
    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- Active pricing plans --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Aktuální tarify</h5>
                            <div class="card-header-right-icon">
                                <a href="{{ route('admin.products.index') }}" class="btn btn-primary btn-sm text-white">
                                    <i data-feather="settings" style="width:13px;height:13px;"></i> Správa produktů
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-12 card-gap">
                            @forelse($plans ?? [] as $plan)
                            @php
                                $price = $plan->priceFor('CZK');
                                $priceVal = $price ? intdiv($price->getMinorAmount()->toInt(), 100) : 0;
                            @endphp
                            <div class="col-span-3 xxl:col-span-6 md:col-span-12">
                                <div class="pricingtable text-center">
                                    <div class="pricingtable-header">
                                        <h6>{{ $plan->product?->name ?? '' }}</h6>
                                        <h4>{{ $plan->name }}</h4>
                                    </div>
                                    <div class="price-value">
                                        <span class="currency">Kč</span>
                                        <span class="amount">{{ number_format($priceVal, 0, ',', ' ') }}</span>
                                        <span class="duration">/{{ $plan->billing_cycle->label() }}</span>
                                    </div>
                                    <ul class="pricing-content">
                                        @foreach((array)($plan->resources ?? []) as $k => $v)
                                        <li>{{ ucfirst($k) }}: <strong>{{ $v }}</strong></li>
                                        @endforeach
                                        <li>Stav: <span class="badge badge-light-{{ $plan->is_active ? 'success' : 'danger' }}">{{ $plan->is_active ? 'Aktivní' : 'Neaktivní' }}</span></li>
                                    </ul>
                                    <div class="pricingtable-signup">
                                        <a class="btn btn-primary text-white"
                                           href="{{ $plan->product ? route('admin.products.edit', $plan->product) : '#' }}">Upravit</a>
                                    </div>
                                </div>
                            </div>
                            @empty
                            {{-- Fallback static pricing display --}}
                            @foreach([
                                ['Start','Webhosting','199','Kč','měsíc',['1 GB disk','5 e-mailů','1 doména','5 GB transfer'],'success'],
                                ['Pro','Webhosting','399','Kč','měsíc',['10 GB disk','Unlimited e-mailů','5 domén','Unlimited transfer'],'primary'],
                                ['Business','Webhosting','799','Kč','měsíc',['50 GB disk','Unlimited e-mailů','Unlimited domén','Prioritní podpora'],'warning'],
                                ['Enterprise','VPS','1 499','Kč','měsíc',['100 GB SSD','8 vCPU','16 GB RAM','Dedikovaná IP'],'danger'],
                            ] as [$name, $cat, $price, $cur, $dur, $features, $color])
                            <div class="col-span-3 xxl:col-span-6 md:col-span-12">
                                <div class="pricingtable text-center">
                                    <div class="pricingtable-header">
                                        <h6>{{ $cat }}</h6>
                                        <h4>{{ $name }}</h4>
                                    </div>
                                    <div class="price-value">
                                        <span class="currency">{{ $cur }}</span>
                                        <span class="amount">{{ $price }}</span>
                                        <span class="duration">/{{ $dur }}</span>
                                    </div>
                                    <ul class="pricing-content">
                                        @foreach($features as $f)
                                        <li>{{ $f }}</li>
                                        @endforeach
                                    </ul>
                                    <div class="pricingtable-signup">
                                        <a class="btn btn-{{ $color }} text-white"
                                           href="{{ route('admin.products.index') }}">Spravovat</a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Simple pricing table --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Srovnávací tabulka</h5></div>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-12 card-gap">
                            @foreach([
                                ['Start','199 Kč','měsíc',['1 GB disk','5 e-mailů','1 doména','Chat podpora']],
                                ['Pro','399 Kč','měsíc',['10 GB disk','Neomezené e-maily','5 domén','Prioritní podpora']],
                                ['Business','799 Kč','měsíc',['50 GB disk','Neomezené e-maily','Neomezené domény','Dedikovaná podpora']],
                            ] as [$name, $price, $per, $features])
                            <div class="col-span-4 xl:col-span-12">
                                <div class="card text-center pricing-simple mb-0">
                                    <div class="card-body">
                                        <h3>{{ $name }}</h3>
                                        <h1>{{ $price }}</h1>
                                        <h6 class="font-primary">za {{ $per }}</h6>
                                        <hr>
                                        @foreach($features as $f)
                                        <p>{{ $f }}</p>
                                        @endforeach
                                        <hr>
                                        <a class="btn btn-primary btn-lg text-white"
                                           href="{{ route('admin.products.index') }}">Spravovat tarif</a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
