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
                                data-call="onhostCopy" data-call-args='["referral-url"]'>
                            <i data-feather="copy" style="width:14px;height:14px;"></i>
                            Kopírovat URL
                        </button>
                    </div>

                    <label class="form-label f-12 f-light">Referral kód</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control f-w-600" id="referral-code"
                               value="{{ $referralCode }}" readonly style="max-width:200px;">
                        <button class="btn btn-outline-primary" type="button"
                                data-call="onhostCopy" data-call-args='["referral-code"]'>
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
                            @if($commissionRate !== null)
                                <span class="f-w-600">{{ rtrim(rtrim(number_format($commissionRate, 2, ',', ' '), '0'), ',') }} %</span>
                            @else
                                <span class="f-light">Kontaktujte nás</span>
                            @endif
                        </li>
                        <li class="flex justify-between py-2 border-bottom">
                            <span class="f-light f-14">Výplatní cyklus</span>
                            <span class="f-light">Měsíční (manuální)</span>
                        </li>
                        <li class="flex justify-between py-2">
                            <span class="f-light f-14">Sledované referraly</span>
                            <span class="f-w-600">
                                {{ $referralCount }}
                                @if($convertedCount > 0)
                                    <span class="badge badge-light-success ms-1">{{ $convertedCount }} konverzí</span>
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- 112: Referral banners --}}
        <div class="col-span-12">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Propagační bannery</h5>
                    </div>
                </div>
                <div class="card-body">
                    @if(count($banners) === 0)
                        <div class="text-center py-4">
                            <i data-feather="image" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                            <p class="f-light f-12 mb-0">Bannery budou dostupné po aktivaci partnerského profilu.</p>
                        </div>
                    @else
                        <p class="f-light f-12 mb-3">
                            Vložte kód banneru na svůj web. Každý banner už obsahuje váš referral odkaz —
                            kliknutí návštěvníka se počítá do vašich provizí.
                        </p>
                        <div class="grid grid-cols-12 gap-3">
                            @foreach($banners as $i => $banner)
                                <div class="col-span-12 md:col-span-6">
                                    <div class="border rounded p-3 h-full">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="f-w-600 f-12">{{ $banner['label'] }}</span>
                                            <span class="badge badge-light-secondary">{{ $banner['size'] }}</span>
                                        </div>
                                        <div class="overflow-x-auto mb-2" style="background:#f6f6f8;border-radius:6px;padding:8px;text-align:center;">
                                            <div style="display:inline-block;max-width:100%;">{!! $banner['svg'] !!}</div>
                                        </div>
                                        <label class="form-label f-11 f-light mb-1">Kód pro vložení</label>
                                        <textarea class="form-control form-control-sm f-11 font-monospace mb-2" id="embed-{{ $i }}" rows="2" readonly>{{ $banner['embed'] }}</textarea>
                                        <div class="flex gap-2">
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                    data-call="onhostCopy" data-call-args='["embed-{{ $i }}"]'>
                                                <i data-feather="copy" style="width:13px;height:13px;"></i>
                                                Kopírovat kód
                                            </button>
                                            <a class="btn btn-outline-secondary btn-sm" download="onhost-banner-{{ $banner['size'] }}.svg"
                                               href="{{ $banner['download'] }}">
                                                <i data-feather="download" style="width:13px;height:13px;"></i>
                                                Stáhnout SVG
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
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
