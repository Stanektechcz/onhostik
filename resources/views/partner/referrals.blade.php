@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Referraly';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Referraly' => ''];
@endphp

@section('title', 'Referraly | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($referralCode)
    {{-- Referral link card --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Váš referral odkaz</h5></div>
                </div>
                <div class="card-body">
                    <p class="f-light f-14 mb-3">Sdílejte tento odkaz a získejte provizi za každého zákazníka, který se přihlásí přes váš odkaz.</p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control f-w-500" id="referral-url-input"
                               value="{{ $referralUrl }}" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyReferralUrl(this)">
                            <i data-feather="copy" style="width:14px;height:14px;"></i>
                            Kopírovat
                        </button>
                    </div>
                    <p class="f-light f-12 mb-0">Kód: <code class="badge badge-light-secondary f-12">{{ $referralCode }}</code></p>
                </div>
            </div>
        </div>
        <div class="col-span-12 lg:col-span-4">
            <div class="card h-full">
                <div class="card-body flex flex-col justify-center">
                    <ul class="list-unstyled mb-0">
                        <li class="flex justify-between py-2 border-bottom">
                            <span class="f-light f-14">Referraly celkem</span>
                            <span class="f-w-600">{{ is_object($referrals) && method_exists($referrals, 'total') ? $referrals->total() : $referrals->count() }}</span>
                        </li>
                        <li class="flex justify-between py-2">
                            <span class="f-light f-14">Sledování konverzí</span>
                            <span class="badge badge-light-success">Aktivní</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Referrals table --}}
    <x-panel.card title="Seznam referralů">
        @if((is_object($referrals) && method_exists($referrals, 'isEmpty') ? $referrals->isEmpty() : $referrals->isEmpty()))
            <div class="text-center py-5">
                <i data-feather="users" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Zatím žádní referral zákazníci</h6>
                <p class="f-light f-12 mb-4">Sdílejte svůj referral odkaz. Zákazníci, kteří se zaregistrují, se zobrazí zde.</p>
                @if($referralUrl)
                    <button class="btn btn-primary btn-sm" onclick="copyReferralUrl(document.querySelector('#copy-btn-fallback'))">
                        <i data-feather="copy" style="width:13px;height:13px;"></i>
                        Zkopírovat referral odkaz
                    </button>
                    <button id="copy-btn-fallback" class="hidden">btn</button>
                @endif
            </div>
        @else
            <x-panel.data-table :headers="['Zákazník', 'Stav', 'První návštěva', 'Registrace', 'Konverze']">
                @foreach($referrals as $ref)
                    <tr>
                        <td>
                            @if($ref->referredUser)
                                <div>
                                    <span class="f-w-500">{{ $ref->referredUser->name }}</span>
                                    <p class="f-light f-12 mb-0">{{ $ref->referredUser->email }}</p>
                                </div>
                            @else
                                <span class="f-light">Anonymní návštěvník</span>
                            @endif
                        </td>
                        <td><x-panel.status-badge :status="$ref->status" /></td>
                        <td class="f-12">{{ $ref->first_seen_at?->format('d.m.Y') }}</td>
                        <td class="f-12">{{ $ref->registered_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="f-12">{{ $ref->converted_at?->format('d.m.Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            @if(method_exists($referrals, 'links'))
                {{ $referrals->links() }}
            @endif
        @endif
    </x-panel.card>
</div>

<script>
function copyReferralUrl(btn) {
    const input = document.getElementById('referral-url-input');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i data-feather="check" style="width:14px;height:14px;"></i> Zkopírováno!';
        btn.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.replace('btn-success', 'btn-primary');
            if (typeof feather !== 'undefined') feather.replace();
        }, 2000);
    }).catch(() => { input.select(); document.execCommand('copy'); });
}
</script>
@endsection
