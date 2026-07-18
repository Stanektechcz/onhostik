@extends('layouts.panel')

@section('title', 'BI 2.0 — Prediktivní analytika')

@section('content')
<div class="container-fluid py-4">

    <div class="flex items-center justify-between mb-4">
        <h1 class="h4 mb-0">BI 2.0 — Prediktivní analytika</h1>
        <a href="{{ route('admin.bi.index') }}" class="btn btn-sm btn-outline-secondary">Klasický BI dashboard</a>
    </div>

    {{-- MRR Summary Cards --}}
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">MRR</div>
                    <div class="h4 mb-0 font-bold text-success">{{ number_format($mrrSummary['mrr'], 0, ',', ' ') }} Kč</div>
                    <div class="text-muted" style="font-size:11px">Monthly Recurring Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">ARR</div>
                    <div class="h4 mb-0 font-bold text-primary">{{ number_format($mrrSummary['arr'], 0, ',', ' ') }} Kč</div>
                    <div class="text-muted" style="font-size:11px">Annual Recurring Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Churn rate</div>
                    <div class="h4 mb-0 font-bold {{ $mrrSummary['churn_rate'] > 5 ? 'text-danger' : 'text-success' }}">
                        {{ $mrrSummary['churn_rate'] }}%
                    </div>
                    <div class="text-muted" style="font-size:11px">Posledních 30 dní</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Růst MOM</div>
                    <div class="h4 mb-0 font-bold {{ $mrrSummary['growth_rate'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $mrrSummary['growth_rate'] >= 0 ? '+' : '' }}{{ $mrrSummary['growth_rate'] }}%
                    </div>
                    <div class="text-muted" style="font-size:11px">Vs. minulý měsíc</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3 mb-4">

        {{-- Revenue Trend (6 months) --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="card h-full">
                <div class="card-header"><strong>Příjmy posledních 6 měsíců</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Měsíc</th>
                                    <th class="text-right">Příjem</th>
                                    <th class="text-right">Noví zákazníci</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mrrTrend as $month => $revenue)
                                    <tr>
                                        <td class="small">{{ $month }}</td>
                                        <td class="text-right small">{{ number_format($revenue, 0, ',', ' ') }} Kč</td>
                                        <td class="text-right small">{{ $newPerMonth[$month] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <td class="small font-semibold">Forecast (příští měsíc)</td>
                                    <td class="text-right small font-semibold">{{ number_format($forecast['forecast'], 0, ',', ' ') }} Kč</td>
                                    <td class="text-right small text-muted">
                                        <span class="badge bg-{{ $forecast['confidence'] === 'high' ? 'success' : ($forecast['confidence'] === 'medium' ? 'warning' : 'secondary') }}">
                                            {{ $forecast['confidence'] }}
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- CLV by Segment --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="card h-full">
                <div class="card-header"><strong>Customer Lifetime Value dle segmentu</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Segment</th>
                                    <th class="text-right">Průměrná CLV</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clvBySegment as $segment => $clv)
                                    <tr>
                                        <td class="small">
                                            @php
                                                $segColor = match($segment) {
                                                    'vip'     => 'warning',
                                                    'healthy' => 'success',
                                                    'at_risk' => 'danger',
                                                    'churned' => 'secondary',
                                                    default   => 'light',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $segColor }}">{{ $segment }}</span>
                                        </td>
                                        <td class="text-right small font-semibold">{{ number_format($clv, 0, ',', ' ') }} Kč</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Cohort Analysis --}}
    <div class="card mb-4">
        <div class="card-header flex justify-between items-center">
            <strong>Kohortní analýza (příjmy dle měsíce akvizice)</strong>
            <span class="text-muted small">CZK, poslední 6 kohort × 4 periody</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Kohorta</th>
                        <th class="text-right">Měsíc 0</th>
                        <th class="text-right">Měsíc 1</th>
                        <th class="text-right">Měsíc 2</th>
                        <th class="text-right">Měsíc 3</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cohortData as $cohortMonth => $periods)
                        <tr>
                            <td class="small font-semibold">{{ $cohortMonth }}</td>
                            @foreach([0, 1, 2, 3] as $p)
                                <td class="text-right small">
                                    @if(isset($periods[$p]) && $periods[$p] !== null)
                                        @php
                                            $val     = $periods[$p];
                                            $base    = $periods[0] ?? 0;
                                            $pct     = $base > 0 ? round($val / $base * 100) : 0;
                                            $bgAlpha = $pct > 0 ? max(0.05, min(0.4, $pct / 100)) : 0;
                                        @endphp
                                        <span style="background:rgba(34,197,94,{{ $bgAlpha }});padding:2px 6px;border-radius:4px;display:block;text-align:right">
                                            {{ number_format($val, 0, ',', ' ') }} Kč
                                            @if($p > 0 && $base > 0)
                                                <small class="text-muted">({{ $pct }}%)</small>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Top At-Risk Customers --}}
    @if($topAtRisk->isNotEmpty())
        <div class="card">
            <div class="card-header"><strong>Zákazníci s nejvyšším rizikem odchodu</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Zákazník</th>
                            <th>Segment</th>
                            <th>Riziko</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topAtRisk as $customer)
                            <tr>
                                <td>
                                    <div class="font-semibold small">{{ $customer->user->name ?? '—' }}</div>
                                    <div class="text-muted" style="font-size:11px">{{ $customer->user->email ?? '' }}</div>
                                </td>
                                <td>
                                    @php
                                        $segBg = match($customer->segment) {
                                            'churned' => 'secondary',
                                            'at_risk' => 'danger',
                                            default   => 'warning',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $segBg }}">{{ $customer->segment ?? '—' }}</span>
                                </td>
                                <td>
                                    @php $risk = $customer->churn_risk_score ?? 0; @endphp
                                    <div class="progress" style="height:6px;width:80px;display:inline-block">
                                        <div class="progress-bar bg-danger" style="width:{{ $risk }}%"></div>
                                    </div>
                                    <span class="small ms-1">{{ $risk }}/100</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-xs btn-sm btn-outline-primary">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
