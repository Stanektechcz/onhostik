@extends('layouts.panel')

@php
    $breadcrumbTitle = 'White-label branding';
    $breadcrumbItems = ['Reseller' => route('reseller.dashboard'), 'Branding' => ''];
    $branding = $profile->branding ?? [];
@endphp

@section('title', 'White-label branding')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Branding form --}}
        <div class="col-span-7 xl:col-span-12">
            <x-panel.card title="Nastavení brandingu">
                <form method="POST" action="{{ route('reseller.branding.update') }}" class="custom-input">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Název společnosti</label>
                        <input class="form-control @error('company_name') is-invalid @enderror"
                               type="text" name="company_name"
                               value="{{ old('company_name', $branding['company_name'] ?? '') }}"
                               maxlength="150" placeholder="{{ $profile->business_name }}">
                        @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slogan / tagline</label>
                        <input class="form-control @error('tagline') is-invalid @enderror"
                               type="text" name="tagline"
                               value="{{ old('tagline', $branding['tagline'] ?? '') }}"
                               maxlength="255" placeholder="Špičkový hosting za vaši cenu">
                        @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL loga</label>
                        <input class="form-control @error('logo_url') is-invalid @enderror"
                               type="url" name="logo_url"
                               value="{{ old('logo_url', $branding['logo_url'] ?? '') }}"
                               maxlength="500" placeholder="https://mujhosting.cz/logo.svg">
                        <div class="f-11 f-light mt-1">Doporučeno: SVG nebo PNG, max. šířka 240 px</div>
                        @error('logo_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Primární barva</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="primary_color"
                                   value="{{ old('primary_color', $branding['primary_color'] ?? '#4B6EF5') }}"
                                   style="width:48px;height:36px;padding:2px;border-radius:4px;">
                            <input class="form-control @error('primary_color') is-invalid @enderror"
                                   type="text" name="primary_color_text"
                                   value="{{ old('primary_color', $branding['primary_color'] ?? '#4B6EF5') }}"
                                   placeholder="#4B6EF5" maxlength="7" style="max-width:120px;"
                                   oninput="this.previousElementSibling.value=this.value">
                        </div>
                        @error('primary_color')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vlastní doména</label>
                        <input class="form-control bg-light" type="text"
                               value="{{ $profile->custom_domain ?? 'Nenastavena' }}" disabled>
                        <div class="f-11 f-light mt-1">Vlastní doménu nastavuje administrátor.</div>
                    </div>

                    <button type="submit" class="btn btn-primary text-white">
                        <i data-feather="save" style="width:14px;height:14px"></i> Uložit branding
                    </button>
                </form>
            </x-panel.card>
        </div>

        {{-- Preview --}}
        <div class="col-span-5 xl:col-span-12">
            <x-panel.card title="Náhled">
                <div class="rounded border p-3 bg-light text-center">
                    @if(!empty($branding['logo_url']))
                        <img src="{{ $branding['logo_url'] }}" alt="Logo" class="max-w-full mb-2" style="max-height:60px;">
                    @else
                        <div class="f-w-700 f-20 mb-1" style="color:{{ $branding['primary_color'] ?? '#4B6EF5' }}">
                            {{ $branding['company_name'] ?? $profile->business_name }}
                        </div>
                    @endif
                    @if(!empty($branding['tagline']))
                        <p class="f-light f-13 mb-0">{{ $branding['tagline'] }}</p>
                    @endif
                    <div class="mt-3">
                        <span class="badge" style="background-color:{{ $branding['primary_color'] ?? '#4B6EF5' }}">
                            Primární barva
                        </span>
                    </div>
                </div>

                <div class="mt-3">
                    <p class="f-12 f-light mb-1">Branding se zobrazí zákazníkům, kteří přistupují přes vaši vlastní doménu.</p>
                    @if($profile->custom_domain)
                        <a href="https://{{ $profile->custom_domain }}" target="_blank" rel="noopener" class="f-12">
                            <i data-feather="external-link" style="width:11px;height:11px"></i>
                            {{ $profile->custom_domain }}
                        </a>
                    @endif
                </div>
            </x-panel.card>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const colorPicker = document.querySelector('input[type="color"]');
    const colorText = document.querySelector('input[name="primary_color_text"]');
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', () => { colorText.value = colorPicker.value; });
    }
});
</script>
@endsection
