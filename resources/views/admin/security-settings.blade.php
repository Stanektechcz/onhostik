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
                <div class="row g-3">
                    {{-- Require customer 2FA --}}
                    <div class="col-12">
                        <div class="card bg-light border-0">
                            <div class="card-body d-flex justify-content-between align-items-start gap-3">
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
                    <div class="col-12">
                        <div class="card bg-light border-0">
                            <div class="card-body d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h6 class="mb-1">Vyžadovat 2FA od administrátorů</h6>
                                    <p class="f-12 f-light mb-0">
                                        Administrátoři bez aktivního 2FA jsou blokováni ve všech admin sekcích.
                                        Toto nastavení je vždy aktivní a nelze jej vypnout.
                                    </p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="require_admin_2fa" disabled checked>
                                    <label class="form-check-label" for="require_admin_2fa">Vždy aktivní</label>
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
