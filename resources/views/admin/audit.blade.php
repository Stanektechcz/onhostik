@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_audit');
    $breadcrumbItems = [__('panel.nav.admin_audit') => ''];
@endphp

@section('title', __('panel.nav.admin_audit'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_audit')">
            {{-- Filter bar --}}
            <form method="GET" action="{{ route('admin.logs.audit') }}" class="mb-4 d-flex gap-2 flex-wrap align-items-center">
                <select class="form-select" style="max-width: 180px;" name="log">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(['order', 'invoice', 'payment', 'service', 'provisioning', 'domain', 'customer', 'user', 'credit', 'product', 'integration', 'support'] as $logName)
                        <option value="{{ $logName }}" @selected($filter === $logName)>{{ $logName }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 240px;"
                       placeholder="Hledat popis…" value="{{ request('q') }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || request('q'))
                    <a href="{{ route('admin.logs.audit') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="f-light f-12">{{ $activities->total() }} záznamů</span>
                    <a href="{{ route('admin.logs.audit.export') . '?' . http_build_query(array_filter(['log' => $filter ?? '', 'from' => request('from'), 'to' => request('to')])) }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i data-feather="download" style="width:12px;height:12px"></i>
                        CSV
                    </a>
                </div>
            </form>

            @if($activities->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="activity" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                </div>
            @else
                <div class="activity-log">
                    <div class="basic-timeline">
                        <ul>
                            @foreach($activities as $activity)
                                @php
                                    $desc = strtolower($activity->description ?? '');
                                    $dotColor = match(true) {
                                        str_contains($desc, 'fail') || str_contains($desc, 'error') || str_contains($desc, 'delet') => 'danger',
                                        str_contains($desc, 'paid') || str_contains($desc, 'creat') || str_contains($desc, 'complet') => 'success',
                                        str_contains($desc, 'suspend') || str_contains($desc, 'warn') => 'warning',
                                        str_contains($desc, 'updat') || str_contains($desc, 'save') => 'info',
                                        default => 'primary',
                                    };
                                    $props = $activity->properties->toArray();
                                    unset($props['attributes'], $props['old']); // hide verbose diff
                                @endphp
                                <li>
                                    <div class="timeline-dot-{{ $dotColor }}"></div>
                                    <div class="ms-4 pb-1">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <span class="badge badge-light-{{ match($activity->log_name) {
                                                    'order', 'invoice', 'payment', 'credit' => 'primary',
                                                    'service', 'provisioning', 'domain' => 'success',
                                                    'customer', 'user' => 'info',
                                                    'product', 'integration' => 'secondary',
                                                    default => 'light'
                                                } }} me-1">{{ $activity->log_name }}</span>
                                                <span class="f-w-500">{{ $activity->description }}</span>
                                            </div>
                                            <span class="f-light f-12 text-nowrap">
                                                {{ $activity->created_at?->format('d.m.Y H:i:s') }}
                                            </span>
                                        </div>
                                        <div class="d-flex gap-3 mt-1 f-12">
                                            <span class="f-light">
                                                <i data-feather="user" style="width:11px;height:11px"></i>
                                                {{ $activity->causer?->name ?? 'system' }}
                                            </span>
                                            @if($activity->subject_id)
                                                <span class="f-light">
                                                    <i data-feather="link" style="width:11px;height:11px"></i>
                                                    {{ class_basename((string) $activity->subject_type) }}
                                                    #{{ $activity->subject_id }}
                                                </span>
                                            @endif
                                            @if(!empty($props))
                                                <span class="f-light" title="{{ json_encode($props, JSON_UNESCAPED_UNICODE) }}">
                                                    {{ \Illuminate\Support\Str::limit(json_encode($props, JSON_UNESCAPED_UNICODE) ?: '', 60) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="mt-3">{{ $activities->withQueryString()->links() }}</div>
            @endif
        </x-panel.card>
    </div>
@endsection
