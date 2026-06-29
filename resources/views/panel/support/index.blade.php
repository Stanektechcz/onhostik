@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.support');
    $breadcrumbItems = [__('panel.nav.support') => ''];
@endphp

@section('title', __('panel.nav.support'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-5 xl:col-span-12">
                <x-panel.card :title="__('panel.support.new_ticket')">
                    <form method="POST" action="{{ route('panel.support.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="ticket-subject">{{ __('panel.support.subject') }}</label>
                            <input id="ticket-subject" type="text" name="subject" class="form-control" value="{{ old('subject') }}" required minlength="3" maxlength="150">
                            @error('subject')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="ticket-priority">{{ __('panel.support.priority') }}</label>
                            <select id="ticket-priority" name="priority" class="form-select">
                                @foreach(\App\Domains\Support\Enums\TicketPriority::cases() as $priority)
                                    <option value="{{ $priority->value }}" @selected($priority === \App\Domains\Support\Enums\TicketPriority::Normal)>{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="ticket-message">{{ __('panel.support.message') }}</label>
                            <textarea id="ticket-message" name="message" class="form-control" rows="5" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                            @error('message')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('panel.support.new_ticket') }}</button>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-span-7 xl:col-span-12">
                <x-panel.card :title="__('panel.nav.support')">
                    @if($tickets->isEmpty())
                        <div class="text-center py-4">
                            <i data-feather="message-square" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                            <p class="f-light mb-0">{{ __('panel.support.none') }}</p>
                        </div>
                    @else
                        <x-panel.data-table :headers="[__('panel.support.subject'), __('panel.common.status'), __('panel.support.priority'), __('panel.support.last_reply')]">
                            @foreach($tickets as $ticket)
                                <tr>
                                    <td><a href="{{ route('panel.support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                                    <td><x-panel.status-badge :status="$ticket->status" /></td>
                                    <td><x-panel.status-badge :status="$ticket->priority" /></td>
                                    <td>{{ $ticket->last_reply_at?->format('d.m.Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                        {{ $tickets->links() }}
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
