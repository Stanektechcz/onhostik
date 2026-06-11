@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_services'))

@section('title', __('panel.nav.admin_services'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_services')">
            @if($services->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.services.label'),
                    __('panel.common.customer'),
                    __('panel.services.product'),
                    __('panel.services.server'),
                    __('panel.services.external_id'),
                    __('panel.services.domain'),
                    __('panel.common.status'),
                ]">
                    @foreach($services as $service)
                        <tr>
                            <td>#{{ $service->id }}</td>
                            <td>{{ $service->label }}</td>
                            <td>{{ $service->customer?->company_name ?? $service->customer?->email }}</td>
                            <td>{{ $service->product?->name }}</td>
                            <td>{{ $service->server?->name ?? '—' }}</td>
                            <td>{{ $service->external_id ?? '—' }}</td>
                            <td>{{ $service->domainRegistration?->fqdn() ?? '—' }}</td>
                            <td><x-panel.status-badge :status="$service->status" /></td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $services->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
