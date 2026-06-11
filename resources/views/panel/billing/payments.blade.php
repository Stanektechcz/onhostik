@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.payments'))

@section('title', __('panel.nav.payments'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.payments')">
            @if($payments->isEmpty())
                <p class="f-light mb-0">{{ __('panel.billing.no_payments') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.common.date'),
                    __('panel.billing.method'),
                    __('panel.orders.invoice'),
                    __('panel.common.status'),
                    __('panel.billing.amount'),
                ]">
                    @foreach($payments as $payment)
                        <tr>
                            <td>{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>
                                @if($payment->invoice)
                                    <a href="{{ route('panel.billing.invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-panel.status-badge :status="$payment->status" /></td>
                            <td><x-panel.money :money="$payment->amount" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $payments->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
