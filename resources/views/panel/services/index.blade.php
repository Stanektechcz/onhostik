@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.services');
    $breadcrumbItems = [__('panel.nav.services') => ''];
@endphp

@section('title', __('panel.nav.services'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-4">
                <x-panel.stat-widget :label="__('panel.services.total')"
                    :value="$countTotal" icon="server" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-4">
                <x-panel.stat-widget :label="__('panel.services.status_active')"
                    :value="$countActive" icon="check-circle" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-4">
                <x-panel.stat-widget :label="__('panel.services.status_suspended')"
                    :value="$countSuspended" icon="pause-circle" color="warning" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.services')">
            <x-slot name="headerRight">
                <a href="{{ route('panel.services.calendar') }}"
                   class="btn btn-outline-secondary btn-sm"
                   title="Stáhnout termíny obnov jako iCal">
                    <i data-feather="calendar" style="width:13px;height:13px;" class="me-1"></i>
                    Export do kalendáře
                </a>
            </x-slot>
            @if($services->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="server" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.services.none') }}</h6>
                    <p class="f-light f-12 mb-3">Nemáte ještě žádné aktivní služby.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary btn-sm">
                        <i data-feather="plus" style="width:13px;height:13px;"></i>
                        {{ __('panel.nav.new_order') }}
                    </a>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.services.label'),
                    __('panel.services.product'),
                    __('panel.common.status'),
                    __('panel.services.next_due'),
                    '',
                ]">
                    @foreach($services as $service)
                        @php
                            $isDueSoon = $service->next_due_date && $service->next_due_date->isPast();
                        @endphp
                        <tr>
                            <td class="f-w-500">{{ $service->label }}</td>
                            <td class="f-light">{{ $service->product?->name }}</td>
                            <td><x-panel.status-badge :status="$service->status" /></td>
                            <td class="{{ $isDueSoon ? 'txt-danger f-w-600' : 'f-12' }}">
                                {{ $service->next_due_date?->format('d.m.Y') ?? '—' }}
                                @if($isDueSoon)
                                    <i data-feather="alert-triangle" style="width:11px;height:11px" class="txt-danger ms-1"></i>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-outline-primary btn-xs" href="{{ route('panel.services.show', $service) }}">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $services->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
