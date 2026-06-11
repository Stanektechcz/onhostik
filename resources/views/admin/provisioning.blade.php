@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_provisioning'))

@section('title', __('panel.nav.admin_provisioning'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_provisioning')">
            <form method="GET" action="{{ route('admin.provisioning.index') }}" class="mb-3 d-flex gap-2 align-items-center">
                <select class="form-select w-auto" name="status">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Provisioning\Enums\TaskStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filter === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
            </form>

            @if($tasks->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.services.label'),
                    __('panel.common.customer'),
                    __('panel.admin.operation'),
                    __('panel.common.status'),
                    __('panel.admin.attempts'),
                    __('panel.admin.error'),
                    __('panel.common.actions'),
                ]">
                    @foreach($tasks as $task)
                        <tr>
                            <td>#{{ $task->id }}</td>
                            <td>{{ $task->service?->label ?? '—' }}</td>
                            <td>{{ $task->service?->customer?->company_name ?? $task->service?->customer?->email }}</td>
                            <td>{{ $task->operation }}</td>
                            <td><x-panel.status-badge :status="$task->status" /></td>
                            <td>{{ $task->attempts }}/{{ $task->max_attempts }}</td>
                            <td class="f-light f-12">{{ \Illuminate\Support\Str::limit($task->error_message ?? '—', 60) }}</td>
                            <td>
                                @if($task->canRetry())
                                    <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
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
                {{ $tasks->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
