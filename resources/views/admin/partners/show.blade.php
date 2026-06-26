@extends('layouts.panel')

@php
    $breadcrumbTitle = $partner->user?->name ?? 'Partner';
    $breadcrumbItems = ['Partneři' => route('admin.partners.index'), $breadcrumbTitle => ''];
@endphp

@section('title', 'Partner: ' . $breadcrumbTitle)

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    @if($errors->any())
        <div class="alert alert-light-danger mb-3">
            @foreach($errors->all() as $err)<p class="mb-0 f-12">{{ $err }}</p>@endforeach
        </div>
    @endif

    {{-- Action bar --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <x-panel.status-badge :status="$partner->status" />
        <a href="{{ route('admin.partners.edit', $partner) }}" class="btn btn-outline-primary btn-sm">
            <i data-feather="edit-2" style="width:13px;height:13px;"></i> Upravit
        </a>
        {{-- Quick status toggle --}}
        @if($partner->status->value !== 'active')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="d-inline">
                @csrf <input type="hidden" name="status" value="active">
                <button type="submit" class="btn btn-outline-success btn-sm">Aktivovat</button>
            </form>
        @endif
        @if($partner->status->value === 'active')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="d-inline">
                @csrf <input type="hidden" name="status" value="paused">
                <button type="submit" class="btn btn-outline-warning btn-sm">Pozastavit</button>
            </form>
        @endif
        @if($partner->status->value !== 'banned')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="d-inline"
                  onsubmit="return confirm('Opravdu zablokovat partnera?')">
                @csrf <input type="hidden" name="status" value="banned">
                <button type="submit" class="btn btn-outline-danger btn-sm">Zablokovat</button>
            </form>
        @endif
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-6 sm:col-span-12 lg:col-span-3">
            <x-panel.stat-widget label="Referraly" :value="$counts['referrals']" icon="users" color="primary" />
        </div>
        <div class="col-span-6 sm:col-span-12 lg:col-span-3">
            <x-panel.stat-widget label="Zákazníci" :value="$counts['customers']" icon="check-circle" color="success" />
        </div>
        <div class="col-span-6 sm:col-span-12 lg:col-span-3">
            <x-panel.stat-widget
                label="Čekající provize"
                :value="number_format($counts['commissionPending'] / 100, 0, ',', ' ') . ' Kč'"
                icon="clock" color="warning" />
        </div>
        <div class="col-span-6 sm:col-span-12 lg:col-span-3">
            <x-panel.stat-widget
                label="Vyplaceno"
                :value="number_format($counts['commissionPaid'] / 100, 0, ',', ' ') . ' Kč'"
                icon="dollar-sign" color="info" />
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap">

        {{-- Partner info --}}
        <div class="col-span-12 xl:col-span-4">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Profil partnera</h5></div>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><td class="f-light f-12 ps-0" style="width:45%">Jméno</td><td class="f-w-500">{{ $partner->user?->name }}</td></tr>
                            <tr><td class="f-light f-12 ps-0">E-mail</td><td>{{ $partner->user?->email }}</td></tr>
                            <tr><td class="f-light f-12 ps-0">Referral kód</td><td><code class="badge badge-light-primary">{{ $partner->referral_code }}</code></td></tr>
                            <tr><td class="f-light f-12 ps-0">Stav</td><td><x-panel.status-badge :status="$partner->status" /></td></tr>
                            <tr><td class="f-light f-12 ps-0">Sazba provize</td><td class="f-w-600">{{ $partner->commission_rate_percent }}%</td></tr>
                            <tr><td class="f-light f-12 ps-0">Schváleno (nevyplaceno)</td>
                                <td class="f-w-600 txt-success">{{ number_format($approvedUnpaidMinor / 100, 0, ',', ' ') }} Kč</td></tr>
                            <tr><td class="f-light f-12 ps-0">Metoda výplaty</td><td>{{ $partner->payout_method ?? '—' }}</td></tr>
                            <tr><td class="f-light f-12 ps-0">Výplatní údaje</td>
                                <td>{{ $partner->payout_details_encrypted ? '●●●●●● (šifrováno)' : '—' }}</td></tr>
                            <tr><td class="f-light f-12 ps-0">Registrace</td><td class="f-12">{{ $partner->created_at?->format('d.m.Y') }}</td></tr>
                        </tbody>
                    </table>

                    {{-- Referral link --}}
                    <div class="mt-3">
                        <label class="form-label f-12 f-light mb-1">Referral odkaz</label>
                        <div class="input-group">
                            <input type="text" id="admin-ref-url" class="form-control f-12"
                                   value="{{ url('/') }}?ref={{ $partner->referral_code }}" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('admin-ref-url').value).then(()=>{this.textContent='✓'})">
                                <i data-feather="copy" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Create payout --}}
            <div class="card mt-3">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>Vytvořit výplatu</h5>
                        <div class="card-header-right-icon"><span class="badge badge-light-warning">MANUAL</span></div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="f-light f-12 mb-3">Výplata probíhá manuálně přes bankovní převod mimo systém.</p>
                    <form method="POST" action="{{ route('admin.partners.payouts.create', $partner) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12 f-light">Částka (Kč, celé číslo)</label>
                            <input type="number" name="amount" class="form-control" min="1" placeholder="1000" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12 f-light">Metoda</label>
                            <input type="text" name="method" class="form-control" placeholder="Bankovní převod">
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Poznámka</label>
                            <input type="text" name="note" class="form-control" placeholder="Poznámka pro partnera">
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">Vytvořit výplatu</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 xl:col-span-8">

            {{-- Commissions --}}
            <x-panel.card title="Provize">
                @if($commissions->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="trending-up" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">Žádné provize</p>
                    </div>
                @else
                    <x-panel.data-table :headers="['#', 'Faktura', 'Částka', 'Stav', 'Eligible od', 'Akce']">
                        @foreach($commissions as $commission)
                            <tr>
                                <td class="f-light f-12">#{{ $commission->id }}</td>
                                <td>
                                    @if($commission->invoice)
                                        <a href="{{ route('admin.invoices.show', $commission->invoice) }}" class="f-12">
                                            {{ $commission->invoice->number }}
                                        </a>
                                    @else
                                        <span class="f-light">—</span>
                                    @endif
                                </td>
                                <td class="f-w-600">{{ $commission->formattedAmount() }}</td>
                                <td><x-panel.status-badge :status="$commission->status" /></td>
                                <td class="f-12">{{ $commission->eligible_at?->format('d.m.Y') ?? '—' }}</td>
                                <td>
                                    @if($commission->status->value === 'pending')
                                        <form method="POST" action="{{ route('admin.partners.commissions.approve', [$partner, $commission]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-xs">Schválit</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.partners.commissions.reject', [$partner, $commission]) }}" class="d-inline ms-1"
                                              onsubmit="return confirm('Opravdu zamítnout?')">
                                            @csrf
                                            <input type="hidden" name="reason" value="Admin zamítl">
                                            <button type="submit" class="btn btn-outline-danger btn-xs">Zamítnout</button>
                                        </form>
                                    @else
                                        <span class="f-light f-12">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>
                    {{ $commissions->withQueryString()->links() }}
                @endif
            </x-panel.card>

            {{-- Payouts --}}
            <x-panel.card title="Výplaty" class="mt-3">
                @if($payouts->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="dollar-sign" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">Žádné výplaty</p>
                    </div>
                @else
                    <x-panel.data-table :headers="['#', 'Částka', 'Metoda', 'Stav', 'Vytvořeno', 'Akce']">
                        @foreach($payouts as $payout)
                            <tr>
                                <td class="f-light f-12">#{{ $payout->id }}</td>
                                <td class="f-w-600">{{ $payout->formattedAmount() }}</td>
                                <td class="f-12">{{ $payout->method ?? '—' }}</td>
                                <td><x-panel.status-badge :status="$payout->status" /></td>
                                <td class="f-12">{{ $payout->requested_at?->format('d.m.Y') }}</td>
                                <td>
                                    @if($payout->status->value !== 'paid')
                                        <form method="POST" action="{{ route('admin.partners.payouts.paid', [$partner, $payout]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-xs">Označit zaplaceno</button>
                                        </form>
                                    @else
                                        <span class="badge badge-light-success">Zaplaceno</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>
                    {{ $payouts->withQueryString()->links() }}
                @endif
            </x-panel.card>

            {{-- Referrals --}}
            <x-panel.card title="Referraly" class="mt-3">
                @if($referrals->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="users" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">Žádní referraly</p>
                    </div>
                @else
                    <x-panel.data-table :headers="['Zákazník/e-mail', 'Stav', 'První návštěva', 'Registrace', 'Konverze']">
                        @foreach($referrals as $ref)
                            <tr>
                                <td>
                                    @if($ref->referredUser)
                                        <span class="f-w-500">{{ $ref->referredUser->name }}</span>
                                        <p class="f-light f-12 mb-0">{{ $ref->referredUser->email }}</p>
                                    @else
                                        <span class="f-light">Anonymní návštěvník</span>
                                    @endif
                                </td>
                                <td><x-panel.status-badge :status="$ref->status" /></td>
                                <td class="f-12">{{ $ref->first_seen_at?->format('d.m.Y') }}</td>
                                <td class="f-12">{{ $ref->registered_at?->format('d.m.Y') ?? '—' }}</td>
                                <td class="f-12">{{ $ref->converted_at?->format('d.m.Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>
                    {{ $referrals->withQueryString()->links() }}
                @endif
            </x-panel.card>

        </div>
    </div>
</div>
@endsection
