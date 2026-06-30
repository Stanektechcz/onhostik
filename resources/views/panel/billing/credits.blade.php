@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.credits');
    $breadcrumbItems = [__('panel.billing.billing') => '#', __('panel.nav.credits') => ''];
@endphp

@section('title', __('panel.nav.credits'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                <x-panel.stat-widget
                    :label="__('panel.billing.balance')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::format($balance)"
                    icon="dollar-sign"
                    color="primary"
                />
            </div>
            <div class="col-span-8 md:col-span-12">
                <x-panel.card :title="__('panel.billing.topup_title')" :subtitle="__('panel.billing.topup_hint')">
                    <form method="POST" action="{{ route('panel.billing.credits.topup') }}" class="grid grid-cols-12 gap-2 items-end">
                        @csrf
                        <div class="col-span-6 sm:col-span-12">
                            <label class="form-label f-12 f-light" for="topup-amount">{{ __('panel.billing.topup_amount') }}</label>
                            <input id="topup-amount" type="number" name="amount" class="form-control"
                                   min="100" max="50000" step="1" value="{{ old('amount', 500) }}" required>
                            @error('amount')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-span-6 sm:col-span-12">
                            <button type="submit" class="btn btn-primary">{{ __('panel.billing.topup_submit') }}</button>
                        </div>
                    </form>
                </x-panel.card>
            </div>
        </div>

        <x-panel.card :title="__('panel.billing.history')">
            @if($history->isEmpty())
                <div class="text-center py-4">
                    <i data-feather="list" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                    <p class="f-light mb-0">{{ __('panel.billing.no_transactions') }}</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.common.date'),
                    __('panel.billing.type'),
                    __('panel.billing.description'),
                    __('panel.billing.amount'),
                    __('panel.billing.balance_after'),
                ]">
                    @foreach($history as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $transaction->type->label() }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td><x-panel.money :money="$transaction->amount" /></td>
                            <td><x-panel.money :money="$transaction->balance_after" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $history->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
