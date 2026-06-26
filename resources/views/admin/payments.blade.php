@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_payments');
    $breadcrumbItems = [__('panel.nav.admin_payments') => ''];
@endphp

@section('title', __('panel.nav.admin_payments'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_payments')">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 180px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Billing\Enums\PaymentStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(($statusFilter ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="form-control" style="max-width: 155px;"
                       value="{{ $dateFrom ?? '' }}" title="Od">
                <input type="date" name="date_to" class="form-control" style="max-width: 155px;"
                       value="{{ $dateTo ?? '' }}" title="Do">
                <input type="text" name="q" class="form-control" style="max-width: 220px;"
                       placeholder="E-mail, firma, faktura…" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if(($statusFilter ?? '') || ($dateFrom ?? '') || ($dateTo ?? '') || ($search ?? ''))
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $payments->total() }} plateb</span>
            </form>

            <p class="f-12 f-light mb-3">
                <i data-feather="alert-triangle" style="width:12px;height:12px;"></i>
                {{ __('panel.admin.refund_warning') }}
            </p>

            @if($payments->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="credit-card" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                </div>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.customer'),
                    __('panel.orders.invoice'),
                    __('panel.billing.method'),
                    __('panel.common.date'),
                    __('panel.common.status'),
                    __('panel.billing.amount'),
                    __('panel.common.actions'),
                ]">
                    @foreach($payments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>
                            <td>
                                @if($payment->customer)
                                    <a href="{{ route('admin.customers.show', $payment->customer) }}">
                                        {{ $payment->customer->company_name ?? $payment->customer->email }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($payment->invoice)
                                    <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                            <td><x-panel.status-badge :status="$payment->status" /></td>
                            <td><x-panel.money :money="$payment->amount" /></td>
                            <td>
                                @if($payment->status === \App\Domains\Billing\Enums\PaymentStatus::Completed)
                                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}"
                                          onsubmit="return onhostConfirmManualRefund(this, {{ $payment->id }})"
                                          title="{{ __('panel.admin.refund_warning') }}">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="btn btn-outline-warning btn-xs">
                                            {{ __('panel.admin.refund') }}
                                        </button>
                                    </form>
                                @elseif($payment->status === \App\Domains\Billing\Enums\PaymentStatus::ManualRefund)
                                    <span class="f-light f-12" title="{{ __('panel.admin.refund_warning') }}">
                                        {{ __('panel.admin.payment_refunded') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $payments->links() }}
            @endif
        </x-panel.card>

        <x-panel.card :title="__('panel.admin.webhook_logs')">
            @if($webhookLogs->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[__('panel.common.date'), __('panel.admin.provider'), 'Event', 'IP', 'Zpracováno', __('panel.admin.error')]">
                    @foreach($webhookLogs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $log->provider }}</td>
                            <td class="f-12">{{ $log->event_id ?? '—' }}</td>
                            <td class="f-12">{{ $log->ip_address }}</td>
                            <td>{{ $log->processed ? __('panel.common.yes') : __('panel.common.no') }}</td>
                            <td class="f-light f-12">{{ $log->error_message ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $webhookLogs->links() }}
            @endif
        </x-panel.card>
    </div>

    <script>
        function onhostConfirmManualRefund(form, paymentId) {
            const reason = prompt(@json(__('panel.admin.refund_reason_prompt')));

            if (reason === null) {
                return false;
            }

            if (reason.trim().length < 3) {
                alert(@json(__('panel.admin.refund_reason_required')));
                return false;
            }

            form.querySelector('input[name="reason"]').value = reason.trim();

            return confirm(@json(__('panel.admin.refund_warning')) + '\n\n#' + paymentId);
        }
    </script>
@endsection
