@extends('layouts.panel')

@section('title', 'Příjmy podle resellera')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <form method="GET" class="flex gap-2 items-center">
            <label class="mb-0">Od</label>
            <input type="date" name="from" class="form-control form-control-sm" style="max-width:160px" value="{{ $from }}">
            <label class="mb-0">Do</label>
            <input type="date" name="to" class="form-control form-control-sm" style="max-width:160px" value="{{ $to }}">
            <button class="btn btn-sm btn-primary">Zobrazit</button>
        </form>
    </div>

    <x-panel.card title="Příjmy podle resellera ({{ $from }} – {{ $to }})">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reseller</th>
                        <th>E-mail</th>
                        <th class="text-right">Zákazníků</th>
                        <th class="text-right">Příjmy (Kč)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $total = 0; @endphp
                    @forelse($revenueByReseller as $i => $row)
                    @php $total += $row['revenue']; @endphp
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>{{ $row['reseller']->user?->name ?? '—' }}</td>
                        <td>{{ $row['reseller']->user?->email ?? '—' }}</td>
                        <td class="text-right">{{ $row['reseller']->customers_count }}</td>
                        <td class="text-right">
                            <strong>{{ number_format($row['revenue'] / 100, 2, ',', ' ') }}</strong>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Žádní reselleři.</td></tr>
                    @endforelse
                </tbody>
                @if(count($revenueByReseller) > 0)
                <tfoot>
                    <tr class="font-bold">
                        <td colspan="4">Celkem</td>
                        <td class="text-right">{{ number_format($total / 100, 2, ',', ' ') }} Kč</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </x-panel.card>
</div>
@endsection
