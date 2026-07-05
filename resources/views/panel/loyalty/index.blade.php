@extends('layouts.panel')

@section('title', 'Věrnostní program')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">Věrnostní program</h2>

    {{-- Next milestone progress --}}
    @if($next)
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Další milník: <strong>{{ $next['milestone']->name }}</strong></h5>
            <p class="text-muted mb-2 small">{{ $next['milestone']->description }}</p>
            <div class="d-flex justify-content-between small mb-1">
                <span>Podmínka: {{ $next['milestone']->triggerLabel() }}</span>
                <span>{{ $next['current'] }} / {{ $next['target'] }}</span>
            </div>
            <div class="progress" style="height:18px">
                <div class="progress-bar bg-warning"
                     role="progressbar"
                     style="width:{{ $next['percent'] }}%"
                     aria-valuenow="{{ $next['percent'] }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    {{ $next['percent'] }} %
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Odměna po dosažení: <strong>{{ $next['milestone']->rewardLabel() }}</strong>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-success mb-4">
        Gratulujeme! Dosáhli jste všech dostupných věrnostních milníků.
    </div>
    @endif

    {{-- Earned rewards --}}
    <div class="card">
        <div class="card-header"><strong>Vaše odměny</strong></div>
        @if($rewards->isEmpty())
            <div class="card-body text-muted">Zatím žádné odměny. Plňte milníky a získejte výhody!</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Milník</th>
                        <th>Odměna</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rewards as $r)
                    <tr>
                        <td>
                            <strong>{{ $r->milestone?->name ?? '—' }}</strong>
                            @if($r->milestone?->description)
                                <div class="text-muted small">{{ $r->milestone->description }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $r->milestone?->rewardLabel() ?? '—' }}</td>
                        <td class="small text-muted">{{ $r->awarded_at?->format('d.m.Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection
