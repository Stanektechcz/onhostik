@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_system');
    $breadcrumbItems = [__('panel.nav.admin_system') => ''];
@endphp

@section('title', __('panel.nav.admin_system'))

@section('content')
    <div class="container-fluid">
        @php
            $allOk    = collect($checks)->every(fn ($c) => $c['ok']);
            $failCount = collect($checks)->filter(fn ($c) => !$c['ok'])->count();
            $healthyProviders = collect($providers)->filter(fn ($p) => $p['health'] === 'healthy')->count();
            $errorProviders   = collect($providers)->filter(fn ($p) => $p['health'] === 'error')->count();
        @endphp

        <div class="row mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-{{ $allOk ? 'success' : 'danger' }} rounded p-2">
                                    <i data-feather="{{ $allOk ? 'check-circle' : 'x-circle' }}"
                                       class="font-{{ $allOk ? 'success' : 'danger' }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600 font-{{ $allOk ? 'success' : 'danger' }}">
                                        {{ $allOk ? 'OK' : $failCount . ' selhání' }}
                                    </h5>
                                    <span class="f-light f-12">System checks</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-success rounded p-2"><i data-feather="link" class="font-success"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $healthyProviders }}</h5>
                                    <span class="f-light f-12">Integrace healthy</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-{{ $errorProviders > 0 ? 'danger' : 'success' }} rounded p-2">
                                    <i data-feather="alert-circle" class="font-{{ $errorProviders > 0 ? 'danger' : 'success' }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $errorProviders > 0 ? 'font-danger' : '' }}">{{ $errorProviders }}</h5>
                                    <span class="f-light f-12">Integrace error</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-primary rounded p-2"><i data-feather="cpu" class="font-primary"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION }}</h5>
                                    <span class="f-light f-12">PHP {{ app()->environment() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.admin.system_checks')">
                    <div class="activity-log">
                        <div class="basic-timeline">
                            <ul>
                                @foreach($checks as $check)
                                    <li>
                                        <div class="timeline-dot-{{ $check['ok'] ? 'success' : 'danger' }}"></div>
                                        <div class="ms-4 pb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i data-feather="{{ $check['ok'] ? 'check-circle' : 'x-circle' }}"
                                                   class="font-{{ $check['ok'] ? 'success' : 'danger' }}"
                                                   style="width:14px;height:14px;flex-shrink:0"></i>
                                                <span class="f-w-600">{{ $check['name'] }}</span>
                                                <span class="badge badge-light-{{ $check['ok'] ? 'success' : 'danger' }} ms-1">
                                                    {{ $check['ok'] ? 'OK' : 'FAIL' }}
                                                </span>
                                            </div>
                                            <p class="f-light f-12 mb-0 mt-1">{{ $check['detail'] }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-panel.card>
            </div>
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.admin.provider_health')">
                    <x-panel.data-table :headers="[__('panel.admin.provider'), __('panel.admin.health'), 'Flags']">
                        @foreach($providers as $provider)
                            @php
                                $healthColor = ['healthy' => 'success', 'error' => 'danger', 'untested' => 'warning', 'inactive' => 'secondary'][$provider['health']] ?? 'secondary';
                            @endphp
                            <tr>
                                <td>
                                    <span class="f-w-600">{{ $provider['label'] }}</span><br>
                                    <span class="f-light f-12">{{ $provider['provider'] }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light-{{ $healthColor }}">{{ $provider['health'] }}</span>
                                </td>
                                <td>
                                    @if($provider['mock'])
                                        <span class="badge badge-light-warning">{{ __('panel.admin.mock_badge') }}</span>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>
                </x-panel.card>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <x-panel.card title="Runtime informace">
                    <div class="d-flex gap-4 flex-wrap f-12">
                        <span><span class="f-light">PHP:</span> <strong>{{ PHP_VERSION }}</strong></span>
                        <span><span class="f-light">Laravel:</span> <strong>{{ app()->version() }}</strong></span>
                        <span><span class="f-light">Prostředí:</span> <strong>{{ app()->environment() }}</strong></span>
                        <span><span class="f-light">Debug:</span>
                            <span class="badge badge-light-{{ config('app.debug') ? 'danger' : 'success' }}">
                                {{ config('app.debug') ? 'ON' : 'OFF' }}
                            </span>
                        </span>
                        <span><span class="f-light">Fronta:</span> <strong>{{ config('queue.default') }}</strong></span>
                        <span><span class="f-light">Mail:</span> <strong>{{ config('mail.default') }}</strong></span>
                        <span><span class="f-light">Provisioning mock:</span>
                            <span class="badge badge-light-{{ config('provisioning.mock_mode') ? 'warning' : 'success' }}">
                                {{ config('provisioning.mock_mode') ? 'ON' : 'OFF' }}
                            </span>
                        </span>
                    </div>
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
