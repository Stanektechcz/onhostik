@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Vypršení kreditů';
    $breadcrumbItems = ['Billing' => '#', 'Vypršení kreditů' => ''];
@endphp

@section('title', 'Vypršení kreditů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter tabs --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="f-12 f-light me-2">Vyprší v příštích:</span>
                @foreach([7, 30, 90] as $d)
                <a href="{{ route('admin.expiring-credits.index', ['days' => $d]) }}"
                   class="btn btn-sm {{ $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $d }} dní <span class="badge bg-white text-dark ms-1">{{ $counts[$d] }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-no-border">
            <div class="d-flex justify-content-between align-items-center">
                <h5>Kredity expirující v příštích {{ $days }} dnech ({{ $transactions->total() }})</h5>
                <div class="d-flex gap-2">
                    <span class="badge badge-light-warning f-12">30d reminder</span>
                    <span class="badge badge-light-danger f-12">7d reminder</span>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            @if($transactions->isEmpty())
                <p class="text-center f-light py-4">Žádné kredity nevyprší v daném období.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Zákazník</th>
                            <th>Transakce</th>
                            <th class="text-end">Částka</th>
                            <th>Datum vkladu</th>
                            <th>Vyprší</th>
                            <th>Za dní</th>
                            <th>Remindery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                        @php
                            $daysLeft  = (int) now()->diffInDays($tx->expires_at, false);
                            $sent30    = $tx->expiryReminders->where('days_before', 30)->first();
                            $sent7     = $tx->expiryReminders->where('days_before', 7)->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="f-w-500">{{ $tx->customer?->company_name ?? '—' }}</div>
                                <div class="f-11 f-light">{{ $tx->customer?->user?->email ?? '' }}</div>
                            </td>
                            <td class="f-12 f-light">#{{ $tx->id }}</td>
                            <td class="text-end">
                                <span class="f-w-500 text-success">
                                    {{ \App\Domains\Shared\Support\MoneyFormatter::format($tx->amount) }}
                                </span>
                            </td>
                            <td class="f-12 text-nowrap">{{ $tx->created_at->format('d.m.Y') }}</td>
                            <td class="f-12 text-nowrap">{{ $tx->expires_at?->format('d.m.Y') }}</td>
                            <td>
                                <span class="badge badge-light-{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 30 ? 'warning' : 'info') }} f-10">
                                    {{ $daysLeft }} d
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <span class="badge {{ $sent30 ? 'badge-light-success' : 'badge-light-secondary' }} f-10"
                                          title="{{ $sent30 ? 'Odesláno ' . $sent30->sent_at?->format('d.m.Y') : 'Neodesláno' }}">
                                        30d
                                    </span>
                                    <span class="badge {{ $sent7 ? 'badge-light-success' : 'badge-light-secondary' }} f-10"
                                          title="{{ $sent7 ? 'Odesláno ' . $sent7->sent_at?->format('d.m.Y') : 'Neodesláno' }}">
                                        7d
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
