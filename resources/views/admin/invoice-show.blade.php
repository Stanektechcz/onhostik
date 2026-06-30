@extends('layouts.panel')

@php($breadcrumbTitle = $invoice->number)
@php($breadcrumbItems = [__('panel.nav.admin_invoices') => route('admin.invoices.index'), $invoice->number => ''])

@section('title', $invoice->number)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Main: line items + actions --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$invoice->type->label() . ' ' . $invoice->number">
                    {{-- Meta strip --}}
                    <div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
                        <x-panel.status-badge :status="$invoice->status" />
                        <span class="f-light f-12">
                            <i data-feather="calendar" style="width:11px;height:11px"></i>
                            {{ __('panel.billing.issue_date') }}: {{ $invoice->issue_date?->format('d.m.Y') }}
                        </span>
                        <span class="f-light f-12">
                            <i data-feather="clock" style="width:11px;height:11px"></i>
                            {{ __('panel.billing.due_date') }}: {{ $invoice->due_date?->format('d.m.Y') }}
                        </span>
                        <span class="badge badge-light-secondary f-12">VS: {{ $invoice->variable_symbol }}</span>
                        @if($invoice->purpose === 'credit_topup')
                            <span class="badge badge-light-primary">{{ __('panel.billing.purpose_topup') }}</span>
                        @elseif($invoice->purpose === 'renewal')
                            <span class="badge badge-light-info">{{ __('panel.billing.purpose_renewal') }}</span>
                            @if($invoice->renewalService)
                                <a href="{{ route('admin.services.show', $invoice->renewalService) }}" class="f-12">
                                    {{ __('panel.billing.renews_service') }}: {{ $invoice->renewalService->label }}
                                </a>
                            @endif
                        @endif
                        @if($invoice->parentInvoice)
                            <a href="{{ route('admin.invoices.show', $invoice->parentInvoice) }}" class="f-12">
                                {{ __('panel.billing.related_proforma') }}: {{ $invoice->parentInvoice->number }}
                            </a>
                        @endif
                    </div>

                    {{-- Line items --}}
                    <x-panel.data-table :headers="[__('panel.billing.description'), __('panel.orders.vat'), __('panel.common.total')]">
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                                <td><x-panel.money :money="$item->total" /></td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>

                    {{-- Totals --}}
                    <div class="d-flex justify-content-end mt-3">
                        <div>
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.subtotal') }}</td>
                                    <td class="text-end"><x-panel.money :money="$invoice->subtotal" /></td>
                                </tr>
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.vat') }}</td>
                                    <td class="text-end"><x-panel.money :money="$invoice->tax_amount" /></td>
                                </tr>
                                <tr>
                                    <td class="f-w-600">{{ __('panel.common.total') }}</td>
                                    <td class="text-end f-w-600"><x-panel.money :money="$invoice->total" /></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="border-top pt-3 mt-3 d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank"
                           class="btn btn-outline-secondary btn-sm">
                            <i data-feather="download" style="width:13px;height:13px"></i>
                            {{ __('panel.billing.print') }} PDF
                        </a>
                        @if($mockMode && $invoice->status->isOpen())
                            <form method="POST" action="{{ route('admin.invoices.mark-paid', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-feather="check-circle" style="width:13px;height:13px"></i>
                                    {{ __('panel.admin.mark_paid') }}
                                    <span class="badge badge-light-warning ms-1">{{ __('panel.admin.mock_badge') }}</span>
                                </button>
                            </form>
                        @endif
                        @if($invoice->status->isOpen())
                            <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}"
                                  onsubmit="return confirm('Opravdu stornovat fakturu {{ $invoice->number }}?')">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i data-feather="x-circle" style="width:13px;height:13px"></i>
                                    {{ __('panel.admin.cancel_invoice') }}
                                </button>
                            </form>
                        @endif
                        @if(!$invoice->isTaxDocument() && $invoice->status === \App\Domains\Billing\Enums\InvoiceStatus::Paid && $taxDocument === null && in_array($invoice->purpose, ['order', 'renewal'], true))
                            <form method="POST" action="{{ route('admin.invoices.tax-document', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    {{ __('panel.admin.issue_tax_document') }}
                                </button>
                            </form>
                        @endif
                        @if($taxDocument !== null)
                            <a href="{{ route('admin.invoices.show', $taxDocument) }}" class="btn btn-outline-success btn-sm">
                                <i data-feather="file-text" style="width:13px;height:13px"></i>
                                {{ __('panel.billing.tax_document') }}: {{ $taxDocument->number }}
                            </a>
                        @endif
                    </div>
                    @error('invoice')<div class="text-danger f-12 mt-2">{{ $message }}</div>@enderror
                </x-panel.card>

                {{-- Payments --}}
                @if($invoice->payments->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_payments')">
                        <x-panel.data-table :headers="[__('panel.common.date'), __('panel.billing.method'), __('panel.common.status'), __('panel.billing.amount'), 'TXN']">
                            @foreach($invoice->payments as $payment)
                                <tr>
                                    <td class="f-12">{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>{{ $payment->method->label() }}</td>
                                    <td><x-panel.status-badge :status="$payment->status" /></td>
                                    <td><x-panel.money :money="$payment->amount" /></td>
                                    <td class="f-light f-12">{{ $payment->gateway_transaction_id ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                @endif
            </div>

            {{-- Sidebar: customer + order links --}}
            <div class="col-span-4 xl:col-span-12">
                {{-- Customer --}}
                @if($invoice->customer)
                    <x-panel.card :title="__('panel.common.customer')">
                        <p class="mb-1 f-w-600">{{ $invoice->customer->company_name ?? $invoice->customer->user?->name ?? '—' }}</p>
                        <p class="mb-1 f-light">{{ $invoice->customer->email }}</p>
                        <p class="mb-3 f-light f-12">{{ $invoice->customer->country_code }} · {{ $invoice->customer->preferred_currency->value }}</p>
                        <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="user" style="width:13px;height:13px"></i>
                            {{ __('panel.common.detail') }}
                        </a>
                    </x-panel.card>
                @endif

                {{-- Related order --}}
                @if($invoice->order)
                    <x-panel.card :title="__('panel.nav.admin_orders')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 f-w-600">#{{ $invoice->order->id }}</p>
                                <x-panel.status-badge :status="$invoice->order->status" />
                            </div>
                            <a href="{{ route('admin.orders.show', $invoice->order) }}" class="btn btn-outline-secondary btn-sm">
                                <i data-feather="package" style="width:13px;height:13px"></i>
                                {{ __('panel.common.detail') }}
                            </a>
                        </div>
                    </x-panel.card>
                @endif

                {{-- Billing address --}}
                @if($invoice->customer?->addresses->isNotEmpty())
                    @php($addr = $invoice->customer->addresses->where('type', 'billing')->first() ?? $invoice->customer->addresses->first())
                    @if($addr)
                        <x-panel.card title="Fakturační adresa">
                            <p class="mb-0 f-12" style="line-height: 1.7;">
                                {{ $addr->street }}<br>
                                {{ $addr->zip }} {{ $addr->city }}<br>
                                {{ $addr->country_code }}
                            </p>
                        </x-panel.card>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
