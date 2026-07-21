@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Odchozí webhooky';
    $breadcrumbItems = ['Odchozí webhooky' => ''];
@endphp

@section('title', 'Odchozí webhooky')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body">
                    <span class="f-light">Webhooky</span>
                    <div class="flex items-end gap-1 mt-1"><h4>{{ $webhooks->count() }}</h4></div>
                    <div class="bg-gradient"><i data-feather="zap"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body">
                    <span class="f-light">Doručeno</span>
                    <div class="flex items-end gap-1 mt-1"><h4 class="txt-success">{{ number_format($deliveredCount) }}</h4></div>
                    <div class="bg-gradient"><i data-feather="check-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body">
                    <span class="f-light">Selhalo</span>
                    <div class="flex items-end gap-1 mt-1"><h4 class="{{ $failedCount > 0 ? 'txt-danger' : '' }}">{{ number_format($failedCount) }}</h4></div>
                    <div class="bg-gradient"><i data-feather="x-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-3 md:col-span-6 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body">
                    <span class="f-light">Aktivních</span>
                    <div class="flex items-end gap-1 mt-1"><h4>{{ $webhooks->where('is_active', true)->count() }}</h4></div>
                    <div class="bg-gradient"><i data-feather="activity"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Odchozí webhooky">
                <div class="flex justify-end mb-3">
                    <a href="{{ route('admin.outgoing-webhooks.create') }}" class="btn btn-primary btn-sm">
                        <i data-feather="plus" style="width:14px;height:14px;"></i> Přidat webhook
                    </a>
                </div>
                @forelse($webhooks as $webhook)
                    <div class="flex items-center justify-between border rounded p-3 mb-2">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="f-w-600 f-14">{{ $webhook->name }}</span>
                                @if($webhook->is_active)
                                    <span class="badge badge-light-success f-10">aktivní</span>
                                @else
                                    <span class="badge badge-light-secondary f-10">neaktivní</span>
                                @endif
                            </div>
                            <div class="f-12 f-light txt-overflow" style="max-width:360px;">{{ $webhook->url }}</div>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($webhook->events as $ev)
                                    <span class="badge badge-light-primary f-10">{{ $ev }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.outgoing-webhooks.deliveries', $webhook) }}" class="btn btn-sm btn-outline-secondary f-11">
                                <i data-feather="list" style="width:12px;height:12px;"></i>
                                {{ $webhook->deliveries_count }} doručení
                            </a>
                            <form method="POST" action="{{ route('admin.outgoing-webhooks.destroy', $webhook) }}" data-confirm="Opravdu smazat?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger f-11">
                                    <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="f-light f-13">Žádné odchozí webhooky. <a href="{{ route('admin.outgoing-webhooks.create') }}">Přidejte první.</a></p>
                @endforelse
            </x-panel.card>
        </div>

        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Poslední doručení">
                @forelse($recentDeliveries as $delivery)
                    <div class="flex items-center justify-between mb-2 pb-2 border-bottom">
                        <div>
                            <span class="badge badge-light-{{ $delivery->status === 'delivered' ? 'success' : ($delivery->status === 'pending' ? 'warning' : 'danger') }} f-10 me-1">
                                {{ $delivery->status }}
                            </span>
                            <span class="f-12">{{ $delivery->event }}</span>
                        </div>
                        <div class="f-11 f-light">
                            {{ $delivery->response_code ? "HTTP {$delivery->response_code}" : '—' }}
                            · {{ $delivery->created_at?->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <p class="f-light f-12">Zatím žádná doručení.</p>
                @endforelse
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
