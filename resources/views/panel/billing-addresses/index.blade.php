@extends('layouts.panel')
@section('title', 'Fakturační adresy')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Fakturační adresy">
        @if($addresses->isEmpty())
            <p class="text-muted">Zatím nemáte žádné fakturační adresy.</p>
        @else
            <div class="grid grid-cols-12 gap-3 mb-4">
                @foreach($addresses as $address)
                <div class="col-span-12 md:col-span-6">
                    <div class="border rounded p-3 h-full">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <strong>{{ $address->label }}</strong>
                                @if($address->is_default)
                                    <span class="badge bg-primary ms-2">Výchozí</span>
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <a href="{{ route('panel.billing-addresses.edit', $address) }}" class="btn btn-sm btn-outline-secondary">Upravit</a>
                                <form method="POST" action="{{ route('panel.billing-addresses.destroy', $address) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Opravdu smazat adresu?">Smazat</button>
                                </form>
                            </div>
                        </div>
                        @if($address->company_name)
                            <div>{{ $address->company_name }}</div>
                        @endif
                        <div>{{ $address->street }}</div>
                        <div>{{ $address->postal_code }} {{ $address->city }}</div>
                        <div>{{ strtoupper($address->country_code) }}</div>
                        @if($address->vat_number)
                            <div class="text-muted small mt-1">DIČ: {{ $address->vat_number }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @endif

        <div>
            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addAddressForm">
                + Přidat novou adresu
            </button>
            <div class="collapse" id="addAddressForm">
                <div class="border rounded p-3">
                    <h6 class="mb-3">Nová fakturační adresa</h6>
                    <form method="POST" action="{{ route('panel.billing-addresses.store') }}">
                        @csrf
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-6">
                                <label class="form-label">Popisek adresy <span class="text-danger">*</span></label>
                                <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label') }}" maxlength="80" required>
                                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="form-label">Název společnosti</label>
                                <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name') }}" maxlength="150">
                                @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12">
                                <label class="form-label">Ulice a č.p. <span class="text-danger">*</span></label>
                                <input type="text" name="street" class="form-control @error('street') is-invalid @enderror" value="{{ old('street') }}" maxlength="150" required>
                                @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="form-label">Město <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" maxlength="80" required>
                                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <label class="form-label">PSČ <span class="text-danger">*</span></label>
                                <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code') }}" maxlength="10" required>
                                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <label class="form-label">Kód země (2 znaky) <span class="text-danger">*</span></label>
                                <input type="text" name="country_code" class="form-control @error('country_code') is-invalid @enderror" value="{{ old('country_code', 'CZ') }}" maxlength="2" required>
                                @error('country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="form-label">DIČ</label>
                                <input type="text" name="vat_number" class="form-control @error('vat_number') is-invalid @enderror" value="{{ old('vat_number') }}" maxlength="30">
                                @error('vat_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <div class="form-check mt-4">
                                    <input type="hidden" name="is_default" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default_new" {{ old('is_default') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_default_new">Nastavit jako výchozí</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Uložit adresu</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-panel.card>
</div>
@endsection
