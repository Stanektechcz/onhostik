@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.billing_details'))

@section('title', __('panel.nav.billing_details'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.billing_details')" :subtitle="__('panel.account.tax_doc_note')">
            <form method="POST" action="{{ route('panel.account.billing.update') }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label class="form-label f-12 f-light" for="acc-type">{{ __('panel.account.type') }}</label>
                    <select id="acc-type" name="type" class="form-select">
                        <option value="person" @selected(old('type', $customer->type) === 'person')>{{ __('panel.account.person') }}</option>
                        <option value="company" @selected(old('type', $customer->type) === 'company')>{{ __('panel.account.company') }}</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label f-12 f-light" for="acc-company">{{ __('panel.account.company_name') }}</label>
                    <input id="acc-company" type="text" name="company_name" class="form-control" value="{{ old('company_name', $customer->company_name) }}">
                    @error('company_name')<div class="text-danger f-12">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label f-12 f-light" for="acc-ic">{{ __('panel.account.ic') }}</label>
                    <input id="acc-ic" type="text" name="registration_number" class="form-control" value="{{ old('registration_number', $customer->registration_number) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label f-12 f-light" for="acc-dic">{{ __('panel.account.dic') }}</label>
                    <input id="acc-dic" type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $customer->vat_number) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label f-12 f-light" for="acc-phone">{{ __('panel.account.phone') }}</label>
                    <input id="acc-phone" type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label f-12 f-light" for="acc-street">{{ __('panel.account.street') }}</label>
                    <input id="acc-street" type="text" name="street" class="form-control" value="{{ old('street', $address->street ?? '') }}" required>
                    @error('street')<div class="text-danger f-12">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label f-12 f-light" for="acc-city">{{ __('panel.account.city') }}</label>
                    <input id="acc-city" type="text" name="city" class="form-control" value="{{ old('city', $address->city ?? '') }}" required>
                    @error('city')<div class="text-danger f-12">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label f-12 f-light" for="acc-zip">{{ __('panel.account.zip') }}</label>
                    <input id="acc-zip" type="text" name="zip" class="form-control" value="{{ old('zip', $address->zip ?? '') }}" required>
                    @error('zip')<div class="text-danger f-12">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-1">
                    <label class="form-label f-12 f-light" for="acc-country">{{ __('panel.account.country') }}</label>
                    <input id="acc-country" type="text" name="country_code" class="form-control" maxlength="2" value="{{ old('country_code', $customer->country_code) }}" required>
                    @error('country_code')<div class="text-danger f-12">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('panel.account.save') }}</button>
                </div>
            </form>
        </x-panel.card>
    </div>
@endsection
