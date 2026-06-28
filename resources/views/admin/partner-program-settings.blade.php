@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nastavení partnerského programu';
    $breadcrumbItems = ['Partneři' => route('admin.partners.index'), 'Nastavení programu' => ''];
    $s = $settings;
@endphp

@section('title', 'Nastavení partnerského programu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <form method="POST" action="{{ route('admin.partner-program.settings.update') }}">
    @csrf @method('PUT')

    <div class="container">
    <div class="grid grid-cols-12 card-gap">

        {{-- ── Affiliate program ──────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Affiliate program</h5>
                        <div class="card-header-right-icon">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="affiliate_enabled"
                                       value="1" id="aff-enabled" {{ ($s['affiliate_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="aff-enabled">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body custom-input">
                    <p class="f-light f-13 mb-3">
                        Partner sdílí odkaz a dostává provizi za každého zákazníka, který nakoupí přes tento odkaz.
                    </p>
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-6">
                            <label class="form-label">Výše provize (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="affiliate_commission_rate"
                                       value="{{ $s['affiliate_commission_rate'] ?? 10 }}" min="1" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-span-6">
                            <label class="form-label">Cookie platnost (dní)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="affiliate_cookie_days"
                                       value="{{ $s['affiliate_cookie_days'] ?? 30 }}" min="1" max="365">
                                <span class="input-group-text">dní</span>
                            </div>
                        </div>
                        <div class="col-span-6">
                            <label class="form-label">Minimální výplata (Kč)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="affiliate_min_payout"
                                       value="{{ $s['affiliate_min_payout'] ?? 500 }}" min="0">
                                <span class="input-group-text">Kč</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Referral program ───────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Referral program</h5>
                        <div class="card-header-right-icon">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="referral_enabled"
                                       value="1" id="ref-enabled" {{ ($s['referral_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="ref-enabled">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body custom-input">
                    <p class="f-light f-13 mb-3">
                        Partner dostane unikátní referral kód nebo odkaz. Za každého doporučeného zákazníka získá odměnu.
                    </p>
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-4">
                            <label class="form-label">Odměna za referral</label>
                            <input type="number" class="form-control" name="referral_reward_amount"
                                   value="{{ $s['referral_reward_amount'] ?? 100 }}" min="0">
                        </div>
                        <div class="col-span-4">
                            <label class="form-label">Měna odměny</label>
                            <select class="form-select" name="referral_reward_currency">
                                @foreach(['CZK', 'EUR', 'USD'] as $cur)
                                <option value="{{ $cur }}" {{ ($s['referral_reward_currency'] ?? 'CZK') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-4">
                            <label class="form-label">Délka kódu (znaky)</label>
                            <input type="number" class="form-control" name="referral_code_length"
                                   value="{{ $s['referral_code_length'] ?? 8 }}" min="4" max="20">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Promo kódy ──────────────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Promo kódy</h5>
                        <div class="card-header-right-icon">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="codes_enabled"
                                       value="1" id="codes-enabled" {{ ($s['codes_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="codes-enabled">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body custom-input">
                    <p class="f-light f-13 mb-3">
                        Slevové kódy které mohou partneři sdílet se zákazníky. Zákazník zadá kód při objednávce.
                    </p>
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-6">
                            <label class="form-label">Typ slevy</label>
                            <select class="form-select" name="codes_discount_type">
                                <option value="percent" {{ ($s['codes_discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' }}>Procentuální (%)</option>
                                <option value="fixed" {{ ($s['codes_discount_type'] ?? '') === 'fixed' ? 'selected' : '' }}>Pevná částka (Kč)</option>
                            </select>
                        </div>
                        <div class="col-span-6">
                            <label class="form-label">Max. použití na kód</label>
                            <input type="number" class="form-control" name="codes_max_uses_per_code"
                                   value="{{ $s['codes_max_uses_per_code'] ?? 100 }}" min="1">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Bannery ──────────────────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Propagační bannery</h5>
                        <div class="card-header-right-icon">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="banners_enabled"
                                       value="1" id="banners-enabled" {{ ($s['banners_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="banners-enabled">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="f-light f-13 mb-3">
                        Partneři mají přístup k bannerům pro propagaci na svých webech. Spravujte bannery v sekci Materiály.
                    </p>
                    <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-primary btn-sm">
                        <i data-feather="image" style="width:13px;height:13px;"></i>
                        Správa bannerů
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Výplaty ──────────────────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top"><h5>Nastavení výplat</h5></div>
                </div>
                <div class="card-body custom-input">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-4">
                            <label class="form-label">Cyklus výplat</label>
                            <select class="form-select" name="payout_schedule">
                                <option value="weekly" {{ ($s['payout_schedule'] ?? '') === 'weekly' ? 'selected' : '' }}>Týdně</option>
                                <option value="monthly" {{ ($s['payout_schedule'] ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Měsíčně</option>
                                <option value="quarterly" {{ ($s['payout_schedule'] ?? '') === 'quarterly' ? 'selected' : '' }}>Čtvrtletně</option>
                            </select>
                        </div>
                        <div class="col-span-4">
                            <label class="form-label">Min. výplata</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="payout_min_amount"
                                       value="{{ $s['payout_min_amount'] ?? 500 }}" min="0">
                                <span class="input-group-text">Kč</span>
                            </div>
                        </div>
                        <div class="col-span-4">
                            <label class="form-label">Měna výplat</label>
                            <select class="form-select" name="payout_currency">
                                @foreach(['CZK', 'EUR', 'USD'] as $cur)
                                <option value="{{ $cur }}" {{ ($s['payout_currency'] ?? 'CZK') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Obecná pravidla ──────────────────────────────── --}}
        <div class="col-span-6 xl:col-span-12">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top"><h5>Obecná pravidla programu</h5></div>
                </div>
                <div class="card-body custom-input">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-6">
                            <label class="form-label">Provize čeká na schválení (dní)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="auto_approve_threshold"
                                       value="{{ $s['auto_approve_threshold'] ?? 30 }}" min="0">
                                <span class="input-group-text">dní</span>
                            </div>
                            <div class="form-text f-11 f-light">Po kolika dnech od platby se provize automaticky schválí</div>
                        </div>
                        <div class="col-span-6">
                            <label class="form-label">Nárok na provizi po (dní)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="eligible_days_after_payment"
                                       value="{{ $s['eligible_days_after_payment'] ?? 30 }}" min="0">
                                <span class="input-group-text">dní</span>
                            </div>
                            <div class="form-text f-11 f-light">Zákazník musí být aktivní X dní po platbě</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save button --}}
        <div class="col-span-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary text-white">
                    <i data-feather="save" style="width:14px;height:14px;"></i>
                    Uložit nastavení programu
                </button>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Zpět na partnery</a>
            </div>
        </div>

    </div>
    </div>
    </form>
</div>
@endsection
