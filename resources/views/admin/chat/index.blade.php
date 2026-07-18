@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Živá podpora — chat';
    $breadcrumbItems = ['Podpora' => route('admin.support.index'), 'Chat' => ''];
@endphp

@section('title', 'Živá podpora | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Status filter tabs --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        @php
            $tabs = [
                ''              => 'Vše',
                'waiting_agent' => 'Čeká na operátora (' . $countWaiting . ')',
                'agent_active'  => 'Živá podpora (' . $countActive . ')',
                'bot'           => 'AI asistent (' . $countBot . ')',
                'closed'        => 'Uzavřené',
            ];
        @endphp
        @foreach($tabs as $val => $label)
            <a href="{{ route('admin.chat.index', array_filter(['status' => $val])) }}"
               class="btn btn-sm {{ $filter === $val ? 'btn-primary text-white' : 'btn-outline-primary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-panel.card title="Konverzace">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Stav</th>
                        <th>Operátor</th>
                        <th>Zpráv</th>
                        <th>Poslední aktivita</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversations as $c)
                        <tr>
                            <td>
                                <span class="f-w-600">{{ $c->customer?->company_name ?? $c->startedBy?->name ?? 'Neznámý' }}</span>
                                <p class="f-light f-12 mb-0">{{ $c->startedBy?->email }}</p>
                            </td>
                            <td><span class="badge badge-light-{{ $c->status->color() }}">{{ $c->status->label() }}</span></td>
                            <td class="f-12">{{ $c->agent?->name ?? '—' }}</td>
                            <td>{{ $c->messages_count }}</td>
                            <td class="f-12">{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.chat.show', $c) }}" class="btn btn-outline-primary btn-sm">
                                    <i data-feather="message-circle" style="width:14px;height:14px;"></i> Otevřít
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-panel.empty-state icon="message-square" title="Žádné konverzace" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $conversations->links() }}</div>
    </x-panel.card>
</div>
@endsection
