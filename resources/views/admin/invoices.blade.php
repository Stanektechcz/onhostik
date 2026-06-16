@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_invoices'))

@section('title', __('panel.nav.admin_invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_invoices')">
            <form method="GET" action="{{ route('admin.invoices.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 200px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Billing\Enums\InvoiceStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 260px;"
                       placeholder="Číslo, var. symbol, zákazník…" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || ($search ?? ''))
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $invoices->total() }} faktur</span>
            </form>

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
                            <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
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
