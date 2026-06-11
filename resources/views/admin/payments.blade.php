@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_payments'))

@section('title', __('panel.nav.admin_payments'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_payments')">
            @if($payments->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.customer'),
                    __('panel.orders.invoice'),
                    __('panel.billing.method'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.billing.amount'),
                ]">
                    @foreach($payments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>
                            <td>{{ $payment->customer?->company_name ?? $payment->customer?->email }}</td>
                            <td>{{ $payment->invoice?->number ?? '—' }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
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
