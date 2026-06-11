@extends('layouts.panel')

@php($breadcrumbTitle = $service->label)
@php($breadcrumbItems = [__('panel.nav.services') => route('panel.services.index'), $service->label => ''])

@section('title', $service->label)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="$service->label" :subtitle="$service->product?->name">
            <div class="row mb-3">
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                    <x-panel.status-badge :status="$service->status" />
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.services.external_id') }}</p>
                    <p class="mb-0">{{ $service->external_id ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.services.next_due') }}</p>
                    <p class="mb-0">{{ $service->next_due_date?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.services.domain') }}</p>
                    <p class="mb-0">
                        @if($service->domainRegistration)
                            <a href="{{ route('panel.domains.show', $service->domainRegistration) }}">
                                {{ $service->domainRegistration->fqdn() }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>

            @if(!empty($service->resources))
                <h6>{{ __('panel.services.resources') }}</h6>
                <ul class="f-light mb-0 ps-3">
                    @foreach($service->resources as $key => $value)
                        <li>{{ __("front.resources.$key") }}: {{ $value }}</li>
                    @endforeach
                </ul>
            @endif
        </x-panel.card>

        <x-panel.card :title="__('panel.services.tasks')">
            @if($service->provisioningTasks->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.admin.operation'),
                    __('panel.common.status'),
                    __('panel.admin.attempts'),
                    __('panel.common.date'),
                    __('panel.admin.error'),
                ]">
                    @foreach($service->provisioningTasks as $task)
                        <tr>
                            <td>{{ $task->operation }}</td>
                            <td><x-panel.status-badge :status="$task->status" /></td>
                            <td>{{ $task->attempts }}/{{ $task->max_attempts }}</td>
                            <td>{{ $task->finished_at?->format('d.m.Y H:i') ?? $task->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="f-light">{{ $task->error_message ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
            @endif
        </x-panel.card>
    </div>
@endsection
