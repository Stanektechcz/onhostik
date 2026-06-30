@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.admin.credit_transactions');
    $breadcrumbItems = [__('panel.admin.wallets') => route('admin.credits.index'), __('panel.admin.credit_transactions') => ''];
@endphp

@section('title', __('panel.admin.credit_transactions'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.admin.credit_transactions')">
            {{-- Filters --}}
            <form method="GET" class="grid grid-cols-12 gap-2 mb-3">
                <div class="col-span-4 sm:col-span-12">
                    <input type="text" name="q" class="form-control" placeholder="{{ __('panel.common.search') }} (e-mail / firma)" value="{{ $search }}">
                </div>
                <div class="col-span-2 sm:col-span-6">
                    <select name="type" class="form-select">
                        <option value="">{{ __('panel.admin.all') }}</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected($typeFilter === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                    @if($search || $typeFilter)
                        <a href="{{ route('admin.credits.transactions') }}" class="btn btn-outline-secondary btn-sm">{{ __('panel.common.reset') }}</a>
                    @endif
                </div>
            </form>

            <x-panel.data-table :headers="[
                __('panel.common.date'),
                __('panel.common.customer'),
                __('panel.billing.type'),
                __('panel.billing.description'),
                __('panel.billing.amount'),
                __('panel.billing.balance_after'),
            ]">
                @forelse($transactions as $transaction)
                    <tr>
                        <td class="f-12">{{ $transaction->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($transaction->customer)
                                <a href="{{ route('admin.customers.show', $transaction->customer) }}" class="f-12">
                                    {{ $transaction->customer->user?->name ?? $transaction->customer->email }}
                                </a>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td><x-panel.status-badge :status="$transaction->type" /></td>
                        <td class="f-light f-12">{{ $transaction->description }}</td>
                        <td><x-panel.money :money="$transaction->amount" /></td>
                        <td><x-panel.money :money="$transaction->balance_after" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                @endforelse
            </x-panel.data-table>
            {{ $transactions->links() }}
        </x-panel.card>
    </div>
@endsection
