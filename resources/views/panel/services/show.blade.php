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
        <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-3 xl:col-span-6 sm:col-span-12">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="bg-light-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }} rounded p-2">
                                    <i data-feather="server" class="font-{{ match($service->status) { \App\Domains\Provisioning\Enums\ServiceStatus::Active => 'success', \App\Domains\Provisioning\Enums\ServiceStatus::Suspended => 'warning', default => 'secondary' } }}"></i>
                                </div>
                                <div>
                                    <x-panel.status-badge :status="$service->status" />
                                    <span class="f-light f-12 block">{{ __('panel.common.status') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-3 xl:col-span-6 sm:col-span-12">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
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
            <div class="col-span-3 xl:col-span-6 sm:col-span-12">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
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
            <div class="col-span-3 xl:col-span-6 sm:col-span-12">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
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
            <div class="alert alert-light-warning flex items-center gap-3 mb-3">
                <i data-feather="loader" style="width:20px;height:20px;" class="txt-warning shrink-0"></i>
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

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-4 xl:col-span-12">
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
                        <div class="grid grid-cols-12 gap-2">
                            @if(isset($res['cpu']))
                                <div class="col-span-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="cpu" class="font-primary mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['cpu'] }} vCPU</div>
                                        <div class="f-light f-12">Procesor</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['ram_mb']))
                                <div class="col-span-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="database" class="font-success mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['ram_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">RAM</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['disk_mb']))
                                <div class="col-span-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="hard-drive" class="font-warning mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ round($res['disk_mb'] / 1024, 1) }} GB</div>
                                        <div class="f-light f-12">Disk</div>
                                    </div>
                                </div>
                            @endif
                            @if(isset($res['bandwidth_gb']))
                                <div class="col-span-6">
                                    <div class="border rounded p-2 text-center">
                                        <i data-feather="wifi" class="font-info mb-1" style="width:18px;height:18px"></i>
                                        <div class="f-w-600">{{ $res['bandwidth_gb'] }} GB</div>
                                        <div class="f-light f-12">Přenos / měs.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @foreach(array_diff_key($res, array_flip(['cpu','ram_mb','disk_mb','bandwidth_gb','ipconfig'])) as $key => $val)
                            <div class="flex justify-between f-12 mt-2">
                                <span class="f-light">{{ $key }}</span>
                                <span>{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- aaPanel Live Usage --}}
                @php
                    $usage = $service->usage_snapshot ?? [];
                    $diskUsed  = $usage['disk_used_mb']      ?? 0;
                    $diskQuota = $usage['disk_quota_mb']      ?? 0;
                    $bwUsed    = $usage['bandwidth_used_mb']  ?? 0;
                    $bwQuota   = $usage['bandwidth_quota_mb'] ?? 0;
                    $diskPct   = $diskQuota  > 0 ? min(round($diskUsed  / $diskQuota  * 100), 100) : 0;
                    $bwPct     = $bwQuota    > 0 ? min(round($bwUsed    / $bwQuota    * 100), 100) : 0;
                @endphp
                @if(!empty($usage))
                    <x-panel.card title="Využití prostředků">
                        @if(!empty($usage['mock']))
                            <p class="f-12 f-light mb-2 flex items-center gap-1">
                                <i data-feather="info" style="width:12px;height:12px;"></i>
                                Demo data — synchronizace probíhá každých 15 min.
                            </p>
                        @endif
                        <div class="mb-3">
                            <div class="flex justify-between mb-1">
                                <span class="f-light f-12">Disk</span>
                                <span class="f-12 f-w-500">
                                    {{ number_format($diskUsed / 1024, 1) }} / {{ number_format($diskQuota / 1024, 1) }} GB
                                </span>
                            </div>
                            <div class="progress" style="height:7px">
                                <div class="progress-bar bg-{{ $diskPct >= 90 ? 'danger' : ($diskPct >= 70 ? 'warning' : 'success') }}"
                                     role="progressbar" style="width:{{ $diskPct }}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="flex justify-between mb-1">
                                <span class="f-light f-12">Přenos</span>
                                <span class="f-12 f-w-500">
                                    {{ number_format($bwUsed / 1024, 1) }} / {{ number_format($bwQuota / 1024, 1) }} GB
                                </span>
                            </div>
                            <div class="progress" style="height:7px">
                                <div class="progress-bar bg-{{ $bwPct >= 90 ? 'danger' : ($bwPct >= 70 ? 'warning' : 'info') }}"
                                     role="progressbar" style="width:{{ $bwPct }}%"></div>
                            </div>
                        </div>
                        <div class="flex gap-3 f-12">
                            @if(isset($usage['db_count']))
                                <span class="f-light">Databáze: <strong>{{ $usage['db_count'] }}</strong></span>
                            @endif
                            @if(isset($usage['email_count']))
                                <span class="f-light">E-maily: <strong>{{ $usage['email_count'] }}</strong></span>
                            @endif
                            @if(isset($usage['php_processes']))
                                <span class="f-light">PHP proc: <strong>{{ $usage['php_processes'] }}</strong></span>
                            @endif
                        </div>
                        @if(!empty($usage['synced_at']))
                            <p class="f-light f-11 mt-2 mb-0">
                                Synced: {{ \Illuminate\Support\Carbon::parse($usage['synced_at'])->diffForHumans() }}
                            </p>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Actions --}}
                <x-panel.card title="Akce">
                    <div class="flex flex-wrap gap-2">
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
                        @if($renewalInvoice)
                            <a href="{{ route('panel.billing.invoices.show', $renewalInvoice) }}"
                               class="btn btn-{{ $renewalInvoice->status->value === 'overdue' ? 'danger' : 'warning' }} btn-sm">
                                <i data-feather="refresh-cw" style="width:13px;height:13px"></i>
                                {{ __('panel.services.pay_renewal') }}
                                @if($renewalInvoice->status->value === 'overdue')
                                    <span class="badge badge-light-warning ms-1">{{ __('panel.billing.overdue') }}</span>
                                @endif
                            </a>
                        @endif
                        @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active && $service->product)
                            <a href="{{ route('panel.services.change-plan', $service) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i data-feather="arrow-up" style="width:13px;height:13px"></i>
                                {{ __('panel.services.change_plan') }}
                            </a>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.upgrade_placeholder') }}</button>
                        @endif
                        @if(in_array($service->status->value, ['active', 'suspended']))
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#cancelModal">
                                <i data-feather="x-circle" style="width:13px;height:13px"></i>
                                {{ __('panel.services.request_cancellation') }}
                            </button>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ __('panel.services.cancel_placeholder') }}</button>
                        @endif
                    </div>
                    <p class="f-light f-12 mb-0 mt-2">{{ __('panel.services.credentials') }}: {{ __('panel.services.credentials_note') }}</p>

                    {{-- Auto-renewal toggle --}}
                    <hr class="my-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="f-13 f-w-500">Automatická obnova</span>
                            <p class="f-light f-12 mb-0">
                                @if($service->auto_renew)
                                    <span class="badge badge-light-success">Zapnuto</span>
                                    Faktura za obnovu bude vystavena automaticky.
                                @else
                                    <span class="badge badge-light-secondary">Vypnuto</span>
                                    Služba nebude automaticky obnovena.
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('panel.services.toggle-auto-renew', $service) }}">
                            @csrf
                            <button type="submit"
                                    class="btn btn-outline-{{ $service->auto_renew ? 'warning' : 'success' }} btn-sm"
                                    data-confirm="{{ $service->auto_renew ? 'Vypnout automatickou obnovu?' : 'Zapnout automatickou obnovu?' }}">
                                <i data-feather="{{ $service->auto_renew ? 'toggle-right' : 'toggle-left' }}" style="width:13px;height:13px"></i>
                                {{ $service->auto_renew ? 'Vypnout' : 'Zapnout' }}
                            </button>
                        </form>
                    </div>

                    {{-- Rename service --}}
                    <hr class="my-3">
                    <span class="f-13 f-w-500">Přejmenovat službu</span>
                    <form method="POST" action="{{ route('panel.services.rename', $service) }}" class="flex gap-2 mt-2">
                        @csrf
                        @method('PATCH')
                        <input type="text"
                               name="label"
                               class="form-control form-control-sm @error('label') is-invalid @enderror"
                               value="{{ old('label', $service->label) }}"
                               placeholder="Název služby"
                               minlength="2"
                               maxlength="100"
                               required>
                        @error('label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">
                            <i data-feather="edit-2" style="width:13px;height:13px"></i>
                            Uložit
                        </button>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-span-8 xl:col-span-12">
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
                        <div class="grid grid-cols-12 gap-3 items-center mb-3">
                            <div class="col-span-6 md:col-span-12">
                                <div class="flex justify-between mb-1">
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
                            <div class="col-span-3 sm:col-span-6">
                                <p class="f-light f-12 mb-1">{{ __('panel.services.last_check') }}</p>
                                <p class="mb-0 f-12">{{ $monitor->last_check_at?->diffForHumans() ?? '—' }}</p>
                            </div>
                            <div class="col-span-3 sm:col-span-6">
                                <p class="f-light f-12 mb-1">SSL platnost</p>
                                <p class="mb-0 f-12 {{ $sslDays !== null && $sslDays < 30 ? 'text-danger' : '' }}">
                                    {{ $monitor->ssl_expires_at?->format('d.m.Y') ?? '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 items-center mb-3">
                            <x-panel.status-badge :status="$monitor->status" />
                            @if($monitor->status->value === 'down')
                                <span class="f-12 text-danger">
                                    <i data-feather="alert-triangle" style="width:12px;height:12px"></i>
                                    Služba není dostupná
                                </span>
                            @endif
                        </div>

                        {{-- Incident timeline --}}
                        @if($incidents->isNotEmpty())
                            <h6 class="f-12 f-light mb-2 border-top pt-3">Incidenty (posledních 10)</h6>
                            <ul class="list-unstyled mb-0">
                                @foreach($incidents as $incident)
                                    <li class="flex items-start gap-2 mb-2">
                                        <span class="badge badge-light-{{ $incident->isOpen() ? 'danger' : 'secondary' }} mt-1 f-10">
                                            {{ $incident->isOpen() ? 'Probíhá' : 'Vyřešeno' }}
                                        </span>
                                        <div>
                                            <p class="mb-0 f-12">{{ $incident->reason ?? 'Výpadek' }}</p>
                                            <p class="mb-0 f-10 f-light">
                                                {{ $incident->started_at?->format('d.m.Y H:i') }}
                                                @if($incident->resolved_at)
                                                    → {{ $incident->resolved_at->format('d.m.Y H:i') }}
                                                    ({{ $incident->started_at?->diffForHumans($incident->resolved_at, true) }})
                                                @endif
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="f-12 f-light mb-0 border-top pt-3">
                                <i data-feather="check-circle" style="width:13px;height:13px" class="text-success"></i>
                                Žádné incidenty za posledních 30 dní.
                            </p>
                        @endif
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

                {{-- Backup schedule configuration --}}
                <x-panel.card title="Plán zálohování">
                    @if(session('status') === 'Plán zálohování byl uložen.')
                        <div class="alert alert-success py-2 mb-3 f-12">Plán zálohování byl uložen.</div>
                    @endif
                    <form method="POST" action="{{ route('panel.services.backup-schedule', $service) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-4">
                                <label class="form-label f-12 f-w-600">Frekvence</label>
                                <select name="frequency" class="form-control form-control-sm @error('frequency') is-invalid @enderror">
                                    @foreach(['daily' => 'Denně', 'weekly' => 'Týdně', 'monthly' => 'Měsíčně'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('frequency', $backupPolicy?->frequency ?? 'daily') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('frequency')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="form-label f-12 f-w-600">Čas zálohy (hodina UTC)</label>
                                <select name="scheduled_hour" class="form-control form-control-sm @error('scheduled_hour') is-invalid @enderror">
                                    @for($h = 0; $h < 24; $h++)
                                        <option value="{{ $h }}" {{ (int) old('scheduled_hour', $backupPolicy?->scheduled_hour ?? 3) === $h ? 'selected' : '' }}>
                                            {{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}:00
                                        </option>
                                    @endfor
                                </select>
                                @error('scheduled_hour')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="form-label f-12 f-w-600">Den (pro týdenní plán)</label>
                                <select name="scheduled_weekday" class="form-control form-control-sm @error('scheduled_weekday') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach([0 => 'Pondělí', 1 => 'Úterý', 2 => 'Středa', 3 => 'Čtvrtek', 4 => 'Pátek', 5 => 'Sobota', 6 => 'Neděle'] as $d => $dn)
                                        <option value="{{ $d }}" {{ (string) old('scheduled_weekday', $backupPolicy?->scheduled_weekday) === (string) $d ? 'selected' : '' }}>{{ $dn }}</option>
                                    @endforeach
                                </select>
                                @error('scheduled_weekday')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-span-12 md:col-span-4">
                                <label class="form-label f-12 f-w-600">Uchovávat zálohy (dní)</label>
                                <input type="number" name="retention_days" min="1" max="365"
                                    value="{{ old('retention_days', $backupPolicy?->retention_days ?? 14) }}"
                                    class="form-control form-control-sm @error('retention_days') is-invalid @enderror">
                                @error('retention_days')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-span-12 md:col-span-4 flex items-center gap-2 pt-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="bp_active"
                                        {{ old('is_active', $backupPolicy?->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label f-12" for="bp_active">Aktivní</label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="notify_on_failure" value="1" id="bp_notify"
                                        {{ old('notify_on_failure', $backupPolicy?->notify_on_failure ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label f-12" for="bp_notify">Upozornit při chybě</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-feather="save" style="width:13px;height:13px"></i>
                                Uložit plán
                            </button>
                        </div>
                    </form>
                </x-panel.card>

                {{-- Phase 278: game server management (Pterodactyl only) --}}
                @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Pterodactyl)
                    <x-panel.card title="Správa game serveru">
                        @error('game')<div class="alert alert-light-danger py-2 f-12 mb-2">{{ $message }}</div>@enderror
                        <div class="flex items-center gap-2 mb-3">
                            <span id="game-status-badge" class="badge badge-light-secondary">Načítám stav…</span>
                            <span id="game-status-flag"></span>
                            <button type="button" id="game-status-refresh" class="btn btn-outline-secondary btn-xs ms-auto">
                                <i data-feather="refresh-cw" style="width:12px;height:12px"></i>
                            </button>
                        </div>
                        <div id="game-status-body" class="mb-3"></div>

                        @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                            <form method="POST" action="{{ route('panel.services.game-action', $service) }}"
                                  data-confirm="POZOR: Reinstalace smaže všechna data serveru a obnoví výchozí instalaci. Opravdu pokračovat?">
                                @csrf
                                <input type="hidden" name="action" value="reinstall">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i data-feather="refresh-ccw" style="width:13px;height:13px"></i>
                                    Reinstalovat server
                                </button>
                            </form>
                            <p class="f-light f-11 mt-2 mb-0">Reinstalace vrátí server do výchozího stavu — všechna herní data budou smazána.</p>
                        @else
                            <p class="f-light f-12 mb-0">Akce jsou dostupné jen pro aktivní služby.</p>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Phase 279: domain management (WEDOS only) --}}
                @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Wedos)
                    <x-panel.card title="Správa domény">
                        @if($service->domainRegistration)
                            @php
                                $domainReg = $service->domainRegistration;
                            @endphp
                            <table class="table table-borderless table-sm mb-3">
                                <tr>
                                    <td class="f-light ps-0" style="width:40%">Doména</td>
                                    <td class="f-w-600">{{ $domainReg->fqdn() }}</td>
                                </tr>
                                <tr>
                                    <td class="f-light ps-0">Expirace</td>
                                    <td>
                                        {{ $domainReg->expires_at?->format('d.m.Y') ?? '—' }}
                                        @if($domainReg->expires_at?->isPast())
                                            <span class="badge badge-light-danger ms-1">Expirováno</span>
                                        @elseif($domainReg->expires_at && $domainReg->expires_at->diffInDays(now()) <= 30)
                                            <span class="badge badge-light-warning ms-1">Brzy expiruje</span>
                                        @endif
                                    </td>
                                </tr>
                                @if(is_array($domainReg->nameservers) && count($domainReg->nameservers) > 0)
                                    <tr>
                                        <td class="f-light ps-0">Nameservery</td>
                                        <td class="f-12">{{ implode(', ', $domainReg->nameservers) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="f-light ps-0">Auto-prodloužení</td>
                                    <td>
                                        <form method="POST" action="{{ route('panel.domains.auto-renew', $domainReg) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-link p-0 border-0">
                                                <span class="badge {{ $domainReg->auto_renew ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $domainReg->auto_renew ? 'Zapnuto' : 'Vypnuto' }}
                                                </span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            </table>
                            <div class="flex gap-2 flex-wrap">
                                <a href="{{ route('panel.domains.show', $domainReg) }}" class="btn btn-outline-primary btn-sm">
                                    <i data-feather="globe" style="width:13px;height:13px"></i> Detail domény
                                </a>
                                <a href="{{ route('panel.domains.dns', $domainReg) }}" class="btn btn-outline-secondary btn-sm">
                                    <i data-feather="list" style="width:13px;height:13px"></i> DNS záznamy
                                </a>
                            </div>
                        @else
                            <p class="f-light f-12 mb-0">Ke službě zatím není připojena žádná registrace domény.</p>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Phase 277: webhosting management (aaPanel only) --}}
                @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel)
                    <x-panel.card title="Správa webhostingu">
                        @error('php')<div class="alert alert-light-danger py-2 f-12 mb-2">{{ $message }}</div>@enderror
                        @php
                            $currentPhp = $service->resources['php_version'] ?? null;
                        @endphp
                        <div class="flex items-center gap-2 mb-3">
                            <span class="f-12 f-light">Aktuální PHP verze:</span>
                            <span class="badge badge-light-primary">
                                {{ \App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob::VERSIONS[$currentPhp] ?? 'Neznámá' }}
                            </span>
                        </div>
                        @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                            <form method="POST" action="{{ route('panel.services.php-version', $service) }}" class="flex gap-2 items-end">
                                @csrf
                                <div>
                                    <label class="form-label f-12 mb-1">Nová PHP verze</label>
                                    <select name="php_version" class="form-select form-select-sm">
                                        @foreach(\App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob::VERSIONS as $val => $label)
                                            <option value="{{ $val }}" @selected($currentPhp === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"
                                        data-confirm="Změnit PHP verzi webu?">
                                    <i data-feather="refresh-cw" style="width:13px;height:13px"></i> Změnit
                                </button>
                            </form>
                            <p class="f-light f-11 mt-2 mb-0">Změna se zpracovává frontou — web může být na pár sekund nedostupný.</p>
                        @else
                            <p class="f-light f-12 mb-0">Změna PHP verze je dostupná jen pro aktivní služby.</p>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Phase 276: VPS power management (Proxmox only) --}}
                @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Proxmox)
                    <x-panel.card title="Správa VPS">
                        @error('vps')<div class="alert alert-light-danger py-2 f-12 mb-2">{{ $message }}</div>@enderror
                        <div class="flex items-center gap-2 mb-3">
                            <span id="vps-status-badge" class="badge badge-light-secondary">Načítám stav…</span>
                            <span id="vps-status-flag"></span>
                            <button type="button" id="vps-status-refresh" class="btn btn-outline-secondary btn-xs ms-auto">
                                <i data-feather="refresh-cw" style="width:12px;height:12px"></i>
                            </button>
                        </div>
                        <div id="vps-status-body" class="mb-3"></div>

                        @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                            <div class="flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('panel.services.vps-action', $service) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i data-feather="play" style="width:13px;height:13px"></i> Spustit
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.services.vps-action', $service) }}"
                                      data-confirm="Opravdu vypnout VPS?">
                                    @csrf
                                    <input type="hidden" name="action" value="stop">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i data-feather="square" style="width:13px;height:13px"></i> Vypnout
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.services.vps-action', $service) }}"
                                      data-confirm="Opravdu restartovat VPS?">
                                    @csrf
                                    <input type="hidden" name="action" value="restart">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i data-feather="rotate-cw" style="width:13px;height:13px"></i> Restartovat
                                    </button>
                                </form>
                            </div>
                            <p class="f-light f-11 mt-2 mb-0">Akce se zpracovávají frontou — stav se projeví během chvíle.</p>
                        @else
                            <p class="f-light f-12 mb-0">Akce napájení jsou dostupné jen pro aktivní služby.</p>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Complete webhosting configuration: databases, FTP, cron, SSL.
                     Same actions the admin screen has, scoped to this service. --}}
                @if($webhosting !== null && $webhosting['supported'])
                    <x-panel.card title="Konfigurace webhostingu">
                        @error('webhosting')<div class="alert alert-light-danger py-2 f-12 mb-2">{{ $message }}</div>@enderror

                        @if($webhosting['dry_run'])
                            <div class="alert alert-light-warning f-12" role="alert">
                                Provisioning běží v simulovaném režimu — změny se zapíší do fronty, ale na server se neodešlou.
                            </div>
                        @endif

                        {{-- Block form on purpose: the paren form would be swallowed
                             by Blade's raw-block regex, which pairs it with the
                             block terminator further down the file. --}}
                        @php
                            $isActive = $service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active;
                        @endphp

                        {{-- SSL --}}
                        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                            <div>
                                <span class="f-w-600">SSL certifikát</span>
                                @if($webhosting['ssl']['error'])
                                    <span class="badge badge-light-secondary">Nelze zjistit</span>
                                @elseif($webhosting['ssl']['active'])
                                    <span class="badge badge-light-success">Aktivní</span>
                                @else
                                    <span class="badge badge-light-warning">Neaktivní</span>
                                @endif
                            </div>
                            @if($isActive && ! $webhosting['ssl']['active'])
                                <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="issue_ssl">
                                    <button type="submit" class="btn btn-primary btn-sm text-white"
                                            data-confirm="Vydat SSL certifikát pro tento web?">
                                        Vydat certifikát
                                    </button>
                                </form>
                            @endif
                        </div>
                        <hr>

                        {{-- Databases --}}
                        <p class="f-w-600 f-14 mb-2">Databáze</p>
                        @if($webhosting['databases']['error'])
                            <div class="alert alert-light-danger f-12">Databáze nelze načíst: {{ $webhosting['databases']['error'] }}</div>
                        @elseif(count($webhosting['databases']['items']) === 0)
                            <p class="f-light f-12 mb-2">Zatím žádná databáze.</p>
                        @else
                            <x-panel.data-table :headers="['Název', 'Uživatel', '']">
                                @foreach($webhosting['databases']['items'] as $db)
                                    <tr>
                                        <td class="f-w-500">{{ $db['name'] ?? '—' }}</td>
                                        <td class="f-12 f-light">{{ $db['username'] ?? '—' }}</td>
                                        <td class="text-right">
                                            @if($isActive)
                                                <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                                      data-confirm="Smazat databázi {{ $db['name'] ?? '' }} i s jejím obsahem?">
                                                    @csrf
                                                    <input type="hidden" name="action" value="delete_database">
                                                    <input type="hidden" name="name" value="{{ $db['name'] ?? '' }}">
                                                    <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </x-panel.data-table>
                        @endif
                        @if($isActive)
                            <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                  class="grid grid-cols-12 gap-2 items-end mb-3">
                                @csrf
                                <input type="hidden" name="action" value="create_database">
                                <div class="col-span-12 md:col-span-5">
                                    <label class="form-label f-12">Název databáze</label>
                                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="64">
                                </div>
                                <div class="col-span-12 md:col-span-5">
                                    <label class="form-label f-12">Uživatel</label>
                                    <input type="text" name="username" class="form-control form-control-sm" required maxlength="32">
                                </div>
                                <div class="col-span-12 md:col-span-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-full text-white">Vytvořit</button>
                                </div>
                            </form>
                            <p class="f-light f-11 mb-3">Heslo databáze najdete po vytvoření v úloze provisioningu.</p>
                        @endif
                        <hr>

                        {{-- FTP --}}
                        <p class="f-w-600 f-14 mb-2">FTP účty</p>
                        @if($webhosting['ftp']['error'])
                            <div class="alert alert-light-danger f-12">FTP účty nelze načíst: {{ $webhosting['ftp']['error'] }}</div>
                        @elseif(count($webhosting['ftp']['items']) === 0)
                            <p class="f-light f-12 mb-2">Zatím žádný FTP účet.</p>
                        @else
                            <x-panel.data-table :headers="['Uživatel', 'Cesta', '']">
                                @foreach($webhosting['ftp']['items'] as $ftp)
                                    <tr>
                                        <td class="f-w-500">{{ $ftp['name'] ?? $ftp['username'] ?? '—' }}</td>
                                        <td class="f-12 f-light">{{ $ftp['path'] ?? '—' }}</td>
                                        <td class="text-right">
                                            @if($isActive)
                                                <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                                      data-confirm="Smazat FTP účet?">
                                                    @csrf
                                                    <input type="hidden" name="action" value="delete_ftp">
                                                    <input type="hidden" name="username" value="{{ $ftp['name'] ?? $ftp['username'] ?? '' }}">
                                                    <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </x-panel.data-table>
                        @endif
                        @if($isActive)
                            <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                  class="grid grid-cols-12 gap-2 items-end mb-3">
                                @csrf
                                <input type="hidden" name="action" value="create_ftp">
                                <div class="col-span-12 md:col-span-5">
                                    <label class="form-label f-12">FTP uživatel</label>
                                    <input type="text" name="username" class="form-control form-control-sm" required maxlength="32">
                                </div>
                                <div class="col-span-12 md:col-span-5">
                                    <label class="form-label f-12">Podsložka (nepovinné)</label>
                                    <input type="text" name="path" class="form-control form-control-sm" placeholder="prázdné = kořen webu">
                                </div>
                                <div class="col-span-12 md:col-span-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-full text-white">Vytvořit</button>
                                </div>
                            </form>
                        @endif
                        <hr>

                        {{-- Cron --}}
                        <p class="f-w-600 f-14 mb-2">Naplánované úlohy</p>
                        @if($webhosting['cron']['error'])
                            <div class="alert alert-light-danger f-12">Úlohy nelze načíst: {{ $webhosting['cron']['error'] }}</div>
                        @elseif(count($webhosting['cron']['items']) === 0)
                            <p class="f-light f-12 mb-2">Zatím žádná naplánovaná úloha.</p>
                        @else
                            <x-panel.data-table :headers="['Název', 'Kdy', '']">
                                @foreach($webhosting['cron']['items'] as $cron)
                                    <tr>
                                        <td class="f-w-500">{{ $cron['name'] ?? '—' }}</td>
                                        <td class="f-12 f-light">{{ $cron['type'] ?? '—' }}</td>
                                        <td class="text-right">
                                            @if($isActive && isset($cron['id']))
                                                <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                                      data-confirm="Smazat úlohu {{ $cron['name'] ?? '' }}?">
                                                    @csrf
                                                    <input type="hidden" name="action" value="delete_cron">
                                                    <input type="hidden" name="cron_id" value="{{ $cron['id'] }}">
                                                    <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </x-panel.data-table>
                        @endif
                        @if($isActive)
                            <form method="POST" action="{{ route('panel.services.webhosting.store', $service) }}"
                                  class="grid grid-cols-12 gap-2 items-end">
                                @csrf
                                <input type="hidden" name="action" value="create_cron">
                                <div class="col-span-12 md:col-span-3">
                                    <label class="form-label f-12">Název</label>
                                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="64">
                                </div>
                                <div class="col-span-12 md:col-span-4">
                                    <label class="form-label f-12">Příkaz</label>
                                    <input type="text" name="command" class="form-control form-control-sm" required maxlength="500">
                                </div>
                                <div class="col-span-6 md:col-span-2">
                                    <label class="form-label f-12">Interval</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="day">Denně</option>
                                        <option value="hour">Hodinově</option>
                                        <option value="week">Týdně</option>
                                        <option value="month">Měsíčně</option>
                                    </select>
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label f-12">Hod.</label>
                                    <input type="number" name="hour" class="form-control form-control-sm" value="3" min="0" max="23">
                                </div>
                                <div class="col-span-3 md:col-span-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-full text-white">Přidat</button>
                                </div>
                            </form>
                        @endif
                    </x-panel.card>
                @endif

                {{-- Git deployment — connect a repository and deploy from it,
                     the equivalent of aaPanel's own git feature. Webhosting only. --}}
                @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel)
                    <x-panel.card title="Nasazení z Gitu">
                        @if($gitRepository !== null)
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="f-w-600">{{ $gitRepository->shortName() }}</div>
                                    <div class="f-12 f-light">
                                        Větev <code>{{ $gitRepository->branch }}</code>
                                        @if($gitRepository->deploy_path)
                                            · cesta <code>{{ $gitRepository->deploy_path }}</code>
                                        @endif
                                    </div>
                                </div>
                                <span class="badge badge-light-{{ $gitRepository->statusColor() }}">
                                    {{ $gitRepository->statusLabel() }}
                                </span>
                            </div>

                            @if($gitRepository->last_deployed_at)
                                <p class="f-12 f-light mb-2">
                                    Naposledy nasazeno {{ $gitRepository->last_deployed_at->diffForHumans() }}
                                    @if($gitRepository->last_commit) · commit <code>{{ $gitRepository->last_commit }}</code> @endif
                                </p>
                            @endif

                            @if($gitRepository->last_error)
                                <div class="alert alert-light-danger f-12" role="alert">
                                    {{ $gitRepository->last_error }}
                                </div>
                            @endif

                            <div class="flex gap-2 flex-wrap mb-3">
                                <form method="POST" action="{{ route('panel.services.git.deploy', $service) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm text-white">
                                        <i data-feather="download-cloud" class="me-1" style="width:14px;height:14px;"></i>
                                        Nasadit nyní
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.services.git.destroy', $service) }}"
                                      data-confirm="Odpojit repozitář? Soubory na webu zůstanou beze změny.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-light btn-sm">Odpojit</button>
                                </form>
                            </div>

                            @if($gitRepository->auto_deploy)
                                <div class="mb-2">
                                    <label class="form-label f-12">Webhook URL (vložte u poskytovatele)</label>
                                    {{-- No onfocus="this.select()": inline handlers are
                                         blocked by our CSP. Click-to-select is wired in
                                         the page's nonced script block instead. --}}
                                    <input type="text" class="form-control form-control-sm font-monospace" readonly
                                           data-select-on-focus
                                           value="{{ route('webhooks.git', $gitRepository->webhook_token) }}">
                                    <div class="f-11 f-light mt-1">
                                        Nasadí se jen push do větve <code>{{ $gitRepository->branch }}</code>.
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('panel.services.git.rotate-token', $service) }}"
                                      data-confirm="Obnovit URL? Stará přestane fungovat.">
                                    @csrf
                                    <button type="submit" class="btn btn-light btn-xs">Obnovit webhook URL</button>
                                </form>
                            @endif

                            @if($gitRepository->last_output)
                                <details class="mt-3">
                                    <summary class="f-12">Výstup posledního nasazení</summary>
                                    <pre class="f-11 mt-2 mb-0">{{ Str::limit($gitRepository->last_output, 2000) }}</pre>
                                </details>
                            @endif
                        @else
                            <p class="f-m-light mb-3">
                                Připojte git repozitář a nasazujte web přímo z něj — ručně tlačítkem,
                                nebo automaticky při každém pushi.
                            </p>
                        @endif

                        <hr>
                        <form method="POST" action="{{ route('panel.services.git.store', $service) }}">
                            @csrf
                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-12 md:col-span-8">
                                    <label class="form-label f-12">Adresa repozitáře</label>
                                    <input type="text" name="repository_url"
                                           class="form-control form-control-sm @error('repository_url') is-invalid @enderror"
                                           value="{{ old('repository_url', $gitRepository->repository_url ?? '') }}"
                                           placeholder="https://github.com/uzivatel/projekt.git" required>
                                    @error('repository_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-12 md:col-span-4">
                                    <label class="form-label f-12">Větev</label>
                                    <input type="text" name="branch" class="form-control form-control-sm"
                                           value="{{ old('branch', $gitRepository->branch ?? 'main') }}" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-12 md:col-span-6">
                                    <label class="form-label f-12">Podsložka (nepovinné)</label>
                                    <input type="text" name="deploy_path" class="form-control form-control-sm"
                                           value="{{ old('deploy_path', $gitRepository->deploy_path ?? '') }}"
                                           placeholder="např. public">
                                </div>
                                <div class="col-span-12 md:col-span-6">
                                    <label class="form-label f-12">Příkazy po nasazení</label>
                                    <textarea name="post_deploy_commands" class="form-control form-control-sm" rows="2"
                                              placeholder="composer install --no-dev">{{ old('post_deploy_commands', $gitRepository->post_deploy_commands ?? '') }}</textarea>
                                    <div class="f-11 f-light mt-1">
                                        Jeden příkaz na řádek. Povoleno: composer, npm, yarn, pnpm, php, node, bun, make.
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_deploy" value="1" id="git-auto"
                                       @checked(old('auto_deploy', $gitRepository->auto_deploy ?? false))>
                                <label class="form-check-label f-12" for="git-auto">
                                    Automaticky nasadit při pushi (vygeneruje webhook URL)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm text-white">
                                {{ $gitRepository !== null ? 'Uložit nastavení' : 'Připojit repozitář' }}
                            </button>
                        </form>
                    </x-panel.card>
                @endif

                {{-- Phase 274: planned maintenance affecting this service --}}
                @if($maintenanceWindows->isNotEmpty())
                    <x-panel.card title="Plánovaná údržba">
                        @foreach($maintenanceWindows as $window)
                            <div class="flex items-start gap-2 py-2 border-bottom">
                                <i data-feather="tool" style="width:15px;height:15px;flex-shrink:0;" class="txt-warning mt-1"></i>
                                <div class="grow">
                                    <div class="flex items-center gap-2">
                                        <span class="f-12 f-w-600">{{ $window->title }}</span>
                                        <span class="badge {{ $window->status === 'in_progress' ? 'badge-light-danger' : 'badge-light-warning' }} f-10">
                                            {{ $window->status === 'in_progress' ? 'Probíhá' : 'Naplánováno' }}
                                        </span>
                                    </div>
                                    @if($window->description)
                                        <p class="f-12 f-light mb-0">{{ $window->description }}</p>
                                    @endif
                                    <p class="f-11 f-light mb-0">
                                        {{ $window->starts_at->format('d.m.Y H:i') }} – {{ $window->ends_at->format('d.m.Y H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- Phase 274: open incidents on this service --}}
                @if($healthIncidents->isNotEmpty())
                    <x-panel.card title="Aktivní incidenty služby">
                        @foreach($healthIncidents as $incident)
                            <div class="flex items-start gap-2 py-2 border-bottom">
                                <i data-feather="alert-triangle" style="width:15px;height:15px;flex-shrink:0;"
                                   class="{{ $incident->severity === 'critical' ? 'txt-danger' : 'txt-warning' }} mt-1"></i>
                                <div class="grow">
                                    <div class="flex items-center gap-2">
                                        <span class="f-12 f-w-600">{{ $incident->title }}</span>
                                        <span class="badge {{ match($incident->severity) { 'critical' => 'badge-light-danger', 'warning' => 'badge-light-warning', default => 'badge-light-info' } }} f-10">
                                            {{ ['info' => 'Info', 'warning' => 'Varování', 'critical' => 'Kritický'][$incident->severity] ?? $incident->severity }}
                                        </span>
                                    </div>
                                    @if($incident->description)
                                        <p class="f-12 f-light mb-0">{{ $incident->description }}</p>
                                    @endif
                                    <p class="f-11 f-light mb-0">{{ $incident->created_at?->format('d.m.Y H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                        <p class="f-light f-11 mt-2 mb-0">
                            <a href="{{ route('panel.service-health-incidents.index') }}">Všechny incidenty →</a>
                        </p>
                    </x-panel.card>
                @endif

                {{-- Phase 274: firewall rules for this service (not for domains) --}}
                @if($service->provisioning_driver !== \App\Domains\Provisioning\Enums\ProvisioningDriver::Wedos)
                    <x-panel.card title="Firewall">
                        @if($firewallRules->isEmpty())
                            <p class="f-light f-12 mb-3">Žádná pravidla firewallu pro tuto službu.</p>
                        @else
                            <x-panel.data-table :headers="['Směr', 'Protokol', 'Porty', 'IP / CIDR', 'Akce', '']">
                                @foreach($firewallRules as $rule)
                                    <tr>
                                        <td><span class="badge badge-light-secondary">{{ strtoupper($rule->direction) }}</span></td>
                                        <td class="f-12">{{ strtoupper($rule->protocol) }}</td>
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
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('panel.service-firewall-rules.destroy', $rule) }}" class="inline"
                                                  data-confirm="Odstranit pravidlo?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-xs">
                                                    <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </x-panel.data-table>
                        @endif

                        <form method="POST" action="{{ route('panel.service-firewall-rules.store') }}" class="grid grid-cols-12 gap-2 items-end mt-3">
                            @csrf
                            <input type="hidden" name="service_id" value="{{ $service->id }}">
                            <div class="col-span-6 col-span-12 md:col-span-2">
                                <label class="form-label f-12 mb-1">Směr</label>
                                <select name="direction" class="form-select form-select-sm">
                                    <option value="in">Příchozí</option>
                                    <option value="out">Odchozí</option>
                                    <option value="both">Obojí</option>
                                </select>
                            </div>
                            <div class="col-span-6 col-span-12 md:col-span-2">
                                <label class="form-label f-12 mb-1">Protokol</label>
                                <select name="protocol" class="form-select form-select-sm">
                                    <option value="tcp">TCP</option>
                                    <option value="udp">UDP</option>
                                    <option value="icmp">ICMP</option>
                                    <option value="any">Any</option>
                                </select>
                            </div>
                            <div class="col-span-6 col-span-12 md:col-span-2">
                                <label class="form-label f-12 mb-1">Port od</label>
                                <input type="number" name="port_from" class="form-control form-control-sm" min="1" max="65535" placeholder="443">
                            </div>
                            <div class="col-span-6 col-span-12 md:col-span-2">
                                <label class="form-label f-12 mb-1">Port do</label>
                                <input type="number" name="port_to" class="form-control form-control-sm" min="1" max="65535" placeholder="443">
                            </div>
                            <div class="col-span-6 col-span-12 md:col-span-2">
                                <label class="form-label f-12 mb-1">IP / CIDR</label>
                                <input type="text" name="ip_cidr" class="form-control form-control-sm" placeholder="0.0.0.0/0" required maxlength="50">
                            </div>
                            <div class="col-span-3 col-span-12 md:col-span-1">
                                <label class="form-label f-12 mb-1">Akce</label>
                                <select name="action" class="form-select form-select-sm">
                                    <option value="allow">Povolit</option>
                                    <option value="deny">Zakázat</option>
                                </select>
                            </div>
                            <div class="col-span-3 col-span-12 md:col-span-1">
                                <button type="submit" class="btn btn-primary btn-sm w-full">Přidat</button>
                            </div>
                        </form>
                    </x-panel.card>
                @endif

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
                                                <div class="flex justify-between">
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

                {{-- Email hosting: mailbox self-service --}}
                @if($webhosting !== null && $webhosting["supported"])
                    @php
                        $mailboxes = $webhosting['mailboxes'];
                    @endphp
                    <x-panel.card title="E-mailové schránky">
                        @if($mailboxes['error'])
                            <div class="alert alert-light-danger f-12 mb-3">Schránky nelze načíst: {{ $mailboxes['error'] }}</div>
                        @elseif(count($mailboxes['items']) === 0)
                            <p class="f-light f-12 mb-3">Zatím žádná schránka. Vytvořte první níže.</p>
                        @else
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Schránka</th><th>Kvóta</th><th class="text-right"></th></tr></thead>
                                    <tbody>
                                        @foreach($mailboxes['items'] as $box)
                                            <tr>
                                                <td class="font-monospace f-12">{{ $box['username'] ?? ($box['full_name'] ?? '—') }}</td>
                                                <td class="f-12">{{ $box['quota_mb'] ?? $box['quota'] ?? '—' }}</td>
                                                <td class="text-right">
                                                    <form method="POST" action="{{ route('panel.services.mailboxes', $service) }}"
                                                          data-confirm="Smazat schránku {{ $box['username'] ?? '' }}? Veškerá pošta bude ztracena.">
                                                        @csrf
                                                        <input type="hidden" name="action" value="delete_mailbox">
                                                        <input type="hidden" name="username" value="{{ $box['username'] ?? '' }}">
                                                        <button type="submit" class="btn btn-xs btn-outline-danger">
                                                            <i data-feather="trash-2" style="width:11px;height:11px;"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('panel.services.mailboxes', $service) }}">
                            @csrf
                            <input type="hidden" name="action" value="create_mailbox">
                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-5">
                                    <label class="form-label f-11 f-light">Jméno schránky</label>
                                    <input type="text" name="username" class="form-control form-control-sm @error('username') is-invalid @enderror" placeholder="info">
                                </div>
                                <div class="col-span-4">
                                    <label class="form-label f-11 f-light">Heslo</label>
                                    <input type="password" name="password" autocomplete="new-password" class="form-control form-control-sm @error('password') is-invalid @enderror">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-3">
                                    <label class="form-label f-11 f-light">Kvóta (MB)</label>
                                    <input type="number" name="quota_mb" value="1024" min="0" max="51200" class="form-control form-control-sm">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-feather="mail" style="width:13px;height:13px;"></i>
                                Vytvořit schránku
                            </button>
                        </form>
                    </x-panel.card>
                @endif

                {{-- Reviews module: rate this service --}}
                <x-panel.card title="Ohodnotit službu">
                    @if($myReview)
                        @php
                            $statusMap = [
                                'pending'  => ['badge' => 'warning', 'label' => 'Čeká na schválení'],
                                'approved' => ['badge' => 'success', 'label' => 'Zveřejněno'],
                                'rejected' => ['badge' => 'secondary', 'label' => 'Zamítnuto'],
                            ][$myReview->status] ?? ['badge' => 'secondary', 'label' => $myReview->status];
                        @endphp
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-warning" style="letter-spacing:1px;">{{ str_repeat('★', $myReview->rating) }}<span class="text-muted">{{ str_repeat('☆', 5 - $myReview->rating) }}</span></span>
                            <span class="badge badge-light-{{ $statusMap['badge'] }}">{{ $statusMap['label'] }}</span>
                        </div>
                        <p class="f-light f-12 mb-3">Svou recenzi můžete kdykoliv upravit — po úpravě projde znovu schválením.</p>
                    @else
                        <p class="f-light f-12 mb-3">Podělte se o zkušenost s touto službou. Recenze se zobrazí po schválení.</p>
                    @endif

                    <form method="POST" action="{{ route('panel.services.review', $service) }}">
                        @csrf
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-4">
                                <label class="form-label f-11 f-light">Hodnocení</label>
                                <select name="rating" class="form-select form-select-sm @error('rating') is-invalid @enderror">
                                    @for($r = 5; $r >= 1; $r--)
                                        <option value="{{ $r }}" {{ (int) old('rating', $myReview->rating ?? 5) === $r ? 'selected' : '' }}>{{ str_repeat('★', $r) }} ({{ $r }})</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-span-8">
                                <label class="form-label f-11 f-light">Nadpis (nepovinné)</label>
                                <input type="text" name="title" maxlength="150" value="{{ old('title', $myReview->title ?? '') }}"
                                       class="form-control form-control-sm @error('title') is-invalid @enderror">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-11 f-light">Vaše recenze</label>
                            <textarea name="body" rows="3" maxlength="2000"
                                      class="form-control form-control-sm @error('body') is-invalid @enderror">{{ old('body', $myReview->body ?? '') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-feather="star" style="width:13px;height:13px;"></i>
                            {{ $myReview ? 'Aktualizovat recenzi' : 'Odeslat recenzi' }}
                        </button>
                    </form>
                </x-panel.card>
            </div>
        </div>
    </div>

    {{-- Cancellation Survey Modal --}}
    @if(in_array($service->status->value, ['active', 'suspended']))
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('panel.services.request-cancel', $service) }}">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title">Zrušení služby</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="f-12 text-muted mb-3">Žádost o zrušení bude odeslána podpoře. Před zpracováním vás budeme kontaktovat.</p>

                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">Důvod zrušení <span class="text-muted f-w-400">(volitelné)</span></label>
                            <select name="cancellation_reason" class="form-select form-select-sm">
                                <option value="">— Vyberte důvod —</option>
                                @foreach(\App\Models\ServiceCancellation::REASONS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12 f-w-600">Doplňující komentář <span class="text-muted f-w-400">(volitelné)</span></label>
                            <textarea name="cancellation_feedback" class="form-control form-control-sm"
                                      rows="3" maxlength="1000"
                                      placeholder="Pomozte nám porozumět vašemu rozhodnutí…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Zpět</button>
                        <button type="submit" class="btn btn-sm btn-danger">Odeslat žádost o zrušení</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Pterodactyl)
        <script nonce="{{ $cspNonce ?? '' }}">
            document.addEventListener('DOMContentLoaded', function () {
                const badge = document.getElementById('game-status-badge');
                const flag  = document.getElementById('game-status-flag');
                const body  = document.getElementById('game-status-body');
                const btn   = document.getElementById('game-status-refresh');
                if (!badge) return;

                const esc = s => String(s ?? '—').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));

                function load() {
                    badge.className = 'badge badge-light-secondary';
                    badge.textContent = 'Načítám stav…';

                    fetch('{{ route('panel.services.live-status', $service) }}', {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        flag.innerHTML = data.dry_run
                            ? '<span class="badge badge-light-warning f-10">MOCK</span>'
                            : '';

                        if (!data.ok) {
                            badge.className = 'badge badge-light-danger';
                            badge.textContent = 'Stav nedostupný';
                            // Say WHY — a blank box is indistinguishable from
                            // "nothing here" and leaves the customer guessing.
                            body.innerHTML = '<span class="f-12 f-light">'
                                + esc(data.error || 'Nepodařilo se spojit se serverem. Zkuste to prosím za chvíli nebo kontaktujte podporu.')
                                + '</span>';
                            return;
                        }

                        const st = String(data.data.status ?? 'neznámý');
                        badge.className = 'badge ' + (st === 'running' ? 'badge-light-success' : (st === 'offline' ? 'badge-light-danger' : 'badge-light-secondary'));
                        badge.textContent = st === 'running' ? 'Běží' : (st === 'offline' ? 'Offline' : esc(st));

                        body.innerHTML = data.data.name
                            ? '<span class="f-12 f-light">Server: <strong>' + esc(data.data.name) + '</strong></span>'
                            : '';
                    })
                    .catch(() => {
                        badge.className = 'badge badge-light-danger';
                        badge.textContent = 'Stav nedostupný';
                    });
                }

                btn?.addEventListener('click', load);
                load();
            });
        </script>
    @endif

    @if($service->provisioning_driver === \App\Domains\Provisioning\Enums\ProvisioningDriver::Proxmox)
        <script nonce="{{ $cspNonce ?? '' }}">
            document.addEventListener('DOMContentLoaded', function () {
                const badge = document.getElementById('vps-status-badge');
                const flag  = document.getElementById('vps-status-flag');
                const body  = document.getElementById('vps-status-body');
                const btn   = document.getElementById('vps-status-refresh');
                if (!badge) return;

                const esc = s => String(s ?? '—').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));

                function load() {
                    badge.className = 'badge badge-light-secondary';
                    badge.textContent = 'Načítám stav…';

                    fetch('{{ route('panel.services.live-status', $service) }}', {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        flag.innerHTML = data.dry_run
                            ? '<span class="badge badge-light-warning f-10">MOCK</span>'
                            : '';

                        if (!data.ok) {
                            badge.className = 'badge badge-light-danger';
                            badge.textContent = 'Stav nedostupný';
                            body.innerHTML = '<span class="f-12 f-light">'
                                + esc(data.error || 'Nepodařilo se spojit se serverem. Zkuste to prosím za chvíli nebo kontaktujte podporu.')
                                + '</span>';
                            return;
                        }

                        const st = String(data.data.status ?? data.data.qmpstatus ?? 'neznámý');
                        badge.className = 'badge ' + (st === 'running' ? 'badge-light-success' : (st === 'stopped' ? 'badge-light-danger' : 'badge-light-secondary'));
                        badge.textContent = st === 'running' ? 'Běží' : (st === 'stopped' ? 'Vypnuto' : esc(st));

                        let html = '';
                        if (data.data.cpus)   html += '<span class="f-12 f-light me-3">CPU: <strong>' + esc(data.data.cpus) + '</strong></span>';
                        if (data.data.maxmem) html += '<span class="f-12 f-light me-3">RAM: <strong>' + Math.round(data.data.maxmem / 1073741824 * 10) / 10 + ' GB</strong></span>';
                        if (data.data.uptime) html += '<span class="f-12 f-light">Uptime: <strong>' + Math.floor(data.data.uptime / 3600) + ' h</strong></span>';
                        body.innerHTML = html;
                    })
                    .catch(() => {
                        badge.className = 'badge badge-light-danger';
                        badge.textContent = 'Stav nedostupný';
                    });
                }

                btn?.addEventListener('click', load);
                load();
            });
        </script>
    @endif

    {{-- Select-all on focus for read-only fields (webhook URL). Delegated so it
         works without an inline handler, which our CSP blocks. --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('focusin', function (e) {
            if (e.target.matches('[data-select-on-focus]')) {
                e.target.select();
            }
        });
    </script>
@endsection
