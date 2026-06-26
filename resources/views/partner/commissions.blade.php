@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Provize';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Provize' => ''];
@endphp

@section('title', 'Provize | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Info banner --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="flex-shrink-0">
                    <i data-feather="info" class="txt-primary" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <span class="f-w-500">Systém provizí se připravuje.</span>
                    <span class="f-light f-14 ms-2">
                        Výpočet a schvalování provizí bude implementováno v dalším vydání.
                        Aktuálně jsou provize spravovány manuálně — kontaktujte partnerský tým.
                    </span>
                    <span class="badge badge-light-warning ms-2">PŘIPRAVUJEME</span>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Čekající provize" :value="'0 Kč'" icon="clock" color="warning" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Schválené provize" :value="'0 Kč'" icon="check-circle" color="success" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Vyplaceno celkem" :value="'0 Kč'" icon="dollar-sign" color="primary" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Stornováno" :value="'0 Kč'" icon="x-circle" color="danger" />
        </div>
    </div>

    <x-panel.card title="Přehled provizí">
        @if($commissions->isEmpty())
            <div class="text-center py-5">
                <i data-feather="trending-up" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádné provize</h6>
                <p class="f-light f-12 mb-3">
                    Provize budou evidovány po aktivaci referral zákazníků.
                    Systém provizí se aktuálně připravuje.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="{{ route('partner.referrals') }}" class="btn btn-primary btn-sm">
                        <i data-feather="share-2" style="width:13px;height:13px;"></i>
                        Sdílet referral odkaz
                    </a>
                    <span class="badge badge-light-warning align-self-center">PŘIPRAVUJEME</span>
                </div>
            </div>
        @else
            <x-panel.data-table :headers="['ID', 'Zákazník', 'Objednávka', 'Částka', 'Stav', 'Vytvořeno', 'Schváleno']">
                @foreach($commissions as $commission)
                    <tr>
                        <td>#{{ $commission['id'] }}</td>
                        <td>{{ $commission['customer'] ?? '—' }}</td>
                        <td>{{ $commission['order'] ?? '—' }}</td>
                        <td class="f-w-600">{{ $commission['amount'] ?? '—' }}</td>
                        <td>
                            @php($color = match($commission['status'] ?? '') { 'pending' => 'warning', 'approved' => 'success', 'paid' => 'primary', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-light-{{ $color }}">{{ $commission['status_label'] ?? '—' }}</span>
                        </td>
                        <td class="f-12">{{ $commission['created_at'] ?? '—' }}</td>
                        <td class="f-12">{{ $commission['approved_at'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        @endif
    </x-panel.card>
</div>
@endsection
