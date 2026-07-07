@extends('layouts.panel')

@section('title', 'Statistiky převodů domén')

@section('content')
<x-panel.flash />

<x-panel.card title="Statistiky převodů domén">
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <h6 class="card-title">Celkem žádostí</h6>
                    <p class="display-6">{{ $totalRequests }}</p>
                </div>
            </div>
        </div>
        @php
            $statusBadges = [
                'pending'   => ['label' => 'Čekající',   'color' => 'secondary'],
                'completed' => ['label' => 'Dokončené',  'color' => 'success'],
                'failed'    => ['label' => 'Selhané',    'color' => 'danger'],
            ];
        @endphp
        @foreach($statusBadges as $key => $meta)
            <div class="col-md-3">
                <div class="card text-center border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title">{{ $meta['label'] }}</h6>
                        <p class="display-6 text-{{ $meta['color'] }}">
                            {{ $statusStats[$key]->count ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h5 class="mb-3">Poslední žádosti</h5>
    @if($recentRequests->isEmpty())
        <p class="text-muted">Žádné žádosti.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Zákazník</th>
                        <th>Doména</th>
                        <th>Stav</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRequests as $req)
                        <tr>
                            <td>{{ $req->customer?->name ?? $req->customer_id }}</td>
                            <td>{{ $req->domain }}</td>
                            <td>
                                @php
                                    $badgeClass = match($req->status) {
                                        'pending'   => 'secondary',
                                        'completed' => 'success',
                                        'failed'    => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">{{ $req->status }}</span>
                            </td>
                            <td>{{ $req->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
