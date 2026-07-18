@extends('layouts.panel')

@section('title', 'Hromadná konverze proform')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Otevřené proformy">
        @if($proformas->isEmpty())
            <p class="text-muted">Žádné otevřené proformy.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Číslo</th>
                        <th>Zákazník</th>
                        <th class="text-right">Celkem</th>
                        <th>Splatnost</th>
                        <th>Stav</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($proformas as $inv)
                    <tr>
                        <td class="f-w-500">{{ $inv->number }}</td>
                        <td>{{ $inv->customer?->display_name ?? '—' }}</td>
                        <td class="text-right">{{ number_format($inv->total->getMinorAmount()->toInt() / 100, 2, ',', ' ') }} Kč</td>
                        <td class="{{ $inv->due_date?->isPast() ? 'text-danger' : '' }}">
                            {{ $inv->due_date?->format('d.m.Y') ?? '—' }}
                        </td>
                        <td><span class="badge bg-{{ $inv->status->color() }}">{{ $inv->status->label() }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.proforma-batch.convert', $inv) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-xs btn-outline-primary" onclick="return confirm('Převést na daňový doklad?')">
                                    Převést na DD
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $proformas->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
