@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.orders');
    $breadcrumbItems = [__('panel.nav.orders') => ''];
@endphp

@section('title', __('panel.nav.orders'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.total')"
                    :value="$countTotal" icon="shopping-cart" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_active')"
                    :value="$countActive" icon="check-circle" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_pending')"
                    :value="$countPending" icon="clock" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.orders.status_cancelled')"
                    :value="$countCancelled" icon="x-circle" color="danger" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.orders')">
            @if($orders->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="shopping-cart" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.orders.none') }}</h6>
                    <p class="f-light f-12 mb-3">Ještě jste neobjednali žádnou službu.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary btn-sm">
                        <i data-feather="plus" style="width:13px;height:13px;"></i>
                        {{ __('panel.nav.new_order') }}
                    </a>
                </div>
            @else
                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary btn-sm">
                        <i data-feather="plus" style="width:13px;height:13px;"></i>
                        {{ __('panel.nav.new_order') }}
                    </a>
                </div>
                <x-panel.data-table :headers="[
                    __('panel.orders.number'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($orders as $order)
                        <tr>
                            <td class="f-w-600">#{{ strtoupper(substr($order->uuid, 0, 8)) }}</td>
                            <td class="f-12">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                            <td><x-panel.status-badge :status="$order->status" /></td>
                            <td><x-panel.money :money="$order->total" /></td>
                            <td>
                                <a class="btn btn-outline-primary btn-xs" href="{{ route('panel.orders.show', $order) }}">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $orders->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
