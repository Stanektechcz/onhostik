@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.payments');
    $breadcrumbItems = [__('panel.billing.billing') => '#', __('panel.nav.payments') => ''];
@endphp

@section('title', __('panel.nav.payments'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.paid_total')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::formatMinor($paidTotalMinor, 'CZK')"
                    icon="dollar-sign" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.payments_paid')"
                    :value="$countPaid" icon="check-circle" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.payments_pending')"
                    :value="$countPending" icon="clock" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.payments_failed')"
                    :value="$countFailed" icon="alert-triangle" color="danger" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.payments')">
            @if($payments->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="credit-card" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.billing.no_payments') }}</h6>
                    <p class="f-light f-12 mb-0">Platební transakce se zobrazí po zaplacení faktury.</p>
                </div>
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
                            <td class="f-12">{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>
                                @if($payment->invoice)
                                    <a href="{{ route('panel.billing.invoices.show', $payment->invoice) }}" class="f-w-600">
                                        {{ $payment->invoice->number }}
                                    </a>
                                @else
                                    <span class="f-light">—</span>
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
