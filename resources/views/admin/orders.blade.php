@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_orders'))

@section('title', __('panel.nav.admin_orders'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_orders')">
            @if($orders->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.customer'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.billing.paid_at'),
                    __('panel.common.total'),
                ]">
                    @foreach($orders as $order)
                        <tr>
                            <td>#{{ $order->id }}</td>
                            <td>{{ $order->customer?->company_name ?? $order->customer?->email }}</td>
                            <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                            <td><x-panel.status-badge :status="$order->status" /></td>
                            <td>{{ $order->paid_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td><x-panel.money :money="$order->total" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $orders->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
