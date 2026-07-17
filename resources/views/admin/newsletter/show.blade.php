@extends('layouts.panel')

@php
    $breadcrumbTitle = Str::limit($campaign->subject, 40);
    $breadcrumbItems = ['Newsletter' => route('admin.newsletter.index'), $campaign->subject => ''];
@endphp

@section('title', $campaign->subject)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Preview --}}
        <div class="col-span-8 sm:col-span-12">
            <div class="card card-no-border">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Náhled e-mailu</h5>
                    @if($campaign->isDraft())
                        <a href="{{ route('admin.newsletter.edit', $campaign) }}" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="edit-2" style="width:13px;height:13px;"></i> Upravit
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div style="border: 1px solid rgba(var(--light-background), 1); border-radius: 0 0 8px 8px; overflow: hidden;">
                        <div style="background:rgba(var(--light-semi-gray),1); padding: 12px 16px; border-bottom: 1px solid rgba(var(--light-background),1); font-size: 12px;">
                            <strong>Předmět:</strong> {{ $campaign->subject }}
                        </div>
                        <div style="padding: 20px; min-height: 300px; overflow: auto;">
                            {!! $campaign->body_html !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-span-4 sm:col-span-12">
            {{-- Status card --}}
            <div class="card card-no-border mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0">Stav kampaně</h6>
                        <span class="badge badge-light-{{ $campaign->statusColor() }} f-12">
                            {{ $campaign->statusLabel() }}
                        </span>
                    </div>

                    @if($campaign->isSent() || $campaign->isSending())
                        <div class="mb-2">
                            <div class="d-flex justify-content-between f-12 mb-1">
                                <span>Odesláno</span>
                                <span class="f-w-500">{{ number_format($campaign->sent_count) }} / {{ number_format($campaign->recipients_count) }}</span>
                            </div>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-success" style="width:{{ $campaign->progressPercent() }}%"></div>
                            </div>
                        </div>
                        @if($campaign->sent_at)
                            <div class="f-light f-11 mb-2">
                                <i data-feather="clock" style="width:11px;height:11px;"></i>
                                Zahájeno: {{ $campaign->sent_at->format('d.m.Y H:i') }}
                            </div>
                        @endif
                        @if($campaign->isSending())
                            <form method="POST" action="{{ route('admin.newsletter.mark-sent', $campaign) }}" class="mt-2">
                                @csrf
                                <button class="btn btn-outline-success btn-sm w-100">
                                    <i data-feather="check" style="width:13px;height:13px;"></i> Označit jako odesláno
                                </button>
                            </form>
                        @endif
                    @endif

                    @if($campaign->isDraft())
                        <div class="alert alert-light-secondary f-12 mb-3">
                            <i data-feather="users" style="width:13px;height:13px;"></i>
                            Aktivní odběratelé: <strong>{{ number_format($activeCount) }}</strong>
                        </div>

                        @if($activeCount > 0)
                            <form method="POST" action="{{ route('admin.newsletter.send', $campaign) }}"
                                  onsubmit="return confirm('Opravdu odeslat tuto kampaň {{ $activeCount }} odběratelům? Tato akce nelze vrátit.')">
                                @csrf
                                <button class="btn btn-danger w-100 text-white">
                                    <i data-feather="send" style="width:14px;height:14px;"></i>
                                    Odeslat {{ number_format($activeCount) }} odběratelům
                                </button>
                            </form>
                        @else
                            <div class="f-light f-12 text-center">Žádní aktivní odběratelé</div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Meta --}}
            <div class="card card-no-border">
                <div class="card-body">
                    <h6 class="mb-3">Informace</h6>
                    <dl class="row g-1 f-12 mb-0">
                        <dt class="col-5 f-light">Autor</dt>
                        <dd class="col-7">{{ $campaign->author?->name ?? '—' }}</dd>
                        <dt class="col-5 f-light">Vytvořeno</dt>
                        <dd class="col-7">{{ $campaign->created_at->format('d.m.Y H:i') }}</dd>
                        <dt class="col-5 f-light">Změněno</dt>
                        <dd class="col-7">{{ $campaign->updated_at->format('d.m.Y H:i') }}</dd>
                    </dl>

                    @if($campaign->isDraft())
                        <hr>
                        <form method="POST" action="{{ route('admin.newsletter.destroy', $campaign) }}"
                              onsubmit="return confirm('Smazat tuto kampaň?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm w-100">
                                <i data-feather="trash-2" style="width:13px;height:13px;"></i> Smazat kampaň
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
