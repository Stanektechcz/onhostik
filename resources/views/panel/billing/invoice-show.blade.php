@extends('layouts.panel')

@php($breadcrumbTitle = $invoice->number)
@php($breadcrumbItems = [__('panel.nav.invoices') => route('panel.billing.invoices'), $invoice->number => ''])

@section('title', $invoice->number)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="$invoice->type->label() . ' ' . $invoice->number">
            <div class="d-flex justify-content-end mb-2">
                <a href="{{ route('panel.billing.invoices.print', $invoice) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    {{ __('panel.billing.print') }}
                </a>
            </div>

            @if($invoice->purpose === 'credit_topup')
                <div class="alert alert-light-primary" role="alert">{{ __('panel.billing.purpose_topup') }}</div>
            @elseif($invoice->purpose === 'renewal')
                <div class="alert alert-light-info" role="alert">
                    {{ __('panel.billing.purpose_renewal') }}
                    @if($invoice->renewalService)
                        — {{ __('panel.billing.renews_service') }}: {{ $invoice->renewalService->label }}
                    @endif
                </div>
            @endif

            @if(!$invoice->isTaxDocument())
                <div class="alert alert-light-info" role="alert">{{ __('panel.billing.proforma_note') }}</div>
            @else
                <div class="alert alert-light-success" role="alert">{{ __('panel.billing.tax_document_note') }}</div>
            @endif

            <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-3 md:col-span-6 sm:col-span-12">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                    <x-panel.status-badge :status="$invoice->status" />
                </div>
                <div class="col-span-3 md:col-span-6 sm:col-span-12">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.issue_date') }}</p>
                    <p class="mb-0">{{ $invoice->issue_date?->format('d.m.Y') }}</p>
                </div>
                <div class="col-span-3 md:col-span-6 sm:col-span-12">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.due_date') }}</p>
                    <p class="mb-0">{{ $invoice->due_date?->format('d.m.Y') }}</p>
                </div>
                <div class="col-span-3 md:col-span-6 sm:col-span-12">
                    <p class="f-light f-12 mb-1">{{ __('panel.billing.variable_symbol') }}</p>
                    <p class="mb-0">{{ $invoice->variable_symbol }}</p>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-6 md:col-span-12">
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

            <div class="d-flex justify-content-end mt-3">
                <div>
                    <table class="table table-borderless mb-0">
                        <tr><td class="f-light">{{ __('panel.orders.subtotal') }}</td><td class="text-end"><x-panel.money :money="$invoice->subtotal" /></td></tr>
                        <tr><td class="f-light">{{ __('panel.orders.vat') }}</td><td class="text-end"><x-panel.money :money="$invoice->tax_amount" /></td></tr>
                        <tr><td class="f-w-600">{{ __('panel.common.total') }}</td><td class="text-end f-w-600"><x-panel.money :money="$invoice->total" /></td></tr>
                        @if($invoice->late_fee_amount !== null)
                        <tr>
                            <td class="text-warning f-12">
                                <i data-feather="alert-circle" style="width:12px;height:12px"></i>
                                Upomínkový poplatek
                                <small class="text-muted ms-1">({{ $invoice->late_fee_applied_at?->format('d.m.Y') }})</small>
                            </td>
                            <td class="text-end text-warning f-12">
                                +{{ number_format($invoice->late_fee_amount / 100, 0, ',', ' ') }} Kč
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($invoice->status->isOpen())
                <div class="border-top pt-3 mt-3">
                    <h6 class="mb-3">{{ __('panel.billing.choose_payment') }}</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <form method="POST" action="{{ route('panel.billing.invoices.pay-comgate', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="credit-card" style="width:14px;height:14px"></i>
                                {{ __('panel.billing.pay_comgate') }}
                            </button>
                        </form>
                        @if($stripeConfigured)
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-stripe', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    <svg viewBox="0 0 28 28" width="14" height="14" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:4px"><path fill="currentColor" d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.272.975 15.507 0 12.543 0 5.45 0 1.23 3.656 1.23 8.56c0 4.514 2.826 6.555 7.374 8.213 2.304.842 3.155 1.547 3.155 2.543 0 1.012-.895 1.562-2.503 1.562-1.88 0-4.693-.756-6.607-1.819l-.91 5.531a19.394 19.394 0 0 0 7.442 1.434c7.277 0 11.629-3.538 11.629-8.64 0-4.674-2.76-6.68-7.834-8.474z"/></svg>
                                    {{ __('panel.billing.pay_stripe') }}
                                </button>
                            </form>
                        @endif
                        @if($gopayConfigured)
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-gopay', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    <i data-feather="globe" style="width:14px;height:14px"></i>
                                    {{ __('panel.billing.pay_gopay') }}
                                </button>
                            </form>
                        @endif
                        @if($invoice->purpose !== 'credit_topup')
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-credit', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    <i data-feather="dollar-sign" style="width:14px;height:14px"></i>
                                    {{ __('panel.billing.pay_credit') }}
                                    <span class="f-light f-12 ms-1">({{ \App\Domains\Shared\Support\MoneyFormatter::format($creditBalance) }})</span>
                                </button>
                            </form>
                        @endif
                        @if($mockMode)
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-mock', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                    {{ __('panel.billing.pay_mock') }}
                                    <span class="badge badge-light-warning ms-1">mock</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('panel.billing.invoices.pay-mock', $invoice) }}">
                                @csrf
                                <input type="hidden" name="outcome" value="fail">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    {{ __('panel.billing.pay_mock_fail') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Bank transfer info --}}
                    @if(in_array($invoice->purpose, ['order', 'credit_topup', 'renewal'], true))
                        <div class="alert alert-light-info py-2 mb-0">
                            <p class="f-12 mb-1 f-w-600">
                                <i data-feather="info" style="width:12px;height:12px"></i>
                                Platba převodem
                            </p>
                            <p class="f-12 mb-1">
                                <span class="f-light">Číslo účtu (CZK):</span>
                                <strong>{{ $bankCzk ?? 'neuvedeno' }}</strong>
                            </p>
                            @if($bankEur)
                                <p class="f-12 mb-1">
                                    <span class="f-light">Číslo účtu (EUR):</span>
                                    <strong>{{ $bankEur }}</strong>
                                </p>
                            @endif
                            <p class="f-12 mb-0">
                                <span class="f-light">Variabilní symbol:</span>
                                <strong>{{ $invoice->variable_symbol }}</strong>
                            </p>
                        </div>
                    @endif
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

        {{-- B2B reference fields (Phase 101) --}}
        <x-panel.card title="Vaše reference">
            @if(session('status') === 'Reference faktury byla uložena.')
                <div class="alert alert-success py-2 mb-3 f-12">Reference faktury byla uložena.</div>
            @endif
            <p class="f-12 text-muted mb-3">
                Vyplňte číslo objednávky (PO) nebo vlastní referenci — pole se zobrazí na faktuře a v PDF.
            </p>
            <form method="POST" action="{{ route('panel.billing.invoices.update-reference', $invoice) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label f-12 f-w-600">Číslo objednávky (PO)</label>
                        <input type="text" name="purchase_order_number" maxlength="100"
                            value="{{ old('purchase_order_number', $invoice->purchase_order_number) }}"
                            class="form-control form-control-sm @error('purchase_order_number') is-invalid @enderror"
                            placeholder="Např. PO-2024-0042">
                        @error('purchase_order_number')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label f-12 f-w-600">Vlastní reference</label>
                        <input type="text" name="custom_reference" maxlength="255"
                            value="{{ old('custom_reference', $invoice->custom_reference) }}"
                            class="form-control form-control-sm @error('custom_reference') is-invalid @enderror"
                            placeholder="Např. projekt Alfa / účetní středisko">
                        @error('custom_reference')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i data-feather="save" style="width:13px;height:13px"></i>
                            Uložit
                        </button>
                    </div>
                </div>
            </form>
        </x-panel.card>
    </div>
@endsection
