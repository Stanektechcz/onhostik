@extends('layouts.panel')

@php($breadcrumbTitle = $ticket->subject)
@php($breadcrumbItems = [__('panel.nav.support') => route('panel.support.index'), $ticket->subject => ''])

@section('title', $ticket->subject)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-8 xl:col-span-12">
                {{-- Conversation timeline --}}
                <div class="card">
                    <div class="card-header card-no-border flex justify-between items-center">
                        <h5 class="mb-0">{{ $ticket->subject }}</h5>
                        <div class="flex gap-2 items-center">
                            <x-panel.status-badge :status="$ticket->priority" />
                            <x-panel.status-badge :status="$ticket->status" />
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($ticket->messages as $message)
                                        @php($isStaff = $message->is_staff)
                                        <li>
                                            <div class="timeline-dot-{{ $isStaff ? 'success' : 'primary' }}"></div>
                                            <div class="ms-4 pb-2">
                                                <div class="flex justify-between items-start mb-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="f-w-600 f-14">
                                                            @if($isStaff)
                                                                <i data-feather="headphones" style="width:13px;height:13px" class="font-success"></i>
                                                                {{ __('panel.support.staff') }}
                                                            @else
                                                                <i data-feather="user" style="width:13px;height:13px" class="font-primary"></i>
                                                                {{ $message->author?->name ?? __('panel.support.you') }}
                                                            @endif
                                                        </span>
                                                        @if($isStaff)
                                                            <span class="badge badge-light-success f-12">Support</span>
                                                        @endif
                                                    </div>
                                                    <span class="f-light f-12 text-nowrap">
                                                        {{ $message->created_at?->format('d.m.Y H:i') }}
                                                    </span>
                                                </div>
                                                <div class="p-3 rounded {{ $isStaff ? 'bg-light-success' : 'bg-light-primary' }}" style="white-space: pre-line;">
                                                    {{ $message->message }}
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        @if($ticket->status->isOpen())
                            <div class="border-top pt-4 mt-2">
                                <h6 class="f-light f-12 mb-3">{{ __('panel.support.reply') }}</h6>
                                <form method="POST" action="{{ route('panel.support.reply', $ticket) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <textarea name="message" class="form-control" rows="4"
                                                  required minlength="2" maxlength="5000"
                                                  placeholder="{{ __('panel.support.reply') }}…"></textarea>
                                        @error('message')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i data-feather="send" style="width:14px;height:14px"></i>
                                            {{ __('panel.support.reply') }}
                                        </button>
                                    </div>
                                </form>
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('panel.support.close', $ticket) }}"
                                          data-confirm="{{ __('panel.support.close_confirm') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                            <i data-feather="x" style="width:13px;height:13px"></i>
                                            {{ __('panel.support.close_ticket') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="border-top pt-3 mt-2">
                                <p class="f-light f-12 mb-0">
                                    <i data-feather="lock" style="width:12px;height:12px"></i>
                                    {{ __('panel.support.closed_note') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Ticket info sidebar --}}
            <div class="col-span-4 xl:col-span-12">
                <x-panel.card :title="__('panel.common.status')">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="f-light ps-0 f-12">{{ __('panel.common.status') }}</td>
                            <td><x-panel.status-badge :status="$ticket->status" /></td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">{{ __('panel.support.priority') }}</td>
                            <td><x-panel.status-badge :status="$ticket->priority" /></td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">{{ __('panel.common.created_at') }}</td>
                            <td class="f-12">{{ $ticket->created_at?->format('d.m.Y H:i') }}</td>
                        </tr>
                        @if($ticket->last_reply_at)
                            <tr>
                                <td class="f-light ps-0 f-12">Poslední zpráva</td>
                                <td class="f-12">{{ $ticket->last_reply_at->diffForHumans() }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0 f-12">Zpráv</td>
                            <td class="f-12">{{ $ticket->messages->count() }}</td>
                        </tr>
                    </table>
                </x-panel.card>

                @if(isset($kbArticles) && $kbArticles->isNotEmpty())
                    <x-panel.card title="Doporučené KB články">
                        <ul class="list-unstyled mb-0">
                            @foreach($kbArticles as $article)
                                <li class="mb-1">
                                    <a href="{{ route('kb.show', $article->slug) }}" class="f-12 text-primary" target="_blank">
                                        <i data-feather="book-open" style="width:12px;height:12px;margin-right:4px;"></i>
                                        {{ $article->title }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-panel.card>
                @endif

                @if($ticket->events->isNotEmpty())
                    <x-panel.card title="Historie">
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($ticket->events->sortBy('created_at') as $event)
                                        <li>
                                            <div class="activity-dot-secondary"></div>
                                            <div class="ms-3">
                                                <p class="f-12 mb-0 f-light">{{ $event->description }}</p>
                                                <p class="f-12 f-light mb-0">{{ $event->created_at?->format('d.m. H:i') }}</p>
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

        {{-- CSAT Rating (closed tickets only) --}}
        @if($ticket->status->value === 'closed')
            <div class="card mt-3">
                <div class="card-body">
                    @if($ticket->rating)
                        <p class="f-14 mb-1"><strong>Vaše hodnocení:</strong>
                            @for($i = 1; $i <= 5; $i++)
                                <span style="color:{{ $i <= $ticket->rating->score ? '#f8961e' : '#ccc' }}">★</span>
                            @endfor
                            <span class="f-12 f-light ms-2">{{ $ticket->rating->label() }}</span>
                        </p>
                        @if($ticket->rating->comment)
                            <p class="f-13 f-light mb-0">{{ $ticket->rating->comment }}</p>
                        @endif
                    @else
                        <p class="f-14 mb-2"><strong>Jak hodnotíte vyřízení tohoto ticketu?</strong></p>
                        @if($errors->has('rating'))
                            <div class="alert alert-danger py-2">{{ $errors->first('rating') }}</div>
                        @endif
                        <form action="{{ route('panel.support.rate', $ticket) }}" method="POST">
                            @csrf
                            <div class="flex gap-3 items-center mb-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="score" id="score_{{ $i }}" value="{{ $i }}" required>
                                        <label class="form-check-label" for="score_{{ $i }}">{{ $i }}★</label>
                                    </div>
                                @endfor
                            </div>
                            <div class="mb-2">
                                <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="Volitelný komentář..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Odeslat hodnocení</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
