@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Provisioning task #' . $task->id;
    $breadcrumbItems = ['Provisioning' => route('admin.provisioning.index'), 'Task #' . $task->id => ''];
@endphp

@section('title', 'Provisioning task #' . $task->id)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Task detail --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card :title="'Task #' . $task->id . ' — ' . $task->operation">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="f-11 f-light mb-1">Status</div>
                        <span class="badge badge-light-{{ $task->status->color() }} f-12">{{ $task->status->label() }}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="f-11 f-light mb-1">Pokusy</div>
                        <span class="f-13 f-w-600">{{ $task->attempts }} / {{ $task->max_attempts }}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="f-11 f-light mb-1">Operace</div>
                        <code class="f-12">{{ $task->operation }}</code>
                    </div>
                    @if($task->error_message)
                        <div class="col-12">
                            <div class="f-11 f-light mb-1">Chyba</div>
                            <div class="alert alert-danger f-12 mb-0" style="white-space:pre-wrap;">{{ $task->error_message }}</div>
                        </div>
                    @endif
                    <div class="col-md-4">
                        <div class="f-11 f-light mb-1">Zahájeno</div>
                        <span class="f-12">{{ $task->started_at?->format('d.m.Y H:i:s') ?? '—' }}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="f-11 f-light mb-1">Dokončeno</div>
                        <span class="f-12">{{ $task->finished_at?->format('d.m.Y H:i:s') ?? '—' }}</span>
                    </div>
                    @if($task->external_request_id)
                        <div class="col-md-4">
                            <div class="f-11 f-light mb-1">External request ID</div>
                            <code class="f-11">{{ $task->external_request_id }}</code>
                        </div>
                    @endif
                </div>

                @if($task->canRetry())
                    <form method="POST" action="{{ route('admin.provisioning.retry', $task) }}" class="mb-3">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i data-feather="refresh-cw" style="width:13px;height:13px;"></i>
                            Opakovat manuálně
                        </button>
                    </form>
                @endif

                {{-- Payload --}}
                @if($task->payload)
                    <div class="mt-3">
                        <p class="f-11 f-light mb-1">Payload (vstup)</p>
                        <pre class="f-11 rounded p-2" style="background:#f8f9fa;max-height:200px;overflow:auto;">{{ json_encode($task->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif

                {{-- Result --}}
                @if($task->result)
                    <div class="mt-3">
                        <p class="f-11 f-light mb-1">Výsledek (výstup)</p>
                        <pre class="f-11 rounded p-2" style="background:#f0fff4;max-height:200px;overflow:auto;">{{ json_encode($task->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            </x-panel.card>

            {{-- History --}}
            <x-panel.card title="Historie tasků pro tuto službu">
                <div class="table-responsive">
                    <table class="table table-hover f-13">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Operace</th>
                                <th>Status</th>
                                <th>Pokusy</th>
                                <th>Dokončeno</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $h)
                                <tr class="{{ $h->id === $task->id ? 'table-active' : '' }}">
                                    <td>
                                        <a href="{{ route('admin.provisioning.show', $h) }}" class="f-12">#{{ $h->id }}</a>
                                    </td>
                                    <td><code class="f-11">{{ $h->operation }}</code></td>
                                    <td><span class="badge badge-light-{{ $h->status->color() }} f-10">{{ $h->status->label() }}</span></td>
                                    <td>{{ $h->attempts }}/{{ $h->max_attempts }}</td>
                                    <td class="f-light f-11">{{ $h->finished_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>

        {{-- Service info --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Služba">
                @if($task->service)
                    <div class="mb-2">
                        <span class="f-11 f-light">Název:</span>
                        <span class="f-w-600 f-13 d-block">{{ $task->service->label }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="f-11 f-light">Status:</span>
                        <span class="badge badge-light-{{ $task->service->status->color() ?? 'secondary' }} f-10">{{ $task->service->status->label() }}</span>
                    </div>
                    @if($task->service->customer)
                        <div class="mb-2">
                            <span class="f-11 f-light">Zákazník:</span>
                            <a href="{{ route('admin.customers.show', $task->service->customer) }}" class="f-12 d-block">
                                {{ $task->service->customer->email }}
                            </a>
                        </div>
                    @endif
                    @if($task->service->external_id)
                        <div class="mb-2">
                            <span class="f-11 f-light">External ID:</span>
                            <code class="f-11">{{ $task->service->external_id }}</code>
                        </div>
                    @endif
                    <a href="{{ route('admin.services.show', $task->service) }}" class="btn btn-outline-primary btn-sm f-12 mt-2">
                        <i data-feather="settings" style="width:12px;height:12px;"></i> Detail služby
                    </a>
                @else
                    <p class="f-light f-12">Služba nenalezena.</p>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
