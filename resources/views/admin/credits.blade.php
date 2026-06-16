@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.admin.wallets');
@endphp

@section('title', __('panel.admin.wallets'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="row mb-1">
            <div class="col-sm-6 col-xl-4">
                <x-panel.stat-widget
                    :label="__('panel.admin.wallets')"
                    :value="$customers->total()"
                    icon="users"
                    color="primary"
                />
            </div>
            <div class="col-sm-6 col-xl-4">
                <x-panel.stat-widget
                    :label="__('panel.billing.balance')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::formatMinor($totalBalance, 'CZK')"
                    icon="dollar-sign"
                    color="success"
                />
            </div>
        </div>

        <x-panel.card :title="__('panel.admin.wallets')">
            {{-- Search --}}
            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-6 col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="{{ __('panel.common.search') }}" value="{{ $search }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                    @if($search)
                        <a href="{{ route('admin.credits.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('panel.common.reset') }}</a>
                    @endif
                </div>
                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.credits.transactions') }}" class="btn btn-outline-info btn-sm">
                        <i data-feather="list" style="width:13px;height:13px"></i>
                        {{ __('panel.admin.all_transactions') }}
                    </a>
                </div>
            </form>

            <x-panel.data-table :headers="[
                __('panel.common.customer'),
                'E-mail',
                __('panel.billing.balance'),
                '',
            ]">
                @foreach($customers as $customer)
                    @php
                        $balanceMinor = (int) ($customer->balance_minor ?? 0);
                        $balanceClass = $balanceMinor > 0 ? 'text-success f-w-600' : ($balanceMinor < 0 ? 'text-danger f-w-600' : 'f-light');
                    @endphp
                    <tr>
                        <td class="f-w-500">{{ $customer->user?->name ?? $customer->company_name ?? '—' }}</td>
                        <td class="f-12">{{ $customer->email }}</td>
                        <td class="{{ $balanceClass }}">
                            {{ \App\Domains\Shared\Support\MoneyFormatter::formatMinor($balanceMinor, $customer->preferred_currency->value) }}
                        </td>
                        <td>
                            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-primary btn-sm">
                                {{ __('panel.common.detail') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            {{ $customers->links() }}
        </x-panel.card>
    </div>
@endsection
