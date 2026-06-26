@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_backups');
    $breadcrumbItems = [__('panel.nav.admin_backups') => ''];
@endphp

@section('title', __('panel.nav.admin_backups'))

@section('content')
    <div class="container-fluid">

        {{-- KPI row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $failedCount > 0 ? 'danger' : 'success' }}">
                        <span class="f-light">{{ __('panel.admin.failed_backups') }}</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $failedCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="alert-triangle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $runningCount > 0 ? 'info' : 'secondary' }}">
                        <span class="f-light">Probíhající zálohy</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $runningCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="loader"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body primary">
                        <span class="f-light">Aktivní politiky</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $policyCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="shield"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body success">
                        <span class="f-light">Celková velikost záloh</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>
                                @if($totalSizeMb >= 1024)
                                    {{ number_format($totalSizeMb / 1024, 1) }} GB
                                @else
                                    {{ $totalSizeMb }} MB
                                @endif
                            </h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="database"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Backup jobs --}}
            <div class="col-xl-8">
                <x-panel.card :title="__('panel.admin.backup_jobs')">
                    @if($jobs->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        <x-panel.data-table :headers="[
                            __('panel.common.date'),
                            __('panel.services.label'),
                            __('panel.common.customer'),
                            'Typ',
                            __('panel.common.status'),
                            'Trvání',
                            'Velikost',
                        ]">
                            @foreach($jobs as $job)
                                @php
                                    $duration = ($job->started_at && $job->finished_at)
                                        ? $job->started_at->diffInSeconds($job->finished_at)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="f-12">{{ $job->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        @if($job->service)
                                            <a href="{{ route('admin.services.show', $job->service) }}" class="f-w-500 f-14">
                                                {{ $job->service->label ?? '—' }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-light f-12">{{ $job->service?->customer?->email ?? '—' }}</td>
                                    <td><span class="badge badge-light-secondary">{{ $job->type }}</span></td>
                                    <td><x-panel.status-badge :status="$job->status" /></td>
                                    <td class="f-12 f-light">
                                        @if($duration !== null)
                                            {{ $duration >= 60 ? floor($duration / 60) . 'm ' . ($duration % 60) . 's' : $duration . 's' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-12">{{ $job->size_mb ? $job->size_mb . ' MB' : '—' }}</td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                        {{ $jobs->links() }}
                    @endif
                </x-panel.card>
            </div>

            {{-- Backup policies --}}
            <div class="col-xl-4">
                <x-panel.card :title="__('panel.admin.policies')">
                    @if($policies->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        @foreach($policies as $policy)
                            <div class="border rounded p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="f-w-600 mb-0">
                                            @if($policy->service)
                                                <a href="{{ route('admin.services.show', $policy->service) }}" class="f-w-600">
                                                    {{ $policy->service->label ?? '—' }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </p>
                                        <p class="f-light f-12 mb-0">{{ $policy->service?->customer?->email }}</p>
                                    </div>
                                    @if($policy->is_active)
                                        <span class="badge badge-light-success">aktivní</span>
                                    @else
                                        <span class="badge badge-light-secondary">neaktivní</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-3 mt-2 f-12 f-light">
                                    <span><i data-feather="clock" style="width:11px;height:11px"></i> {{ $policy->frequency }}</span>
                                    <span><i data-feather="trash-2" style="width:11px;height:11px"></i> {{ $policy->retention_days }}d</span>
                                    <span><i data-feather="server" style="width:11px;height:11px"></i> {{ $policy->provider }}</span>
                                    <span class="ms-auto">{{ $policy->jobs_count }} jobů</span>
                                </div>
                                @if($policy->last_run_at)
                                    <p class="f-12 f-light mb-0 mt-1">
                                        Naposledy: {{ $policy->last_run_at->format('d.m.Y H:i') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                        {{ $policies->links() }}
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
