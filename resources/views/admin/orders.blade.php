@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_orders');
    $breadcrumbItems = [__('panel.nav.admin_orders') => ''];
@endphp

@section('title', __('panel.nav.admin_orders'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_active')"
                    :value="$countActive" icon="check-circle" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_pending')"
                    :value="$countPending" icon="clock" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_processing')"
                    :value="$countProcessing" icon="loader" color="info" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_cancelled')"
                    :value="$countCancelled" icon="x-circle" color="danger" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_orders')">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 180px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Billing\Enums\OrderStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 200px;"
                       placeholder="E-mail, firma…" value="{{ $search ?? '' }}">
                <input type="date" name="from" class="form-control" style="max-width: 145px;"
                       value="{{ $dateFrom ?? '' }}" title="Od">
                <input type="date" name="to" class="form-control" style="max-width: 145px;"
                       value="{{ $dateTo ?? '' }}" title="Do">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || ($search ?? '') || ($dateFrom ?? '') || ($dateTo ?? ''))
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $orders->total() }} objednávek</span>
                <a href="{{ route('admin.orders.export', array_filter(['status' => $filter, 'from' => $dateFrom ?? '', 'to' => $dateTo ?? ''])) }}"
                   class="btn btn-outline-success btn-sm ms-2" title="Export do CSV">
                    <i data-feather="download" style="width:13px;height:13px;"></i> CSV
                </a>
            </form>

            @if($orders->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="shopping-cart" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                    <p class="f-light f-12 mb-0">Žádné objednávky neodpovídají filtru.</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.customer'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.billing.paid_at'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="f-w-600">#{{ $order->id }}</a>
                            </td>
                            <td>
                                @if($order->customer)
                                    <a href="{{ route('admin.customers.show', $order->customer) }}" class="f-light">
                                        {{ $order->customer->company_name ?? $order->customer->email }}
                                    </a>
                                @else
                                    <span class="f-light">—</span>
                                @endif
                            </td>
                            <td class="f-12">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                            <td><x-panel.status-badge :status="$order->status" /></td>
                            <td class="f-12">{{ $order->paid_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td><x-panel.money :money="$order->total" /></td>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-primary btn-xs">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $orders->withQueryString()->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
