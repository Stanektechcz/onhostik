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

                {{-- Phase 272: live status from the backend panel --}}
                @if($service->provisioning_driver !== null)
                    <x-panel.card title="Živý stav — {{ $service->provisioning_driver->label() }}">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <button type="button" id="live-status-btn" class="btn btn-outline-primary btn-sm">
                                <i data-feather="activity" style="width:14px;height:14px"></i>
                                Načíst stav z panelu
                            </button>
                            <span id="live-status-flag"></span>
                        </div>
                        <div id="live-status-body">
                            <p class="f-light f-12 mb-0">Stav se načítá na vyžádání — klikněte na tlačítko výše.</p>
                        </div>

                        {{-- Phase 277: PHP version change (aaPanel) --}}
                        @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel)
                            <form method="POST" action="{{ route('admin.services.php-version', $service) }}"
                                  class="d-flex gap-2 align-items-end mt-3 border-top pt-3">
                                @csrf
                                @php
                                    $currentPhp = $service->resources['php_version'] ?? null;
                                @endphp
                                <div>
                                    <label class="form-label f-12 mb-1">PHP verze
                                        <span class="badge badge-light-primary ms-1">{{ \App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob::VERSIONS[$currentPhp] ?? '—' }}</span>
                                    </label>
                                    <select name="php_version" class="form-select form-select-sm">
                                        @foreach(\App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob::VERSIONS as $val => $label)
                                            <option value="{{ $val }}" @selected($currentPhp === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm"
                                        onclick="return confirm('Změnit PHP verzi webu?')">
                                    <i data-feather="refresh-cw" style="width:13px;height:13px"></i> Změnit PHP
                                </button>
                            </form>
                        @endif

                        {{-- Phase 276: VPS power actions --}}
                        @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Proxmox)
                            <div class="d-flex gap-2 flex-wrap mt-3 border-top pt-3">
                                <form method="POST" action="{{ route('admin.services.vps-action', $service) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i data-feather="play" style="width:13px;height:13px"></i> Spustit VM
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.services.vps-action', $service) }}"
                                      onsubmit="return confirm('Opravdu vypnout VM?')">
                                    @csrf
                                    <input type="hidden" name="action" value="stop">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i data-feather="square" style="width:13px;height:13px"></i> Vypnout VM
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.services.vps-action', $service) }}"
                                      onsubmit="return confirm('Opravdu restartovat VM?')">
                                    @csrf
                                    <input type="hidden" name="action" value="restart">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i data-feather="rotate-cw" style="width:13px;height:13px"></i> Restart VM
                                    </button>
                                </form>
                            </div>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Phase 272: firewall rules inline (skip for domain services) --}}
                @if($service->provisioning_driver !== \App\Domains\Provisioning\Enums\ProvisioningDriver::Wedos)
                    <x-panel.card title="Firewall pravidla">
                        @if($service->firewallRules->isEmpty())
                            <p class="f-light f-12 mb-3">Žádná pravidla. Přidejte první níže.</p>
                        @else
                            <div class="table-responsive mb-3">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Směr</th>
                                            <th>Protokol</th>
                                            <th>Porty</th>
                                            <th>IP / CIDR</th>
                                            <th>Akce</th>
                                            <th>Stav</th>
                                            <th class="text-end"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($service->firewallRules as $rule)
                                            <tr>
                                                <td><span class="badge badge-light-secondary">{{ strtoupper($rule->direction) }}</span></td>
                                                <td>{{ strtoupper($rule->protocol) }}</td>
                                                <td class="f-12">
                                                    @if($rule->port_from)
                                                        {{ $rule->port_from }}@if($rule->port_to && $rule->port_to !== $rule->port_from)–{{ $rule->port_to }}@endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td><code class="f-12">{{ $rule->ip_cidr }}</code></td>
                                                <td>
                                                    <span class="badge {{ $rule->action === 'allow' ? 'badge-light-success' : 'badge-light-danger' }}">
                                                        {{ $rule->action === 'allow' ? 'Povolit' : 'Zakázat' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.service-firewall-rules.update', $rule) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-link p-0 border-0">
                                                            <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                                {{ $rule->is_active ? 'Aktivní' : 'Vypnuto' }}
                                                            </span>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('admin.service-firewall-rules.destroy', $rule) }}" class="d-inline"
                                                          onsubmit="return confirm('Odstranit pravidlo?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-xs">
                                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.service-firewall-rules.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="service_id" value="{{ $service->id }}">
                            <div class="col-6 col-md-2">
                                <label class="form-label f-12 mb-1">Směr</label>
                                <select name="direction" class="form-select form-select-sm">
                                    <option value="in">Příchozí</option>
                                    <option value="out">Odchozí</option>
                                    <option value="both">Obojí</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label f-12 mb-1">Protokol</label>
                                <select name="protocol" class="form-select form-select-sm">
                                    <option value="tcp">TCP</option>
                                    <option value="udp">UDP</option>
                                    <option value="icmp">ICMP</option>
                                    <option value="any">Any</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label f-12 mb-1">Port od</label>
                                <input type="number" name="port_from" class="form-control form-control-sm" min="1" max="65535" placeholder="80">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label f-12 mb-1">Port do</label>
                                <input type="number" name="port_to" class="form-control form-control-sm" min="1" max="65535" placeholder="80">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label f-12 mb-1">IP / CIDR</label>
                                <input type="text" name="ip_cidr" class="form-control form-control-sm" placeholder="0.0.0.0/0" required maxlength="50">
                            </div>
                            <div class="col-6 col-md-1">
                                <label class="form-label f-12 mb-1">Akce</label>
                                <select name="action" class="form-select form-select-sm">
                                    <option value="allow">Povolit</option>
                                    <option value="deny">Zakázat</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-1">
                                <button type="submit" class="btn btn-primary btn-sm w-100 text-white">Přidat</button>
                            </div>
                        </form>
                        <p class="f-light f-11 mt-2 mb-0">
                            <a href="{{ route('admin.service-firewall-rules.index', ['service_id' => $service->id]) }}">Všechna pravidla služby →</a>
                        </p>
                    </x-panel.card>
                @endif

                {{-- Phase 272: health incidents inline --}}
                <x-panel.card title="Health incidenty">
                    @if($service->healthIncidents->isEmpty())
                        <p class="f-light f-12 mb-3">Žádné incidenty.</p>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Závažnost</th>
                                        <th>Titulek</th>
                                        <th>Stav</th>
                                        <th>Vytvořeno</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($service->healthIncidents as $incident)
                                        <tr>
                                            <td>
                                                <span class="badge {{ match($incident->severity) { 'critical' => 'bg-danger', 'warning' => 'bg-warning text-dark', default => 'bg-info' } }}">
                                                    {{ ['info' => 'Info', 'warning' => 'Varování', 'critical' => 'Kritický'][$incident->severity] ?? $incident->severity }}
                                                </span>
                                            </td>
                                            <td class="f-12 f-w-600">{{ $incident->title }}</td>
                                            <td>
                                                <span class="badge {{ match($incident->status) { 'resolved' => 'badge-light-success', 'investigating' => 'badge-light-warning', default => 'badge-light-danger' } }}">
                                                    {{ ['open' => 'Otevřený', 'investigating' => 'Šetří se', 'resolved' => 'Vyřešeno'][$incident->status] ?? $incident->status }}
                                                </span>
                                            </td>
                                            <td class="f-12 f-light">{{ $incident->created_at?->format('d.m. H:i') }}</td>
                                            <td class="text-end">
                                                @if($incident->status !== 'resolved')
                                                    <form method="POST" action="{{ route('admin.service-health-incidents.update', $incident) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="resolved">
                                                        <button type="submit" class="btn btn-outline-success btn-xs">Vyřešit</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.service-health-incidents.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        <input type="hidden" name="status" value="open">
                        <div class="col-12 col-md-3">
                            <label class="form-label f-12 mb-1">Závažnost</label>
                            <select name="severity" class="form-select form-select-sm">
                                <option value="info">Info</option>
                                <option value="warning">Varování</option>
                                <option value="critical">Kritický</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label f-12 mb-1">Titulek incidentu</label>
                            <input type="text" name="title" class="form-control form-control-sm" required maxlength="255" placeholder="Např. vysoká zátěž disku">
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 text-white">Nahlásit</button>
                        </div>
                    </form>
                    <p class="f-light f-11 mt-2 mb-0">
                        <a href="{{ route('admin.service-health-incidents.index', ['service_id' => $service->id]) }}">Všechny incidenty služby →</a>
                    </p>
                </x-panel.card>

                {{-- Phase 272: recent backup runs --}}
                @if($service->backupLogs->isNotEmpty())
                    <x-panel.card title="Poslední zálohy">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Zahájeno</th>
                                        <th>Stav</th>
                                        <th>Velikost</th>
                                        <th>Trvání</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($service->backupLogs as $log)
                                        <tr>
                                            <td class="f-12">{{ $log->started_at?->format('d.m.Y H:i') }}</td>
                                            <td>
                                                <span class="badge {{ match($log->status) { 'success' => 'badge-light-success', 'failed' => 'badge-light-danger', 'cancelled' => 'badge-light-secondary', default => 'badge-light-warning' } }}">
                                                    {{ ['success' => 'Dokončeno', 'failed' => 'Selhalo', 'running' => 'Běží', 'cancelled' => 'Zrušeno'][$log->status] ?? $log->status }}
                                                </span>
                                            </td>
                                            <td class="f-12">{{ $log->size_bytes ? number_format($log->size_bytes / 1048576, 1, ',', ' ') . ' MB' : '—' }}</td>
                                            <td class="f-12">{{ $log->duration_seconds !== null ? $log->duration_seconds . ' s' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="f-light f-11 mt-2 mb-0">
                            <a href="{{ route('admin.service-backup-logs.index', ['service_id' => $service->id]) }}">Všechny zálohy služby →</a>
                        </p>
                    </x-panel.card>
                @endif
            </div>
        </div>
    </div>

    @if($service->provisioning_driver !== null)
        <script nonce="{{ $cspNonce ?? '' }}">
            document.addEventListener('DOMContentLoaded', function () {
                const btn  = document.getElementById('live-status-btn');
                const body = document.getElementById('live-status-body');
                const flag = document.getElementById('live-status-flag');
                if (!btn) return;

                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    body.innerHTML = '<p class="f-light f-12 mb-0">Načítám…</p>';
                    flag.innerHTML = '';

                    fetch('{{ route('admin.services.live-status', $service) }}', {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        flag.innerHTML = data.dry_run
                            ? '<span class="badge badge-light-warning">MOCK / DRY-RUN</span>'
                            : '<span class="badge badge-light-success">ŽIVÁ DATA</span>';

                        if (!data.ok) {
                            body.innerHTML = '<div class="alert alert-light-danger f-12 mb-0">' + (data.error ?? 'Neznámá chyba') + '</div>';
                            return;
                        }

                        const rows = Object.entries(data.data ?? {});
                        if (rows.length === 0) {
                            body.innerHTML = '<p class="f-light f-12 mb-0">Panel nevrátil žádná data.</p>';
                            return;
                        }

                        let html = '<div class="table-responsive"><table class="table table-sm table-borderless mb-0">';
                        for (const [key, val] of rows) {
                            const safeKey = String(key).replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
                            const safeVal = String(val ?? '—').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
                            html += '<tr><td class="f-light f-12 ps-0" style="width:35%">' + safeKey + '</td><td class="f-12 f-w-600">' + safeVal + '</td></tr>';
                        }
                        html += '</table></div>';
                        body.innerHTML = html;
                    })
                    .catch(() => {
                        btn.disabled = false;
                        body.innerHTML = '<div class="alert alert-light-danger f-12 mb-0">Požadavek selhal.</div>';
                    });
                });
            });
        </script>
    @endif
@endsection
