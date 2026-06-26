@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_customers');
    $breadcrumbItems = [__('panel.nav.admin_customers') => ''];
@endphp

@section('title', __('panel.nav.admin_customers'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_customers') . ' celkem'"
                    :value="$totalCount"
                    icon="users" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.account.company')"
                    :value="$companyCount"
                    icon="briefcase" color="info" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.account.person')"
                    :value="$personCount"
                    icon="user" color="secondary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    label="S aktivní službou"
                    :value="$withServiceCount"
                    icon="server" color="success" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_customers')">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="d-flex gap-2 mb-3 align-items-center flex-wrap">
                <input type="text" name="q" class="form-control" style="max-width: 300px;"
                       placeholder="Jméno, e-mail, firma…" value="{{ $search }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($search)
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $customers->total() }} zákazníků</span>
            </form>

            @if($customers->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="users" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                    <p class="f-light f-12 mb-0">Žádní zákazníci neodpovídají hledání.</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.customer'),
                    'E-mail',
                    'Země / měna',
                    __('panel.nav.admin_orders'),
                    __('panel.nav.admin_services'),
                    '',
                ]">
                    @foreach($customers as $customer)
                        <tr>
                            <td class="f-light f-12">#{{ $customer->id }}</td>
                            <td class="f-w-600">
                                {{ $customer->company_name ?? $customer->user?->name ?? '—' }}
                            </td>
                            <td class="f-light">{{ $customer->email }}</td>
                            <td class="f-12">{{ $customer->country_code }} · {{ $customer->preferred_currency->value }}</td>
                            <td>
                                <span class="{{ $customer->orders_count > 0 ? '' : 'f-light' }}">
                                    {{ $customer->orders_count }}
                                </span>
                            </td>
                            <td>
                                @php($sc = $customer->services_count)
                                <span class="{{ $sc > 0 ? 'badge badge-light-success' : 'f-light' }}">
                                    {{ $sc }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer) }}"
                                   class="btn btn-outline-primary btn-sm">{{ __('panel.common.detail') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $customers->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
