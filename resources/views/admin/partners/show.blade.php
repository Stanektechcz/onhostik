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
    <div class="flex items-center gap-2 mb-3 flex-wrap">
        <x-panel.status-badge :status="$partner->status" />
        <a href="{{ route('admin.partners.edit', $partner) }}" class="btn btn-outline-primary btn-sm">
            <i data-feather="edit-2" style="width:13px;height:13px;"></i> Upravit
        </a>
        {{-- Quick status toggle --}}
        @if($partner->status->value !== 'active')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="inline">
                @csrf <input type="hidden" name="status" value="active">
                <button type="submit" class="btn btn-outline-success btn-sm">Aktivovat</button>
            </form>
        @endif
        @if($partner->status->value === 'active')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="inline">
                @csrf <input type="hidden" name="status" value="paused">
                <button type="submit" class="btn btn-outline-warning btn-sm">Pozastavit</button>
            </form>
        @endif
        @if($partner->status->value !== 'banned')
            <form method="POST" action="{{ route('admin.partners.status', $partner) }}" class="inline"
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
                    @php
                        $unpaidApproved = $partner->commissions()
                            ->where('status', \App\Domains\Partner\Enums\CommissionStatus::Approved->value)
                            ->whereNull('partner_payout_id')
                            ->get();
                    @endphp

                    @if($unpaidApproved->isEmpty())
                        <div class="alert alert-light-secondary f-12 mb-0">
                            <i data-feather="info" style="width:12px;height:12px;" class="me-1"></i>
                            Žádné schválené nevyplacené provize. Nejprve schvalte provize.
                        </div>
                    @else
                    <form method="POST" action="{{ route('admin.partners.payouts.create', $partner) }}" id="payout-form">
                        @csrf

                        {{-- Commission checkboxes --}}
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Zahrnout provize do výplaty</label>
                            @php $payoutTotal = 0; @endphp
                            @foreach($unpaidApproved as $c)
                                @php $payoutTotal += $c->amount; @endphp
                                <div class="form-check mb-1">
                                    <input class="form-check-input payout-commission-cb" type="checkbox"
                                           name="commission_ids[]" value="{{ $c->id }}"
                                           id="com_{{ $c->id }}" checked
                                           onchange="updatePayoutTotal()">
                                    <label class="form-check-label f-12" for="com_{{ $c->id }}">
                                        #{{ $c->id }}
                                        @if($c->invoice) — {{ $c->invoice->number ?? '' }} @endif
                                        — <strong>{{ $c->formattedAmount() }}</strong>
                                        <span class="f-light">({{ $c->eligible_at?->format('d.m.Y') ?? 'eligible' }})</span>
                                    </label>
                                </div>
                            @endforeach
                            <p class="f-12 mt-2 mb-0 f-w-600">
                                Celkem k výplatě: <span id="payout-total">{{ number_format($payoutTotal / 100, 0, ',', ' ') }} Kč</span>
                            </p>
                        </div>
                        <input type="hidden" id="commission-amounts"
                               data-amounts="{{ $unpaidApproved->mapWithKeys(fn($c) => [$c->id => $c->amount])->toJson() }}">

                        <div class="mb-2">
                            <label class="form-label f-12 f-light">Metoda</label>
                            <input type="text" name="method" class="form-control"
                                   value="{{ $partner->payout_method }}" placeholder="Bankovní převod">
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Poznámka</label>
                            <input type="text" name="note" class="form-control" placeholder="Poznámka pro auditní log">
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">Vytvořit výplatu</button>
                    </form>
                    @endif
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
                                        <form method="POST" action="{{ route('admin.partners.commissions.approve', [$partner, $commission]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-xs">Schválit</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.partners.commissions.reject', [$partner, $commission]) }}" class="inline ms-1"
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
                                    @if($payout->status->value === 'paid')
                                        <span class="badge badge-light-success">Zaplaceno</span>
                                    @elseif($payout->status->value !== 'cancelled')
                                        <form method="POST" action="{{ route('admin.partners.payouts.paid', [$partner, $payout]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-xs">Zaplaceno</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.partners.payouts.cancel', [$partner, $payout]) }}" class="inline ms-1"
                                              onsubmit="return confirm('Zrušit výplatu?')">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-xs">Zrušit</button>
                                        </form>
                                    @else
                                        <span class="badge badge-light-danger">Zrušeno</span>
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
                    <x-panel.data-table :headers="['Zákazník/e-mail', 'Stav', 'UTM zdroj', 'První návštěva', 'Registrace', 'Konverze', '']">
                        @foreach($referrals as $ref)
                            @php $hasUtm = $ref->utm_source || $ref->utm_medium || $ref->utm_campaign; @endphp
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
                                <td class="f-12">
                                    @if($ref->utm_source)
                                        <span class="badge badge-light-primary">{{ $ref->utm_source }}</span>
                                        @if($ref->utm_medium)
                                            <span class="badge badge-light-secondary ms-1">{{ $ref->utm_medium }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="f-12">{{ $ref->first_seen_at?->format('d.m.Y') }}</td>
                                <td class="f-12">{{ $ref->registered_at?->format('d.m.Y') ?? '—' }}</td>
                                <td class="f-12">{{ $ref->converted_at?->format('d.m.Y') ?? '—' }}</td>
                                <td>
                                    @if($hasUtm || $ref->utm_campaign || $ref->source_url || $ref->landing_url)
                                        <button class="btn btn-light btn-xs py-0 px-1" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#ref-detail-{{ $ref->id }}">
                                            <i data-feather="chevron-down" style="width:12px;height:12px;"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if($hasUtm || $ref->utm_campaign || $ref->source_url || $ref->landing_url)
                            <tr class="collapse" id="ref-detail-{{ $ref->id }}">
                                <td colspan="7" class="bg-light py-2 px-3">
                                    <div class="flex flex-wrap gap-3 f-12">
                                        @if($ref->utm_source)
                                            <div><span class="f-light">utm_source:</span> <code>{{ $ref->utm_source }}</code></div>
                                        @endif
                                        @if($ref->utm_medium)
                                            <div><span class="f-light">utm_medium:</span> <code>{{ $ref->utm_medium }}</code></div>
                                        @endif
                                        @if($ref->utm_campaign)
                                            <div><span class="f-light">utm_campaign:</span> <code>{{ $ref->utm_campaign }}</code></div>
                                        @endif
                                        @if($ref->utm_term)
                                            <div><span class="f-light">utm_term:</span> <code>{{ $ref->utm_term }}</code></div>
                                        @endif
                                        @if($ref->utm_content)
                                            <div><span class="f-light">utm_content:</span> <code>{{ $ref->utm_content }}</code></div>
                                        @endif
                                        @if($ref->source_url)
                                            <div><span class="f-light">Zdroj:</span> <span class="truncate" style="max-width:300px;display:inline-block;vertical-align:bottom;" title="{{ $ref->source_url }}">{{ $ref->source_url }}</span></div>
                                        @endif
                                        @if($ref->landing_url)
                                            <div><span class="f-light">Landing:</span> <span class="truncate" style="max-width:300px;display:inline-block;vertical-align:bottom;" title="{{ $ref->landing_url }}">{{ $ref->landing_url }}</span></div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </x-panel.data-table>
                    {{ $referrals->withQueryString()->links() }}
                @endif
            </x-panel.card>

        </div>
    </div>
</div>

<script>
function updatePayoutTotal() {
    const el = document.getElementById('commission-amounts');
    if (!el) return;
    const amounts = JSON.parse(el.dataset.amounts || '{}');
    let total = 0;
    document.querySelectorAll('.payout-commission-cb:checked').forEach(cb => {
        total += (amounts[cb.value] || 0);
    });
    const display = document.getElementById('payout-total');
    if (display) {
        display.textContent = new Intl.NumberFormat('cs-CZ').format(Math.round(total / 100)) + ' Kč';
    }
}
</script>
@endsection
