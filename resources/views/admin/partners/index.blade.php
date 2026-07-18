@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Partneři';
    $breadcrumbItems = ['Partneři' => ''];
@endphp

@section('title', 'Partneři')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-6 sm:col-span-12 md:col-span-6">
            <x-panel.stat-widget label="Partneři celkem" :value="$totalCount" icon="share-2" color="primary" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-6">
            <x-panel.stat-widget label="Aktivní" :value="$activeCount" icon="check-circle" color="success" />
        </div>
    </div>

    <x-panel.card title="Partnerské profily">
        <div class="flex justify-end mb-3">
            <a href="{{ route('admin.partners.create') }}" class="btn btn-primary btn-sm">
                <i data-feather="plus" style="width:13px;height:13px;"></i>
                Nový partner
            </a>
        </div>
        @if($partners->isEmpty())
            <div class="text-center py-5">
                <i data-feather="share-2" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádní partneři</h6>
                <p class="f-light f-12 mb-3">Zatím nebyl přidán žádný partnerský profil.</p>
                <a href="{{ route('admin.partners.create') }}" class="btn btn-primary btn-sm">Vytvořit prvního partnera</a>
            </div>
        @else
            <x-panel.data-table :headers="['Partner', 'Referral kód', 'Stav', 'Referraly', 'Provize', 'Registrace', '']">
                @foreach($partners as $partner)
                    <tr>
                        <td class="f-w-500">{{ $partner->user?->name ?? '—' }}</td>
                        <td><code class="badge badge-light-secondary">{{ $partner->referral_code }}</code></td>
                        <td><x-panel.status-badge :status="$partner->status" /></td>
                        <td>{{ $partner->referrals_count }}</td>
                        <td class="f-12">
                            <span class="txt-warning">{{ number_format(($partner->pending_sum ?? 0) / 100, 0, ',', ' ') }} Kč čeká</span>
                        </td>
                        <td class="f-12">{{ $partner->created_at?->format('d.m.Y') }}</td>
                        <td>
                            <a href="{{ route('admin.partners.show', $partner) }}" class="btn btn-outline-primary btn-xs">Detail</a>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            {{ $partners->links() }}
        @endif
    </x-panel.card>
</div>
@endsection
