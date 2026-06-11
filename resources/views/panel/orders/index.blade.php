@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.orders'))

@section('title', __('panel.nav.orders'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.orders')">
            @if($orders->isEmpty())
                <p class="f-light mb-0">{{ __('panel.orders.none') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.orders.number'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($orders as $order)
                        <tr>
                            <td>#{{ strtoupper(substr($order->uuid, 0, 8)) }}</td>
                            <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                            <td><x-panel.status-badge :status="$order->status" /></td>
                            <td><x-panel.money :money="$order->total" /></td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('panel.orders.show', $order) }}">
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
