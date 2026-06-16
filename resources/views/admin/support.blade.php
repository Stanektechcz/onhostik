@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_support');
@endphp

@section('title', __('panel.nav.admin_support'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.admin_support')">
            <form method="GET" action="{{ route('admin.support.index') }}" class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select name="status" class="form-select" style="max-width: 200px;">
                    <option value="">{{ __('panel.admin.all') }}</option>
                    @foreach(\App\Domains\Support\Enums\TicketStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filter === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select name="priority" class="form-select" style="max-width: 180px;">
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
                <p class="f-light mb-0">{{ __('panel.support.none') }}</p>
            @else
                <x-panel.data-table :headers="[__('panel.support.subject'), __('panel.common.customer'), __('panel.common.status'), __('panel.support.priority'), __('panel.support.last_reply')]">
                    @foreach($tickets as $ticket)
                        @php
                            $priorityColor = match($ticket->priority->value ?? '') {
                                'urgent'   => 'danger',
                                'high'     => 'warning',
                                'normal'   => 'primary',
                                'low'      => 'secondary',
                                default    => 'light',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.support.show', $ticket) }}" class="f-w-500">
                                    {{ $ticket->subject }}
                                </a>
                            </td>
                            <td class="f-light">{{ $ticket->customer?->user?->name ?? $ticket->customer?->email }}</td>
                            <td><x-panel.status-badge :status="$ticket->status" /></td>
                            <td><span class="badge badge-light-{{ $priorityColor }}">{{ $ticket->priority->label() }}</span></td>
                            <td class="f-light f-12">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $tickets->withQueryString()->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
