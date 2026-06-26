@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.invoices');
    $breadcrumbItems = [__('panel.billing.billing') => '#', __('panel.nav.invoices') => ''];
@endphp

@section('title', __('panel.nav.invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.total')"
                    :value="$countTotal" icon="file-text" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.unpaid')"
                    :value="$countUnpaid" icon="alert-circle" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.overdue')"
                    :value="$countOverdue" icon="clock" color="danger" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.billing.paid')"
                    :value="$countPaid" icon="check-circle" color="success" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.invoices')">
            @if($invoices->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="file-text" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.billing.no_invoices') }}</h6>
                    <p class="f-light f-12 mb-0">Faktury se objeví po první objednávce.</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.billing.number'),
                    __('panel.billing.type'),
                    __('panel.billing.issue_date'),
                    __('panel.billing.due_date'),
                    __('panel.common.status'),
                    __('panel.common.total'),
                    '',
                ]">
                    @foreach($invoices as $invoice)
                        @php($isOverdue = $invoice->status->value === 'overdue' || ($invoice->due_date?->isPast() && $invoice->status->isOpen()))
                        <tr>
                            <td>
                                <a href="{{ route('panel.billing.invoices.show', $invoice) }}" class="f-w-600">
                                    {{ $invoice->number }}
                                </a>
                            </td>
                            <td class="f-12">{{ $invoice->type->label() }}</td>
                            <td class="f-12">{{ $invoice->issue_date?->format('d.m.Y') }}</td>
                            <td class="f-12 {{ $isOverdue ? 'txt-danger f-w-600' : '' }}">
                                {{ $invoice->due_date?->format('d.m.Y') }}
                                @if($isOverdue)
                                    <i data-feather="alert-circle" style="width:11px;height:11px" class="txt-danger ms-1"></i>
                                @endif
                            </td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                            <td>
                                <a class="btn btn-{{ $isOverdue ? 'primary' : 'outline-primary' }} btn-xs"
                                   href="{{ route('panel.billing.invoices.show', $invoice) }}">
                                    {{ $isOverdue ? __('panel.dashboard.pay_now') : __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $invoices->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
