@extends('layouts.panel')

@section('title', 'Heatmapa rizika odchodu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Heatmapa rizika odchodu zákazníků">
        <p class="text-muted mb-3">Rozdělení zákazníků podle segmentu a míry rizika odchodu (churn risk score).</p>

        @if(empty($data))
            <p class="text-muted">Žádná data k zobrazení.</p>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Segment</th>
                        @foreach($buckets as $bucket => $label)
                            <th class="text-center">{{ $label }}</th>
                        @endforeach
                        <th class="text-center">Celkem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($segments as $segment)
                    <tr>
                        <td><strong>{{ $segment->value }}</strong></td>
                        @foreach(array_keys($buckets) as $bucket)
                        @php $count = $data[$segment->value][$bucket] ?? 0; @endphp
                        <td class="text-center">
                            @if($count > 0)
                                <span class="badge {{ $bucket === 'high' ? 'bg-danger' : ($bucket === 'medium' ? 'bg-warning text-dark' : 'bg-success') }} fs-6">{{ $count }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endforeach
                        <td class="text-center font-bold">
                            {{ array_sum(array_map(fn($b) => $data[$segment->value][$b] ?? 0, array_keys($buckets))) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($topRisk->count())
        <h6 class="mt-4">Top 10 zákazníků s nejvyšším rizikem</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Zákazník</th><th>Segment</th><th class="text-right">Churn skóre</th></tr>
                </thead>
                <tbody>
                    @foreach($topRisk as $s)
                    <tr>
                        <td>{{ $s->customer_name ?? ('Zákazník #' . $s->customer_id) }}</td>
                        <td>{{ $s->segment ?? '—' }}</td>
                        <td class="text-right">
                            <span class="badge bg-danger">{{ $s->churn_risk_score }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @endif
    </x-panel.card>
</div>
@endsection
