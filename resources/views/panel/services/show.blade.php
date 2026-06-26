@extends('layouts.panel')

@php
    $breadcrumbTitle = $service->label;
    $breadcrumbItems = [__('panel.nav.services') => route('panel.services.index'), $service->label => ''];
@endphp

@section('title', $service->label)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="row mb-1">
            <div class="col-sm-6 col-xl-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }} rounded p-2">
                                    <i data-feather="server" class="font-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }}"></i>
                                </div>
                                <div>
                                    <x-panel.status-badge :status="$service->status" />
                                    <span class="f-light f-12 d-block">{{ __('panel.common.status') }}</span>
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
                                <div class="bg-light-primary rounded p-2"><i data-feather="calendar" class="font-primary"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $service->next_due_date?->isPast() ? 'font-danger' : '' }}">
                                        {{ $service->next_due_date?->format('d.m.Y') ?? '—' }}
                                    </h5>
                                    <span class="f-light f-12">{{ __('panel.services.next_due') }}</span>
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
                                <div class="bg-light-{{ $monitor?->uptime_percent >= 99 ? 'success' : ($monitor?->uptime_percent >= 95 ? 'warning' : 'danger') }} rounded p-2">
                                    <i data-feather="activity" class="font-{{ $monitor?->uptime_percent >= 99 ? 'success' : ($monitor?->uptime_percent >= 95 ? 'warning' : 'danger') }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $monitor ? $monitor->uptime_percent . ' %' : '—' }}</h5>
                                    <span class="f-light f-12">Uptime</span>
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
                                @php
                                    $sslDays = $monitor?->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null;
                                @endphp
                                <div class="bg-light-{{ $sslDays === null ? 'secondary' : ($sslDays < 14 ? 'danger' : ($sslDays < 30 ? 'warning' : 'success')) }} rounded p-2">
                                    <i data-feather="lock" class="font-{{ $sslDays === null ? 'secondary' : ($sslDays < 14 ? 'danger' : ($sslDays < 30 ? 'warning' : 'success')) }}"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $sslDays !== null ? $sslDays . 'd' : '—' }}</h5>
                                    <span class="f-light f-12">SSL platnost</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending/activating banner for VPS and other provisioning-heavy services --}}
        @if($service->status->value === 'pending')
            @php
                $isVps = $service->provisioning_driver?->value === 'proxmox';
                $latestTask = $service->provisioningTasks->first();
            @endphp
            <div class="alert alert-light-warning d-flex align-items-center gap-3 mb-3">
                <i data-feather="loader" style="width:20px;height:20px;" class="txt-warning flex-shrink-0"></i>
                <div>
                    <span class="f-w-600">Služba se aktivuje</span>
                    <p class="mb-0 f-14 f-light">
                        {{ $isVps
                            ? 'VPS server se provisionuje — klonování a konfigurace může trvat 2–5 minut.'
                            : 'Hosting se zřizuje. Obvykle to trvá méně než minutu.' }}
                        Stránku obnovte za chvíli.
                        @if($latestTask)
                            <span class="f-12 text-muted ms-2">
                                ({{ $latestTask->operation }}: {{ $latestTask->status->label() }}
                                @if($latestTask->attempts) · {{ $latestTask->attempts }}/{{ $latestTask->max_attempts }} @endif)
                            </span>
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-xl-4">
                {{-- Service info --}}
                <x-panel.card :title="$service->label" :subtitle="$service->product?->name">
                    <table class="table table-borderless mb-0">
                        @if($service->external_id)
                            <tr>
                                <td class="f-light ps-0 f-12">{{ __('panel.services.external_id') }}</td>
                                <td><code>{{ $service->external_id }}</code></td>
                            </tr>
                        @endif
                        @if($service->domainRegistration)
                            <tr>
                                <td class="f-light ps-0 f-12">{{ __('panel.services.domain') }}</td>
                                <td>
                                    <a href="{{ route('panel.domains.show', $service->domainRegistration) }}">
                                        {{ $service->domainRegistration->fqdn() }}
                                    </a>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0 f-12">Produkt</td>
                            <td>{{ $service->product?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">Aktivní od</td>
                            <td>{{ $service->created_at?->format('d.m.Y') }}</td>
                        </tr>
                    </table>
                </x-panel.card>

                {{-- Resources --}}
                @if(!empty($service->resources))
                    <x-panel.card :title="__('panel.services.resources')">
                        @php
                            $res = $service->resources;
                        @endphp
                        <div class="row g-2">
                            @if(isset($res['cpu']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="cpu" class="font-primary mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['cpu'] }} vCPU</div>
                                        <div class="f-light f-12">Procesor</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['ram_mb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="database" class="font-success mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['ram_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">RAM</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['disk_mb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="hard-drive" class="font-warning mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['disk_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">Disk</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['bandwidth_gb']))
                                <div class="col-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="wifi" class="font-info mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['bandwidth_gb'] }} GB</div>
                                        <div class="f-light f-12">Přenos / měs.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @foreach(array_diff_key($res, array_flip(['cpu','ram_mb','disk_mb','bandwidth_gb','ipconfig'])) as $key => $val)
                            <div class="d-flex justify-content-between f-12 mt-2">
                                <span class="f-light">{{ $key }}</span>
                                <span>{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- Actions --}}
                <x-panel.card title="Akce">
                    <div class="d-flex flex-wrap gap-2">
                        @if($mockMode && $service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                            <form method="POST" action="{{ route('panel.services.wordpress', $service) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-feather="code" style="width:13px;height:13px"></i>
                                    {{ __('panel.services.wp_install') }}
                                    <span class="badge badge-light-warning ms-1">mock</span>
                                </button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.renew_placeholder') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.upgrade_placeholder') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.cancel_placeholder') }}</button>
                    </div>
                    <p class="f-light f-12 mb-0 mt-2">{{ __('panel.services.credentials') }}: {{ __('panel.services.credentials_note') }}</p>
                </x-panel.card>
            </div>

            <div class="col-xl-8">
                {{-- Monitoring --}}
                <x-panel.card :title="__('panel.services.monitoring')">
                    @if($monitor === null)
                        <div class="text-center py-4">
                            <i data-feather="activity" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                            <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                            <p class="f-light f-12 mb-0">Monitoring se nastaví automaticky po aktivaci služby.</p>
                        </div>
                    @else
                        @php
                            $uptime = $monitor->uptime_percent ?? 0;
                            $uptimeColor = $uptime >= 99 ? 'success' : ($uptime >= 95 ? 'warning' : 'danger');
                        @endphp
                        @if($monitor->provider === 'internal_mock')
                            <p class="f-12 f-light mb-2">
                                <i data-feather="info" style="width:12px;height:12px;"></i>
                                {{ __('panel.services.monitoring_mock_note') }}
                            </p>
                        @endif
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">Uptime (30 dní)</span>
                                    <span class="f-w-600 f-12">{{ $uptime }} %</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $uptimeColor }}"
                                         role="progressbar"
                                         style="width: {{ $uptime }}%"
                                         aria-valuenow="{{ $uptime }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <p class="f-light f-12 mb-1">{{ __('panel.services.last_check') }}</p>
                                <p class="mb-0 f-12">{{ $monitor->last_check_at?->diffForHumans() ?? '—' }}</p>
                            </div>
                            <div class="col-md-3">
                                <p class="f-light f-12 mb-1">SSL platnost</p>
                                <p class="mb-0 f-12 {{ $sslDays !== null && $sslDays < 30 ? 'text-danger' : '' }}">
                                    {{ $monitor->ssl_expires_at?->format('d.m.Y') ?? '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <x-panel.status-badge :status="$monitor->status" />
                            @if($monitor->status->value === 'down')
                                <span class="f-12 text-danger">
                                    <i data-feather="alert-triangle" style="width:12px;height:12px"></i>
                                    Služba není dostupná
                                </span>
                            @endif
                        </div>
                    @endif
                </x-panel.card>

                {{-- Backups --}}
                <x-panel.card :title="__('panel.services.backups')">
                    @if($mockMode && $service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                        <form method="POST" action="{{ route('panel.services.backup', $service) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i data-feather="archive" style="width:13px;height:13px"></i>
                                {{ __('panel.services.request_backup') }}
                                <span class="badge badge-light-warning ms-1">mock</span>
                            </button>
                        </form>
                    @endif
                    @error('backup')<div class="text-danger f-12 mb-2">{{ $message }}</div>@enderror
                    @error('wordpress')<div class="text-danger f-12 mb-2">{{ $message }}</div>@enderror
                    @if($backupJobs->isEmpty())
                        <div class="text-center py-3">
                            <i data-feather="archive" style="width:30px;height:30px;" class="text-muted mb-2"></i>
                            <p class="f-light mb-0 f-12">{{ __('panel.common.empty') }}</p>
                        </div>
                    @else
                        <x-panel.data-table :headers="[__('panel.common.date'), __('panel.common.status'), 'Velikost']">
                            @foreach($backupJobs as $backupJob)
                                <tr>
                                    <td>{{ $backupJob->created_at?->format('d.m.Y H:i') }}</td>
                                    <td><x-panel.status-badge :status="$backupJob->status" /></td>
                                    <td>{{ $backupJob->size_mb ? $backupJob->size_mb . ' MB' : '—' }}</td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    @endif
                </x-panel.card>

                {{-- Provisioning tasks timeline --}}
                @if($service->provisioningTasks->isNotEmpty())
                    <x-panel.card :title="__('panel.services.tasks')">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($service->provisioningTasks as $task)
                                        @php
                                            $dotColor = match($task->status) {
                                                \App\Domains\Provisioning\Enums\TaskStatus::Success => 'success',
                                                \App\Domains\Provisioning\Enums\TaskStatus::Failed,
                                                \App\Domains\Provisioning\Enums\TaskStatus::ManualReview => 'danger',
                                                \App\Domains\Provisioning\Enums\TaskStatus::Running,
                                                \App\Domains\Provisioning\Enums\TaskStatus::Retrying => 'warning',
                                                default => 'primary',
                                            };
                                        @endphp
                                        <li>
                                            <div class="timeline-dot-{{ $dotColor }}"></div>
                                            <div class="ms-4 pb-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <span class="f-w-500">{{ $task->operation }}</span>
                                                        <x-panel.status-badge :status="$task->status" />
                                                    </div>
                                                    <span class="f-light f-12 text-nowrap">
                                                        {{ $task->finished_at?->format('d.m. H:i') ?? $task->created_at?->format('d.m. H:i') }}
                                                    </span>
                                                </div>
                                                @if($task->error_message)
                                                    <p class="f-12 text-danger mb-0 mt-1">{{ $task->error_message }}</p>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </x-panel.card>
                @endif
            </div>
        </div>
    </div>
@endsection
