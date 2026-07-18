@extends('layouts.panel')
@section('title', 'Fraud reviews')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Čekající fraud reviews (seřazeno dle skóre)">
        @if($pending->isEmpty())
            <p class="text-muted">Žádné čekající reviews.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Objednávka</th>
                        <th class="text-right">Skóre</th>
                        <th>Signály</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $r)
                    <tr>
                        <td>{{ $r->customer?->company_name ?? '—' }}</td>
                        <td>{{ $r->order_id ?? '—' }}</td>
                        <td class="text-right font-bold {{ $r->score >= 80 ? 'text-danger' : ($r->score >= 50 ? 'text-warning' : 'text-muted') }}">
                            {{ $r->score }}
                        </td>
                        <td class="small truncate" style="max-width:220px">
                            {{ implode(', ', (array)($r->signals ?? [])) }}
                        </td>
                        <td class="small text-muted">{{ $r->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.fraud-reviews.update', $r) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cleared">
                                    <button class="btn btn-sm btn-success">Vyčistit</button>
                                </form>
                                <form method="POST" action="{{ route('admin.fraud-reviews.update', $r) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="blocked">
                                    <button class="btn btn-sm btn-danger">Blokovat</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $pending->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
