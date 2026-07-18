@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.profile');
    $breadcrumbItems = [__('panel.nav.account') => '#', __('panel.nav.profile') => ''];
@endphp

@section('title', __('panel.nav.profile'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-5 xl:col-span-12">
                <x-panel.card :title="__('panel.nav.profile')">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="f-light ps-0 f-12" style="width:120px;">{{ __('panel.account.name') }}</td>
                            <td class="f-w-600">{{ $user?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">E-mail</td>
                            <td>{{ $user?->email ?? '—' }}</td>
                        </tr>
                        @if($customer)
                            <tr>
                                <td class="f-light ps-0 f-12">{{ __('panel.account.type') }}</td>
                                <td>
                                    @if($customer->type === 'company')
                                        <span class="badge badge-light-primary">{{ __('panel.account.company') }}</span>
                                    @else
                                        <span class="badge badge-light-secondary">{{ __('panel.account.person') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @if($customer->company_name)
                                <tr>
                                    <td class="f-light ps-0 f-12">{{ __('panel.account.company_name') }}</td>
                                    <td>{{ $customer->company_name }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="f-light ps-0 f-12">Země</td>
                                <td>{{ $customer->country_code }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">Měna</td>
                                <td>{{ $customer->preferred_currency->value }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0 f-12">Registrace</td>
                                <td class="f-12">{{ $customer->created_at?->format('d.m.Y') }}</td>
                            </tr>
                        @endif
                    </table>

                    <form method="POST" action="{{ route('panel.account.profile.update') }}" class="border-top pt-3 mt-3">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <label class="form-label f-12 f-light" for="prof-name">{{ __('panel.account.name') }}</label>
                            <input id="prof-name" type="text" name="name"
                                   value="{{ old('name', $user?->name) }}"
                                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i data-feather="save" style="width:13px;height:13px"></i>
                            {{ __('panel.common.save') }}
                        </button>
                    </form>
                </x-panel.card>

                <x-panel.card :title="__('panel.nav.billing_details')">
                    @if($customer?->billingAddress())
                        @php($addr = $customer->billingAddress())
                        <p class="mb-1 f-w-500">{{ $customer->company_name ?? $user?->name }}</p>
                        @if($customer->registration_number)
                            <p class="mb-1 f-12 f-light">IČ: {{ $customer->registration_number }}</p>
                        @endif
                        @if($customer->vat_number)
                            <p class="mb-1 f-12 f-light">DIČ: {{ $customer->vat_number }}</p>
                        @endif
                        <p class="mb-0 f-12 f-light">
                            {{ $addr->street }}, {{ $addr->zip }} {{ $addr->city }}
                            ({{ $addr->country_code }})
                        </p>
                        <div class="mt-3">
                            <a href="{{ route('panel.account.billing') }}" class="btn btn-outline-primary btn-sm">
                                <i data-feather="edit" style="width:13px;height:13px"></i>
                                {{ __('panel.account.edit_billing') }}
                            </a>
                        </div>
                    @else
                        <p class="f-light mb-2">{{ __('panel.account.no_billing_address') }}</p>
                        <a href="{{ route('panel.account.billing') }}" class="btn btn-primary btn-sm">
                            <i data-feather="plus" style="width:13px;height:13px"></i>
                            {{ __('panel.account.add_billing') }}
                        </a>
                    @endif
                </x-panel.card>
            </div>

            <div class="col-span-7 xl:col-span-12">
                <x-panel.card :title="__('panel.nav.security')">
                    <p class="f-light mb-3">{{ __('panel.account.security_note') }}</p>
                    <div class="flex gap-2 flex-wrap">
                        <a href="{{ route('panel.account.security') }}" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="shield" style="width:13px;height:13px"></i>
                            Bezpečnostní nastavení
                        </a>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="f-light f-12 mb-2">Přihlašovací informace</h6>
                        <p class="f-12 mb-1"><span class="f-light">Účet:</span> {{ $user?->email }}</p>
                        <p class="f-12 mb-0"><span class="f-light">Heslo:</span> ••••••••</p>
                    </div>
                </x-panel.card>

                <x-panel.card title="Rychlé akce">
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-6 sm:col-span-12">
                            <a href="{{ route('panel.billing.invoices') }}" class="card card-no-border border p-3 block text-decoration-none">
                                <div class="flex items-center gap-2">
                                    <i data-feather="file-text" class="font-primary" style="width:18px;height:18px"></i>
                                    <div>
                                        <div class="f-w-600 f-14">Faktury</div>
                                        <div class="f-light f-12">Přehled fakturace</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12">
                            <a href="{{ route('panel.billing.credits') }}" class="card card-no-border border p-3 block text-decoration-none">
                                <div class="flex items-center gap-2">
                                    <i data-feather="dollar-sign" class="font-success" style="width:18px;height:18px"></i>
                                    <div>
                                        <div class="f-w-600 f-14">Kredit</div>
                                        <div class="f-light f-12">Dobít / přehled</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12">
                            <a href="{{ route('panel.support.index') }}" class="card card-no-border border p-3 block text-decoration-none">
                                <div class="flex items-center gap-2">
                                    <i data-feather="life-buoy" class="font-warning" style="width:18px;height:18px"></i>
                                    <div>
                                        <div class="f-w-600 f-14">Podpora</div>
                                        <div class="f-light f-12">Otevřít ticket</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-span-6 sm:col-span-12">
                            <a href="{{ route('panel.services.index') }}" class="card card-no-border border p-3 block text-decoration-none">
                                <div class="flex items-center gap-2">
                                    <i data-feather="server" class="font-info" style="width:18px;height:18px"></i>
                                    <div>
                                        <div class="f-w-600 f-14">Moje služby</div>
                                        <div class="f-light f-12">Spravovat hosting</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </x-panel.card>

                <x-panel.card title="Soukromí a data (GDPR)">
                    <p class="f-light f-12 mb-3">Stáhněte export všech osobních údajů vedených k vašemu účtu (čl. 20 GDPR — přenositelnost dat).</p>
                    <a href="{{ route('panel.account.data-export') }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="download" style="width:13px;height:13px"></i>
                        Stáhnout data (ZIP)
                    </a>
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
