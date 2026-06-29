@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.security');
    $breadcrumbItems = [__('panel.nav.account') => '#', __('panel.nav.security') => ''];
@endphp

@section('title', __('panel.nav.security'))

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- ── Left column ────────────────────────────────────── --}}
            <div class="col-span-6 xl:col-span-12">

                {{-- Password change --}}
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Změna hesla</h5>
                        </div>
                    </div>
                    <div class="card-body custom-input">
                        <form method="POST" action="{{ route('panel.account.password.update') }}">
                            @csrf @method('PUT')
                            <div class="mb-3">
                                <label class="form-label">Současné heslo *</label>
                                <div class="form-input position-relative">
                                    <input class="form-control @error('current_password') is-invalid @enderror"
                                           type="password" name="current_password" placeholder="Zadejte současné heslo" required>
                                    <div class="show-hide"><span class="show"></span></div>
                                </div>
                                @error('current_password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nové heslo * <small class="f-light">(min. 8 znaků)</small></label>
                                <div class="form-input position-relative">
                                    <input class="form-control @error('password') is-invalid @enderror"
                                           type="password" name="password" placeholder="Zadejte nové heslo" required minlength="8">
                                    <div class="show-hide"><span class="show"></span></div>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Potvrzení hesla *</label>
                                <div class="form-input position-relative">
                                    <input class="form-control" type="password" name="password_confirmation"
                                           placeholder="Zopakujte nové heslo" required>
                                    <div class="show-hide"><span class="show"></span></div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary text-white">
                                <i data-feather="key" style="width:14px;height:14px;"></i>
                                Změnit heslo
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ── Two-Factor Authentication ────────────────── --}}
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Dvoufázové ověřování (2FA)</h5>
                            <div class="card-header-right-icon">
                                @if($twoFactorEnabled && $twoFactorConfirmed)
                                    <span class="badge badge-light-success">
                                        <i data-feather="shield" style="width:12px;height:12px;"></i> Aktivní
                                    </span>
                                @elseif($hasTwoFactorSecret && !$twoFactorConfirmed)
                                    <span class="badge badge-light-warning">Čeká na potvrzení kódu</span>
                                @else
                                    <span class="badge badge-light-secondary">Neaktivní</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="f-light f-13 mb-3">
                            Přidejte další vrstvu zabezpečení. Po přihlášení bude vyžadován kód z autentifikační aplikace
                            (Google Authenticator, Authy, Microsoft Authenticator).
                        </p>

                        @if(!$hasTwoFactorSecret)
                        {{-- NOT STARTED: Show enable button --}}
                        <div class="d-flex align-items-start gap-3 p-3 border rounded mb-3" style="background:rgba(var(--light-background),.4);">
                            <i data-feather="shield-off" style="width:32px;height:32px;opacity:.4;flex-shrink:0;"></i>
                            <div>
                                <h6 class="mb-1">2FA není zapnuto</h6>
                                <p class="f-light f-12 mb-0">Váš účet není chráněn dvoufázovým ověřením. Doporučujeme aktivovat.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary text-white">
                                <i data-feather="shield" style="width:14px;height:14px;"></i>
                                Zapnout 2FA
                            </button>
                        </form>

                        @elseif($hasTwoFactorSecret && !$twoFactorConfirmed)
                        {{-- SETUP IN PROGRESS: secret exists, not confirmed yet — show QR --}}
                        <div class="alert alert-light-warning mb-3">
                            <i data-feather="alert-triangle" style="width:14px;height:14px;"></i>
                            Naskenujte QR kód a zadejte kód pro potvrzení.
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-5 xl:col-span-12 text-center">
                                <div class="border rounded p-3 d-inline-block bg-white" id="qr-code-container">
                                    <div id="qr-loading" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <p class="f-light f-12 mt-2 mb-0">Načítám QR kód…</p>
                                    </div>
                                </div>
                                <p class="f-light f-11 mt-2 mb-0">Naskenujte v autentifikační aplikaci</p>
                            </div>
                            <div class="col-span-7 xl:col-span-12">
                                {{-- Confirm TOTP code --}}
                                <form id="confirm-2fa-form" method="POST"
                                      action="{{ url('/user/confirmed-two-factor-authentication') }}"
                                      class="custom-input">
                                    @csrf
                                    <label class="form-label">Ověřovací kód z aplikace *</label>
                                    <input class="form-control mb-3" type="text" name="code"
                                           inputmode="numeric" autocomplete="one-time-code"
                                           pattern="[0-9]{6}" maxlength="6"
                                           placeholder="000000" required autofocus>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success text-white">
                                            <i data-feather="check" style="width:14px;height:14px;"></i>
                                            Potvrdit 2FA
                                        </button>
                                        {{-- Cannot nest <form> inside <form> — use a separate form below --}}
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="document.getElementById('cancel-2fa-form').submit()">
                                            Zrušit nastavení
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        {{-- Cancel 2FA setup — separate form, outside grid context --}}
                        <form id="cancel-2fa-form" method="POST"
                              action="{{ url('/user/two-factor-authentication') }}" style="display:none;">
                            @csrf @method('DELETE')
                        </form>

                        @else
                        {{-- ENABLED AND CONFIRMED --}}
                        <div class="d-flex align-items-start gap-3 p-3 border rounded mb-3"
                             style="background:rgba(84,186,74,.08);border-color:rgba(84,186,74,.3)!important;">
                            <i data-feather="shield" style="width:32px;height:32px;color:#54ba4a;flex-shrink:0;"></i>
                            <div>
                                <h6 class="mb-1">2FA je aktivní</h6>
                                <p class="f-light f-12 mb-0">Váš účet je chráněn dvoufázovým ověřením.</p>
                            </div>
                        </div>

                        {{-- Recovery codes --}}
                        @if(!empty($recoveryCodes))
                        <div class="mb-3">
                            <h6 class="f-14 mb-2">Záložní kódy <small class="f-light">(uložte na bezpečné místo)</small></h6>
                            <div class="p-3 rounded" style="background:rgba(var(--light-background),.5);font-family:monospace;font-size:12px;">
                                @foreach($recoveryCodes as $code)
                                    <div class="py-1">{{ $code }}</div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap">
                            {{-- Regenerate recovery codes --}}
                            <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i data-feather="refresh-cw" style="width:13px;height:13px;"></i>
                                    Obnovit záložní kódy
                                </button>
                            </form>
                            {{-- Disable 2FA --}}
                            <form method="POST" action="{{ url('/user/two-factor-authentication') }}"
                                  onsubmit="return confirm('Opravdu chcete vypnout 2FA? Váš účet bude méně bezpečný.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i data-feather="shield-off" style="width:13px;height:13px;"></i>
                                    Vypnout 2FA
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ── Right column ────────────────────────────────────── --}}
            <div class="col-span-6 xl:col-span-12">

                {{-- Account info --}}
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Informace o účtu</h5></div>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="f-light ps-0 f-12" style="width:140px;">Jméno</td>
                                <td class="f-w-500">{{ $user?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">E-mail</td>
                                <td>
                                    {{ $user?->email ?? '—' }}
                                    @if($user?->email_verified_at)
                                        <span class="badge badge-light-success ms-1 f-10">
                                            <i data-feather="check" style="width:10px;height:10px;"></i> Ověřen
                                        </span>
                                    @else
                                        <span class="badge badge-light-warning ms-1 f-10">Neověřen</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">2FA ochrana</td>
                                <td>
                                    @if($twoFactorEnabled && $twoFactorConfirmed)
                                        <span class="badge badge-light-success">Aktivní</span>
                                    @else
                                        <span class="badge badge-light-danger">Neaktivní</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">Poslední přihlášení</td>
                                <td class="f-12">
                                    {{ $user?->last_login_at?->format('d.m.Y H:i') ?? '—' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">Stav účtu</td>
                                <td>
                                    @if($user?->is_active)
                                        <span class="badge badge-light-success">Aktivní</span>
                                    @else
                                        <span class="badge badge-light-danger">Neaktivní</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                {{-- Security tips --}}
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Bezpečnostní tipy</h5></div>
                    </div>
                    <div class="card-body">
                        @foreach([
                            ['Silné heslo','Používejte min. 12 znaků, kombinaci písmen, číslic a symbolů.','check-circle'],
                            ['Jedinečné heslo','Nepoužívejte stejné heslo na více službách.','check-circle'],
                            ['2FA ochrana','Zapněte dvoufázové ověření pro maximum bezpečnosti.','shield'],
                            ['Záložní kódy','Uložte záložní kódy 2FA na bezpečné místo.','lock'],
                        ] as [$title, $tip, $icon])
                        <div class="d-flex gap-3 align-items-start py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <i data-feather="{{ $icon }}" style="width:16px;height:16px;color:#54ba4a;flex-shrink:0;margin-top:2px;"></i>
                            <div>
                                <p class="f-w-500 f-13 mb-0">{{ $title }}</p>
                                <p class="f-light f-12 mb-0">{{ $tip }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Quick links --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            <a href="{{ route('panel.account.profile') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i data-feather="user" style="width:13px;height:13px;"></i> Profil
                            </a>
                            <a href="{{ route('panel.account.billing') }}" class="btn btn-outline-secondary btn-sm text-start">
                                <i data-feather="file-text" style="width:13px;height:13px;"></i> Fakturační údaje
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if($showingQrCode ?? false)
/* showingQrCode = hasTwoFactorSecret && !twoFactorConfirmed */
// Load QR code SVG from Fortify
fetch('/user/two-factor-qr-code')
    .then(r => r.json())
    .then(data => {
        var container = document.getElementById('qr-code-container');
        var loading = document.getElementById('qr-loading');
        if (container && data.svg) {
            loading.remove();
            container.insertAdjacentHTML('beforeend', data.svg);
        }
    })
    .catch(function() {
        var l = document.getElementById('qr-loading');
        if (l) l.innerHTML = '<p class="f-light f-12 text-danger">QR kód nelze načíst.</p>';
    });
@endif
</script>
@endpush
