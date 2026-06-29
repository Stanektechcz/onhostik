@extends('layouts.panel')

@php
    $breadcrumbTitle = $ticket->subject;
    $breadcrumbItems = [__('panel.nav.admin_support') => route('admin.support.index'), $ticket->subject => ''];
@endphp

@section('title', $ticket->subject)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Left: messages + reply form --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$ticket->subject" :subtitle="$ticket->customer?->email">
                    @forelse($ticket->messages as $message)
                        @php
                            $dotColor = $message->is_staff ? 'success' : 'primary';
                        @endphp
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0 pt-1">
                                <div class="activity-dot-{{ $dotColor }}" style="margin-top:4px"></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="rounded p-3 {{ $message->is_staff ? 'bg-light-success' : 'bg-light-primary' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="f-w-600 f-13">
                                            @if($message->is_staff)
                                                <i data-feather="shield" class="font-success" style="width:12px;height:12px"></i>
                                                {{ __('panel.support.staff') }}
                                            @else
                                                <i data-feather="user" class="font-primary" style="width:12px;height:12px"></i>
                                                {{ $message->author?->name ?? $ticket->customer?->user?->name ?? '—' }}
                                            @endif
                                        </span>
                                        <span class="f-light f-11">{{ $message->created_at?->format('d.m.Y H:i') }}</span>
                                    </div>
                                    <p class="mb-0 f-13" style="white-space: pre-line;">{{ $message->message }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="f-light mb-3">{{ __('panel.common.empty') }}</p>
                    @endforelse

                    <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="border-top pt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="admin-reply">{{ __('panel.support.reply') }}</label>
                            <textarea id="admin-reply" name="message" class="form-control @error('message') is-invalid @enderror"
                                      rows="4" required minlength="2"></textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="send" style="width:14px;height:14px"></i>
                            {{ __('panel.support.reply') }}
                        </button>
                    </form>
                </x-panel.card>
            </div>

            {{-- Right: info + actions + timeline --}}
            <div class="col-span-4 xl:col-span-12">
                {{-- Customer info --}}
                <x-panel.card :title="__('panel.common.customer')">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="f-w-600">{{ $ticket->customer?->user?->name ?? $ticket->customer?->email }}</span>
                        <a href="{{ route('admin.customers.show', $ticket->customer) }}" class="btn btn-outline-primary btn-sm f-12">
                            <i data-feather="user" style="width:12px;height:12px"></i> Detail
                        </a>
                    </div>
                    <p class="f-light f-12 mb-0">{{ $ticket->customer?->email }}</p>
                    @if($ticket->customer?->company_name)
                        <p class="f-light f-12 mb-0">{{ $ticket->customer->company_name }}</p>
                    @endif
                </x-panel.card>

                {{-- Status & priority controls --}}
                <x-panel.card :title="__('panel.common.status')">
                    @error('approval')<div class="text-danger f-12 mb-2">{{ $message }}</div>@enderror
                    <form method="POST" action="{{ route('admin.support.update', $ticket) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="t-status">{{ __('panel.support.status') }}</label>
                            <select id="t-status" name="status" class="form-select">
                                @foreach(\App\Domains\Support\Enums\TicketStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="t-priority">{{ __('panel.support.priority') }}</label>
                            <select id="t-priority" name="priority" class="form-select">
                                @foreach(\App\Domains\Support\Enums\TicketPriority::cases() as $priority)
                                    <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('panel.admin.save') }}</button>
                    </form>

                    <div class="border-top pt-3 mt-3 f-12 f-light">
                        <div class="d-flex justify-content-between">
                            <span>Otevřeno:</span>
                            <span>{{ $ticket->created_at?->format('d.m.Y H:i') }}</span>
                        </div>
                        @if($ticket->closed_at)
                            <div class="d-flex justify-content-between mt-1">
                                <span>Uzavřeno:</span>
                                <span>{{ $ticket->closed_at->format('d.m.Y H:i') }}</span>
                            </div>
                        @endif
                        @if($ticket->last_reply_at)
                            <div class="d-flex justify-content-between mt-1">
                                <span>Poslední odpověď:</span>
                                <span>{{ $ticket->last_reply_at->format('d.m.Y H:i') }}</span>
                            </div>
                        @endif
                    </div>
                </x-panel.card>

                {{-- Events timeline --}}
                @if($ticket->events->isNotEmpty())
                    <x-panel.card title="Historie událostí">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($ticket->events as $event)
                                        @php
                                            $evtDesc  = strtolower($event->event ?? '');
                                            $dotColor = match(true) {
                                                str_contains($evtDesc, 'clos') || str_contains($evtDesc, 'resolv') => 'success',
                                                str_contains($evtDesc, 'open') || str_contains($evtDesc, 'creat') => 'primary',
                                                str_contains($evtDesc, 'priorit') => 'warning',
                                                str_contains($evtDesc, 'reply') || str_contains($evtDesc, 'odpov') => 'info',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <li>
                                            <div class="timeline-dot-{{ $dotColor }}"></div>
                                            <div class="ms-4">
                                                <p class="f-w-500 mb-0">{{ $event->event }}</p>
                                                <p class="f-12 f-light mb-0">
                                                    {{ $event->user?->name ?? 'system' }}
                                                    · {{ $event->created_at?->format('d.m.Y H:i') }}
                                                </p>
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
