@extends('layouts.panel')

@php
    $breadcrumbTitle = $domain->fqdn();
    $breadcrumbItems = [__('panel.nav.admin_domains') => route('admin.domains.index'), $domain->fqdn() => ''];
@endphp

@section('title', $domain->fqdn())

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        @php
            $expiresDays = $domain->expires_at ? now()->diffInDays($domain->expires_at, false) : null;
            $expiryClass = '';
            if ($expiresDays !== null) {
                if ($expiresDays < 0) $expiryClass = 'text-danger f-w-600';
                elseif ($expiresDays <= 30) $expiryClass = 'text-warning f-w-600';
            }
        @endphp

        <div class="grid grid-cols-12 card-gap">
            {{-- Main: domain details + tasks --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$domain->fqdn()">
                    {{-- Status badge --}}
                    <div class="flex gap-2 mb-3 items-center">
                        @if($domain->wedos_domain_id)
                            <span class="badge badge-light-success">{{ __('panel.domains.registered') }}</span>
                        @else
                            <span class="badge badge-light-warning">{{ __('panel.domains.pending') }}</span>
                        @endif
                        @if($domain->auto_renew)
                            <span class="badge badge-light-success">auto-renew ON</span>
                        @else
                            <span class="badge badge-light-secondary">auto-renew OFF</span>
                        @endif
                    </div>

                    {{-- Info grid --}}
                    <div class="grid grid-cols-12 gap-3 mb-4">
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.registrar') }}</p>
                            <p class="mb-0 f-w-500 uppercase">{{ $domain->registrar ?? '—' }}</p>
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.registered_at') }}</p>
                            <p class="mb-0">{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</p>
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.expires_at') }}</p>
                            <p class="mb-0 {{ $expiryClass }}">
                                {{ $domain->expires_at?->format('d.m.Y') ?? '—' }}
                                @if($expiresDays !== null && $expiresDays >= 0 && $expiresDays <= 30)
                                    <br><span class="f-11">({{ $expiresDays }}d)</span>
                                @elseif($expiresDays !== null && $expiresDays < 0)
                                    <br><span class="badge badge-light-danger f-12">EXPIRED</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">WEDOS ID</p>
                            <p class="mb-0 f-12 font-monospace">{{ $domain->wedos_domain_id ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- Nameservers --}}
                    <div class="border-top pt-3 mt-1 mb-3">
                        <p class="f-light f-12 mb-2">{{ __('panel.domains.nameservers') }}</p>
                        @if(!empty($domain->nameservers))
                            <ul class="list-unstyled mb-0">
                                @foreach($domain->nameservers as $ns)
                                    <li class="mb-1 flex items-center gap-2">
                                        <i data-feather="server" class="font-secondary" style="width:12px;height:12px;flex-shrink:0"></i>
                                        <span class="f-13 font-monospace">{{ $ns }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="f-light f-12 mb-0">—</p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="border-top pt-3 mt-1 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.domains.toggle-auto-renew', $domain) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-{{ $domain->auto_renew ? 'warning' : 'success' }} btn-sm">
                                <i data-feather="{{ $domain->auto_renew ? 'toggle-right' : 'toggle-left' }}" style="width:14px;height:14px;"></i>
                                {{ $domain->auto_renew ? __('panel.domains.auto_renew_disable') : __('panel.domains.auto_renew_enable') }}
                            </button>
                        </form>
                        @if($domain->service)
                            <a href="{{ route('admin.services.show', $domain->service) }}" class="btn btn-outline-secondary btn-sm">
                                <i data-feather="server" style="width:13px;height:13px"></i>
                                {{ __('panel.nav.admin_services') }}
                            </a>
                        @endif
                    </div>
                </x-panel.card>

                {{-- Provisioning tasks --}}
                @if($tasks->isNotEmpty())
                    <x-panel.card title="{{ __('panel.admin.task') }}">
                        <x-panel.data-table :headers="[
                            '#', __('panel.admin.task'), __('panel.common.status'),
                            __('panel.admin.attempts'), __('panel.admin.error'), ''
                        ]">
                            @foreach($tasks as $task)
                                <tr>
                                    <td class="f-12 f-light">{{ $task->id }}</td>
                                    <td class="f-12">{{ $task->operation }}</td>
                                    <td><x-panel.status-badge :status="$task->status" /></td>
                                    <td class="f-12">{{ $task->attempts }}/{{ $task->max_attempts }}</td>
                                    <td class="f-light f-12">{{ $task->error_message ?? '—' }}</td>
                                    <td>
                                        @if($task->status->canRetry())
                                            <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.retry') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                @endif
            </div>

            {{-- Sidebar: customer + service --}}
            <div class="col-span-4 xl:col-span-12">
                @if($domain->service?->customer)
                    <x-panel.card :title="__('panel.common.customer')">
                        <p class="mb-1 f-w-600">{{ $domain->service->customer->company_name ?? '—' }}</p>
                        <p class="mb-3 f-light">{{ $domain->service->customer->email }}</p>
                        <a href="{{ route('admin.customers.show', $domain->service->customer) }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="user" style="width:13px;height:13px"></i>
                            {{ __('panel.common.detail') }}
                        </a>
                    </x-panel.card>
                @endif

                @if($domain->service)
                    <x-panel.card :title="__('panel.nav.admin_services')">
                        <p class="mb-1 f-w-600">{{ $domain->service->label }}</p>
                        <p class="mb-1 f-light f-12">{{ $domain->service->product->name ?? '—' }}</p>
                        <x-panel.status-badge :status="$domain->service->status" />
                        <div class="mt-3">
                            <a href="{{ route('admin.services.show', $domain->service) }}" class="btn btn-outline-secondary btn-sm">
                                <i data-feather="server" style="width:13px;height:13px"></i>
                                {{ __('panel.common.detail') }}
                            </a>
                        </div>
                    </x-panel.card>
                @endif

                <div class="mt-1">
                    <a href="{{ route('admin.domains.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px"></i>
                        {{ __('panel.nav.admin_domains') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
