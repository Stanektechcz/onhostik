@extends('layouts.panel')

@section('title', 'PIN správa — ' . $service->name)

@section('content')
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-6">
        <x-panel.card title="Stav PIN — {{ $service->name }}">
            <x-panel.flash />

            @if ($pin === null)
                <div class="alert alert-warning mb-0">
                    PIN není nastaven.
                </div>
            @else
                <dl class="grid grid-cols-12 mb-0">
                    <dt class="col-span-12 sm:col-span-4">Stav</dt>
                    <dd class="col-span-12 sm:col-span-8"><span class="badge bg-success">PIN nastaven</span></dd>

                    <dt class="col-span-12 sm:col-span-4">Nápověda</dt>
                    <dd class="col-span-12 sm:col-span-8">{{ $pin->hint ?? '—' }}</dd>

                    <dt class="col-span-12 sm:col-span-4">Nastaveno</dt>
                    <dd class="col-span-12 sm:col-span-8">
                        {{ $pin->set_at ? \Carbon\Carbon::parse($pin->set_at)->format('d.m.Y H:i') : '—' }}
                    </dd>
                </dl>
            @endif
        </x-panel.card>
    </div>

    <div class="col-span-12 lg:col-span-6">
        <x-panel.card title="{{ $pin ? 'Změnit PIN' : 'Nastavit PIN' }}">
            <form method="POST" action="{{ route('panel.service-pins.store', $service) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Nový PIN <span class="text-danger">*</span></label>
                    <input type="password" name="pin"
                        class="form-control @error('pin') is-invalid @enderror"
                        minlength="4" maxlength="8" autocomplete="new-password" required>
                    @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">4–8 číslic.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Potvrzení PIN <span class="text-danger">*</span></label>
                    <input type="password" name="pin_confirmation"
                        class="form-control @error('pin_confirmation') is-invalid @enderror"
                        minlength="4" maxlength="8" autocomplete="new-password" required>
                    @error('pin_confirmation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Nápověda k PIN</label>
                    <input type="text" name="hint"
                        class="form-control @error('hint') is-invalid @enderror"
                        value="{{ old('hint', $pin?->hint) }}" maxlength="100"
                        placeholder="Nepovinná nápověda (nezobrazuje PIN)">
                    @error('hint') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    {{ $pin ? 'Aktualizovat PIN' : 'Nastavit PIN' }}
                </button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
