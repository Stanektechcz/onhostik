@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_invoices');
    $breadcrumbItems = [__('panel.nav.admin_invoices') => ''];
@endphp

@section('title', __('panel.nav.admin_invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.billing.sent')"
                    :value="$countSent"
                    icon="send" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.billing.overdue')"
                    :value="$countOverdue"
                    icon="alert-circle" color="danger" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.billing.paid')"
                    :value="$countPaid"
                    icon="check-circle" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget
                    :label="__('panel.billing.draft')"
                    :value="$countDraft"
                    icon="file" color="secondary" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_invoices')">
            <form method="GET" action="{{ route('admin.invoices.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 180px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Billing\Enums\InvoiceStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 200px;"
                       placeholder="Číslo, var. symbol, zákazník…" value="{{ $search ?? '' }}">
                <input type="date" name="from" class="form-control" style="max-width: 145px;"
                       value="{{ $dateFrom ?? '' }}" title="Vystaveno od">
                <input type="date" name="to" class="form-control" style="max-width: 145px;"
                       value="{{ $dateTo ?? '' }}" title="Vystaveno do">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || ($search ?? '') || ($dateFrom ?? '') || ($dateTo ?? ''))
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $invoices->total() }} faktur</span>
                <a href="{{ route('admin.invoices.export', array_filter(['status' => $filter, 'from' => $dateFrom ?? '', 'to' => $dateTo ?? ''])) }}"
                   class="btn btn-outline-success btn-sm ms-2" title="Export do CSV">
                    <i data-feather="download" style="width:13px;height:13px;"></i> CSV
                </a>
            </form>

            @if($invoices->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="file-text" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                    <p class="f-light f-12 mb-0">Žádné faktury neodpovídají zvoleným filtrům.</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.billing.number'),
                    __('panel.billing.type'),
                    __('panel.common.customer'),
                    __('panel.billing.issue_date'),
                    __('panel.billing.due_date'),
                    __('panel.common.status'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($invoices as $invoice)
                        @php($isOverdue = $invoice->status->value === 'overdue')
                        <tr>
                            <td>
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="f-w-600">
                                    {{ $invoice->number }}
                                </a>
                            </td>
                            <td class="f-12">{{ $invoice->type->label() }}</td>
                            <td>
                                @if($invoice->customer)
                                    <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="f-light">
                                        {{ $invoice->customer->company_name ?? $invoice->customer->email }}
                                    </a>
                                @else
                                    <span class="f-light">—</span>
                                @endif
                            </td>
                            <td class="f-12">{{ $invoice->issue_date?->format('d.m.Y') ?? '—' }}</td>
                            <td class="f-12 {{ $isOverdue ? 'txt-danger f-w-600' : '' }}">
                                {{ $invoice->due_date?->format('d.m.Y') ?? '—' }}
                                @if($isOverdue)
                                    <i data-feather="alert-circle" style="width:11px;height:11px" class="txt-danger ms-1"></i>
                                @endif
                            </td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                            <td>
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-outline-primary btn-xs">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $invoices->withQueryString()->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
