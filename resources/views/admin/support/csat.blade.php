@extends('layouts.panel')

@section('title', 'CSAT — Spokojenost zákazníků')

@section('content')
<div class="container-fluid py-4">

    <div class="flex items-center justify-between mb-4">
        <h1 class="h4 mb-0">CSAT — Spokojenost zákazníků</h1>
        <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-outline-secondary">← Support</a>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Průměrné skóre</div>
                    <div class="h3 font-bold mb-0 {{ $avgScore >= 4 ? 'text-success' : ($avgScore >= 3 ? 'text-warning' : 'text-danger') }}">
                        {{ number_format($avgScore, 2) }} / 5
                    </div>
                    <div class="text-muted" style="font-size:11px">CSAT</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Celkem hodnocení</div>
                    <div class="h3 font-bold mb-0 text-primary">{{ $total }}</div>
                    <div class="text-muted" style="font-size:11px">hodnocených ticketů</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center h-full">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">% spokojených (4–5★)</div>
                    @php
                        $satisfied = ($scoreDist->get(4, 0) + $scoreDist->get(5, 0));
                        $pct = $total > 0 ? round($satisfied / $total * 100) : 0;
                    @endphp
                    <div class="h3 font-bold mb-0 {{ $pct >= 70 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger') }}">
                        {{ $pct }}%
                    </div>
                    <div class="text-muted" style="font-size:11px">spokojených zákazníků</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3 mb-4">
        {{-- Score distribution --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="card h-full">
                <div class="card-header"><strong>Rozložení hodnocení</strong></div>
                <div class="card-body">
                    @foreach([5, 4, 3, 2, 1] as $s)
                        @php
                            $cnt = $scoreDist->get($s, 0);
                            $barPct = $total > 0 ? round($cnt / $total * 100) : 0;
                            $barColor = $s >= 4 ? 'success' : ($s === 3 ? 'warning' : 'danger');
                        @endphp
                        <div class="flex items-center mb-2 gap-2">
                            <span class="text-warning" style="min-width:28px">{{ $s }}★</span>
                            <div class="progress grow" style="height:16px">
                                <div class="progress-bar bg-{{ $barColor }}" style="width:{{ $barPct }}%"></div>
                            </div>
                            <span class="small text-muted" style="min-width:30px;text-align:right">{{ $cnt }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Monthly trend --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="card h-full">
                <div class="card-header"><strong>Trend průměrného skóre (6 měsíců)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Měsíc</th>
                                    <th class="text-right">Průměr</th>
                                    <th class="text-right">Počet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trend as $row)
                                    <tr>
                                        <td class="small">{{ $row->month }}</td>
                                        <td class="text-right small font-semibold">
                                            <span class="{{ $row->avg_score >= 4 ? 'text-success' : ($row->avg_score >= 3 ? 'text-warning' : 'text-danger') }}">
                                                {{ number_format($row->avg_score, 2) }}
                                            </span>
                                        </td>
                                        <td class="text-right small text-muted">{{ $row->count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Žádná data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent ratings --}}
    <div class="card">
        <div class="card-header"><strong>Poslední hodnocení</strong></div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Zákazník</th>
                        <th>Ticket</th>
                        <th class="text-center">Skóre</th>
                        <th>Komentář</th>
                        <th>Hodnoceno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $rating)
                        <tr>
                            <td class="small">{{ $rating->ticket?->customer?->user?->name ?? '—' }}</td>
                            <td class="small">
                                <a href="{{ route('admin.support.show', $rating->ticket) }}" class="txt-primary">
                                    #{{ $rating->ticket?->id }} {{ Str::limit($rating->ticket?->subject ?? '', 40) }}
                                </a>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $rating->score >= 4 ? 'success' : ($rating->score === 3 ? 'warning' : 'danger') }}">
                                    {{ $rating->score }}★
                                </span>
                            </td>
                            <td class="small text-muted">{{ Str::limit($rating->comment ?? '', 60) }}</td>
                            <td class="small text-muted">{{ $rating->rated_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Žádná hodnocení</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
