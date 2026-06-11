@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.services'))

@section('title', __('panel.nav.services'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.services')">
            @if($services->isEmpty())
                <p class="f-light mb-0">{{ __('panel.services.none') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.services.label'),
                    __('panel.services.product'),
                    __('panel.common.status'),
                    __('panel.services.next_due'),
                    '',
                ]">
                    @foreach($services as $service)
                        <tr>
                            <td>{{ $service->label }}</td>
                            <td>{{ $service->product?->name }}</td>
                            <td><x-panel.status-badge :status="$service->status" /></td>
                            <td>{{ $service->next_due_date?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('panel.services.show', $service) }}">
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
