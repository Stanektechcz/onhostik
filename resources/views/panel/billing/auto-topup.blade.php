@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Automatické dobití kreditu';
    $breadcrumbItems = ['Fakturace' => route('panel.billing.credits'), 'Auto-dobití' => ''];
@endphp

@section('title', 'Automatické dobití kreditu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Automatické dobití kreditu">
        <p class="f-light f-13 mb-4">
            Pokud zůstatek vašeho kreditu klesne pod zvolenou hranici, systém automaticky vystaví
            fakturu za dobití. Po jejím uhrazení bude kredit připsán na váš účet.
        </p>

        <form method="POST" action="{{ route('panel.billing.auto-topup.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled"
                               value="1" {{ $enabled ? 'checked' : '' }}>
                        <label class="form-check-label f-w-500" for="enabled">
                            Aktivovat automatické dobití
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label f-12 f-w-600" for="threshold_amount">
                        Spouštěcí hranice (Kč)
                    </label>
                    <div class="input-group">
                        <input type="number" id="threshold_amount" name="threshold_amount"
                               class="form-control @error('threshold_amount') is-invalid @enderror"
                               value="{{ old('threshold_amount', $threshold_amount) }}"
                               min="100" max="50000" step="1">
                        <span class="input-group-text">Kč</span>
                    </div>
                    <div class="f-light f-11 mt-1">Dobití se spustí, pokud kredit klesne pod tuto částku.</div>
                    @error('threshold_amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label f-12 f-w-600" for="topup_amount">
                        Částka dobití (Kč)
                    </label>
                    <div class="input-group">
                        <input type="number" id="topup_amount" name="topup_amount"
                               class="form-control @error('topup_amount') is-invalid @enderror"
                               value="{{ old('topup_amount', $topup_amount) }}"
                               min="100" max="50000" step="1">
                        <span class="input-group-text">Kč</span>
                    </div>
                    <div class="f-light f-11 mt-1">Výše faktury, která bude automaticky vystavena.</div>
                    @error('topup_amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="save" style="width:13px;height:13px;" class="me-1"></i>
                        Uložit nastavení
                    </button>
                    <a href="{{ route('panel.billing.credits') }}" class="btn btn-outline-secondary btn-sm ms-2">
                        Zpět na kredit
                    </a>
                </div>
            </div>
        </form>
    </x-panel.card>
</div>
@endsection
