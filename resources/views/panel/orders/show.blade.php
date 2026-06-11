@extends('layouts.panel')

@php($orderNo = '#' . strtoupper(substr($order->uuid, 0, 8)))
@php($breadcrumbTitle = __('panel.nav.orders') . ' ' . $orderNo)
@php($breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), $orderNo => ''])

@section('title', __('panel.nav.orders') . ' ' . $orderNo)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.orders') . ' ' . $orderNo">
            <div class="row mb-3">
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                    <x-panel.status-badge :status="$order->status" />
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.created_at') }}</p>
                    <p class="mb-0">{{ $order->created_at?->format('d.m.Y H:i') }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.paid_at') }}</p>
                    <p class="mb-0">{{ $order->paid_at?->format('d.m.Y H:i') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.total') }}</p>
                    <p class="f-w-600 mb-0"><x-panel.money :money="$order->total" /></p>
                </div>
            </div>

            <h6>{{ __('panel.orders.items') }}</h6>
            <x-panel.data-table :headers="[
                __('panel.orders.plan'),
                __('panel.orders.period'),
                __('panel.orders.vat'),
                __('panel.common.total'),
            ]">
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->description }}
                            @if(($item->config['domain'] ?? null) !== null)
                                <br><small class="f-light">{{ __('panel.services.domain') }}: {{ $item->config['domain'] }}</small>
                            @endif
                        </td>
                        <td>{{ $item->period_from?->format('d.m.Y') }} – {{ $item->period_to?->format('d.m.Y') }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                        <td><x-panel.money :money="$item->total" /></td>
                    </tr>
                @endforeach
            </x-panel.data-table>

            <div class="row justify-content-end mt-3">
                <div class="col-md-4">
                    <table class="table table-borderless mb-0">
                        <tr><td class="f-light">{{ __('panel.orders.subtotal') }}</td><td class="text-end"><x-panel.money :money="$order->subtotal" /></td></tr>
                        <tr><td class="f-light">{{ __('panel.orders.vat') }}</td><td class="text-end"><x-panel.money :money="$order->tax_amount" /></td></tr>
                        <tr><td class="f-w-600">{{ __('panel.common.total') }}</td><td class="text-end f-w-600"><x-panel.money :money="$order->total" /></td></tr>
                    </table>
                </div>
            </div>
        </x-panel.card>

        <x-panel.card :title="__('panel.orders.invoice')">
            @if($order->invoices->isEmpty())
                <p class="f-light mb-0">{{ __('panel.billing.no_invoices') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.billing.number'),
                    __('panel.billing.type'),
                    __('panel.common.status'),
                    __('panel.billing.due_date'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($order->invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->number }}</td>
                            <td>{{ $invoice->type->label() }}</td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td>{{ $invoice->due_date?->format('d.m.Y') }}</td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('panel.billing.invoices.show', $invoice) }}">
                                    {{ __('panel.orders.view_invoice') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
            @endif
        </x-panel.card>
    </div>
@endsection
