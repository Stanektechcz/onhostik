@extends('layouts.panel')
@section('title', 'Upravit fakturační adresu')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 justify-center">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Upravit fakturační adresu">
                <form method="POST" action="{{ route('panel.billing-addresses.update', $address) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Popisek adresy <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $address->label) }}" maxlength="80" required>
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Název společnosti</label>
                            <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $address->company_name) }}" maxlength="150">
                            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12">
                            <label class="form-label">Ulice a č.p. <span class="text-danger">*</span></label>
                            <input type="text" name="street" class="form-control @error('street') is-invalid @enderror" value="{{ old('street', $address->street) }}" maxlength="150" required>
                            @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">Město <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $address->city) }}" maxlength="80" required>
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="form-label">PSČ <span class="text-danger">*</span></label>
                            <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $address->postal_code) }}" maxlength="10" required>
                            @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="form-label">Kód země (2 znaky) <span class="text-danger">*</span></label>
                            <input type="text" name="country_code" class="form-control @error('country_code') is-invalid @enderror" value="{{ old('country_code', $address->country_code) }}" maxlength="2" required>
                            @error('country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label">DIČ</label>
                            <input type="text" name="vat_number" class="form-control @error('vat_number') is-invalid @enderror" value="{{ old('vat_number', $address->vat_number) }}" maxlength="30">
                            @error('vat_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <div class="form-check mt-4">
                                <input type="hidden" name="is_default" value="0">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default_edit" {{ old('is_default', $address->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_default_edit">Nastavit jako výchozí</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn btn-primary">Uložit změny</button>
                        <a href="{{ route('panel.billing-addresses.index') }}" class="btn btn-outline-secondary">Zpět</a>
                    </div>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
