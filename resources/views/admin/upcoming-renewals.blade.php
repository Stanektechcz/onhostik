@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nadcházející obnovy';
    $breadcrumbItems = ['Billing' => '#', 'Nadcházející obnovy' => ''];
@endphp

@section('title', 'Nadcházející obnovy')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter tabs --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="f-12 f-light me-2">Zobrazit obnovy v příštích:</span>
                @foreach([7, 14, 30] as $d)
                <a href="{{ route('admin.upcoming-renewals.index', ['days' => $d]) }}"
                   class="btn btn-sm {{ $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $d }} dní <span class="badge bg-white text-dark ms-1">{{ $counts[$d] }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-no-border">
            <h5>Obnovy v příštích {{ $days }} dnech ({{ $services->total() }})</h5>
        </div>
        <div class="card-body pt-0">
            @if($services->isEmpty())
                <p class="text-center f-light py-4">Žádné nadcházející obnovy.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Zákazník</th>
                            <th>Služba</th>
                            <th>Produkt</th>
                            <th>Datum obnovy</th>
                            <th>Za dní</th>
                            <th>Remindery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($services as $service)
                        @php $daysLeft = (int) now()->diffInDays($service->next_due_date, false); @endphp
                        <tr>
                            <td>
                                <div class="f-w-500">{{ $service->customer?->company_name ?? '—' }}</div>
                                <div class="f-11 f-light">{{ $service->customer?->user?->email ?? '' }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.services.show', $service) }}" class="f-w-500">
                                    {{ $service->label ?: "#{$service->id}" }}
                                </a>
                            </td>
                            <td class="f-12">{{ $service->product?->name ?? '—' }}</td>
                            <td class="f-12 text-nowrap">{{ $service->next_due_date?->format('d.m.Y') }}</td>
                            <td>
                                <span class="badge badge-light-{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : 'info') }} f-10">
                                    {{ $daysLeft }} d
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    @foreach([30 => '30d', 14 => '14d', 7 => '7d', 1 => '1d'] as $th => $lbl)
                                        @php $col = "renewal_reminder_{$th}d_sent_at"; @endphp
                                        <span class="badge {{ $service->$col ? 'badge-light-success' : 'badge-light-secondary' }} f-10">
                                            {{ $lbl }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $services->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
