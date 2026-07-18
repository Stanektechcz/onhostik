@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Výplaty';
    $breadcrumbItems = ['Partner' => route('partner.dashboard'), 'Výplaty' => ''];
@endphp

@section('title', 'Výplaty | Partner')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Manual payout notice --}}
    <div class="card mb-3 border-warning">
        <div class="card-body py-3">
            <div class="flex items-start gap-3">
                <div class="shrink-0 mt-1">
                    <i data-feather="alert-triangle" class="txt-warning" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <span class="f-w-600 txt-warning">Manuální výplaty</span>
                    <span class="badge badge-light-warning ms-2">MANUAL</span>
                    <p class="f-light f-14 mb-0 mt-1">
                        Výplaty provizí probíhají manuálně přes partnerský tým. Automatický výplatní systém
                        se připravuje. Pro žádost o výplatu kontaktujte
                        <a href="mailto:partner@onhost.cz" class="txt-primary">partner@onhost.cz</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Historie výplat">
        @if($payouts->isEmpty())
            <div class="text-center py-5">
                <i data-feather="dollar-sign" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádné výplaty</h6>
                <p class="f-light f-12 mb-3">
                    Historie výplat se zobrazí po první manuální výplatě.
                </p>
                <div class="flex justify-center gap-2">
                    <a href="{{ route('partner.commissions') }}" class="btn btn-outline-primary btn-sm">
                        <i data-feather="trending-up" style="width:13px;height:13px;"></i>
                        Přehled provizí
                    </a>
                    <span class="badge badge-light-warning self-center">MANUAL</span>
                </div>
            </div>
        @else
            <x-panel.data-table :headers="['ID', 'Částka', 'Metoda', 'Stav', 'Požadavek', 'Zpracováno']">
                @foreach($payouts as $payout)
                    <tr>
                        <td>#{{ $payout['id'] }}</td>
                        <td class="f-w-600">{{ $payout['amount'] ?? '—' }}</td>
                        <td>{{ $payout['method'] ?? 'Bankovní převod' }}</td>
                        <td>
                            @php($color = match($payout['status'] ?? '') { 'pending' => 'warning', 'processed' => 'success', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-light-{{ $color }}">{{ $payout['status_label'] ?? '—' }}</span>
                        </td>
                        <td class="f-12">{{ $payout['created_at'] ?? '—' }}</td>
                        <td class="f-12">{{ $payout['processed_at'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        @endif
    </x-panel.card>
</div>
@endsection
