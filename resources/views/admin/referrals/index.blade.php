@extends('layouts.panel')

@section('title', 'Referral program')

@section('content')
<div class="container-fluid py-4">

    <h1 class="h4 mb-4">Referral program zákazníků</h1>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h3 mb-1">{{ number_format($stats['total']) }}</div>
                    <div class="text-muted small">Celkem referralů</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h3 mb-1 text-warning">{{ number_format($stats['pending']) }}</div>
                    <div class="text-muted small">Čeká na kvalifikaci</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="h3 mb-1 text-success">{{ number_format($stats['rewarded']) }}</div>
                    <div class="text-muted small">Odměněno</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.referrals.index') }}">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">— Všechny stavy —</option>
                @foreach(['pending' => 'Čeká', 'qualified' => 'Kvalifikováno', 'rewarded' => 'Odměněno', 'expired' => 'Vypršelo'] as $v => $l)
                    <option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Doporučitel</th>
                        <th>Nový zákazník</th>
                        <th>Stav</th>
                        <th>Odměna doporučitele</th>
                        <th>Bonus nováčka</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($referrals as $r)
                    <tr>
                        <td class="small">{{ $r->referrer?->user->name ?? '—' }}</td>
                        <td class="small">{{ $r->referee?->user->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $r->statusBadgeClass() }}">{{ $r->statusLabel() }}</span>
                        </td>
                        <td class="small">{{ number_format($r->referrer_reward_haler / 100, 0, ',', ' ') }} Kč</td>
                        <td class="small">{{ number_format($r->referee_reward_haler / 100, 0, ',', ' ') }} Kč</td>
                        <td class="small text-muted">{{ $r->created_at->format('d.m.Y') }}</td>
                        <td class="text-end text-nowrap">
                            @if($r->status === 'pending')
                                <form action="{{ route('admin.referrals.qualify', $r) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-info">Kvalifikovat</button>
                                </form>
                            @endif
                            @if($r->status === 'qualified')
                                <form action="{{ route('admin.referrals.reward', $r) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-success">Odměnit</button>
                                </form>
                            @endif
                            @if(in_array($r->status, ['pending', 'qualified']))
                                <form action="{{ route('admin.referrals.expire', $r) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-secondary">Expirovat</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádné referraly.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $referrals->links() }}</div>

</div>
@endsection
