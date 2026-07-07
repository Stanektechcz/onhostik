@extends('layouts.panel')
@section('title', 'White-label: ' . $reseller->business_name)
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="White-label nastavení — {{ $reseller->business_name }}">
        <form method="POST" action="{{ route('admin.reseller-whitelabel.update', $reseller) }}">
            @csrf @method('PATCH')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Vlastní doména</label>
                    <input type="text" name="custom_domain" class="form-control" value="{{ old('custom_domain', $reseller->custom_domain) }}" placeholder="panel.reseller.cz">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Název panelu</label>
                    <input type="text" name="panel_title" class="form-control" value="{{ old('panel_title', $reseller->panel_title) }}" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Support e-mail</label>
                    <input type="email" name="support_email" class="form-control" value="{{ old('support_email', $reseller->support_email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Support telefon</label>
                    <input type="text" name="support_phone" class="form-control" value="{{ old('support_phone', $reseller->support_phone) }}" maxlength="30">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo URL</label>
                    <input type="text" name="branding[logo_url]" class="form-control" value="{{ old('branding.logo_url', $reseller->branding['logo_url'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Primární barva (#rrggbb)</label>
                    <input type="text" name="branding[primary_color]" class="form-control" value="{{ old('branding.primary_color', $reseller->branding['primary_color'] ?? '') }}" placeholder="#3B5998">
                    @error('branding.primary_color') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Uložit</button>
                <a href="{{ route('admin.reseller-whitelabel.index') }}" class="btn btn-outline-secondary ms-2">Zpět</a>
            </div>
        </form>
    </x-panel.card>
</div>
@endsection
