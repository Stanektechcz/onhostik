@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Newsletter';
    $breadcrumbItems = ['Newsletter' => ''];
@endphp

@section('title', 'Newsletter kampaně')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Koncepty" :value="$draftCount" icon="file-text" color="secondary" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Odeslané" :value="$sentCount" icon="send" color="success" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Aktivní odběratelé" :value="$activeCount" icon="users" color="primary" />
        </div>
    </div>

    <x-panel.card title="Kampaně">
        <x-slot name="actions">
            <a href="{{ route('admin.newsletter.create') }}" class="btn btn-primary btn-sm text-white">
                <i data-feather="plus" style="width:13px;height:13px;"></i> Nová kampaň
            </a>
        </x-slot>

        @if($campaigns->isEmpty())
            <div class="text-center py-5">
                <i data-feather="mail" style="width:48px;height:48px;" class="text-muted mb-3 block mx-auto"></i>
                <h6 class="f-light mt-2">Žádné kampaně</h6>
                <p class="f-light f-12 mb-3">Začněte vytvořením první e-mailové kampaně.</p>
                <a href="{{ route('admin.newsletter.create') }}" class="btn btn-primary btn-sm text-white">
                    <i data-feather="plus" style="width:13px;height:13px;"></i> Vytvořit kampaň
                </a>
            </div>
        @else
            <x-panel.data-table :headers="['Předmět', 'Stav', 'Odesláno', 'Odběratelé', 'Autor', 'Vytvořeno', '']">
                @foreach($campaigns as $campaign)
                    <tr>
                        <td class="f-w-500">
                            <a href="{{ route('admin.newsletter.show', $campaign) }}" class="text-reset">
                                {{ $campaign->subject }}
                            </a>
                        </td>
                        <td>
                            <span class="badge badge-light-{{ $campaign->statusColor() }}">
                                {{ $campaign->statusLabel() }}
                            </span>
                        </td>
                        <td class="f-12">
                            @if($campaign->isSent() || $campaign->isSending())
                                {{ number_format($campaign->sent_count) }} / {{ number_format($campaign->recipients_count) }}
                                @if($campaign->recipients_count > 0)
                                    <div class="progress mt-1" style="height:3px;">
                                        <div class="progress-bar bg-success" style="width:{{ $campaign->progressPercent() }}%"></div>
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="f-12">{{ number_format($campaign->recipients_count) ?: '—' }}</td>
                        <td class="f-light f-12">{{ $campaign->author?->name ?? '—' }}</td>
                        <td class="f-light f-12">{{ $campaign->created_at->format('d.m.Y') }}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="{{ route('admin.newsletter.show', $campaign) }}"
                                   class="btn btn-outline-primary btn-xs" title="Detail">
                                    <i data-feather="eye" style="width:11px;height:11px;"></i>
                                </a>
                                @if($campaign->isDraft())
                                    <a href="{{ route('admin.newsletter.edit', $campaign) }}"
                                       class="btn btn-outline-secondary btn-xs" title="Upravit">
                                        <i data-feather="edit-2" style="width:11px;height:11px;"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.newsletter.destroy', $campaign) }}"
                                          data-confirm="Smazat kampaň?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-xs" title="Smazat">
                                            <i data-feather="trash-2" style="width:11px;height:11px;"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>

            <div class="mt-3">
                {{ $campaigns->links() }}
            </div>
        @endif
    </x-panel.card>
</div>
@endsection
