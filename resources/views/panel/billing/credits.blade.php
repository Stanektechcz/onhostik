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

        <div class="grid grid-cols-12 card-gap">
            {{-- Credit that will be forfeited unless spent. The reminder e-mail
                 already existed; the page it points at did not show this. --}}
            @if($expiring->isNotEmpty())
                <div class="col-span-6 md:col-span-12">
                    <x-panel.card :title="__('panel.billing.expiring_title')" :subtitle="__('panel.billing.expiring_hint')">
                        <x-panel.data-table :headers="[
                            __('panel.billing.amount'),
                            __('panel.billing.expiring_on'),
                        ]">
                            @foreach($expiring as $deposit)
                                <tr>
                                    <td><x-panel.money :money="$deposit->amount" /></td>
                                    <td>
                                        {{ $deposit->expires_at?->format('d.m.Y') }}
                                        @if($deposit->expires_at?->isBefore(now()->addDays(30)))
                                            <span class="badge badge-light-warning f-10">{{ __('panel.billing.expiring_soon') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                </div>
            @endif

            <div class="col-span-{{ $expiring->isNotEmpty() ? '6' : '12' }} md:col-span-12">
                <x-panel.card :title="__('panel.billing.auto_topup_title')">
                    @if($autoTopup !== null && ($autoTopup['enabled'] ?? false))
                        <p class="mb-2">
                            {{ __('panel.billing.auto_topup_on', [
                                'threshold' => \App\Domains\Shared\Support\MoneyFormatter::format(
                                    \Brick\Money\Money::ofMinor((int) ($autoTopup['threshold_minor'] ?? 0), $balance->getCurrency()->getCurrencyCode())
                                ),
                                'amount' => \App\Domains\Shared\Support\MoneyFormatter::format(
                                    \Brick\Money\Money::ofMinor((int) ($autoTopup['topup_minor'] ?? 0), $balance->getCurrency()->getCurrencyCode())
                                ),
                            ]) }}
                        </p>
                        @if($defaultCard !== null)
                            <p class="f-12 f-light mb-3">
                                {{ __('panel.billing.auto_topup_card', ['card' => '•••• ' . $defaultCard->last4]) }}
                            </p>
                        @else
                            <div class="alert alert-light-warning f-12" role="alert">
                                {{ __('panel.billing.auto_topup_no_card') }}
                            </div>
                        @endif
                    @else
                        <p class="f-m-light mb-3">{{ __('panel.billing.auto_topup_off') }}</p>
                    @endif
                    <a href="{{ route('panel.billing.auto-topup.show') }}" class="btn btn-light btn-sm">
                        {{ __('panel.billing.auto_topup_manage') }}
                    </a>
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
