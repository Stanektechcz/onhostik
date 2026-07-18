@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_servers');
    $breadcrumbItems = [__('panel.nav.admin_servers') => ''];
@endphp

@section('title', __('panel.nav.admin_servers'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />
        @error('server')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

        <div class="grid grid-cols-12 gap-3 mb-3">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="bg-light-primary rounded p-2"><i data-feather="server" class="font-primary"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $totalCount }}</h5>
                                    <span class="f-light f-12">Celkem serverů</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="bg-light-success rounded p-2"><i data-feather="check-circle" class="font-success"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $activeCount }}</h5>
                                    <span class="f-light f-12">Aktivní</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="bg-light-warning rounded p-2"><i data-feather="eye-off" class="font-warning"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $mockCount }}</h5>
                                    <span class="f-light f-12">Mock mode</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="bg-light-{{ $failedHealth > 0 ? 'danger' : 'success' }} rounded p-2">
                                    <i data-feather="{{ $failedHealth > 0 ? 'x-circle' : 'activity' }}"
                                       class="font-{{ $failedHealth > 0 ? 'danger' : 'success' }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $failedHealth > 0 ? 'font-danger' : '' }}">{{ $failedHealth }}</h5>
                                    <span class="f-light f-12">Health FAIL</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end mb-3">
            <a href="{{ route('admin.servers.create') }}" class="btn btn-primary btn-sm">
                <i data-feather="plus" style="width:14px;height:14px"></i> Přidat server
            </a>
        </div>

        <x-panel.card :title="__('panel.nav.admin_servers')">
            @if($servers->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="hard-drive" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                    <p class="f-light f-12 mb-0">Žádné servery. Přidejte server pomocí tlačítka výše.</p>
                </div>
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
                    'Health',
                    __('panel.common.actions'),
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
                            <td>
                                @if($server->last_health_check_at)
                                    <span class="badge badge-light-{{ $server->last_health_ok ? 'success' : 'danger' }}">
                                        {{ $server->last_health_ok ? 'OK' : 'FAIL' }}
                                    </span>
                                    <span class="f-light f-12 block">{{ $server->last_health_check_at->format('d.m. H:i') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <div class="flex gap-1 flex-wrap">
                                    <a href="{{ route('admin.servers.edit', $server) }}" class="btn btn-outline-secondary btn-sm">Upravit</a>
                                    <form method="POST" action="{{ route('admin.servers.test', $server) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.test_connection') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.servers.destroy', $server) }}"
                                          onsubmit="return confirm('Smazat server {{ $server->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Smazat</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $servers->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
