@extends('layouts.panel')

@section('title', 'Historie přihlášení')

@section('content')
<div class="container py-5">
    <h2 class="mb-4">Historie přihlášení</h2>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>IP adresa</th>
                            <th>Prohlížeč / Zařízení</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logins as $login)
                        <tr>
                            <td><code>{{ $login->ip_address }}</code></td>
                            <td class="text-muted small">{{ Str::limit($login->user_agent, 80) }}</td>
                            <td class="text-muted">{{ \Carbon\Carbon::parse($login->created_at)->format('d.m.Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Žádná historie přihlášení.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
