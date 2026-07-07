@extends('layouts.panel')

@section('title', 'NPS — Net Promoter Score')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0">NPS — Net Promoter Score</h1>
        <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-outline-secondary">← Support</a>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">NPS skóre</div>
                    @if($npsScore !== null)
                        <div class="h3 fw-bold mb-0 {{ $npsScore >= 50 ? 'text-success' : ($npsScore >= 0 ? 'text-warning' : 'text-danger') }}">
                            {{ $npsScore > 0 ? '+' : '' }}{{ $npsScore }}
                        </div>
                    @else
                        <div class="h3 fw-bold mb-0 text-muted">—</div>
                    @endif
                    <div class="text-muted" style="font-size:11px">Net Promoter Score</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Průměrné skóre</div>
                    <div class="h3 fw-bold mb-0 text-primary">{{ $avgScore !== null ? number_format((float)$avgScore, 1) : '—' }}</div>
                    <div class="text-muted" style="font-size:11px">z 10 bodů</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Odpovědí</div>
                    <div class="h3 fw-bold mb-0 text-info">{{ $total }} / {{ $sentCount }}</div>
                    <div class="text-muted" style="font-size:11px">odesláno / doručeno</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Míra odpovědi</div>
                    <div class="h3 fw-bold mb-0">{{ $sentCount > 0 ? round($total / $sentCount * 100) : 0 }}%</div>
                    <div class="text-muted" style="font-size:11px">response rate</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Promoter / Passive / Detractor --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Rozložení</strong></div>
                <div class="card-body">
                    @php
                        $categories = [
                            ['label' => 'Promotéři (9–10)', 'count' => $promoters,  'color' => 'success'],
                            ['label' => 'Pasivní (7–8)',     'count' => $passives,   'color' => 'warning'],
                            ['label' => 'Kritici (0–6)',     'count' => $detractors, 'color' => 'danger'],
                        ];
                    @endphp
                    @foreach($categories as $cat)
                        @php $pct = $total > 0 ? round($cat['count'] / $total * 100) : 0; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $cat['label'] }}</span>
                                <span class="fw-bold">{{ $cat['count'] }} ({{ $pct }}%)</span>
                            </div>
                            <div class="progress" style="height:10px">
                                <div class="progress-bar bg-{{ $cat['color'] }}" style="width:{{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Trend --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><strong>Trend (posledních 6 měsíců)</strong></div>
                <div class="card-body">
                    @if($trend->isEmpty())
                        <p class="text-muted text-center py-3">Zatím žádná data.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Měsíc</th><th>Odpovědí</th><th>Průměr</th></tr></thead>
                                <tbody>
                                    @foreach($trend as $row)
                                        <tr>
                                            <td>{{ $row->month }}</td>
                                            <td>{{ $row->count }}</td>
                                            <td>{{ number_format((float)$row->avg_score, 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent responses --}}
    <div class="card">
        <div class="card-header"><strong>Poslední odpovědi</strong></div>
        <div class="card-body p-0">
            @if($recent->isEmpty())
                <p class="text-muted text-center py-4">Zatím žádné odpovědi.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Zákazník</th>
                                <th>Tiketa</th>
                                <th>Skóre</th>
                                <th>Kategorie</th>
                                <th>Komentář</th>
                                <th>Odesláno</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent as $nps)
                                <tr>
                                    <td>
                                        @if($nps->customer)
                                            <a href="{{ route('admin.customers.show', $nps->customer) }}">
                                                {{ $nps->customer->company_name ?: $nps->customer->email }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($nps->ticket)
                                            <a href="{{ route('admin.support.show', $nps->ticket) }}">#{{ $nps->ticket->id }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold {{ $nps->isPromoter() ? 'text-success' : ($nps->isPassive() ? 'text-warning' : 'text-danger') }}">
                                            {{ $nps->score }}/10
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $nps->categoryColor() }}">{{ $nps->categoryLabel() }}</span>
                                    </td>
                                    <td class="text-muted" style="max-width:250px">
                                        <span class="text-truncate d-inline-block" style="max-width:200px" title="{{ $nps->comment }}">
                                            {{ $nps->comment ?: '—' }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ $nps->submitted_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
