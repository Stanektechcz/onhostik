@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_integrations');
    $breadcrumbItems = [__('panel.nav.admin_integrations') => ''];
@endphp

@section('title', __('panel.nav.admin_integrations'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_integrations')">
            <x-panel.data-table :headers="[
                __('panel.admin.provider'),
                __('panel.admin.health'),
                __('panel.admin.active'),
                __('panel.admin.mock_mode'),
                __('panel.admin.dry_run'),
                __('panel.admin.last_success'),
                __('panel.common.actions'),
            ]">
                @foreach($integrations as $integration)
                    <tr>
                        <td class="f-w-600">{{ $integration->label }}<br><span class="f-light f-12">{{ $integration->provider }}</span></td>
                        <td>
                            @php($health = $integration->healthStatus())
                            <span class="badge badge-light-{{ ['healthy' => 'success', 'error' => 'danger', 'untested' => 'warning', 'inactive' => 'gray'][$health] ?? 'gray' }}">{{ $health }}</span>
                        </td>
                        <td>{{ $integration->is_active ? __('panel.common.yes') : __('panel.common.no') }}</td>
                        <td>@if($integration->mock_mode)<span class="badge badge-light-warning">{{ __('panel.admin.mock_badge') }}</span>@endif</td>
                        <td>{{ $integration->dry_run ? __('panel.common.yes') : __('panel.common.no') }}</td>
                        <td class="f-12">{{ $integration->last_success_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.integrations.edit', $integration) }}" class="btn btn-outline-primary btn-sm">{{ __('panel.common.detail') }}</a>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        </x-panel.card>

        <x-panel.card title="Payment providers">
            <x-panel.data-table :headers="[__('panel.admin.provider'), __('panel.common.status')]">
                @foreach($paymentProviders as $provider)
                    <tr>
                        <td class="f-w-600">{{ $provider['label'] }}</td>
                        <td class="f-light">{{ $provider['status'] }}</td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        </x-panel.card>
    </div>
@endsection
