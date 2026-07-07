@extends('layouts.panel')

@php
    $breadcrumbTitle = $service->label ?? 'Služba #' . $service->id;
    $breadcrumbItems = [__('panel.nav.admin_services') => route('admin.services.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />
        @error('service')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

        {{-- Top KPI row --}}
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'primary', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }}">
                        <span class="f-light">{{ __('panel.common.status') }}</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4><x-panel.status-badge :status="$service->status" /></h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="server"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body secondary">
                        <span class="f-light">Driver</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $service->provisioning_driver?->label() ?? '—' }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="cpu"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $service->external_id ? 'success' : 'warning' }}">
                        <span class="f-light">External ID</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $service->external_id ?? 'Neprovizionováno' }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="link"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="card small-widget">
                    <div class="card-body {{ $service->next_due_date?->isPast() ? 'danger' : 'primary' }}">
                        <span class="f-light">Další platba</span>
                        <div class="d-flex align-items-end gap-1">
                            <h4>{{ $service->next_due_date?->format('d.m.Y') ?? '—' }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 card-gap">
            {{-- Left column: info + actions --}}
            <div class="col-span-4 xl:col-span-12">
                {{-- Service info card --}}
                <x-panel.card :title="__('panel.nav.admin_services')" :subtitle="$service->label">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="f-light ps-0">Produkt</td>
                            <td class="f-w-600">{{ $service->product?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0">Server</td>
                            <td>{{ $service->server?->name ?? '—' }}</td>
                        </tr>
                        @if($service->domainRegistration)
                            <tr>
                                <td class="f-light ps-0">Doména</td>
                                <td>{{ $service->domainRegistration->fqdn() }}</td>
                            </tr>
                        @endif
                        @if($service->suspension_reason)
                            <tr>
                                <td class="f-light ps-0">Důvod pozastavení</td>
                                <td class="text-danger">{{ $service->suspension_reason }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0">Vytvořeno</td>
                            <td>{{ $service->created_at?->format('d.m.Y H:i') }}</td>
                        </tr>
                        @if($service->suspended_at)
                            <tr>
                                <td class="f-light ps-0">Pozastaveno</td>
                                <td>{{ $service->suspended_at->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </x-panel.card>

                {{-- Resources --}}
                @if($service->resources)
                    <x-panel.card title="Zdroje / konfigurace">
                        @php
                            $resources = $service->resources;
                        @endphp
                        @if(isset($resources['cpu']))
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">CPU</span>
                                    <span class="f-w-600">{{ $resources['cpu'] }} vCPU</span>
                                </div>
                            </div>
                        @endif
                        @if(isset($resources['ram_mb']))
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">RAM</span>
                                    <span class="f-w-600">{{ round($resources['ram_mb'] / 1024, 1) }} GB</span>
                                </div>
                            </div>
                        @endif
                        @if(isset($resources['disk_mb']))
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">Disk</span>
                                    <span class="f-w-600">{{ round($resources['disk_mb'] / 1024, 1) }} GB</span>
                                </div>
                            </div>
                        @endif
                        @if(isset($resources['bandwidth_gb']))
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="f-light f-12">Přenos</span>
                                    <span class="f-w-600">{{ $resources['bandwidth_gb'] }} GB / měs.</span>
                                </div>
                            </div>
                        @endif
                        @foreach(array_diff_key($resources, array_flip(['cpu','ram_mb','disk_mb','bandwidth_gb','ipconfig'])) as $key => $val)
                            <div class="mb-2">
                                <span class="f-light f-12">{{ $key }}</span>:
                                <span class="f-w-600 ms-1">{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- Actions --}}
                <x-panel.card title="Akce">
                    @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                        <form method="POST" action="{{ route('admin.services.suspend', $service) }}" class="d-flex gap-2 align-items-start">
                            @csrf
                            <div class="flex-grow-1">
                                <input type="text" name="reason" class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                       placeholder="{{ __('panel.admin.suspend_reason') }}" required minlength="3">
                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm text-nowrap">
                                <i data-feather="pause-circle" style="width:14px;height:14px"></i>
                                {{ __('panel.admin.suspend') }}
                            </button>
                        </form>
                    @elseif($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Suspended)
                        <form method="POST" action="{{ route('admin.services.unsuspend', $service) }}">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i data-feather="play-circle" style="width:14px;height:14px"></i>
                                {{ __('panel.admin.unsuspend') }}
                            </button>
                        </form>
                    @else
                        <p class="f-light mb-0 f-12">Žádné dostupné akce pro stav {{ $service->status->label() }}.</p>
                    @endif

                    <div class="mt-3 border-top pt-3 d-flex gap-2">
                        <a href="{{ route('admin.customers.show', $service->customer) }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="user" style="width:14px;height:14px"></i>
                            Zákazník
                        </a>
                        @if($service->orderItem?->order_id)
                            <a href="{{ route('admin.orders.show', $service->orderItem->order_id) }}" class="btn btn-outline-secondary btn-sm">
                                <i data-feather="package" style="width:14px;height:14px"></i>
                                Objednávka
                            </a>
                        @endif
                    </div>
                </x-panel.card>

                {{-- Label edit --}}
                <x-panel.card title="Název služby">
                    <form method="POST" action="{{ route('admin.services.update-label', $service) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <input type="text" name="label"
                                   value="{{ old('label', $service->label) }}"
                                   class="form-control form-control-sm @error('label') is-invalid @enderror"
                                   required maxlength="150"
                                   placeholder="Název zobrazený zákazníkovi">
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i data-feather="save" style="width:13px;height:13px"></i>
                            {{ __('panel.common.save') }}
                        </button>
                    </form>
                </x-panel.card>

                {{-- Due date adjustment --}}
                @if($service->next_due_date !== null)
                    <x-panel.card title="Datum splatnosti">
                        <p class="f-light f-12 mb-2">Aktuálně: <strong>{{ $service->next_due_date->format('d.m.Y') }}</strong></p>
                        <form method="POST" action="{{ route('admin.services.adjust-due-date', $service) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-2">
                                <input type="date" name="next_due_date"
                                       value="{{ old('next_due_date', $service->next_due_date->format('Y-m-d')) }}"
                                       class="form-control form-control-sm @error('next_due_date') is-invalid @enderror"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       required>
                                @error('next_due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i data-feather="calendar" style="width:13px;height:13px"></i>
                                {{ __('panel.admin.adjust_due_date') }}
                            </button>
                        </form>
                    </x-panel.card>
                @endif

                {{-- Manual renewal invoice --}}
                @if($service->next_due_date !== null)
                    <x-panel.card title="{{ __('panel.admin.manual_renewal') }}">
                        <p class="f-light f-12 mb-2">
                            {{ __('panel.admin.manual_renewal_hint') }}
                        </p>
                        @error('service')<div class="alert alert-danger py-1 px-2 mb-2 f-12">{{ $message }}</div>@enderror
                        <form method="POST" action="{{ route('admin.services.manual-renewal', $service) }}"
                              onsubmit="return confirm('{{ __('panel.admin.manual_renewal_confirm') }}')">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm">
                                <i data-feather="file-plus" style="width:13px;height:13px"></i>
                                {{ __('panel.admin.manual_renewal_issue') }}
                            </button>
                        </form>
                    </x-panel.card>
                @endif
            </div>

            {{-- Right column: provisioning tasks timeline + audit --}}
            <div class="col-span-8 xl:col-span-12">
                {{-- Provisioning tasks --}}
                <x-panel.card title="Provisioning úkoly">
                    @if($service->provisioningTasks->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
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
                                            <div class="d-flex justify-content-between align-items-start ms-4">
                                                <div>
                                                    <h6 class="mb-0">{{ $task->operation }}
                                                        <x-panel.status-badge :status="$task->status" />
                                                    </h6>
                                                    @if($task->error_message)
                                                        <p class="f-12 text-danger mb-0 mt-1">{{ $task->error_message }}</p>
                                                    @endif
                                                    @if($task->result && isset($task->result['external_id']))
                                                        <p class="f-12 f-light mb-0 mt-1">External ID: <code>{{ $task->result['external_id'] }}</code></p>
                                                    @endif
                                                    <p class="f-12 f-light mb-0">
                                                        Pokus {{ $task->attempts }}/{{ $task->max_attempts }}
                                                        @if($task->started_at) · {{ $task->started_at->format('d.m. H:i') }} @endif
                                                        @if($task->finished_at) → {{ $task->finished_at->format('H:i') }} @endif
                                                    </p>
                                                </div>
                                                @if($task->canRetry())
                                                    <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}" class="ms-2">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                                            <i data-feather="refresh-cw" style="width:12px;height:12px"></i>
                                                            Retry
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </x-panel.card>

                {{-- Audit log timeline --}}
                @if($audit->isNotEmpty())
                    <x-panel.card title="Auditní záznamy">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($audit as $activity)
                                        <li>
                                            <div class="activity-dot-primary"></div>
                                            <div class="ms-4">
                                                <h6 class="mb-0">
                                                    <span class="badge badge-light-primary me-1">{{ $activity->log_name }}</span>
                                                    {{ $activity->description }}
                                                </h6>
                                                <p class="f-12 f-light mb-0">
                                                    {{ $activity->causer?->name ?? 'system' }}
                                                    · {{ $activity->created_at?->format('d.m.Y H:i') }}
                                                </p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </x-panel.card>
                @endif

                {{-- Plan change history (Phase 97) --}}
                @if($planChanges->isNotEmpty())
                    <x-panel.card title="Historie změn plánu">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ze plánu</th>
                                        <th>Na plán</th>
                                        <th>Provedl</th>
                                        <th>Důvod</th>
                                        <th>Kdy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($planChanges as $change)
                                        <tr>
                                            <td class="text-muted">{{ $change->fromPlan?->name ?? '—' }}</td>
                                            <td class="fw-semibold">{{ $change->toPlan->name }}</td>
                                            <td>{{ $change->changedBy?->name ?? '—' }}</td>
                                            <td><span class="badge bg-secondary">{{ $change->reasonLabel() }}</span></td>
                                            <td class="text-muted small">{{ $change->changed_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-panel.card>
                @endif

                {{-- Backup schedule (Phase 99) --}}
                @if($backupPolicy)
                    <x-panel.card title="Plán zálohování">
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <p class="f-12 text-muted mb-1">Frekvence</p>
                                <span class="badge bg-info">{{ $backupPolicy->frequencyLabel() }}</span>
                            </div>
                            <div class="col-sm-4">
                                <p class="f-12 text-muted mb-1">Čas zálohy</p>
                                <span class="f-12">{{ str_pad((string) $backupPolicy->scheduled_hour, 2, '0', STR_PAD_LEFT) }}:00 UTC</span>
                                @if($backupPolicy->frequency === 'weekly')
                                    &mdash; {{ $backupPolicy->weekdayLabel() }}
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <p class="f-12 text-muted mb-1">Uchovávání</p>
                                <span class="f-12">{{ $backupPolicy->retention_days }} dní</span>
                            </div>
                            <div class="col-sm-4">
                                <p class="f-12 text-muted mb-1">Stav</p>
                                @if($backupPolicy->is_active)
                                    <span class="badge bg-success">Aktivní</span>
                                @else
                                    <span class="badge bg-secondary">Neaktivní</span>
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <p class="f-12 text-muted mb-1">Upozornit při chybě</p>
                                @if($backupPolicy->notify_on_failure)
                                    <span class="badge bg-warning text-dark">Ano</span>
                                @else
                                    <span class="badge bg-secondary">Ne</span>
                                @endif
                            </div>
                            @if($backupPolicy->last_run_at)
                                <div class="col-sm-4">
                                    <p class="f-12 text-muted mb-1">Poslední záloha</p>
                                    <span class="f-12">{{ $backupPolicy->last_run_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </x-panel.card>
                @endif
            </div>
        </div>
    </div>
@endsection
