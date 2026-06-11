@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_invoices'))

@section('title', __('panel.nav.admin_invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_invoices')">
            @if($invoices->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.billing.number'),
                    __('panel.billing.type'),
                    __('panel.common.customer'),
                    __('panel.billing.issue_date'),
                    __('panel.common.status'),
                    __('panel.common.total'),
                ]">
                    @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->number }}</td>
                            <td>{{ $invoice->type->label() }}</td>
                            <td>{{ $invoice->customer?->company_name ?? $invoice->customer?->email }}</td>
                            <td>{{ $invoice->issue_date?->format('d.m.Y') }}</td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $invoices->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
