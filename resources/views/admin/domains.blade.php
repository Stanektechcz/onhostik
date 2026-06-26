@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_domains');
    $breadcrumbItems = [__('panel.nav.admin_domains') => ''];
@endphp

@section('title', __('panel.nav.admin_domains'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_domains') . ' celkem'"
                    :value="$totalCount"
                    icon="globe" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    label="Registrováno (WEDOS ID)"
                    :value="$activeCount"
                    icon="check-circle" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $expiringCount > 0 ? 'warning' : 'secondary' }}">
                        <span class="f-light">Vyprší do 30 dní</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $expiringCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="clock"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card small-widget">
                    <div class="card-body {{ $expiredCount > 0 ? 'danger' : 'secondary' }}">
                        <span class="f-light">Vypršelé domény</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $expiredCount }}</h4>
                        </div>
                        <div class="bg-gradient"><i data-feather="alert-octagon"></i></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Domains table --}}
        <x-panel.card :title="__('panel.nav.admin_domains')">
            <form method="GET" action="{{ route('admin.domains.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <input type="text" name="q" class="form-control" style="max-width: 260px;"
                       placeholder="Doména, zákazník…" value="{{ $search }}">
                <select name="expiry" class="form-select w-auto">
                    <option value="">Všechny expiry</option>
                    <option value="soon" @selected($expiryFilter === 'soon')>Vyprší do 30 dní</option>
                    <option value="expired" @selected($expiryFilter === 'expired')>Vypršelé</option>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($search || $expiryFilter)
                    <a href="{{ route('admin.domains.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $domains->total() }} domén</span>
            </form>

            @if($domains->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="globe" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.domains.none') }}</h6>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>{{ __('panel.domains.domain') }}</th>
                                <th>{{ __('panel.common.customer') }}</th>
                                <th>Registrace</th>
                                <th>Expirace</th>
                                <th>Auto-renew</th>
                                <th>NS servery</th>
                                <th>WEDOS ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($domains as $domain)
                                @php
                                    $daysLeft  = $domain->expires_at ? now()->diffInDays($domain->expires_at, false) : null;
                                    $expiryClr = $daysLeft === null ? '' :
                                        ($daysLeft < 0 ? 'text-danger' : ($daysLeft <= 30 ? 'text-warning' : ''));
                                @endphp
                                <tr>
                                    <td class="f-w-600">
                                        {{ $domain->fqdn() }}
                                        @if($domain->wedos_domain_id)
                                            <span class="badge badge-light-success ms-1 f-12">aktivní</span>
                                        @else
                                            <span class="badge badge-light-warning ms-1 f-12">neregitrováno</span>
                                        @endif
                                    </td>
                                    <td class="f-light f-12">
                                        @if($domain->service?->customer)
                                            <a href="{{ route('admin.customers.show', $domain->service->customer) }}" class="f-light">
                                                {{ $domain->service->customer->email }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-12">{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="f-12 {{ $expiryClr }}">
                                        {{ $domain->expires_at?->format('d.m.Y') ?? '—' }}
                                        @if($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 30)
                                            <br><span class="f-12">({{ $daysLeft }}d)</span>
                                        @elseif($daysLeft !== null && $daysLeft < 0)
                                            <br><span class="badge badge-light-danger f-12">EXPIRED</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($domain->auto_renew)
                                            <span class="badge badge-light-success">ano</span>
                                        @else
                                            <span class="badge badge-light-secondary">ne</span>
                                        @endif
                                    </td>
                                    <td class="f-12 f-light">
                                        @if($domain->nameservers)
                                            {{ implode(', ', array_slice($domain->nameservers, 0, 2)) }}
                                            @if(count($domain->nameservers) > 2)
                                                + {{ count($domain->nameservers) - 2 }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-light f-12">{{ $domain->wedos_domain_id ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $domains->links() }}
            @endif
        </x-panel.card>

        {{-- Domain registration tasks --}}
        <x-panel.card title="Úkoly registrace domén">
            @if($domainTasks->isEmpty())
                <div class="text-center py-4">
                    <i data-feather="check-circle" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                    <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.admin.task'),
                    __('panel.common.customer'),
                    __('panel.common.status'),
                    __('panel.admin.attempts'),
                    __('panel.admin.error'),
                    __('panel.common.actions')
                ]">
                    @foreach($domainTasks as $task)
                        <tr>
                            <td>
                                #{{ $task->id }}
                                @if($task->service)
                                    · <a href="{{ route('admin.services.show', $task->service) }}" class="f-light">{{ $task->service->label }}</a>
                                @endif
                            </td>
                            <td class="f-light f-12">{{ $task->service?->customer?->company_name ?? $task->service?->customer?->email ?? '—' }}</td>
                            <td><x-panel.status-badge :status="$task->status" /></td>
                            <td class="f-12">{{ $task->attempts }}/{{ $task->max_attempts }}</td>
                            <td class="f-light f-12">{{ $task->error_message ?? '—' }}</td>
                            <td>
                                @if($task->status->canRetry())
                                    <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.retry') }}</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $domainTasks->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
