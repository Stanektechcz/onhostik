@extends('layouts.panel')

@php
    $breadcrumbTitle = $reseller->business_name ?? 'Reseller';
    $breadcrumbItems = ['Reseller program' => route('admin.resellers.index'), $breadcrumbTitle => ''];

    $badgeMap = [
        'pending'   => 'badge badge-light-warning txt-warning',
        'active'    => 'badge badge-light-success txt-success',
        'suspended' => 'badge badge-light-danger txt-danger',
        'rejected'  => 'badge badge-light-secondary txt-secondary',
    ];
    $badgeClass = $badgeMap[$reseller->status] ?? 'badge badge-light-secondary';
@endphp

@section('title', 'Reseller: ' . $breadcrumbTitle)

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    @if($errors->any())
        <div class="alert alert-light-danger mb-3">
            @foreach($errors->all() as $err)<p class="mb-0 f-12">{{ $err }}</p>@endforeach
        </div>
    @endif

    {{-- Action bar --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="{{ $badgeClass }}">{{ ucfirst($reseller->status) }}</span>

        @if($reseller->status === 'pending' || $reseller->status === 'suspended' || $reseller->status === 'rejected')
            <form method="POST" action="{{ route('admin.resellers.approve', $reseller) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i data-feather="check" style="width:13px;height:13px;"></i> Schválit
                </button>
            </form>
        @endif

        @if($reseller->status === 'pending' || $reseller->status === 'active')
            <form method="POST" action="{{ route('admin.resellers.reject', $reseller) }}" class="d-inline"
                  onsubmit="return confirm('Opravdu zamítnout resellera?')">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i data-feather="x" style="width:13px;height:13px;"></i> Zamítnout
                </button>
            </form>
        @endif

        @if($reseller->status === 'active')
            <form method="POST" action="{{ route('admin.resellers.suspend', $reseller) }}" class="d-inline"
                  onsubmit="return confirm('Opravdu pozastavit resellera?')">
                @csrf
                <button type="submit" class="btn btn-outline-warning btn-sm">
                    <i data-feather="pause" style="width:13px;height:13px;"></i> Pozastavit
                </button>
            </form>
            <form method="POST" action="{{ route('admin.resellers.revoke', $reseller) }}" class="d-inline"
                  onsubmit="return confirm('Opravdu odebrat resellerovi přístup? Uživatel ztratí reseller oprávnění.')">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i data-feather="shield-off" style="width:13px;height:13px;"></i> Odebrat přístup
                </button>
            </form>
        @endif

        <a href="{{ route('admin.resellers.index') }}" class="btn btn-light btn-sm ms-auto">
            <i data-feather="arrow-left" style="width:13px;height:13px;"></i> Zpět na seznam
        </a>
    </div>

    <div class="grid grid-cols-12 card-gap">

        {{-- Left column: info + markup form --}}
        <div class="col-span-12 xl:col-span-4">

            {{-- Info card --}}
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Profil resellera</h5></div>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="f-light f-12 ps-0" style="width:45%">Firma</td>
                                <td class="f-w-500">{{ $reseller->business_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Uživatel</td>
                                <td class="f-w-500">{{ $reseller->user?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">E-mail</td>
                                <td>{{ $reseller->user?->email ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Vlastní doména</td>
                                <td class="f-12">{{ $reseller->custom_domain ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Markup</td>
                                <td class="f-w-600">{{ number_format((float) $reseller->markup_percent, 2) }}%</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Stav</td>
                                <td><span class="{{ $badgeClass }}">{{ ucfirst($reseller->status) }}</span></td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Schváleno</td>
                                <td class="f-12">{{ $reseller->approved_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Registrace</td>
                                <td class="f-12">{{ $reseller->created_at?->format('d.m.Y') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Markup update form --}}
            <div class="card mt-3">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Upravit markup</h5></div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.resellers.markup', $reseller) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Markup % <span class="txt-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="markup_percent" step="0.01" min="0" max="100"
                                       class="form-control @error('markup_percent') is-invalid @enderror"
                                       value="{{ old('markup_percent', number_format((float) $reseller->markup_percent, 2)) }}">
                                <span class="input-group-text">%</span>
                                @error('markup_percent')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="f-light f-12">Povolený rozsah: 0 – 100</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Uložit markup</button>
                    </form>
                </div>
            </div>

        </div>

        {{-- Right column: JSON branding + allowed_products --}}
        <div class="col-span-12 xl:col-span-8">

            <x-panel.card title="Branding (JSON)">
                @if(empty($reseller->branding))
                    <p class="f-light f-12 mb-0">Žádná branding data.</p>
                @else
                    <pre class="mb-0" style="background:#f8f8f8;border-radius:6px;padding:12px;font-size:12px;overflow-x:auto;">{{ json_encode($reseller->branding, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </x-panel.card>

            <x-panel.card title="Povolené produkty (JSON)" class="mt-3">
                @if(is_null($reseller->allowed_products))
                    <p class="f-light f-12 mb-0">
                        <span class="badge badge-light-success txt-success">Všechny produkty</span>
                        — <code>null</code> = přístup ke všem produktům
                    </p>
                @elseif(count($reseller->allowed_products) === 0)
                    <p class="f-light f-12 mb-0">Prázdný seznam — žádné produkty nejsou povoleny.</p>
                @else
                    <pre class="mb-0" style="background:#f8f8f8;border-radius:6px;padding:12px;font-size:12px;overflow-x:auto;">{{ json_encode($reseller->allowed_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </x-panel.card>

        </div>
    </div>
</div>
@endsection
