@extends('layouts.panel')

@section('title', 'Čekající přihlášky — Partneři')

@section('content')
<div class="container-fluid py-4">

    <div class="flex items-center justify-between mb-3">
        <h1 class="h4 mb-0">Čekající přihlášky do partnerského programu</h1>
        <a href="{{ route('admin.partners.index') }}" class="btn btn-sm btn-outline-secondary">Všichni partneři</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    @if($pending->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <p class="mb-0">Žádné čekající přihlášky.</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Uživatel</th>
                            <th>Referral kód</th>
                            <th>Způsob výplaty</th>
                            <th>Přihlášeno</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $partner)
                            <tr>
                                <td>
                                    <div class="font-semibold small">{{ $partner->user?->name ?? '—' }}</div>
                                    <div class="text-muted" style="font-size:11px">{{ $partner->user?->email ?? '' }}</div>
                                </td>
                                <td><code class="small">{{ $partner->referral_code }}</code></td>
                                <td class="small">{{ $partner->payout_method ?? '—' }}</td>
                                <td class="small text-muted">{{ $partner->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <div class="flex gap-1 items-center">
                                        <a href="{{ route('admin.partners.show', $partner) }}"
                                           class="btn btn-xs btn-sm btn-outline-primary">Detail</a>

                                        <form method="POST" action="{{ route('admin.partners.approve', $partner) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-sm btn-success"
                                                    onclick="return confirm('Schválit partnera {{ addslashes($partner->user?->name ?? '') }}?')">
                                                Schválit
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-xs btn-sm btn-outline-danger"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#reject-{{ $partner->id }}">
                                            Zamítnout
                                        </button>
                                    </div>

                                    <div id="reject-{{ $partner->id }}" class="collapse mt-2">
                                        <form method="POST" action="{{ route('admin.partners.reject', $partner) }}">
                                            @csrf
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="reason" class="form-control form-control-sm"
                                                       placeholder="Důvod zamítnutí (nepovinné)">
                                                <button type="submit" class="btn btn-danger btn-sm">Zamítnout</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $pending->links() }}
            </div>
        </div>
    @endif

</div>
@endsection
