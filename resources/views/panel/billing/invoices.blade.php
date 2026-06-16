@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.invoices'))

@section('title', __('panel.nav.invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.invoices')">
            @if($invoices->isEmpty())
                <p class="f-light mb-0">{{ __('panel.billing.no_invoices') }}</p>
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
                            <td class="f-12 {{ $isOverdue ? 'text-danger f-w-600' : '' }}">
                                {{ $invoice->due_date?->format('d.m.Y') }}
                                @if($isOverdue)
                                    <i data-feather="alert-circle" style="width:11px;height:11px" class="text-danger"></i>
                                @endif
                            </td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                            <td>
                                <a class="btn btn-{{ $isOverdue ? 'primary' : 'outline-primary' }} btn-sm" href="{{ route('panel.billing.invoices.show', $invoice) }}">
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
