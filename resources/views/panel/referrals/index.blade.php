@extends('layouts.panel')

@section('title', 'Referral program')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">Referral program</h2>

    {{-- Your code card --}}
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Váš referral kód</h5>
            <p class="text-muted small mb-3">
                Sdílejte tento kód s přáteli. Jakmile se zaregistrují a provedou první platbu,
                získáte <strong>200 Kč kredit</strong> a váš přátelé dostanou <strong>100 Kč uvítací bonus</strong>.
            </p>
            <div class="flex items-center gap-3">
                <div class="fs-3 font-bold font-monospace text-primary border rounded px-4 py-2 bg-light">{{ $code }}</div>
                <button class="btn btn-outline-secondary btn-sm"
                        onclick="navigator.clipboard.writeText('{{ $code }}').then(()=>alert('Kód zkopírován!'))">
                    Kopírovat
                </button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0">{{ $stats['total'] }}</div>
                    <div class="text-muted small">Celkem doporučení</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-info">{{ $stats['qualified'] }}</div>
                    <div class="text-muted small">Kvalifikovaných</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-success">{{ $stats['rewarded'] }}</div>
                    <div class="text-muted small">Odměněno</div>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h4 mb-0 text-success">{{ number_format($stats['earned_haler'] / 100, 0, ',', ' ') }} Kč</div>
                    <div class="text-muted small">Celkem vydělano</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Referral list --}}
    @if($referrals->isNotEmpty())
    <div class="card">
        <div class="card-header"><strong>Vaše doporučení</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Zákazník</th><th>Datum</th><th>Stav</th><th>Odměna</th></tr>
                </thead>
                <tbody>
                    @foreach($referrals as $r)
                    <tr>
                        <td class="small">{{ $r->referee?->user->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $r->created_at->format('d.m.Y') }}</td>
                        <td><span class="badge {{ $r->statusBadgeClass() }}">{{ $r->statusLabel() }}</span></td>
                        <td class="small">
                            @if($r->status === 'rewarded')
                                <span class="text-success">+{{ number_format($r->referrer_reward_haler / 100, 0, ',', ' ') }} Kč</span>
                            @else
                                <span class="text-muted">{{ number_format($r->referrer_reward_haler / 100, 0, ',', ' ') }} Kč (čeká)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body text-muted text-center py-4">
            Zatím jste nikoho nedoporučili. Sdílejte svůj kód a začněte vydělávat!
        </div>
    </div>
    @endif

</div>
@endsection
