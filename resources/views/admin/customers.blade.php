@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_customers');
    $breadcrumbItems = [__('panel.nav.admin_customers') => ''];
@endphp

@section('title', __('panel.nav.admin_customers'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_customers') . ' celkem'"
                    :value="$totalCount"
                    icon="users" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.account.company')"
                    :value="$companyCount"
                    icon="briefcase" color="info" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.account.person')"
                    :value="$personCount"
                    icon="user" color="secondary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    label="S aktivní službou"
                    :value="$withServiceCount"
                    icon="server" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    label="S aktivním 2FA"
                    :value="$with2faCount"
                    icon="shield" color="warning" />
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
                <a href="{{ route('admin.customers.export', array_filter(['q' => $search])) }}"
                   class="btn btn-outline-success btn-sm ms-2" title="Export do CSV">
                    <i data-feather="download" style="width:13px;height:13px;"></i> CSV
                </a>
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
                    'Zdraví',
                    '2FA',
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
                                @if($customer->health_score !== null)
                                    <span class="badge badge-light-{{ $customer->health_score >= 80 ? 'success' : ($customer->health_score >= 50 ? 'warning' : 'danger') }} f-12">
                                        {{ $customer->health_score }}
                                    </span>
                                @else
                                    <span class="f-light f-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($customer->user?->two_factor_confirmed_at)
                                    <span class="badge badge-light-success" title="{{ $customer->user->two_factor_confirmed_at->format('d.m.Y') }}">
                                        <i data-feather="shield" style="width:11px;height:11px;"></i> Aktivní
                                    </span>
                                @else
                                    <span class="badge badge-light-secondary f-light">-</span>
                                @endif
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
