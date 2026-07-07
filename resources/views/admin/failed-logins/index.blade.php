@extends('layouts.panel')

@section('title', 'Monitor neúspěšných přihlášení')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <x-panel.card title="Nejčastější IP adresy">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>IP adresa</th><th class="text-end">Pokusů</th></tr></thead>
                        <tbody>
                            @foreach($topIps as $ip)
                            <tr>
                                <td><code>{{ $ip->ip_address }}</code></td>
                                <td class="text-end"><span class="badge bg-danger">{{ $ip->attempts }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
        <div class="col-md-6">
            <x-panel.card title="Nejčastější emaily">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Email</th><th class="text-end">Pokusů</th></tr></thead>
                        <tbody>
                            @foreach($topEmails as $em)
                            <tr>
                                <td>{{ $em->email }}</td>
                                <td class="text-end"><span class="badge bg-warning text-dark">{{ $em->attempts }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
    </div>

    <x-panel.card title="Posledních 100 neúspěšných pokusů">
        @if($recent->isEmpty())
            <p class="text-muted">Žádné záznamy.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead>
                    <tr><th>Email</th><th>IP adresa</th><th>User Agent</th><th>Čas</th></tr>
                </thead>
                <tbody>
                    @foreach($recent as $r)
                    <tr>
                        <td>{{ $r->email }}</td>
                        <td><code>{{ $r->ip_address }}</code></td>
                        <td class="text-muted small" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->user_agent }}</td>
                        <td class="text-muted small">{{ $r->attempted_at?->format('d.m.Y H:i:s') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
