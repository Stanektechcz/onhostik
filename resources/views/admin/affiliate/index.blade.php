@extends('layouts.panel')

@section('title', 'Affiliate komise')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-3 mb-4">
        @foreach(['pending' => ['Čeká', 'warning text-dark'], 'approved' => ['Schváleno', 'info text-dark'], 'paid' => ['Vyplaceno', 'success'], 'cancelled' => ['Zrušeno', 'secondary']] as $st => [$label, $cls])
        <div class="col-span-6 col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <span class="badge bg-{{ $cls }} mb-1">{{ $label }}</span>
                    <div class="font-bold fs-5">{{ number_format(($summary[$st] ?? 0) / 100, 2) }} Kč</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <x-panel.card title="Seznam komisí">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Kód</th>
                        <th>Zdroj</th>
                        <th class="text-right">Částka</th>
                        <th>Stav</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                    <tr>
                        <td>{{ $c->customer?->company_name ?? ('Zákazník #' . $c->customer_id) }}</td>
                        <td><code>{{ $c->affiliate_code }}</code></td>
                        <td class="text-muted">{{ $c->source ?? '—' }}</td>
                        <td class="text-right">{{ number_format($c->commission_haler / 100, 2) }} Kč</td>
                        <td>
                            @php $badges = ['pending'=>'warning text-dark','approved'=>'info text-dark','paid'=>'success','cancelled'=>'secondary'] @endphp
                            <span class="badge bg-{{ $badges[$c->status] ?? 'secondary' }}">{{ $c->status }}</span>
                        </td>
                        <td>
                            @if($c->status === 'pending')
                            <form method="POST" action="{{ route('admin.affiliate-commissions.approve', $c) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-success">Schválit</button>
                            </form>
                            @elseif($c->status === 'approved')
                            <form method="POST" action="{{ route('admin.affiliate-commissions.pay', $c) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-primary">Vyplatit</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">Žádné komise.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $commissions->links() }}</div>
    </x-panel.card>
</div>
@endsection
