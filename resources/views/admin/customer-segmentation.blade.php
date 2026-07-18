@extends('layouts.panel')

@php($breadcrumbTitle = 'Segmentace zákazníků')
@php($breadcrumbItems = ['Zákazníci' => route('admin.customers.index'), 'Segmentace' => ''])

@section('title', 'Segmentace zákazníků')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Segment summary cards --}}
    <div class="grid grid-cols-12 card-gap">
        @foreach($segments as $key => $data)
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                :label="$data['segment']->label()"
                :value="$data['count']"
                icon="users"
                :color="$data['segment']->color()" />
        </div>
        @endforeach
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Bez segmentu"
                :value="$unassignedCount"
                icon="help-circle"
                color="secondary" />
        </div>
    </div>

    {{-- Segment detail table --}}
    <x-panel.card title="Přehled segmentů">
        <div class="table-responsive">
            <table class="table table-hover table-borderless mb-0">
                <thead>
                    <tr class="border-bottom">
                        <th class="f-12 f-w-600">Segment</th>
                        <th class="f-12 f-w-600 text-right">Zákazníků</th>
                        <th class="f-12 f-w-600 text-right">Průměrné zdraví</th>
                        <th class="f-12 f-w-600 text-right">Průměrné riziko odchodu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($segments as $key => $data)
                    <tr>
                        <td>
                            <span class="badge badge-light-{{ $data['segment']->color() }}">
                                {{ $data['segment']->label() }}
                            </span>
                        </td>
                        <td class="text-right f-w-600">{{ $data['count'] }}</td>
                        <td class="text-right">
                            @if($data['avg_health'] > 0)
                                <span class="{{ $data['avg_health'] >= 70 ? 'txt-success' : ($data['avg_health'] >= 40 ? 'txt-warning' : 'txt-danger') }}">
                                    {{ $data['avg_health'] }} / 100
                                </span>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($data['avg_churn_risk'] > 0)
                                <span class="{{ $data['avg_churn_risk'] <= 30 ? 'txt-success' : ($data['avg_churn_risk'] <= 60 ? 'txt-warning' : 'txt-danger') }}">
                                    {{ $data['avg_churn_risk'] }} / 100
                                </span>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.customers.segmentation', ['segment' => $key]) }}"
                               class="btn btn-outline-{{ $data['segment']->color() }} btn-xs">
                                Zobrazit
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    <tr>
                        <td><span class="badge badge-light-secondary">Bez segmentu</span></td>
                        <td class="text-right f-w-600">{{ $unassignedCount }}</td>
                        <td class="text-right f-light">—</td>
                        <td class="text-right f-light">—</td>
                        <td>
                            <a href="{{ route('admin.customers.segmentation', ['segment' => 'unknown']) }}"
                               class="btn btn-outline-secondary btn-xs">
                                Zobrazit
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-panel.card>

    {{-- Customer list (filterable) --}}
    @if($selected !== '')
    <x-panel.card :title="'Zákazníci — ' . ($selected === 'unknown' ? 'Bez segmentu' : (collect($segments)->get($selected)['segment']->label() ?? $selected))">
        <x-slot name="headerRight">
            <a href="{{ route('admin.customers.segmentation') }}" class="btn btn-outline-secondary btn-xs">
                Vše
            </a>
        </x-slot>

        @if($customers->isEmpty())
            <p class="f-light f-12 text-center py-4 mb-0">Žádní zákazníci v tomto segmentu.</p>
        @else
            <x-panel.data-table :headers="['Zákazník', 'E-mail', 'Zdraví', 'Riziko odchodu', '']">
                @foreach($customers as $customer)
                <tr>
                    <td class="f-w-500">{{ $customer->user?->name ?? '—' }}</td>
                    <td class="f-light f-12">{{ $customer->email }}</td>
                    <td>
                        @if($customer->health_score !== null)
                            <span class="{{ $customer->health_score >= 70 ? 'txt-success' : ($customer->health_score >= 40 ? 'txt-warning' : 'txt-danger') }}">
                                {{ $customer->health_score }}
                            </span>
                        @else
                            <span class="f-light">—</span>
                        @endif
                    </td>
                    <td>
                        @if($customer->churn_risk_score !== null)
                            <span class="{{ $customer->churn_risk_score <= 30 ? 'txt-success' : ($customer->churn_risk_score <= 60 ? 'txt-warning' : 'txt-danger') }}">
                                {{ $customer->churn_risk_score }}
                            </span>
                        @else
                            <span class="f-light">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.customers.show', $customer) }}"
                           class="btn btn-outline-primary btn-xs">Detail</a>
                    </td>
                </tr>
                @endforeach
            </x-panel.data-table>
            {{ $customers->links() }}
        @endif
    </x-panel.card>
    @endif
</div>
@endsection
