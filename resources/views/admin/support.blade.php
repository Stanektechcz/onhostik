@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_support');
    $breadcrumbItems = [__('panel.nav.admin_support') => ''];
@endphp

@section('title', __('panel.nav.admin_support'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- KPI strip --}}
        <div class="grid grid-cols-12 card-gap mb-1">
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.support.status_open')"
                    :value="$countOpen" icon="message-circle" color="danger" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.support.status_pending')"
                    :value="$countPending" icon="clock" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.support.status_answered')"
                    :value="$countAnswered" icon="check" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3">
                <x-panel.stat-widget :label="__('panel.support.status_closed')"
                    :value="$countClosed" icon="archive" color="secondary" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.admin_support')">
            <form method="GET" action="{{ route('admin.support.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 200px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Support\Enums\TicketStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select name="priority" class="form-select" style="max-width: 160px;">
                    <option value="">{{ __('panel.admin.all') }} priority</option>
                    @foreach(\App\Domains\Support\Enums\TicketPriority::cases() as $p)
                        <option value="{{ $p->value }}" @selected(($priorityFilter ?? '') === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" class="form-control" style="max-width: 240px;"
                       placeholder="Předmět, zákazník…" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                @if($filter || ($priorityFilter ?? '') || ($search ?? ''))
                    <a href="{{ route('admin.support.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $tickets->total() }} ticketů</span>
            </form>

            @if($tickets->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="message-square" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.support.none') }}</h6>
                    <p class="f-light f-12 mb-0">Žádné tickety neodpovídají filtru.</p>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.support.subject'),
                    __('panel.common.customer'),
                    __('panel.common.status'),
                    __('panel.support.priority'),
                    __('panel.support.last_reply'),
                    '',
                ]">
                    @foreach($tickets as $ticket)
                        @php
                            $priorityColor = match($ticket->priority->value ?? '') {
                                'urgent' => 'danger',
                                'high'   => 'warning',
                                'normal' => 'primary',
                                'low'    => 'secondary',
                                default  => 'light',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.support.show', $ticket) }}" class="f-w-500">
                                    {{ $ticket->subject }}
                                </a>
                            </td>
                            <td class="f-light f-12">{{ $ticket->customer?->user?->name ?? $ticket->customer?->email ?? '—' }}</td>
                            <td><x-panel.status-badge :status="$ticket->status" /></td>
                            <td><span class="badge badge-light-{{ $priorityColor }}">{{ $ticket->priority->label() }}</span></td>
                            <td class="f-light f-12">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.support.show', $ticket) }}" class="btn btn-outline-primary btn-xs">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $tickets->withQueryString()->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
