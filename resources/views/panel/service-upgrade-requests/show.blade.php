@extends('layouts.panel')

@section('title', 'Detail žádosti o upgrade')

@section('content')
<x-panel.flash />

<x-panel.card title="Detail žádosti o upgrade">
    <dl class="grid grid-cols-12">
        <dt class="col-span-12 sm:col-span-3">Služba</dt>
        <dd class="col-span-12 sm:col-span-9">
            {{ $serviceUpgradeRequest->service?->name ?? '—' }}
        </dd>

        <dt class="col-span-12 sm:col-span-3">Požadovaný plán ID</dt>
        <dd class="col-span-12 sm:col-span-9">
            {{ $serviceUpgradeRequest->requested_plan_id ?? '—' }}
        </dd>

        <dt class="col-span-12 sm:col-span-3">Stav</dt>
        <dd class="col-span-12 sm:col-span-9">
            @php
                $badgeClass = match($serviceUpgradeRequest->status) {
                    'pending'  => 'secondary',
                    'approved' => 'info',
                    'applied'  => 'success',
                    'rejected' => 'danger',
                    default    => 'secondary',
                };
            @endphp
            <span class="badge bg-{{ $badgeClass }}">{{ $serviceUpgradeRequest->status }}</span>
        </dd>

        <dt class="col-span-12 sm:col-span-3">Poznámka zákazníka</dt>
        <dd class="col-span-12 sm:col-span-9">
            {{ $serviceUpgradeRequest->customer_note ?: '—' }}
        </dd>

        @if($serviceUpgradeRequest->admin_note)
            <dt class="col-span-12 sm:col-span-3">Poznámka administrátora</dt>
            <dd class="col-span-12 sm:col-span-9">
                {{ $serviceUpgradeRequest->admin_note }}
            </dd>
        @endif

        <dt class="col-span-12 sm:col-span-3">Datum odeslání</dt>
        <dd class="col-span-12 sm:col-span-9">
            {{ $serviceUpgradeRequest->created_at->format('d.m.Y H:i') }}
        </dd>
    </dl>

    <div class="mt-3">
        <a href="{{ route('panel.service-upgrade-requests.index') }}" class="btn btn-secondary">
            &larr; Zpět na seznam
        </a>
    </div>
</x-panel.card>
@endsection
