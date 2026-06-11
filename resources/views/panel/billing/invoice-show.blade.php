@extends('layouts.panel')

@php($breadcrumbTitle = $invoice->number)
@php($breadcrumbItems = [__('panel.nav.invoices') => route('panel.billing.invoices'), $invoice->number => ''])

@section('title', $invoice->number)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="$invoice->type->label() . ' ' . $invoice->number">
            @if(!$invoice->isTaxDocument())
                <div class="alert alert-light-info" role="alert">{{ __('panel.billing.proforma_note') }}</div>
            @endif

            <div class="row mb-3">
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                    <x-panel.status-badge :status="$invoice->status" />
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.issue_date') }}</p>
                    <p class="mb-0">{{ $invoice->issue_date?->format('d.m.Y') }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.due_date') }}</p>
                    <p class="mb-0">{{ $invoice->due_date?->format('d.m.Y') }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.variable_symbol') }}</p>
                    <p class="mb-0">{{ $invoice->variable_symbol }}</p>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <h6 class="f-light f-12">{{ __('panel.billing.subscriber') }}</h6>
                    <p class="mb-0 f-w-600">{{ $invoice->snapshot_name }}</p>
                    @if($invoice->snapshot_street)<p class="mb-0">{{ $invoice->snapshot_street }}</p>@endif
                    <p class="mb-0">
                        @if($invoice->snapshot_zip){{ $invoice->snapshot_zip }} @endif
                        {{ $invoice->snapshot_city }}
                        @if($invoice->snapshot_country_code)({{ $invoice->snapshot_country_code }})@endif
                    </p>
                    @if($invoice->snapshot_vat_number)<p class="mb-0">DIČ: {{ $invoice->snapshot_vat_number }}</p>@endif
                    @if($invoice->snapshot_registration_number)<p class="mb-0">IČ: {{ $invoice->snapshot_registration_number }}</p>@endif
                </div>
            </div>

            <x-panel.data-table :headers="[
                __('panel.billing.description'),
                __('panel.orders.vat'),
                __('panel.common.total'),
            ]">
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                        <td><x-panel.money :money="$item->total" /></td>
                    </tr>
                @endforeach
            </x-panel.data-table>

            <div class="row justify-content-end mt-3">
                <div class="col-md-4">
                    <table class="table table-borderless mb-0">
                        <tr><td class="f-light">{{ __('panel.orders.subtotal') }}</td><td class="text-end"><x-panel.money :money="$invoice->subtotal" /></td></tr>
                        <tr><td class="f-light">{{ __('panel.orders.vat') }}</td><td class="text-end"><x-panel.money :money="$invoice->tax_amount" /></td></tr>
                        <tr><td class="f-w-600">{{ __('panel.common.total') }}</td><td class="text-end f-w-600"><x-panel.money :money="$invoice->total" /></td></tr>
                    </table>
                </div>
            </div>

            @if($invoice->status->isOpen())
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if($mockMode)
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-mock', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    {{ __('panel.billing.pay_mock') }}
                                    <span class="badge badge-light-warning ms-1">{{ __('panel.admin.mock_badge') }}</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-mock', $invoice) }}">
                                @csrf
                                <input type="hidden" name="outcome" value="fail">
                                <button type="submit" class="btn btn-outline-warning">
                                    {{ __('panel.billing.pay_mock_fail') }}
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('panel.billing.invoices.pay-credit', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                {{ __('panel.billing.pay_credit') }}
                            </button>
                        </form>
                        <span class="f-light f-12">
                            {{ __('panel.billing.balance') }}: <x-panel.money :money="$creditBalance" />
                        </span>
                    </div>
                </div>
            @endif
        </x-panel.card>

        @if($invoice->payments->isNotEmpty())
            <x-panel.card :title="__('panel.nav.payments')">
                <x-panel.data-table :headers="[
                    __('panel.common.date'),
                    __('panel.billing.method'),
                    __('panel.common.status'),
                    __('panel.billing.amount'),
                ]">
                    @foreach($invoice->payments as $payment)
                        <tr>
                            <td>{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td><x-panel.status-badge :status="$payment->status" /></td>
                            <td><x-panel.money :money="$payment->amount" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
            </x-panel.card>
        @endif
    </div>
@endsection
