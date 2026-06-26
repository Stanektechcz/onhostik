@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Provize';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Provize' => ''];
@endphp

@section('title', 'Provize | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Čekající provize"
                :value="number_format(($counts['pending'] ?? 0) / 100, 0, ',', ' ') . ' Kč'"
                icon="clock" color="warning" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Schválené provize"
                :value="number_format(($counts['approved'] ?? 0) / 100, 0, ',', ' ') . ' Kč'"
                icon="check-circle" color="success" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Vyplaceno celkem"
                :value="number_format(($counts['paid'] ?? 0) / 100, 0, ',', ' ') . ' Kč'"
                icon="dollar-sign" color="primary" />
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Zamítnuto"
                :value="number_format(($counts['rejected'] ?? 0) / 100, 0, ',', ' ') . ' Kč'"
                icon="x-circle" color="danger" />
        </div>
    </div>

    <x-panel.card title="Přehled provizí">
        @if((method_exists($commissions, 'isEmpty') ? $commissions->isEmpty() : true))
            <div class="text-center py-5">
                <i data-feather="trending-up" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádné provize</h6>
                <p class="f-light f-12 mb-3">Provize vzniknou po zaplacené objednávce vašich referral zákazníků.</p>
                <a href="{{ route('partner.referrals') }}" class="btn btn-primary btn-sm">
                    <i data-feather="share-2" style="width:13px;height:13px;"></i>
                    Sdílet referral odkaz
                </a>
            </div>
        @else
            <x-panel.data-table :headers="['#', 'Faktura', 'Částka', 'Sazba', 'Stav', 'Eligible od', 'Schváleno']">
                @foreach($commissions as $commission)
                    <tr>
                        <td class="f-light f-12">#{{ $commission->id }}</td>
                        <td>
                            @if($commission->invoice)
                                <span class="f-w-600">{{ $commission->invoice->number }}</span>
                                <p class="f-light f-12 mb-0">{{ $commission->invoice->created_at?->format('d.m.Y') }}</p>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td class="f-w-600 txt-success">{{ $commission->formattedAmount() }}</td>
                        <td class="f-12">{{ $commission->rate_percent }}%</td>
                        <td><x-panel.status-badge :status="$commission->status" /></td>
                        <td class="f-12">{{ $commission->eligible_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="f-12">{{ $commission->approved_at?->format('d.m.Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            @if(method_exists($commissions, 'links'))
                {{ $commissions->links() }}
            @endif
        @endif
    </x-panel.card>
</div>
@endsection
