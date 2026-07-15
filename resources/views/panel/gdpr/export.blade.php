@extends('layouts.panel')
@section('title', 'Export osobních dat (GDPR)')
@section('content')
<div class="container py-4" style="max-width:700px">
    <x-panel.flash />

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Export osobních dat</div>
        <div class="card-body">
            <p class="text-muted">Můžete si vyžádat export všech vašich osobních dat uložených v systému. Export se připraví na pozadí a budete o něm informováni.</p>
            <form method="POST" action="{{ route('panel.gdpr.export.store') }}">
                @csrf
                <button class="btn btn-primary">Vyžádat export dat</button>
            </form>
        </div>
    </div>

    @if($exports->isNotEmpty())
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Historie exportů</div>
        <div class="list-group list-group-flush">
            @foreach($exports as $req)
            <div class="list-group-item d-flex align-items-center justify-content-between">
                <div>
                    <span class="me-2">
                        @switch($req->status)
                            @case('queued')    <span class="badge bg-secondary">Zařazen</span> @break
                            @case('processing') <span class="badge bg-info text-dark">Zpracovávám</span> @break
                            @case('ready')     <span class="badge bg-success">Připraven</span> @break
                            @case('failed')    <span class="badge bg-danger">Chyba</span> @break
                        @endswitch
                    </span>
                    <small class="text-muted">{{ $req->created_at?->format('d.m.Y H:i') }}</small>
                    @if($req->status === 'ready')
                        <small class="ms-2 text-muted">Platnost do: {{ $req->expires_at?->format('d.m.Y') }}</small>
                    @endif
                </div>
                @if($req->status === 'ready' && $req->expires_at?->isFuture())
                <a href="{{ route('panel.gdpr.export.download', $req->download_token) }}" class="btn btn-sm btn-outline-primary">Stáhnout ZIP</a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
