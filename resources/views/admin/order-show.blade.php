@extends('layouts.panel')

@php($breadcrumbTitle = '#' . $order->id)
@php($breadcrumbItems = [__('panel.nav.admin_orders') => route('admin.orders.index'), '#' . $order->id => ''])

@section('title', 'Objednávka #' . $order->id)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row">
            {{-- Main content --}}
            <div class="col-xl-8">
                {{-- Order items --}}
                <x-panel.card title="Položky objednávky">
                    <x-panel.data-table :headers="['Popis', 'Plán', 'Množství', 'DPH', 'Celkem', 'Provisioning']">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="f-w-600">{{ $item->description }}</td>
                                <td class="f-light f-12">{{ $item->pricingPlan?->name ?? '—' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ' '), '0'), ',') }} %</td>
                                <td><x-panel.money :money="$item->total" /></td>
                                <td>
                                    @if($item->provisioning_status)
                                        <x-panel.status-badge :status="$item->provisioning_status" />
                                    @else
                                        <span class="f-light">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>

                    <div class="row justify-content-end mt-3">
                        <div class="col-md-4">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.subtotal') }}</td>
                                    <td class="text-end"><x-panel.money :money="$order->subtotal" /></td>
                                </tr>
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.vat') }}</td>
                                    <td class="text-end"><x-panel.money :money="$order->tax_amount" /></td>
                                </tr>
                                <tr class="border-top">
                                    <td class="f-w-600">{{ __('panel.common.total') }}</td>
                                    <td class="text-end f-w-600"><x-panel.money :money="$order->total" /></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </x-panel.card>

                {{-- Provisioned services --}}
                @if($services->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_services')">
                        <x-panel.data-table :headers="['ID', __('panel.services.label'), __('panel.services.product'), __('panel.services.server'), __('panel.common.status'), '']">
                            @foreach($services as $service)
                                <tr>
                                    <td class="f-light f-12">#{{ $service->id }}</td>
                                    <td class="f-w-600">{{ $service->label }}</td>
                                    <td>{{ $service->product?->name ?? '—' }}</td>
                                    <td>{{ $service->server?->name ?? '—' }}</td>
                                    <td><x-panel.status-badge :status="$service->status" /></td>
                                    <td>
                                        <a href="{{ route('admin.services.show', $service) }}"
                                           class="btn btn-outline-primary btn-sm">{{ __('panel.common.detail') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                @endif

                {{-- Invoices --}}
                @if($order->invoices->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_invoices')">
                        <x-panel.data-table :headers="[__('panel.billing.number'), __('panel.billing.type'), __('panel.billing.issue_date'), __('panel.common.status'), __('panel.common.total'), 'Platby']">
                            @foreach($order->invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                    <td>{{ $invoice->type->label() }}</td>
                                    <td>{{ $invoice->issue_date?->format('d.m.Y') }}</td>
                                    <td><x-panel.status-badge :status="$invoice->status" /></td>
                                    <td><x-panel.money :money="$invoice->total" /></td>
                                    <td>
                                        @if($invoice->payments->isNotEmpty())
                                            @foreach($invoice->payments as $payment)
                                                <span class="badge badge-light-{{ $payment->status->value === 'completed' ? 'success' : 'warning' }} f-12">
                                                    {{ $payment->status->value }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="f-light f-12">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-xl-4">
                {{-- Status --}}
                <x-panel.card :title="__('panel.common.status')">
                    <div class="mb-2">
                        <x-panel.status-badge :status="$order->status" />
                    </div>
                    <p class="mb-1 f-12">
                        <span class="f-light">Vytvořeno:</span>
                        {{ $order->created_at?->format('d.m.Y H:i') }}
                    </p>
                    @if($order->paid_at)
                        <p class="mb-1 f-12">
                            <span class="f-light">{{ __('panel.billing.paid_at') }}:</span>
                            {{ $order->paid_at->format('d.m.Y H:i') }}
                        </p>
                    @endif
                    @if($order->cancelled_at)
                        <p class="mb-1 f-12 text-danger">
                            <span class="f-light">Zrušeno:</span>
                            {{ $order->cancelled_at->format('d.m.Y H:i') }}
                        </p>
                    @endif
                    @if($order->notes)
                        <div class="border-top pt-2 mt-2">
                            <p class="mb-0 f-12 f-light">{{ $order->notes }}</p>
                        </div>
                    @endif
                </x-panel.card>

                {{-- Customer --}}
                <x-panel.card :title="__('panel.common.customer')">
                    @if($order->customer)
                        <p class="mb-1 f-w-600">{{ $order->customer->company_name ?? $order->customer->user?->name ?? '—' }}</p>
                        <p class="mb-1 f-light f-12">{{ $order->customer->email }}</p>
                        <p class="mb-3 f-light f-12">{{ $order->customer->country_code }} · {{ $order->customer->preferred_currency->value }}</p>
                        <a href="{{ route('admin.customers.show', $order->customer) }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="user" style="width:14px;height:14px"></i>
                            {{ __('panel.common.detail') }}
                        </a>
                    @else
                        <p class="f-light mb-0">—</p>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
