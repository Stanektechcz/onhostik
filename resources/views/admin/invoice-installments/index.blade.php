@extends('layouts.panel')

@section('title', 'Splátkové plány')

@section('content')
<div class="container-fluid">
    <x-panel.card title="Splátkové plány faktur">
        @if($installments->isEmpty())
            <p class="text-muted">Žádné splátkové plány.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Faktura</th>
                        <th>Zákazník</th>
                        <th>Splátka</th>
                        <th class="text-right">Částka</th>
                        <th>Splatnost</th>
                        <th>Stav</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($installments as $inst)
                    <tr>
                        <td class="f-w-500">{{ $inst->invoice?->number ?? '—' }}</td>
                        <td>{{ $inst->customer?->display_name ?? '—' }}</td>
                        <td>{{ $inst->installment_number }} / {{ $inst->total_count }}</td>
                        <td class="text-right">{{ number_format($inst->amount_minor / 100, 2, ',', ' ') }} Kč</td>
                        <td>{{ $inst->due_date->format('d.m.Y') }}</td>
                        <td>
                            @if($inst->status === 'paid')
                                <span class="badge bg-success">Zaplaceno</span>
                            @elseif($inst->status === 'overdue')
                                <span class="badge bg-danger">Po splatnosti</span>
                            @else
                                <span class="badge bg-info">Čeká</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $installments->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
