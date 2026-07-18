@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.orders');
    $breadcrumbItems = [__('panel.nav.orders') => ''];
@endphp

@section('title', __('panel.nav.orders'))

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget :label="__('panel.orders.total')"
                :value="$countTotal" icon="shopping-cart" color="primary" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget :label="__('panel.orders.status_active')"
                :value="$countActive" icon="check-circle" color="success" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget :label="__('panel.orders.status_pending')"
                :value="$countPending" icon="clock" color="warning" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget :label="__('panel.orders.status_cancelled')"
                :value="$countCancelled" icon="x-circle" color="danger" />
        </div>
    </div>

    <div class="container common-order-history">
        <div class="grid grid-cols-12 card-gap">

            {{-- Filter card --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('panel.orders.index') }}">
                            <div class="grid grid-cols-12 gap-3 custom-input">
                                <div class="col-span-3 xl:col-span-6 md:col-span-12">
                                    <label class="form-label">Od:</label>
                                    <input class="form-control" type="date" name="from" value="{{ request('from') }}"
                                           placeholder="dd/mm/yyyy">
                                </div>
                                <div class="col-span-3 xl:col-span-6 md:col-span-12">
                                    <label class="form-label">Do:</label>
                                    <input class="form-control" type="date" name="to" value="{{ request('to') }}"
                                           placeholder="dd/mm/yyyy">
                                </div>
                                <div class="col-span-3 xl:col-span-6 md:col-span-12">
                                    <label class="form-label">{{ __('panel.common.status') }}</label>
                                    <select class="form-select" name="status">
                                        <option value="">Všechny</option>
                                        @foreach(\App\Domains\Billing\Enums\OrderStatus::cases() as $s)
                                            <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                                                {{ $s->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-3 xl:col-span-6 md:col-span-12 flex justify-start items-end">
                                    <button class="btn btn-primary font-medium text-white" type="submit">
                                        Filtrovat
                                    </button>
                                    @if(request()->hasAny(['from','to','status']))
                                        <a href="{{ route('panel.orders.index') }}" class="btn btn-outline-secondary ms-2">
                                            Resetovat
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Orders table --}}
            <div class="col-span-12">
                <div class="card heading-space">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>{{ __('panel.nav.orders') }}</h5>
                            <div class="card-header-right-icon">
                                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary text-white font-medium btn-sm">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i>
                                    {{ __('panel.nav.new_order') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body pt-0 px-0">
                        @if($orders->isEmpty())
                            <div class="text-center py-5 px-3">
                                <i data-feather="shopping-cart" style="width:48px;height:48px;" class="text-muted mb-3 block mx-auto"></i>
                                <h6 class="f-light mt-2">{{ __('panel.orders.none') }}</h6>
                                <p class="f-light f-12 mb-3">Ještě jste neobjednali žádnou službu.</p>
                                <a href="{{ route('panel.orders.create') }}" class="btn btn-primary btn-sm">
                                    <i data-feather="plus" style="width:13px;height:13px;"></i>
                                    {{ __('panel.nav.new_order') }}
                                </a>
                            </div>
                        @else
                            <div class="grid grid-cols-12">
                                <div class="col-span-12">
                                    <div class="order-history-wrapper">
                                        <div class="recent-table overflow-x-auto custom-scrollbar">
                                            <table class="table" id="order-history-table">
                                                <thead>
                                                    <tr>
                                                        <th></th>
                                                        <th class="[@media(max-width:1260px)]:!min-w-[120px]">
                                                            <span class="f-light font-semibold">{{ __('panel.orders.number') }}</span>
                                                        </th>
                                                        <th class="[@media(max-width:1260px)]:!min-w-[180px]">
                                                            <span class="f-light font-semibold">{{ __('panel.common.date') }}</span>
                                                        </th>
                                                        <th class="[@media(min-width:1261px)_and_(max-width:1417px)]:hidden [@media(max-width:1260px)]:!min-w-[160px]">
                                                            <span class="f-light font-semibold">Tarif</span>
                                                        </th>
                                                        <th class="[@media(max-width:1260px)]:!min-w-[130px]">
                                                            <span class="f-light font-semibold">{{ __('panel.common.total') }}</span>
                                                        </th>
                                                        <th class="[@media(max-width:1260px)]:!min-w-[140px]">
                                                            <span class="f-light font-semibold">{{ __('panel.common.status') }}</span>
                                                        </th>
                                                        <th class="[@media(max-width:1260px)]:!min-w-[140px]">
                                                            <span class="f-light font-semibold">Platba</span>
                                                        </th>
                                                        <th><span class="f-light font-semibold">{{ __('panel.common.actions') }}</span></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($orders as $order)
                                                    @php
                                                        $firstItem = $order->items->first();
                                                        $latestInvoice = $order->invoices->sortByDesc('id')->first();
                                                        $payStatus = $latestInvoice?->status;
                                                    @endphp
                                                    <tr class="inbox-data">
                                                        <td></td>
                                                        <td class="[@media(max-width:1260px)]:!min-w-[120px]">
                                                            <a href="{{ route('panel.orders.show', $order) }}" class="f-w-600">
                                                                #{{ strtoupper(substr($order->uuid, 0, 8)) }}
                                                            </a>
                                                        </td>
                                                        <td class="[@media(max-width:1260px)]:!min-w-[180px]">
                                                            <p class="c-o-light">{{ $order->created_at?->format('d.m.Y H:i') }}</p>
                                                        </td>
                                                        <td class="[@media(min-width:1261px)_and_(max-width:1417px)]:hidden [@media(max-width:1260px)]:!min-w-[160px]">
                                                            <p class="c-o-light">{{ $firstItem?->description ?? '—' }}</p>
                                                        </td>
                                                        <td class="[@media(max-width:1260px)]:!min-w-[130px]">
                                                            <p class="c-o-light"><x-panel.money :money="$order->total" /></p>
                                                        </td>
                                                        <td class="[@media(max-width:1260px)]:!min-w-[140px]">
                                                            <x-panel.status-badge :status="$order->status" />
                                                        </td>
                                                        <td class="[@media(max-width:1260px)]:!min-w-[140px]">
                                                            @if($payStatus)
                                                                <x-panel.status-badge :status="$payStatus" />
                                                            @else
                                                                <span class="f-light">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="common-align gap-2 justify-start">
                                                                <a class="square-white" href="{{ route('panel.orders.show', $order) }}"
                                                                   data-bs-toggle="tooltip" data-bs-placement="top" data-tooltip="{{ __('panel.common.detail') }}">
                                                                    <svg><use href="{{ asset('panel/svg/icon-sprite.svg#fill-view') }}"></use></svg>
                                                                </a>
                                                                @if($latestInvoice)
                                                                <a class="square-white" href="{{ route('panel.billing.invoices.show', $latestInvoice) }}"
                                                                   data-bs-toggle="tooltip" data-bs-placement="top" data-tooltip="Faktura">
                                                                    <i data-feather="file-text" style="width:14px;height:14px;"></i>
                                                                </a>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="px-3 pt-2">
                                {{ $orders->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
