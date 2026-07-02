@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_services');
    $breadcrumbItems = [__('panel.nav.admin_services') => ''];
@endphp

@section('title', __('panel.nav.admin_services'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />
        @error('service')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

        <div class="grid grid-cols-12 gap-3 mb-3">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-success rounded p-2"><i data-feather="check-circle" class="font-success"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $activeCount }}</h5>
                                    <span class="f-light f-12">Aktivní</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-warning rounded p-2"><i data-feather="pause-circle" class="font-warning"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600">{{ $suspendedCount }}</h5>
                                    <span class="f-light f-12">Pozastavené</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-warning rounded p-2"><i data-feather="clock" class="font-warning"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $expiringCount > 0 ? 'font-warning' : '' }}">{{ $expiringCount }}</h5>
                                    <span class="f-light f-12">Platba do 30 dní</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <div class="small-widget">
                    <div class="card card-no-border">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light-danger rounded p-2"><i data-feather="alert-circle" class="font-danger"></i></div>
                                <div>
                                    <h5 class="mb-0 f-w-600 {{ $overdueCount > 0 ? 'font-danger' : '' }}">{{ $overdueCount }}</h5>
                                    <span class="f-light f-12">Po splatnosti</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_services')">
            <form method="GET" action="{{ route('admin.services.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 180px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Provisioning\Enums\ServiceStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select name="due" class="form-select" style="max-width: 160px;">
                    <option value="">Všechna data</option>
                    <option value="soon" @selected($dueFilter === 'soon')>Platba do 30 dní</option>
                    <option value="overdue" @selected($dueFilter === 'overdue')>Po splatnosti</option>
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 220px;"
                       placeholder="Label, ext. ID, e-mail…" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || $search || $dueFilter)
                    <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $services->total() }} služeb</span>
                <a href="{{ route('admin.services.export', array_filter(['status' => $filter, 'q' => $search, 'due' => $dueFilter])) }}"
                   class="btn btn-outline-success btn-sm ms-2" title="Export do CSV">
                    <i data-feather="download" style="width:13px;height:13px;"></i> CSV
                </a>
            </form>

            @if($services->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="server" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                </div>
            @else
                {{-- Bulk actions toolbar --}}
                <div id="bulk-toolbar" class="d-none alert alert-light-primary py-2 px-3 mb-2 d-flex align-items-center gap-3">
                    <span class="f-14"><strong id="bulk-count">0</strong> vybráno</span>
                    <button type="button" class="btn btn-warning btn-sm text-white"
                            data-bs-toggle="modal" data-bs-target="#batchSuspendModal">
                        <i data-feather="pause" style="width:12px;height:12px;"></i> Pozastavit
                    </button>
                    <form id="batch-unsuspend-form" method="POST" action="{{ route('admin.services.batch-unsuspend') }}" class="d-inline">
                        @csrf
                        <div id="batch-unsuspend-ids"></div>
                        <button type="submit" class="btn btn-success btn-sm text-white"
                                onclick="return confirm('Reaktivovat vybrané služby?')">
                            <i data-feather="play" style="width:12px;height:12px;"></i> Reaktivovat
                        </button>
                    </form>
                    <a href="#" class="f-light f-12 ms-auto" onclick="uncheckAll();return false;">Zrušit výběr</a>
                </div>

                <form id="services-table-form">
                    <div class="recent-table overflow-x-auto custom-scrollbar">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:36px;">
                                        <input type="checkbox" class="form-check-input" id="select-all-services" title="Vybrat vše">
                                    </th>
                                    <th>ID</th>
                                    <th>{{ __('panel.services.label') }}</th>
                                    <th>{{ __('panel.common.customer') }}</th>
                                    <th>{{ __('panel.services.product') }}</th>
                                    <th>{{ __('panel.services.server') }}</th>
                                    <th>Doména</th>
                                    <th>Příští platba</th>
                                    <th>{{ __('panel.common.status') }}</th>
                                    <th>{{ __('panel.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($services as $service)
                                    @php
                                        $due = $service->next_due_date;
                                        $dueClass = '';
                                        if ($due) {
                                            if ($due->isPast()) { $dueClass = 'text-danger f-w-600'; }
                                            elseif ($due->diffInDays(now()) <= 30) { $dueClass = 'text-warning'; }
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input service-check"
                                                   value="{{ $service->id }}" data-status="{{ $service->status->value }}">
                                        </td>
                                        <td><a href="{{ route('admin.services.show', $service) }}">#{{ $service->id }}</a></td>
                                        <td><a href="{{ route('admin.services.show', $service) }}" class="f-w-600">{{ $service->label }}</a></td>
                                        <td>{{ $service->customer?->company_name ?? $service->customer?->email }}</td>
                                        <td>{{ $service->product?->name }}</td>
                                        <td>{{ $service->server?->name ?? '—' }}</td>
                                        <td>{{ $service->domainRegistration?->fqdn() ?? '—' }}</td>
                                        <td class="f-12 {{ $dueClass }}">{{ $due?->format('d.m.Y') ?? '—' }}</td>
                                        <td><x-panel.status-badge :status="$service->status" /></td>
                                        <td>
                                            @if($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Active)
                                                <form method="POST" action="{{ route('admin.services.suspend', $service) }}" class="d-flex gap-1">
                                                    @csrf
                                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('panel.admin.suspend_reason') }}" required minlength="3" style="max-width: 130px;">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('panel.admin.suspend') }}</button>
                                                </form>
                                            @elseif($service->status === \App\Domains\Provisioning\Enums\ServiceStatus::Suspended)
                                                <form method="POST" action="{{ route('admin.services.unsuspend', $service) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-success btn-sm">{{ __('panel.admin.unsuspend') }}</button>
                                                </form>
                                            @else
                                                <a href="{{ route('admin.services.show', $service) }}" class="btn btn-outline-secondary btn-sm">Detail</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
                {{ $services->links() }}
            @endif
        </x-panel.card>
    </div>

{{-- Batch suspend modal --}}
<div class="modal fade" id="batchSuspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hromadné pozastavení</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="batch-suspend-form" method="POST" action="{{ route('admin.services.batch-suspend') }}">
                @csrf
                <div id="batch-suspend-ids"></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Důvod pozastavení *</label>
                        <input type="text" name="reason" class="form-control" required minlength="3" maxlength="255"
                               placeholder="Důvod pozastavení…">
                    </div>
                    <div class="alert alert-light-warning f-12">
                        Budou pozastaveny pouze aktivní služby z výběru.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-warning text-white">Pozastavit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var checkboxes = document.querySelectorAll('.service-check');
    var selectAll  = document.getElementById('select-all-services');
    var toolbar    = document.getElementById('bulk-toolbar');
    var countEl    = document.getElementById('bulk-count');

    function updateToolbar() {
        var checked = document.querySelectorAll('.service-check:checked');
        var ids     = Array.from(checked).map(function (cb) { return cb.value; });
        var count   = ids.length;

        if (count > 0) {
            toolbar.classList.remove('d-none');
            toolbar.classList.add('d-flex');
        } else {
            toolbar.classList.add('d-none');
            toolbar.classList.remove('d-flex');
        }
        countEl.textContent = count;

        // Populate hidden ID fields for batch forms
        ['batch-suspend-ids', 'batch-unsuspend-ids'].forEach(function (containerId) {
            var c = document.getElementById(containerId);
            if (c) {
                c.innerHTML = ids.map(function (id) {
                    return '<input type="hidden" name="ids[]" value="' + id + '">';
                }).join('');
            }
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            updateToolbar();
        });
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateToolbar);
    });

    window.uncheckAll = function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        if (selectAll) selectAll.checked = false;
        updateToolbar();
    };
})();
</script>
@endpush
