@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.dashboard'))

@section('title', __('panel.nav.dashboard'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- ===== stat row ===== --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget :label="__('panel.dashboard.active_services')" :value="$activeServices" icon="server" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget :label="__('panel.dashboard.active_domains')" :value="$activeDomains" icon="globe" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget :label="__('panel.dashboard.credit')" :value="\App\Domains\Shared\Support\MoneyFormatter::format($creditBalance)" icon="dollar-sign" color="warning" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget :label="__('panel.dashboard.open_tickets')" :value="$openTickets" icon="life-buoy" color="secondary" />
            </div>
        </div>

        <div class="row">
            {{-- ===== unpaid invoices ===== --}}
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.dashboard.unpaid_invoices')">
                    @if($unpaidInvoices->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.billing.no_invoices') }}</p>
                    @else
                        <x-panel.data-table :headers="[__('panel.billing.number'), __('panel.billing.due_date'), __('panel.common.total'), '']">
                            @foreach($unpaidInvoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('panel.billing.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                    <td>{{ $invoice->due_date?->format('d.m.Y') }}</td>
                                    <td><x-panel.money :money="$invoice->total" /></td>
                                    <td><a href="{{ route('panel.billing.invoices.show', $invoice) }}" class="btn btn-primary btn-sm">{{ __('panel.dashboard.pay_now') }}</a></td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    @endif
                </x-panel.card>
            </div>

            {{-- ===== quick actions + incident ===== --}}
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.dashboard.quick_actions')">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('panel.orders.create') }}" class="btn btn-primary">{{ __('panel.nav.new_order') }}</a>
                        <a href="{{ route('panel.billing.credits') }}" class="btn btn-outline-primary">{{ __('panel.billing.topup_title') }}</a>
                        <a href="{{ route('panel.support.index') }}" class="btn btn-outline-secondary">{{ __('panel.support.new_ticket') }}</a>
                        <a href="{{ route('panel.ai.index') }}" class="btn btn-outline-secondary">{{ __('panel.nav.ai') }}</a>
                    </div>
                    <div class="mt-4">
                        <h6 class="f-light f-12">{{ __('panel.dashboard.latest_incident') }}</h6>
                        @if($latestIncident === null)
                            <p class="font-success mb-0">{{ __('panel.dashboard.no_incident') }}</p>
                        @else
                            <p class="mb-0">
                                {{ $latestIncident->started_at?->format('d.m.Y H:i') }} — {{ $latestIncident->reason }}
                                @if($latestIncident->isOpen())
                                    <span class="badge badge-light-danger">OPEN</span>
                                @else
                                    <span class="badge badge-light-success">RESOLVED</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </x-panel.card>
            </div>
        </div>

        <div class="row">
            {{-- ===== recent orders ===== --}}
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.dashboard.recent_orders')">
                    @if($recentOrders->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.orders.none') }}</p>
                    @else
                        <x-panel.data-table :headers="[__('panel.orders.number'), __('panel.common.status'), __('panel.common.total')]">
                            @foreach($recentOrders as $order)
                                <tr>
                                    <td><a href="{{ route('panel.orders.show', $order) }}">#{{ $order->id }}</a></td>
                                    <td><x-panel.status-badge :status="$order->status" /></td>
                                    <td><x-panel.money :money="$order->total" /></td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    @endif
                </x-panel.card>
            </div>

            {{-- ===== recent payments ===== --}}
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.dashboard.recent_payments')">
                    @if($recentPayments->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.billing.no_payments') }}</p>
                    @else
                        <x-panel.data-table :headers="[__('panel.common.date'), __('panel.billing.method'), __('panel.common.status'), __('panel.billing.amount')]">
                            @foreach($recentPayments as $payment)
                                <tr>
                                    <td>{{ $payment->created_at?->format('d.m.Y') }}</td>
                                    <td>{{ $payment->method->label() }}</td>
                                    <td><x-panel.status-badge :status="$payment->status" /></td>
                                    <td><x-panel.money :money="$payment->amount" /></td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
