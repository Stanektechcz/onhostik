@extends('layouts.panel')

@section('title', 'Referral statistiky')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($profile === null)
    <div class="alert alert-info">
        Nemáte aktivní partnerský profil. Pro zapojení do referral programu kontaktujte podporu.
    </div>
    @else
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="mb-1">{{ $referrals->total() }}</h4>
                    <p class="text-muted mb-0 small">Celkem referralů</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="mb-1">{{ number_format($totalEarned, 2, ',', ' ') }} Kč</h4>
                    <p class="text-muted mb-0 small">Vyplacené provize</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="mb-1">{{ number_format($pendingPayout, 2, ',', ' ') }} Kč</h4>
                    <p class="text-muted mb-0 small">Čeká na vyplacení</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <p class="text-muted mb-1 small">Referral kód</p>
                    <code class="fs-5">{{ $profile->referral_code ?? '—' }}</code>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Přivedení zákazníci">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Zákazník</th>
                        <th>Stav</th>
                        <th>Registrován</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($referrals as $ref)
                    <tr>
                        <td>{{ $ref->id }}</td>
                        <td>{{ $ref->referredUser?->email ?? $ref->referredCustomer?->company_name ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $ref->status->value ?? $ref->status }}</span></td>
                        <td>{{ $ref->created_at->format('d.m.Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Žádné referraly.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $referrals->links() }}</div>
    </x-panel.card>
    @endif
</div>
@endsection
