@extends('layouts.panel')

@section('title', 'Detail webhooku')

@section('content')
<div class="container-fluid py-4" style="max-width:900px">

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.webhooks.inbound.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět</a>
        <h1 class="h5 mb-0">Webhook #{{ $log->id }} — {{ $log->source }}</h1>
        <span class="badge {{ $log->statusBadgeClass() }}">{{ $log->statusLabel() }}</span>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><strong>Metadata</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><th class="ps-3">Zdroj</th><td>{{ $log->source }}</td></tr>
                        <tr><th class="ps-3">Typ události</th><td><code>{{ $log->event_type ?? '—' }}</code></td></tr>
                        <tr><th class="ps-3">Stav</th>
                            <td><span class="badge {{ $log->statusBadgeClass() }}">{{ $log->statusLabel() }}</span></td></tr>
                        <tr><th class="ps-3">Podpis</th>
                            <td>
                                @if($log->signature_valid)
                                    <span class="badge bg-success">✓ Platný</span>
                                @else
                                    <span class="badge bg-danger">✗ Neplatný</span>
                                @endif
                            </td></tr>
                        <tr><th class="ps-3">IP adresa</th><td>{{ $log->ip_address ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Idempotency key</th><td class="small text-muted">{{ $log->idempotency_key ?? '—' }}</td></tr>
                        <tr><th class="ps-3">Přijato</th><td class="small">{{ $log->created_at->format('d.m.Y H:i:s') }}</td></tr>
                    </table>
                </div>
            </div>

            @if($log->error_message)
            <div class="alert alert-danger mt-3 small">
                <strong>Chyba:</strong> {{ $log->error_message }}
            </div>
            @endif
        </div>

        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header"><strong>Payload (JSON)</strong></div>
                <div class="card-body p-0">
                    <pre class="m-0 p-3 bg-light rounded-bottom" style="font-size:11px;max-height:400px;overflow:auto">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
