@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.credits'))

@section('title', __('panel.nav.credits'))

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <x-panel.stat-widget
                    :label="__('panel.billing.balance')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::format($balance)"
                    icon="dollar-sign"
                    color="primary"
                />
            </div>
        </div>

        <x-panel.card :title="__('panel.billing.history')" :subtitle="__('panel.billing.topup_placeholder')">
            @if($history->isEmpty())
                <p class="f-light mb-0">{{ __('panel.billing.no_transactions') }}</p>
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
