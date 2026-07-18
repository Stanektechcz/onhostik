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
        <div class="col-span-12">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Výplatní údaje</h5>
                        <div class="card-header-right-icon">
                            <span class="badge badge-light-warning">MANUAL</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light-warning mb-3">
                        <i data-feather="alert-triangle" style="width:14px;height:14px;" class="me-2"></i>
                        Výplaty provizí jsou aktuálně manuální. Bankovní spojení pro výplatu provizí
                        prosím zašlete na <a href="mailto:partner@onhost.cz" class="txt-primary f-w-500">partner@onhost.cz</a>.
                        Automatická správa výplatních údajů bude dostupná v dalším vydání.
                    </div>
                    <div class="text-center py-3">
                        <i data-feather="credit-card" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                        <p class="f-light f-12 mb-0">Správa výplatních údajů přímo v panelu — připravujeme.</p>
                        <span class="badge badge-light-warning mt-2">PŘIPRAVUJEME</span>
                    </div>
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
