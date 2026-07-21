@extends('layouts.panel')

@php
    $breadcrumbTitle = '#' . $order->id;
    $breadcrumbItems = [__('panel.nav.admin_orders') => route('admin.orders.index'), '#' . $order->id => ''];
@endphp

@section('title', 'Objednávka #' . $order->id)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Main content --}}
            <div class="col-span-8 xl:col-span-12">
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

                    <div class="flex justify-end mt-3">
                        <div>
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.subtotal') }}</td>
                                    <td class="text-right"><x-panel.money :money="$order->subtotal" /></td>
                                </tr>
                                <tr>
                                    <td class="f-light">{{ __('panel.orders.vat') }}</td>
                                    <td class="text-right"><x-panel.money :money="$order->tax_amount" /></td>
                                </tr>
                                <tr class="border-top">
                                    <td class="f-w-600">{{ __('panel.common.total') }}</td>
                                    <td class="text-right f-w-600"><x-panel.money :money="$order->total" /></td>
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
            <div class="col-span-4 xl:col-span-12">
                {{-- Admin actions --}}
                <x-panel.card title="Akce">
                    @php
                        $openInvoice = $order->invoices->first(fn ($i) => $i->status->isOpen());
                        $canProvision = $services->isNotEmpty()
                            && $services->contains(fn ($s) => $s->status->value !== 'active');
                    @endphp
                    @if($openInvoice)
                        <form method="POST" action="{{ route('admin.orders.accept', $order) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success w-full text-white">
                                <i data-feather="check-circle" style="width:14px;height:14px;"></i>
                                Akceptovat a aktivovat
                            </button>
                        </form>
                        <p class="f-light f-12 mb-3">Zaznamená platbu k faktuře {{ $openInvoice->number }} a spustí zřízení služeb.</p>
                    @endif

                    @if($canProvision)
                        <form method="POST" action="{{ route('admin.orders.provision', $order) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-full">
                                <i data-feather="refresh-cw" style="width:14px;height:14px;"></i>
                                Znovu zřídit služby
                            </button>
                        </form>
                    @endif

                    @if($order->status->value !== 'cancelled')
                        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}"
                              data-confirm="Opravdu zrušit tuto objednávku?">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-full">
                                <i data-feather="x-circle" style="width:14px;height:14px;"></i>
                                Zrušit objednávku
                            </button>
                        </form>
                    @endif

                    @if(!$openInvoice && !$canProvision && $order->status->value !== 'cancelled')
                        <p class="f-light f-12 mb-0">Objednávka je vyřízena — žádné akce nejsou potřeba.</p>
                    @endif
                </x-panel.card>

                {{-- Edit order --}}
                <x-panel.card title="Upravit objednávku">
                    <form method="POST" action="{{ route('admin.orders.update', $order) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label f-12">Stav</label>
                            <select name="status" class="form-select">
                                @foreach(['pending' => 'Čeká na platbu', 'processing' => 'Zpracovává se', 'active' => 'Aktivní', 'cancelled' => 'Zrušena', 'fraud' => 'Podvodná'] as $val => $lbl)
                                    <option value="{{ $val }}" @selected($order->status->value === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>

                        @foreach($order->items as $item)
                            <div class="mb-3">
                                <label class="form-label f-12">Doména — {{ $item->pricingPlan?->name ?? ('položka #' . $item->id) }}</label>
                                <input type="text" name="item_domain[{{ $item->id }}]" class="form-control"
                                       value="{{ $item->config['domain'] ?? '' }}" placeholder="mujweb.cz">
                            </div>
                        @endforeach

                        <div class="mb-3">
                            <label class="form-label f-12">Poznámka</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Interní poznámka…">{{ $order->notes }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-full text-white">
                            <i data-feather="save" style="width:14px;height:14px;"></i>
                            Uložit změny
                        </button>
                    </form>
                </x-panel.card>

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

                {{-- 75: unified internal notes --}}
                <x-panel.entity-notes :notable="$order" type="order" />
            </div>
        </div>
    </div>
@endsection
