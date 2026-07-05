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
                            $isInternal = (bool) ($message->is_internal ?? false);
                            $dotColor   = $isInternal ? 'warning' : ($message->is_staff ? 'success' : 'primary');
                            $bgClass    = $isInternal ? 'bg-light-warning' : ($message->is_staff ? 'bg-light-success' : 'bg-light-primary');
                        @endphp
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0 pt-1">
                                <div class="activity-dot-{{ $dotColor }}" style="margin-top:4px"></div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="rounded p-3 {{ $bgClass }}" style="{{ $isInternal ? 'border-left:3px solid #f39c12;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="f-w-600 f-13">
                                            @if($isInternal)
                                                <i data-feather="lock" class="font-warning" style="width:12px;height:12px"></i>
                                                Interní poznámka
                                                <span class="badge badge-light-warning f-10 ms-1">INTERNÍ</span>
                                            @elseif($message->is_staff)
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
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_internal" id="reply-internal" value="1">
                            <label class="form-check-label f-12 f-light" for="reply-internal">
                                <i data-feather="lock" style="width:12px;height:12px;"></i>
                                Interní poznámka (zákazník nevidí)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="send" style="width:14px;height:14px"></i>
                            {{ __('panel.support.reply') }}
                        </button>
                        <button type="submit" name="is_internal" value="1" class="btn btn-outline-warning ms-2">
                            <i data-feather="lock" style="width:14px;height:14px"></i>
                            Přidat interní poznámku
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

                {{-- Assigned to --}}
                @if($ticket->assignee)
                    <x-panel.card>
                        <div class="d-flex align-items-center gap-2">
                            <i data-feather="user-check" class="font-success" style="width:14px;height:14px"></i>
                            <span class="f-12 f-light">{{ __('panel.support.assignee') }}:</span>
                            <span class="f-w-600 f-13">{{ $ticket->assignee->name }}</span>
                        </div>
                    </x-panel.card>
                @endif

                {{-- AI Analysis --}}
                @if($ticket->ai_analysed_at)
                    <x-panel.card title="AI analýza">
                        @php
                            $sentimentColor = match($ticket->ai_sentiment) {
                                'positive' => 'success',
                                'negative' => 'danger',
                                default    => 'secondary',
                            };
                            $classColor = match($ticket->ai_classification) {
                                'billing'   => 'warning',
                                'technical' => 'primary',
                                'account'   => 'info',
                                'sales'     => 'success',
                                default     => 'secondary',
                            };
                        @endphp

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @if($ticket->ai_classification)
                                <span class="badge badge-light-{{ $classColor }}">
                                    <i data-feather="tag" style="width:10px;height:10px;margin-right:3px;"></i>
                                    {{ ucfirst($ticket->ai_classification) }}
                                </span>
                            @endif
                            @if($ticket->ai_sentiment)
                                <span class="badge badge-light-{{ $sentimentColor }}">
                                    <i data-feather="activity" style="width:10px;height:10px;margin-right:3px;"></i>
                                    {{ match($ticket->ai_sentiment) {
                                        'positive' => 'Pozitivní',
                                        'negative' => 'Negativní',
                                        default    => 'Neutrální',
                                    } }}
                                </span>
                            @endif
                        </div>

                        @if($ticket->ai_draft)
                            <p class="f-11 f-light mb-2">Navržená odpověď AI:</p>
                            <div class="rounded p-2 bg-light-primary f-12" style="white-space:pre-line;max-height:140px;overflow-y:auto;font-size:11px;">{{ $ticket->ai_draft }}</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 f-12"
                                    onclick="document.getElementById('admin-reply').value = {{ Js::from($ticket->ai_draft) }}; document.getElementById('admin-reply').scrollIntoView({behavior:'smooth'});">
                                <i data-feather="copy" style="width:12px;height:12px;"></i>
                                Použít jako odpověď
                            </button>
                        @endif

                        <div class="mt-2 d-flex align-items-center justify-content-between">
                            <span class="f-11 f-light">Analysováno {{ $ticket->ai_analysed_at->diffForHumans() }}</span>
                            <form method="POST" action="{{ route('admin.support.kb-draft', $ticket) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary f-11">
                                    <i data-feather="book-open" style="width:10px;height:10px;"></i>
                                    KB návrh
                                </button>
                            </form>
                        </div>
                    </x-panel.card>
                @else
                    <x-panel.card title="AI analýza">
                        <p class="f-12 f-light mb-2">
                            <i data-feather="cpu" style="width:12px;height:12px;"></i>
                            Analýza nebyla spuštěna.
                        </p>
                        <form method="POST" action="{{ route('admin.support.ai-analyse', $ticket) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary f-11 w-100">
                                <i data-feather="zap" style="width:10px;height:10px;"></i>
                                Spustit AI analýzu
                            </button>
                        </form>
                    </x-panel.card>
                @endif

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
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="t-assignee">{{ __('panel.support.assignee') }}</label>
                            <select id="t-assignee" name="assigned_to" class="form-select">
                                <option value="">— {{ __('panel.support.unassigned') }} —</option>
                                @foreach($staffUsers as $staff)
                                    <option value="{{ $staff->id }}" @selected($ticket->assigned_to === $staff->id)>{{ $staff->name }}</option>
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
                        @if($ticket->sla_deadline)
                            <div class="d-flex justify-content-between mt-1">
                                <span>SLA deadline:</span>
                                <span class="{{ $ticket->sla_deadline->isPast() ? 'txt-danger f-w-600' : 'txt-warning' }}">
                                    {{ $ticket->sla_deadline->format('d.m.Y H:i') }}
                                    ({{ $ticket->sla_deadline->diffForHumans() }})
                                </span>
                            </div>
                        @endif
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

                {{-- SLA deadline setter --}}
                <x-panel.card title="SLA deadline">
                    @if($ticket->sla_deadline)
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i data-feather="clock" class="{{ $ticket->sla_deadline->isPast() ? 'font-danger' : 'font-warning' }}" style="width:14px;height:14px;flex-shrink:0;"></i>
                            <span class="f-12">
                                {{ $ticket->sla_deadline->format('d.m.Y H:i') }}
                                <br><span class="f-light f-11">{{ $ticket->sla_deadline->diffForHumans() }}</span>
                            </span>
                        </div>
                    @else
                        <p class="f-12 f-light mb-2">SLA deadline není nastaven.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.support.sla', $ticket) }}">
                        @csrf
                        <div class="mb-2">
                            <input type="datetime-local" name="sla_deadline" class="form-control form-control-sm"
                                   value="{{ $ticket->sla_deadline?->format('Y-m-d\TH:i') }}">
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i data-feather="clock" style="width:12px;height:12px;"></i>
                            {{ $ticket->sla_deadline ? 'Aktualizovat SLA' : 'Nastavit SLA' }}
                        </button>
                    </form>
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
