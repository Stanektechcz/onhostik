@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Profil partnera';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Profil' => ''];
@endphp

@section('title', 'Profil partnera | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Account info --}}
        <div class="col-span-12 xl:col-span-6">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Přihlašovací účet</h5>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="f-light f-12 ps-0" style="width:40%">Jméno</td>
                                <td class="f-w-500">{{ $user?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">E-mail</td>
                                <td>{{ $user?->email ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Registrace</td>
                                <td class="f-12">{{ $user?->created_at?->format('d.m.Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light f-12 ps-0">Role</td>
                                <td>
                                    @foreach($user?->getRoleNames() ?? [] as $role)
                                        <span class="badge badge-light-primary me-1">{{ $role }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-3">
                        <a href="{{ route('panel.account.profile') }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="edit-2" style="width:13px;height:13px;"></i>
                            Upravit profil
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Billing address --}}
        <div class="col-span-12 xl:col-span-6">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Fakturační údaje</h5>
                    </div>
                </div>
                <div class="card-body">
                    @if($customer)
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="f-light f-12 ps-0" style="width:40%">Firma / jméno</td>
                                    <td class="f-w-500">{{ $customer->company_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="f-light f-12 ps-0">IČO</td>
                                    <td>{{ $customer->ic ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="f-light f-12 ps-0">DIČ</td>
                                    <td>{{ $customer->dic ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="f-light f-12 ps-0">E-mail</td>
                                    <td>{{ $customer->email ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="f-light f-12 ps-0">Země</td>
                                    <td>{{ $customer->country_code ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-3">
                            <i data-feather="briefcase" style="width:32px;height:32px;" class="text-muted mb-2"></i>
                            <p class="f-light f-12 mb-2">Fakturační údaje nejsou vyplněny.</p>
                        </div>
                    @endif
                    <div class="mt-3">
                        <a href="{{ route('panel.account.billing') }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="edit-2" style="width:13px;height:13px;"></i>
                            {{ $customer ? 'Upravit fakturační údaje' : 'Přidat fakturační údaje' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payout settings --}}
        @php
            $payoutDetails = $partnerProfile?->getPayoutDetails() ?? [];
            $savedAccount  = $payoutDetails['account_number'] ?? null;
            $maskedAccount = $savedAccount
                ? str_repeat('•', max(0, strlen($savedAccount) - 4)) . substr($savedAccount, -4)
                : null;
        @endphp
        <div class="col-span-12">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Výplatní údaje</h5>
                    </div>
                </div>
                <div class="card-body">
                    @if(!$partnerProfile)
                        <p class="f-light f-12 mb-0">Výplatní údaje budou dostupné po aktivaci partnerského profilu.</p>
                    @else
                        <p class="f-light f-12 mb-3">
                            Zadejte účet, na který vám budeme vyplácet provize. Údaje jsou uloženy šifrovaně.
                        </p>
                        @if($maskedAccount)
                            <div class="alert alert-light-success f-12 mb-3">
                                <i data-feather="check-circle" style="width:14px;height:14px;" class="me-1"></i>
                                Uložený účet: <strong class="font-monospace">{{ $maskedAccount }}</strong>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('partner.profile.payout') }}">
                            @csrf
                            <div class="grid grid-cols-12 gap-3">
                                <div class="col-span-12 md:col-span-4">
                                    <label class="form-label f-12 f-light">Způsob výplaty</label>
                                    <select name="payout_method" class="form-select form-select-sm @error('payout_method') is-invalid @enderror">
                                        @foreach(['bank_transfer' => 'Bankovní převod', 'paypal' => 'PayPal'] as $val => $label)
                                            <option value="{{ $val }}" {{ ($partnerProfile->payout_method ?? old('payout_method')) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('payout_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-12 md:col-span-4">
                                    <label class="form-label f-12 f-light">Majitel účtu</label>
                                    <input type="text" name="account_holder" maxlength="120"
                                           value="{{ old('account_holder', $payoutDetails['account_holder'] ?? '') }}"
                                           class="form-control form-control-sm @error('account_holder') is-invalid @enderror">
                                    @error('account_holder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-12 md:col-span-4">
                                    <label class="form-label f-12 f-light">Číslo účtu / IBAN</label>
                                    <input type="text" name="account_number" maxlength="64"
                                           value="{{ old('account_number') }}" autocomplete="off"
                                           placeholder="{{ $maskedAccount ?? 'CZ...' }}"
                                           class="form-control form-control-sm font-monospace @error('account_number') is-invalid @enderror">
                                    @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-3">
                                <i data-feather="save" style="width:13px;height:13px;"></i>
                                Uložit výplatní údaje
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="col-span-12">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Rychlé akce</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-12 card-gap">
                        <div class="col-span-6 sm:col-span-12 md:col-span-3">
                            <a href="{{ route('partner.assets') }}" class="card text-center p-3 block text-decoration-none border b-light">
                                <i data-feather="share-2" class="txt-primary mb-2" style="width:24px;height:24px;"></i>
                                <p class="f-w-500 mb-0 f-14">Referral odkaz</p>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12 md:col-span-3">
                            <a href="{{ route('partner.commissions') }}" class="card text-center p-3 block text-decoration-none border b-light">
                                <i data-feather="trending-up" class="txt-success mb-2" style="width:24px;height:24px;"></i>
                                <p class="f-w-500 mb-0 f-14">Provize</p>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12 md:col-span-3">
                            <a href="{{ route('partner.payouts') }}" class="card text-center p-3 block text-decoration-none border b-light">
                                <i data-feather="dollar-sign" class="txt-warning mb-2" style="width:24px;height:24px;"></i>
                                <p class="f-w-500 mb-0 f-14">Výplaty</p>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12 md:col-span-3">
                            <a href="{{ route('partner.referrals') }}" class="card text-center p-3 block text-decoration-none border b-light">
                                <i data-feather="users" class="txt-info mb-2" style="width:24px;height:24px;"></i>
                                <p class="f-w-500 mb-0 f-14">Referraly</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
