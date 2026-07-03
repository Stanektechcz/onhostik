@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Reseller program';
    $breadcrumbItems = ['Reseller program' => ''];
@endphp

@section('title', 'Reseller účty')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Status filter pills --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        @foreach(['all' => 'Vše', 'pending' => 'Čekající', 'active' => 'Aktivní', 'suspended' => 'Pozastavení', 'rejected' => 'Zamítnutí'] as $val => $label)
            <a href="{{ route('admin.resellers.index', array_filter(['status' => $val === 'all' ? null : $val, 'search' => $search ?: null])) }}"
               class="btn btn-sm {{ ($statusFilter === $val || ($statusFilter === '' && $val === 'all')) ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('admin.resellers.index') }}" class="ms-auto d-flex gap-2">
            @if($statusFilter && $statusFilter !== 'all')
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif
            <input type="text" name="search" value="{{ $search }}"
                   class="form-control form-control-sm" placeholder="Hledat název / doménu…" style="width:220px;">
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i data-feather="search" style="width:13px;height:13px;"></i>
            </button>
        </form>
    </div>

    <x-panel.card title="Reseller účty">
        @if($resellers->isEmpty())
            <div class="text-center py-5">
                <i data-feather="users" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádní reselleři</h6>
                <p class="f-light f-12 mb-0">Zatím neexistuje žádný reseller profil.</p>
            </div>
        @else
            <x-panel.data-table :headers="['Firma', 'Uživatel / e-mail', 'Vlastní doména', 'Markup %', 'Stav', 'Schváleno', '']">
                @foreach($resellers as $reseller)
                    @php
                        $badgeMap = [
                            'pending'   => 'badge badge-light-warning txt-warning',
                            'active'    => 'badge badge-light-success txt-success',
                            'suspended' => 'badge badge-light-danger txt-danger',
                            'rejected'  => 'badge badge-light-secondary txt-secondary',
                        ];
                        $badgeClass = $badgeMap[$reseller->status] ?? 'badge badge-light-secondary';
                    @endphp
                    <tr>
                        <td class="f-w-500">{{ $reseller->business_name ?? '—' }}</td>
                        <td>
                            <span class="f-w-500">{{ $reseller->user?->name ?? '—' }}</span>
                            <p class="f-light f-12 mb-0">{{ $reseller->user?->email ?? '' }}</p>
                        </td>
                        <td class="f-12">{{ $reseller->custom_domain ?? '—' }}</td>
                        <td class="f-w-600">{{ number_format((float) $reseller->markup_percent, 2) }}%</td>
                        <td><span class="{{ $badgeClass }}">{{ ucfirst($reseller->status) }}</span></td>
                        <td class="f-12">{{ $reseller->approved_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.resellers.show', $reseller) }}" class="btn btn-outline-primary btn-xs">Detail</a>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            {{ $resellers->links() }}
        @endif
    </x-panel.card>
</div>
@endsection
