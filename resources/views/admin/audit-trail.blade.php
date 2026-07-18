@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Audit trail adminů';
    $breadcrumbItems = ['Nastavení' => '#', 'Audit trail' => ''];
@endphp

@section('title', 'Audit trail adminů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="flex gap-2 items-center flex-wrap">
                <select name="admin_id" class="form-select form-select-sm" style="max-width:200px">
                    <option value="">Všichni admini</option>
                    @foreach($admins as $adm)
                    <option value="{{ $adm->id }}" {{ (string)$adminId === (string)$adm->id ? 'selected' : '' }}>
                        {{ $adm->name }}
                    </option>
                    @endforeach
                </select>
                <select name="action" class="form-select form-select-sm" style="max-width:220px">
                    <option value="">Všechny akce</option>
                    @foreach($actions as $key => $label)
                    <option value="{{ $key }}" {{ $action === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Filtrovat</button>
                <a href="{{ route('admin.audit-trail.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-no-border">
            <h5>Audit trail adminů ({{ $logs->total() }})</h5>
        </div>
        <div class="card-body pt-0">
            @if($logs->isEmpty())
                <p class="text-center f-light py-4">Žádné záznamy.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Čas</th>
                            <th>Admin</th>
                            <th>Akce</th>
                            <th>Cíl</th>
                            <th>Metadata</th>
                            <th>IP adresa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td class="text-nowrap f-12">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <div class="f-w-500">{{ $log->admin?->name ?? '—' }}</div>
                                <div class="f-11 f-light">{{ $log->admin?->email ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge badge-light-primary f-11">{{ $log->actionLabel() }}</span>
                            </td>
                            <td class="f-12 f-light">
                                @if($log->target_type)
                                    {{ class_basename($log->target_type) }} #{{ $log->target_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="f-11 f-light" style="max-width:250px">
                                @if($log->metadata)
                                    <code>{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="f-11 text-nowrap">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
