@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Marketplace';
    $breadcrumbItems = [
        __('panel.nav.services') => route('panel.services.index'),
        $service->label          => route('panel.services.show', $service),
        'Marketplace'            => '',
    ];
@endphp

@section('title', 'Marketplace — ' . $service->label)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">

        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('panel.services.show', $service) }}" class="btn btn-outline-secondary btn-sm">
                <svg data-feather="arrow-left" style="width:13px;height:13px" class="me-1"></svg>
                {{ $service->label }}
            </a>
            <h5 class="mb-0">One-Click Marketplace</h5>
            @if (config('provisioning.mock_mode', true))
                <span class="badge bg-warning text-dark f-11">Mock mode</span>
            @endif
        </div>

        @php
            $grouped = $apps->groupBy('category');
            $categoryLabels = ['cms' => 'CMS', 'ecommerce' => 'E-shop', 'database' => 'Databáze', 'email' => 'E-mail', 'other' => 'Ostatní'];
        @endphp

        @foreach ($grouped as $category => $categoryApps)
        <h6 class="text-muted f-12 uppercase mb-3 mt-4">{{ $categoryLabels[$category] ?? $category }}</h6>
        <div class="grid grid-cols-12 gap-3 mb-2">
            @foreach ($categoryApps as $app)
            @php
                $status   = $installed[$app->id] ?? null;
                $isActive = in_array($status, ['installed', 'installing', 'pending']);
            @endphp
            <div class="col-span-12 md:col-span-4 col-span-12 lg:col-span-3">
                <div class="card h-full @if($isActive) border-success @endif">
                    <div class="card-body flex flex-col gap-2 p-3">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded bg-light-primary">
                                <svg data-feather="{{ $app->icon }}" style="width:20px;height:20px" class="font-primary"></svg>
                            </div>
                            <div>
                                <p class="f-w-600 mb-0">{{ $app->name }}</p>
                                <span class="badge badge-light-secondary f-10">{{ $app->categoryLabel() }}</span>
                            </div>
                        </div>
                        @if ($app->description)
                            <p class="text-muted f-12 mb-0 grow">{{ $app->description }}</p>
                        @endif
                        <p class="text-muted f-11 mb-0">Min. disk: {{ $app->min_disk_gb }} GB</p>

                        @if ($app->isPaid())
                            <p class="f-w-600 f-12 mb-0 text-primary">
                                {{ number_format($app->price_halere / 100, 0, ',', ' ') }} Kč jednorázově
                            </p>
                        @else
                            <span class="badge badge-light-success f-10">Zdarma</span>
                        @endif

                        @if ($app->docs_url)
                            <a href="{{ $app->docs_url }}" target="_blank" rel="noopener noreferrer"
                               class="f-11 font-primary">Dokumentace</a>
                        @endif

                        @if ($isActive)
                            {{-- The badge reports the real state: an install that only ran as
                                 a dry run has not put anything on the server yet. --}}
                            @if ($status === 'installed')
                                <span class="badge badge-light-success f-11">
                                    <svg data-feather="check-circle" style="width:11px;height:11px" class="me-1"></svg>
                                    Nainstalováno
                                </span>
                            @else
                                <span class="badge badge-light-warning f-11">
                                    <svg data-feather="clock" style="width:11px;height:11px" class="me-1"></svg>
                                    Čeká na dokončení
                                </span>
                            @endif
                            <form method="POST" action="{{ route('panel.marketplace.remove', [$service, $app]) }}"
                                  data-confirm="Odinstalovat {{ $app->name }}? Soubory aplikace budou smazány.">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-xs w-full">
                                    Odinstalovat
                                </button>
                            </form>
                        @elseif (! $app->isInstallable())
                            <span class="badge badge-light-secondary f-11">Připravujeme</span>
                        @else
                            <form method="POST" action="{{ route('panel.marketplace.install', [$service, $app]) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm w-full">
                                    <svg data-feather="download" style="width:13px;height:13px" class="me-1"></svg>
                                    @if ($app->isPaid())
                                        Koupit a instalovat
                                    @else
                                        Instalovat
                                    @endif
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach

    </div>
</div>
@endsection
