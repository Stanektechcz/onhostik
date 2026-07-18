@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Materiály';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Materiály' => ''];
@endphp

@section('title', 'Partnerské materiály | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(!$referralCode)
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="flex items-center gap-3">
                    <i data-feather="info" class="txt-warning" style="width:18px;height:18px;"></i>
                    <span class="f-light f-14">Váš partnerský profil ještě není aktivní. Kontaktujte administrátora pro aktivaci.</span>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-12 card-gap">

        {{-- Referral link card --}}
        <div class="col-span-12 xl:col-span-8">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Referral odkaz</h5>
                    </div>
                </div>
                <div class="card-body">
                    <p class="f-light mb-3">
                        Sdílejte svůj unikátní odkaz na sociálních sítích, v e-mailu nebo na webu.
                        Za každého zákazníka, který se zaregistruje přes váš link, získáte provizi.
                    </p>

                    <label class="form-label f-12 f-light">Váš referral odkaz</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control f-w-500" id="referral-url"
                               value="{{ $referralUrl }}" readonly>
                        <button class="btn btn-primary" type="button" id="copy-url-btn"
                                onclick="onhostCopy('referral-url', this)">
                            <i data-feather="copy" style="width:14px;height:14px;"></i>
                            Kopírovat URL
                        </button>
                    </div>

                    <label class="form-label f-12 f-light">Referral kód</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control f-w-600" id="referral-code"
                               value="{{ $referralCode }}" readonly style="max-width:200px;">
                        <button class="btn btn-outline-primary" type="button"
                                onclick="onhostCopy('referral-code', this)">
                            <i data-feather="copy" style="width:14px;height:14px;"></i>
                            Kopírovat kód
                        </button>
                    </div>

                    <div class="alert alert-light-info f-12 mb-0">
                        <i data-feather="info" style="width:12px;height:12px;" class="me-1"></i>
                        Kód použijte jako parametr
                        <code>?ref={{ $referralCode }}</code> při sdílení jakékoliv stránky Onhost.cz.
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick stats --}}
        <div class="col-span-12 xl:col-span-4">
            <div class="card h-full">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Stav programu</h5>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="flex justify-between py-2 border-bottom">
                            <span class="f-light f-14">Referral kód</span>
                            <code class="badge badge-light-primary">{{ $referralCode }}</code>
                        </li>
                        <li class="flex justify-between py-2 border-bottom">
                            <span class="f-light f-14">Sazba provize</span>
                            <span class="f-w-600">Kontaktujte nás <span class="badge badge-light-warning">MANUAL</span></span>
                        </li>
                        <li class="flex justify-between py-2 border-bottom">
                            <span class="f-light f-14">Výplatní cyklus</span>
                            <span class="f-light">Měsíční <span class="badge badge-light-warning">MANUAL</span></span>
                        </li>
                        <li class="flex justify-between py-2">
                            <span class="f-light f-14">Sledování konverzí</span>
                            <span class="badge badge-light-warning">PŘIPRAVUJEME</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Banner materials --}}
        <div class="col-span-12">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Propagační materiály</h5>
                        <div class="card-header-right-icon">
                            <span class="badge badge-light-warning">PŘIPRAVUJEME</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center py-4">
                        <i data-feather="image" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                        <h6 class="f-light mt-2">Bannery a grafické materiály</h6>
                        <p class="f-light f-12 mb-0">
                            Připravujeme sadu bannerů, logotypů a marketingových textů pro partnerský program.
                            Dostupné v dalším vydání.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function onhostCopy(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i data-feather="check" style="width:14px;height:14px;"></i> Zkopírováno!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-primary','btn-outline-primary');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.remove('btn-success');
            if (inputId === 'referral-url') {
                btn.classList.add('btn-primary');
            } else {
                btn.classList.add('btn-outline-primary');
            }
            if (typeof feather !== 'undefined') feather.replace();
        }, 2000);
    }).catch(() => {
        el.select(); document.execCommand('copy');
    });
}
</script>
@endsection
