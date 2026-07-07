@extends('layouts.panel')

@section('title', 'Analytika logů služeb')

@section('content')
<x-panel.flash />

<x-panel.card title="Analytika logů služeb">
    <div class="row g-3 mb-4">
        @foreach(['debug' => 'secondary', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'] as $level => $color)
            <div class="col-md-3">
                <div class="card text-center border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-uppercase">{{ $level }}</h6>
                        <p class="display-6 text-{{ $color }}">
                            {{ $levelStats[$level]->count ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h5 class="mb-3">Top chybové služby</h5>
    @if($topErrorServices->isEmpty())
        <p class="text-muted">Žádné chybové záznamy.</p>
    @else
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Služba ID</th>
                        <th>Počet chyb</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topErrorServices as $row)
                        <tr>
                            <td>{{ $row->service_id }}</td>
                            <td>{{ $row->error_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h5 class="mb-3">Poslední chyby</h5>
    @if($recentErrors->isEmpty())
        <p class="text-muted">Žádné chyby.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Datum</th>
                        <th>Služba ID</th>
                        <th>Zdroj</th>
                        <th>Zpráva</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentErrors as $log)
                        <tr>
                            <td>{{ $log->logged_at ? \Carbon\Carbon::parse($log->logged_at)->format('d.m.Y H:i') : '—' }}</td>
                            <td>{{ $log->service_id }}</td>
                            <td>{{ $log->source ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($log->message ?? '', 100) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
