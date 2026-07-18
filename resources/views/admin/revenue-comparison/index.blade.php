@extends('layouts.panel')

@section('title', 'Porovnání tržeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- KPI Cards --}}
        <div class="col-span-3 xl:col-span-6">
            <x-panel.card title="Aktuální měsíc">
                <div class="text-center py-2">
                    <div class="f-24 f-w-700 text-primary">{{ number_format($currentMonth / 100, 2, ',', ' ') }} Kč</div>
                    <div class="text-muted f-12 mt-1">{{ now()->translatedFormat('F Y') }}</div>
                    @if($momChange !== null)
                        <div class="mt-2">
                            @if($momChange > 0)
                                <span class="badge bg-success">↑ {{ $momChange }}% MoM</span>
                            @elseif($momChange < 0)
                                <span class="badge bg-danger">↓ {{ abs($momChange) }}% MoM</span>
                            @else
                                <span class="badge bg-secondary">→ 0% MoM</span>
                            @endif
                        </div>
                    @else
                        <div class="mt-2"><span class="badge bg-secondary">N/A</span></div>
                    @endif
                </div>
            </x-panel.card>
        </div>

        <div class="col-span-3 xl:col-span-6">
            <x-panel.card title="Předchozí měsíc">
                <div class="text-center py-2">
                    <div class="f-24 f-w-700 text-secondary">{{ number_format($previousMonth / 100, 2, ',', ' ') }} Kč</div>
                    <div class="text-muted f-12 mt-1">{{ now()->subMonth()->translatedFormat('F Y') }}</div>
                </div>
            </x-panel.card>
        </div>

        <div class="col-span-3 xl:col-span-6">
            <x-panel.card title="Aktuální rok">
                <div class="text-center py-2">
                    <div class="f-24 f-w-700 text-success">{{ number_format($currentYear / 100, 2, ',', ' ') }} Kč</div>
                    <div class="text-muted f-12 mt-1">{{ now()->year }}</div>
                    @if($yoyChange !== null)
                        <div class="mt-2">
                            @if($yoyChange > 0)
                                <span class="badge bg-success">↑ {{ $yoyChange }}% YoY</span>
                            @elseif($yoyChange < 0)
                                <span class="badge bg-danger">↓ {{ abs($yoyChange) }}% YoY</span>
                            @else
                                <span class="badge bg-secondary">→ 0% YoY</span>
                            @endif
                        </div>
                    @else
                        <div class="mt-2"><span class="badge bg-secondary">N/A</span></div>
                    @endif
                </div>
            </x-panel.card>
        </div>

        <div class="col-span-3 xl:col-span-6">
            <x-panel.card title="Předchozí rok">
                <div class="text-center py-2">
                    <div class="f-24 f-w-700 text-secondary">{{ number_format($previousYear / 100, 2, ',', ' ') }} Kč</div>
                    <div class="text-muted f-12 mt-1">{{ now()->subYear()->year }}</div>
                </div>
            </x-panel.card>
        </div>

        {{-- Summary Table --}}
        <div class="col-span-12">
            <x-panel.card title="Přehled porovnání">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Období</th>
                                <th class="text-right">Tržby</th>
                                <th class="text-center">Změna</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="f-w-500">Aktuální měsíc</span>
                                    <div class="text-muted f-12">{{ now()->translatedFormat('F Y') }}</div>
                                </td>
                                <td class="text-right f-w-500">{{ number_format($currentMonth / 100, 2, ',', ' ') }} Kč</td>
                                <td class="text-center">
                                    @if($momChange !== null)
                                        @if($momChange > 0)
                                            <span class="badge bg-success">↑ {{ $momChange }}%</span>
                                        @elseif($momChange < 0)
                                            <span class="badge bg-danger">↓ {{ abs($momChange) }}%</span>
                                        @else
                                            <span class="badge bg-secondary">0%</span>
                                        @endif
                                        <div class="text-muted f-11">vs. předchozí měsíc</div>
                                    @else
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="f-w-500">Předchozí měsíc</span>
                                    <div class="text-muted f-12">{{ now()->subMonth()->translatedFormat('F Y') }}</div>
                                </td>
                                <td class="text-right">{{ number_format($previousMonth / 100, 2, ',', ' ') }} Kč</td>
                                <td class="text-center text-muted">—</td>
                            </tr>
                            <tr class="table-light">
                                <td>
                                    <span class="f-w-500">Aktuální rok</span>
                                    <div class="text-muted f-12">{{ now()->year }}</div>
                                </td>
                                <td class="text-right f-w-500">{{ number_format($currentYear / 100, 2, ',', ' ') }} Kč</td>
                                <td class="text-center">
                                    @if($yoyChange !== null)
                                        @if($yoyChange > 0)
                                            <span class="badge bg-success">↑ {{ $yoyChange }}%</span>
                                        @elseif($yoyChange < 0)
                                            <span class="badge bg-danger">↓ {{ abs($yoyChange) }}%</span>
                                        @else
                                            <span class="badge bg-secondary">0%</span>
                                        @endif
                                        <div class="text-muted f-11">vs. předchozí rok</div>
                                    @else
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td>
                                    <span class="f-w-500">Předchozí rok</span>
                                    <div class="text-muted f-12">{{ now()->subYear()->year }}</div>
                                </td>
                                <td class="text-right">{{ number_format($previousYear / 100, 2, ',', ' ') }} Kč</td>
                                <td class="text-center text-muted">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
