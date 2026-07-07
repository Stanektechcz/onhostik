@extends('layouts.front')

@section('title', 'Odměny z doporučení')

@section('content')
<div class="container py-5">
    <h2 class="mb-4">Moje odměny z doporučení</h2>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h4 text-success">{{ number_format($totalReferrerReward / 100, 2) }} Kč</div>
                    <small class="text-muted">Celková odměna</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h4 text-warning">{{ $pendingCount }}</div>
                    <small class="text-muted">Čeká na vyplacení</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Doporučený zákazník</th>
                            <th class="text-end">Odměna</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rewards as $r)
                        <tr>
                            <td>{{ $r->referee?->company_name ?? ('Zákazník #' . $r->referee_id) }}</td>
                            <td class="text-end">{{ number_format($r->referrer_reward_haler / 100, 2) }} Kč</td>
                            <td class="text-muted">{{ $r->rewarded_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Zatím žádné odměny. Doporučte nás a získejte bonus!</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
