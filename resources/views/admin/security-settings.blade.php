@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nastavení zabezpečení';
    $breadcrumbItems = ['Nastavení' => route('admin.settings.index'), 'Zabezpečení' => ''];
@endphp

@section('title', 'Nastavení zabezpečení')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <form method="POST" action="{{ route('admin.security-settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header card-no-border">
                <h5>Dvoufázové ověření (2FA)</h5>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-12 gap-3">
                    {{-- Require customer 2FA --}}
                    <div class="col-span-12">
                        <div class="card bg-light border-0">
                            <div class="card-body flex justify-between items-start gap-3">
                                <div>
                                    <h6 class="mb-1">Vyžadovat 2FA od zákazníků</h6>
                                    <p class="f-12 f-light mb-0">
                                        Zákazníci bez aktivního 2FA budou přesměrováni na stránku nastavení zabezpečení.
                                        Dokud 2FA nenastaví, nebudou moci využívat portál.
                                    </p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="require_customer_2fa" name="require_customer_2fa" value="1"
                                           {{ ($stored['require_customer_2fa'] ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="require_customer_2fa">Aktivní</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Require admin 2FA (informational - enforced by RequireAdminTwoFactor middleware) --}}
                    <div class="col-span-12">
                        <div class="card bg-light border-0">
                            <div class="card-body flex justify-between items-start gap-3">
                                <div>
                                    <h6 class="mb-1">Vyžadovat 2FA od administrátorů</h6>
                                    <p class="f-12 f-light mb-0">
                                        Když je zapnuto, administrátoři bez aktivního 2FA jsou přesměrováni
                                        na tuto stránku, dokud si dvoufázové ověření nenastaví. Doporučeno
                                        zapnout až poté, co má hlavní administrátor 2FA nastavené.
                                    </p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="require_admin_2fa" name="require_admin_2fa" value="1"
                                           {{ ($stored['require_admin_2fa'] ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="require_admin_2fa">Aktivní</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Uložit nastavení</button>
            </div>
        </div>
    </form>
</div>
@endsection
