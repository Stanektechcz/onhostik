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
                        <tr>
                            <td>{{ $invoice->number }}</td>
                            <td>{{ $invoice->type->label() }}</td>
                            <td>{{ $invoice->issue_date?->format('d.m.Y') }}</td>
                            <td>{{ $invoice->due_date?->format('d.m.Y') }}</td>
                            <td><x-panel.status-badge :status="$invoice->status" /></td>
                            <td><x-panel.money :money="$invoice->total" /></td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('panel.billing.invoices.show', $invoice) }}">
                                    {{ __('panel.common.detail') }}
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
