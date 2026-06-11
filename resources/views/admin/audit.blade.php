@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_audit'))

@section('title', __('panel.nav.admin_audit'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_audit')">
            <form method="GET" action="{{ route('admin.logs.audit') }}" class="mb-3 d-flex gap-2 align-items-center">
                <select class="form-select w-auto" name="log">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(['order', 'invoice', 'payment', 'service', 'provisioning', 'domain', 'customer', 'user'] as $logName)
                        <option value="{{ $logName }}" @selected($filter === $logName)>{{ $logName }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
            </form>

            @if($activities->isEmpty())
                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.common.date'),
                    'Log',
                    __('panel.admin.event'),
                    __('panel.admin.subject'),
                    __('panel.admin.causer'),
                    __('panel.admin.properties'),
                ]">
                    @foreach($activities as $activity)
                        <tr>
                            <td>#{{ $activity->id }}</td>
                            <td>{{ $activity->created_at?->format('d.m.Y H:i:s') }}</td>
                            <td><span class="badge badge-light-info">{{ $activity->log_name }}</span></td>
                            <td>{{ $activity->description }}</td>
                            <td class="f-12">
                                {{ class_basename((string) $activity->subject_type) }}
                                @if($activity->subject_id)#{{ $activity->subject_id }}@endif
                            </td>
                            <td>{{ $activity->causer?->name ?? 'system' }}</td>
                            <td class="f-light f-12">
                                {{ \Illuminate\Support\Str::limit(json_encode($activity->properties, JSON_UNESCAPED_UNICODE) ?: '', 80) }}
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $activities->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
