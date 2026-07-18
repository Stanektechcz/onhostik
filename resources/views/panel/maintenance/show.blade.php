@extends('layouts.panel')

@section('title', 'Údržba služby')

@section('content')
<div class="container py-4">

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('panel.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět na službu</a>
        <h2 class="mb-0 h5">Údržba: {{ $service->label ?? "Služba #{$service->id}" }}</h2>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Nadcházející / probíhající</strong></div>
        @if($upcoming->isEmpty())
            <div class="card-body text-muted">Žádná plánovaná údržba pro tuto službu.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Název</th><th>Začátek</th><th>Konec</th><th>Stav</th></tr>
                </thead>
                <tbody>
                    @foreach($upcoming as $w)
                    <tr>
                        <td>
                            <strong>{{ $w->title }}</strong>
                            @if($w->description)<div class="text-muted small">{{ $w->description }}</div>@endif
                        </td>
                        <td class="small">{{ $w->scheduled_start->format('d.m.Y H:i') }}</td>
                        <td class="small">{{ $w->scheduled_end->format('d.m.Y H:i') }}</td>
                        <td><span class="badge {{ $w->statusBadgeClass() }}">{{ $w->statusLabel() }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @if($past->isNotEmpty())
    <div class="card">
        <div class="card-header"><strong>Minulá údržba</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Název</th><th>Datum</th><th>Stav</th></tr>
                </thead>
                <tbody>
                    @foreach($past as $w)
                    <tr>
                        <td>{{ $w->title }}</td>
                        <td class="small text-muted">{{ $w->scheduled_start->format('d.m.Y') }}</td>
                        <td><span class="badge {{ $w->statusBadgeClass() }}">{{ $w->statusLabel() }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
