@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_provisioning');
    $breadcrumbItems = [__('panel.nav.admin_provisioning') => ''];
@endphp

@section('title', __('panel.nav.admin_provisioning'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI row --}}
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $failedCount > 0 ? 'danger' : 'success' }}">
                        <span class="f-light">Selhané úkoly</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $failedCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="alert-triangle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $pendingCount > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">Čekající / probíhající</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $pendingCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="loader"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $reviewCount > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">Vyžaduje review</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $reviewCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="eye"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body success">
                        <span class="f-light">Dokončeno (celkem)</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $successCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_provisioning')">
            <form method="GET" action="{{ route('admin.provisioning.index') }}" class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                <select class="form-select w-auto" name="status">
                    <option value="">{{ __('panel.admin.all') }} stav</option>
                    @foreach(\App\Domains\Provisioning\Enums\TaskStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select class="form-select w-auto" name="operation">
                    <option value="">{{ __('panel.admin.all') }} operace</option>
                    @foreach($operations as $op)
                        <option value="{{ $op }}" @selected(($operationFilter ?? '') === $op)>{{ $op }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || ($operationFilter ?? ''))
                    <a href="{{ route('admin.provisioning.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $tasks->total() }} úkolů</span>
            </form>

            @if($tasks->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="cpu" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                </div>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.services.label'),
                    __('panel.common.customer'),
                    __('panel.admin.operation'),
                    __('panel.common.status'),
                    __('panel.admin.attempts'),
                    'Spuštěno',
                    __('panel.admin.error'),
                    __('panel.common.actions'),
                ]">
                    @foreach($tasks as $task)
                        <tr>
                            <td class="f-12">#{{ $task->id }}</td>
                            <td>
                                @if($task->service)
                                    <a href="{{ route('admin.services.show', $task->service) }}" class="f-w-500">
                                        {{ $task->service->label ?? '—' }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="f-light f-12">{{ $task->service?->customer?->company_name ?? $task->service?->customer?->email }}</td>
                            <td><code class="f-12">{{ $task->operation }}</code></td>
                            <td><x-panel.status-badge :status="$task->status" /></td>
                            <td class="f-12">{{ $task->attempts }}/{{ $task->max_attempts }}</td>
                            <td class="f-12 f-light">{{ $task->started_at?->format('d.m. H:i') ?? '—' }}</td>
                            <td class="f-light f-12" style="max-width: 200px; word-break: break-word;">
                                {{ \Illuminate\Support\Str::limit($task->error_message ?? '—', 60) }}
                            </td>
                            <td>
                                @if($task->canRetry())
                                    <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i data-feather="refresh-cw" style="width:12px;height:12px"></i>
                                            {{ __('panel.admin.retry') }}
                                        </button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $tasks->withQueryString()->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
