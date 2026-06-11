@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_servers'))

@section('title', __('panel.nav.admin_servers'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_servers')">
            @if($servers->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    'Server',
                    __('panel.admin.driver'),
                    __('panel.common.status'),
                    __('panel.admin.services_count'),
                    __('panel.admin.capacity'),
                    __('panel.admin.default'),
                    'Mock',
                ]">
                    @foreach($servers as $server)
                        <tr>
                            <td>#{{ $server->id }}</td>
                            <td>{{ $server->name }}</td>
                            <td>{{ $server->driver->label() }}</td>
                            <td>
                                <span class="badge badge-light-{{ $server->status === 'active' ? 'success' : 'warning' }}">
                                    {{ $server->status }}
                                </span>
                            </td>
                            <td>{{ $server->services_count }}</td>
                            <td>{{ $server->current_services }}/{{ $server->max_services ?? '∞' }}</td>
                            <td>{{ $server->is_default ? __('panel.common.yes') : __('panel.common.no') }}</td>
                            <td>
                                @if($server->mock_mode)
                                    <span class="badge badge-light-warning">{{ __('panel.admin.mock_badge') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $servers->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
