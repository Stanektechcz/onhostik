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

        <div class="row mb-3">
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
            </form>

            @if($services->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="server" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                </div>
            @else
                <x-panel.data-table :headers="[
                    'ID',
                    __('panel.services.label'),
                    __('panel.common.customer'),
                    __('panel.services.product'),
                    __('panel.services.server'),
                    __('panel.services.external_id'),
                    __('panel.services.domain'),
                    'Příští platba',
                    __('panel.common.status'),
                    __('panel.common.actions'),
                ]">
                    @foreach($services as $service)
                        @php
                            $due = $service->next_due_date;
                            $dueClass = '';
                            if ($due) {
                                if ($due->isPast()) {
                                    $dueClass = 'text-danger f-w-600';
                                } elseif ($due->diffInDays(now()) <= 30) {
                                    $dueClass = 'text-warning';
                                }
                            }
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.services.show', $service) }}">#{{ $service->id }}</a></td>
                            <td><a href="{{ route('admin.services.show', $service) }}" class="f-w-600">{{ $service->label }}</a></td>
                            <td>{{ $service->customer?->company_name ?? $service->customer?->email }}</td>
                            <td>{{ $service->product?->name }}</td>
                            <td>{{ $service->server?->name ?? '—' }}</td>
                            <td class="f-light f-12">{{ $service->external_id ?? '—' }}</td>
                            <td>{{ $service->domainRegistration?->fqdn() ?? '—' }}</td>
                            <td class="f-12 {{ $dueClass }}">
                                {{ $due?->format('d.m.Y') ?? '—' }}
                            </td>
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
                </x-panel.data-table>
                {{ $services->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
