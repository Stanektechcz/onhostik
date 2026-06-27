@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Výsledky hledání';
    $breadcrumbItems = ['Hledání' => ''];
@endphp

@section('title', 'Hledání')

@section('content')
<div class="container-fluid">
    <div class="container search-page">

        {{-- Search bar --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.search') }}">
                    <div class="input-group">
                        <span class="input-group-text"><i data-feather="search" style="width:16px;height:16px;"></i></span>
                        <input type="text" class="form-control form-control-lg" name="q"
                               value="{{ $query }}" placeholder="Hledat zákazníky, faktury, objednávky…" autofocus>
                        <button type="submit" class="btn btn-primary text-white">Hledat</button>
                    </div>
                </form>
            </div>
        </div>

        @if($query)
        <div class="grid grid-cols-12 card-gap">

            {{-- Results left --}}
            <div class="col-span-8 xl:col-span-12">

                {{-- Customers --}}
                @if($customers->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header card-no-border">
                        <h5>Zákazníci <span class="badge badge-light-primary ms-2">{{ $customers->count() }}</span></h5>
                    </div>
                    <div class="card-body pt-0">
                        @foreach($customers as $c)
                        <div class="info-block d-flex align-items-start gap-3 py-2 border-bottom">
                            <div>
                                <a href="{{ route('admin.customers.show', $c) }}" class="f-w-600">{{ $c->company_name ?? $c->user?->name }}</a>
                                <p class="f-light f-12 mb-0">{{ $c->email }}</p>
                                <p class="f-light f-11 mb-0">{{ url('/admin/zakaznici/' . $c->id) }}</p>
                            </div>
                            <a href="{{ route('admin.customers.show', $c) }}" class="btn btn-outline-primary btn-xs ms-auto">Otevřít</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Orders --}}
                @if($orders->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header card-no-border">
                        <h5>Objednávky <span class="badge badge-light-success ms-2">{{ $orders->count() }}</span></h5>
                    </div>
                    <div class="card-body pt-0">
                        @foreach($orders as $o)
                        <div class="info-block d-flex align-items-center gap-3 py-2 border-bottom">
                            <div class="flex-1">
                                <a href="{{ route('admin.orders.show', $o) }}" class="f-w-600">#{{ strtoupper(substr($o->uuid, 0, 8)) }}</a>
                                <span class="f-light f-12 ms-2">{{ $o->created_at?->format('d.m.Y') }}</span>
                            </div>
                            <x-panel.status-badge :status="$o->status" />
                            <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-outline-primary btn-xs">Detail</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Invoices --}}
                @if($invoices->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header card-no-border">
                        <h5>Faktury <span class="badge badge-light-warning ms-2">{{ $invoices->count() }}</span></h5>
                    </div>
                    <div class="card-body pt-0">
                        @foreach($invoices as $inv)
                        <div class="info-block d-flex align-items-center gap-3 py-2 border-bottom">
                            <div class="flex-1">
                                <a href="{{ route('admin.invoices.show', $inv) }}" class="f-w-600">{{ $inv->number }}</a>
                                <span class="f-light f-12 ms-2">{{ $inv->issue_date?->format('d.m.Y') }}</span>
                            </div>
                            <x-panel.status-badge :status="$inv->status" />
                            <a href="{{ route('admin.invoices.show', $inv) }}" class="btn btn-outline-primary btn-xs">Detail</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($customers->isEmpty() && $orders->isEmpty() && $invoices->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i data-feather="search" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                        <h5 class="f-light">Žádné výsledky pro „{{ $query }}"</h5>
                        <p class="f-light f-12">Zkuste jiné klíčové slovo nebo změňte hledaný výraz.</p>
                    </div>
                </div>
                @endif

            </div>

            {{-- Right sidebar --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border"><h5>Rychlý přístup</h5></div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            @foreach([
                                ['Zákazníci','admin.customers.index','users'],
                                ['Objednávky','admin.orders.index','shopping-cart'],
                                ['Faktury','admin.invoices.index','file-text'],
                                ['Produkty','admin.products.index','package'],
                                ['Servery','admin.servers.index','server'],
                                ['Podpora','admin.support.index','message-square'],
                            ] as [$label, $route, $icon])
                            <li class="py-2 border-bottom">
                                <a href="{{ route($route) }}" class="d-flex align-items-center gap-2 f-14">
                                    <i data-feather="{{ $icon }}" style="width:15px;height:15px;opacity:.5;"></i>
                                    {{ $label }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

        </div>
        @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="search" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                <h5 class="f-light">Zadejte hledaný výraz</h5>
                <p class="f-light f-12">Hledejte zákazníky, faktury, objednávky, produkty…</p>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
