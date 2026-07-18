@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Bezpečnostní audit';
    $breadcrumbItems = ['Bezpečnostní audit' => ''];
@endphp

@section('title', 'Bezpečnostní audit')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI grid grid-cols-12 --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body {{ $failedCount > 0 ? 'danger' : 'success' }}">
                    <span class="f-light">Neúsp. přihlášení (24 h)</span>
                    <div class="flex items-end gap-1">
                        <h4>{{ $failedCount }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="alert-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-4 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body {{ $suspiciousIps > 0 ? 'warning' : 'success' }}">
                    <span class="f-light">Podezřelé IP adresy</span>
                    <div class="flex items-end gap-1">
                        <h4>{{ $suspiciousIps }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="globe"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-4 sm:col-span-12">
            <div class="card small-widget">
                <div class="card-body {{ $newIpCount > 0 ? 'warning' : 'success' }}">
                    <span class="f-light">Přihlášení z nové IP (24 h)</span>
                    <div class="flex items-end gap-1">
                        <h4>{{ $newIpCount }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="map-pin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap">

        {{-- Top attackers --}}
        @if($topAttackers->isNotEmpty())
        <div class="col-span-5 xl:col-span-12">
            <x-panel.card title="Nejaktivnější neúsp. IP (24 h)">
                <table class="table table-sm f-12 mb-0">
                    <thead>
                        <tr>
                            <th>IP adresa</th>
                            <th class="text-right">Pokusů</th>
                            <th>Poslední e-mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topAttackers as $row)
                        <tr>
                            <td class="f-w-500">{{ $row->ip_address }}</td>
                            <td class="text-right">
                                <span class="badge badge-light-danger">{{ $row->attempts }}</span>
                            </td>
                            <td class="f-light">{{ $row->last_email ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel.card>
        </div>
        @endif

        {{-- Recent events --}}
        <div class="{{ $topAttackers->isNotEmpty() ? 'col-span-7' : 'col-span-12' }} xl:col-span-12">
            <x-panel.card title="Nedávné bezpečnostní události (50)">
                <div class="table-responsive">
                    <table class="table table-hover f-12 mb-0">
                        <thead>
                            <tr>
                                <th>Typ</th>
                                <th>Uživatel / E-mail</th>
                                <th>IP adresa</th>
                                <th>Čas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentEvents as $ev)
                            <tr>
                                <td>
                                    <span class="badge badge-light-{{ $ev->severityClass() }}">
                                        {{ $ev->label() }}
                                    </span>
                                </td>
                                <td class="f-light">
                                    @if($ev->user)
                                        <a href="{{ route('admin.customers.index', ['q' => $ev->user->email]) }}" class="f-w-500">
                                            {{ $ev->user->email }}
                                        </a>
                                    @else
                                        {{ $ev->email ?? '—' }}
                                    @endif
                                </td>
                                <td class="f-light">{{ $ev->ip_address }}</td>
                                <td class="f-light" style="white-space:nowrap;">
                                    {{ $ev->created_at?->format('d.m.Y H:i:s') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center f-light py-4">
                                    Žádné bezpečnostní události.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>

    </div>
</div>
@endsection
