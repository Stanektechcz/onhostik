@extends('layouts.panel')

@php
    use App\Domains\Bi\Enums\CustomerSegment;
    $breadcrumbTitle = 'Business Intelligence';
    $breadcrumbItems = ['Business Intelligence' => ''];
@endphp

@section('title', 'Business Intelligence')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Revenue forecast KPI row --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body primary">
                    <span class="f-light">Výhled příjmů (příští měsíc)</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ number_format($forecast['forecast'], 0, ',', ' ') }} Kč</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="trending-up"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body {{ $forecast['trend'] === 'growing' ? 'success' : ($forecast['trend'] === 'declining' ? 'danger' : 'warning') }}">
                    <span class="f-light">Trend příjmů</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>
                            @if($forecast['trend'] === 'growing') ▲ Rostoucí
                            @elseif($forecast['trend'] === 'declining') ▼ Klesající
                            @else → Stabilní
                            @endif
                        </h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="activity"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body {{ ($segmentCounts['at_risk'] ?? 0) + ($segmentCounts['churned'] ?? 0) > 0 ? 'warning' : 'success' }}">
                    <span class="f-light">Ohrožení + Odchody</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ ($segmentCounts['at_risk'] ?? 0) + ($segmentCounts['churned'] ?? 0) }}</h4>
                        <span class="f-light f-12 mb-1">zákazníků</span>
                    </div>
                    <div class="bg-gradient"><i data-feather="user-x"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-3">
            <div class="card small-widget">
                <div class="card-body {{ $forecast['confidence'] === 'high' ? 'success' : ($forecast['confidence'] === 'medium' ? 'warning' : 'secondary') }}">
                    <span class="f-light">Spolehlivost výhledu</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ match($forecast['confidence']) { 'high' => 'Vysoká', 'medium' => 'Střední', default => 'Nízká' } }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="bar-chart-2"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap">

        {{-- Revenue forecast data --}}
        <div class="col-span-5 xl:col-span-12">
            <x-panel.card title="Výhled příjmů">
                <p class="f-12 f-light mb-3">
                    Výhled je průměrem posledních 3 měsíců faktur se statusem <em>Zaplacena</em>.
                    Spolehlivost: <span class="badge badge-light-{{ $forecast['confidence'] === 'high' ? 'success' : ($forecast['confidence'] === 'medium' ? 'warning' : 'secondary') }}">{{ match($forecast['confidence']) { 'high' => 'Vysoká', 'medium' => 'Střední', default => 'Nízká' } }}</span>
                </p>
                <table class="table table-sm f-12 mb-3">
                    <thead>
                        <tr>
                            <th>Měsíc</th>
                            <th class="text-end">Příjmy (Kč)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($forecast['months'] as $month => $amount)
                        <tr>
                            <td>{{ $month }}</td>
                            <td class="text-end f-w-500">{{ number_format($amount, 0, ',', ' ') }} Kč</td>
                        </tr>
                        @endforeach
                        <tr class="table-light">
                            <td class="f-w-600">Výhled příští měsíc</td>
                            <td class="text-end f-w-600 font-primary">{{ number_format($forecast['forecast'], 0, ',', ' ') }} Kč</td>
                        </tr>
                    </tbody>
                </table>

                <div class="d-flex align-items-center gap-2 f-12 f-light">
                    <i data-feather="info" style="width:12px;height:12px"></i>
                    Přepočteno z haléřů. Spusťte <code>php artisan bi:compute-insights</code> pro aktualizaci segmentů.
                </div>
            </x-panel.card>
        </div>

        {{-- Customer segment distribution --}}
        <div class="col-span-7 xl:col-span-12">
            <x-panel.card title="Segmentace zákazníků">
                @if($totalWithInsights === 0)
                    <div class="text-center py-4">
                        <i data-feather="cpu" style="width:36px;height:36px" class="text-muted mb-2"></i>
                        <p class="f-light f-12 mb-1">Segmenty zatím nevypočteny.</p>
                        <p class="f-11 f-light">Spusťte: <code>php artisan bi:compute-insights</code></p>
                    </div>
                @else
                    <div class="row g-3 mb-4">
                        @foreach(CustomerSegment::cases() as $seg)
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 text-center">
                                <span class="badge badge-light-{{ $seg->color() }} f-12 d-block mb-1">{{ $seg->label() }}</span>
                                <h4 class="mb-0">{{ $segmentCounts[$seg->value] ?? 0 }}</h4>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if(($segmentCounts['unknown'] ?? 0) > 0)
                        <p class="f-11 f-light mb-0">
                            + {{ $segmentCounts['unknown'] }} zákazník(ů) bez vypočteného segmentu.
                        </p>
                    @endif
                @endif
            </x-panel.card>
        </div>

    </div>

    {{-- At-risk customers table --}}
    @if($atRiskCustomers->isNotEmpty())
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12">
            <x-panel.card title="Zákazníci s nejvyšším rizikem odchodu">
                <div class="table-responsive">
                    <table class="table table-hover f-13">
                        <thead>
                            <tr>
                                <th>Zákazník</th>
                                <th>Segment</th>
                                <th>Skóre rizika</th>
                                <th>Skóre (vizuál)</th>
                                <th>Poslední výpočet</th>
                                <th class="text-end">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($atRiskCustomers as $customer)
                            @php
                                $seg         = $customer->segment ? \App\Domains\Bi\Enums\CustomerSegment::tryFrom($customer->segment) : null;
                                $riskColor   = match(true) {
                                    $customer->churn_risk_score >= 70 => 'danger',
                                    $customer->churn_risk_score >= 30 => 'warning',
                                    default                           => 'info',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="f-w-500">
                                        {{ $customer->email }}
                                    </a>
                                    @if($customer->company_name)
                                        <div class="f-11 f-light">{{ $customer->company_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($seg)
                                        <span class="badge badge-light-{{ $seg->color() }}">{{ $seg->label() }}</span>
                                    @else
                                        <span class="f-light">—</span>
                                    @endif
                                </td>
                                <td class="f-w-600">
                                    <span class="text-{{ $riskColor }}">{{ $customer->churn_risk_score ?? '—' }}</span>/100
                                </td>
                                <td style="min-width:120px">
                                    @if($customer->churn_risk_score !== null)
                                    <div class="progress" style="height:6px">
                                        <div class="progress-bar bg-{{ $riskColor }}"
                                             style="width:{{ $customer->churn_risk_score }}%"></div>
                                    </div>
                                    @endif
                                </td>
                                <td class="f-light f-12">
                                    {{ $customer->insights_updated_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                       class="btn btn-xs btn-outline-secondary">
                                        <i data-feather="eye" style="width:11px;height:11px"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
    </div>
    @endif

</div>
@endsection
