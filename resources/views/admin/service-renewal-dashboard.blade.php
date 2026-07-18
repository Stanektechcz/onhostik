@extends('layouts.panel')

@php($breadcrumbTitle = 'Dashboard obnov')
@php($breadcrumbItems = ['Služby' => route('admin.services.index'), 'Dashboard obnov' => ''])

@section('title', 'Dashboard obnov služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Po splatnosti"
                :value="$totalOverdue"
                icon="alert-triangle"
                color="danger" />
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                :label="'Obnova do ' . $window . ' dní'"
                :value="$totalDueSoon"
                icon="clock"
                color="warning" />
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Bez auto-obnovy"
                :value="$noAutoRenew"
                icon="x-circle"
                color="secondary" />
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                :label="'Má auto-obnovu'"
                :value="$totalDueSoon - $noAutoRenew"
                icon="refresh-cw"
                color="success" />
        </div>
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 mb-3">
        @foreach([7, 14, 30, 60, 90] as $d)
        <a href="{{ route('admin.services.renewal-dashboard', ['days' => $d]) }}"
           class="btn btn-sm {{ $window === $d ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $d }} dní
        </a>
        @endforeach
    </div>

    {{-- Overdue --}}
    @if($overdue->isNotEmpty())
    <x-panel.card title="Po splatnosti (okamžitě nutné řešení)">
        <x-panel.data-table :headers="['Služba', 'Zákazník', 'Produkt', 'Splatnost', 'Auto-obnova', '']">
            @foreach($overdue as $service)
            <tr>
                <td class="f-w-500">{{ $service->label }}</td>
                <td class="f-light f-12">{{ $service->customer?->email }}</td>
                <td class="f-light f-12">{{ $service->product?->getTranslation('name', 'cs') ?? '—' }}</td>
                <td class="txt-danger f-w-600">{{ $service->next_due_date?->format('d.m.Y') }}</td>
                <td>
                    @if($service->auto_renew)
                        <span class="badge badge-light-success">Ano</span>
                    @else
                        <span class="badge badge-light-secondary">Ne</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.services.show', $service) }}" class="btn btn-outline-danger btn-xs">Detail</a>
                </td>
            </tr>
            @endforeach
        </x-panel.data-table>
    </x-panel.card>
    @endif

    {{-- Due soon --}}
    <x-panel.card :title="'Obnova do ' . $window . ' dní'">
        @if($dueSoon->isEmpty())
            <p class="f-light f-12 text-center py-4 mb-0">Žádné služby se neobnovují v tomto okně.</p>
        @else
            <x-panel.data-table :headers="['Služba', 'Zákazník', 'Produkt', 'Splatnost', 'Auto-obnova', '']">
                @foreach($dueSoon as $service)
                <tr>
                    <td class="f-w-500">{{ $service->label }}</td>
                    <td class="f-light f-12">{{ $service->customer?->email }}</td>
                    <td class="f-light f-12">{{ $service->product?->getTranslation('name', 'cs') ?? '—' }}</td>
                    <td class="{{ $service->next_due_date?->diffInDays() <= 7 ? 'txt-warning f-w-600' : 'f-12' }}">
                        {{ $service->next_due_date?->format('d.m.Y') }}
                        <span class="f-light f-11 ms-1">({{ $service->next_due_date?->diffForHumans() }})</span>
                    </td>
                    <td>
                        @if($service->auto_renew)
                            <span class="badge badge-light-success">Ano</span>
                        @else
                            <span class="badge badge-light-secondary">Ne</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.services.show', $service) }}" class="btn btn-outline-primary btn-xs">Detail</a>
                    </td>
                </tr>
                @endforeach
            </x-panel.data-table>
            {{ $dueSoon->links() }}
        @endif
    </x-panel.card>
</div>
@endsection
