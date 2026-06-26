@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Referraly';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Referraly' => ''];
@endphp

@section('title', 'Referraly | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Referral link card --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Váš referral odkaz</h5>
                    </div>
                </div>
                <div class="card-body">
                    <p class="f-light f-14 mb-3">
                        Sdílejte tento odkaz a získejte provizi za každého nového zákazníka, který se přihlásí přes váš odkaz.
                    </p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control f-w-500" id="referral-url-input"
                               value="{{ $referralUrl }}" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyReferralUrl(this)"
                                id="copy-btn">
                            <i data-feather="copy" style="width:14px;height:14px;"></i>
                            Kopírovat
                        </button>
                    </div>
                    <p class="f-light f-12 mb-0">
                        Kód: <code class="badge badge-light-secondary f-12">{{ $referralCode }}</code>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-span-12 lg:col-span-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <div class="mb-2">
                        <span class="badge badge-light-warning f-12 mb-2">PŘIPRAVUJEME</span>
                    </div>
                    <h6 class="f-w-600">Sledování konverzí</h6>
                    <p class="f-light f-12 mb-0">
                        Automatické sledování registrací přes referral link bude dostupné v dalším vydání.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Referrals table --}}
    <x-panel.card title="Seznam referralů">
        @if($referrals->isEmpty())
            <div class="text-center py-5">
                <i data-feather="users" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Zatím žádní referral zákazníci</h6>
                <p class="f-light f-12 mb-4">
                    Sdílejte svůj referral odkaz. Zákazníci, kteří se zaregistrují přes váš link, se zobrazí zde.
                </p>
                <button class="btn btn-primary btn-sm" onclick="copyReferralUrl(document.getElementById('copy-btn'))">
                    <i data-feather="copy" style="width:13px;height:13px;"></i>
                    Zkopírovat referral odkaz
                </button>
                <div class="mt-3">
                    <span class="badge badge-light-warning">PŘIPRAVUJEME</span>
                    <span class="f-light f-12 ms-2">Automatické sledování konverzí</span>
                </div>
            </div>
        @else
            <x-panel.data-table :headers="['Zákazník', 'Stav', 'Registrace', 'Navázaná služba', 'Provize']">
                @foreach($referrals as $ref)
                    <tr>
                        <td>{{ $ref['email'] ?? '—' }}</td>
                        <td><span class="badge badge-light-success">Aktivní</span></td>
                        <td class="f-12">{{ $ref['created_at'] ?? '—' }}</td>
                        <td>{{ $ref['service'] ?? '—' }}</td>
                        <td><span class="f-w-600 txt-success">{{ $ref['commission'] ?? '—' }}</span></td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        @endif
    </x-panel.card>
</div>

<script>
function copyReferralUrl(btn) {
    const input = document.getElementById('referral-url-input');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    try {
        navigator.clipboard.writeText(input.value).then(function() {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i data-feather="check" style="width:14px;height:14px;"></i> Zkopírováno!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
                if (typeof feather !== 'undefined') feather.replace();
            }, 2000);
        });
    } catch(e) {
        document.execCommand('copy');
    }
}
</script>
@endsection
