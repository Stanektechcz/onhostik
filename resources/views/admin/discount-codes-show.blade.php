@extends('layouts.panel')

@php
    $breadcrumbTitle = "Kód {$code->code}";
    $breadcrumbItems = ['Slevové kódy' => route('admin.discount-codes.index'), $code->code => ''];
@endphp

@section('title', "Slevový kód {$code->code}")

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0">{{ $summary['used_count'] }}</h3>
                    <small class="f-light">Celkem použití</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0">{{ $summary['unique_customers'] }}</h3>
                    <small class="f-light">Unikátní zákazníci</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0">{{ number_format($summary['total_saved_haler'] / 100, 0, ',', ' ') }} Kč</h3>
                    <small class="f-light">Celkem ušetřeno</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0 {{ $code->isValid() ? 'text-success' : 'text-danger' }}">
                        {{ $code->isValid() ? 'Aktivní' : 'Neplatný' }}
                    </h3>
                    <small class="f-light">Stav kódu</small>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3">
        <div class="col-span-12 md:col-span-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Detaily kódu</h5></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th class="f-light">Kód</th><td><code>{{ $code->code }}</code></td></tr>
                        <tr><th class="f-light">Typ</th><td>{{ $code->type === 'percent' ? 'Procenta' : 'Fixní' }}</td></tr>
                        <tr><th class="f-light">Hodnota</th><td>{{ $code->formattedValue() }}</td></tr>
                        <tr><th class="f-light">Max. použití</th><td>{{ $code->max_uses ?? '∞' }}</td></tr>
                        <tr><th class="f-light">Max./zákazník</th><td>{{ $code->max_uses_per_customer ?? '∞' }}</td></tr>
                        <tr><th class="f-light">Min. objednávka</th>
                            <td>{{ $code->min_order_haler > 0 ? number_format($code->min_order_haler / 100, 0, ',', ' ') . ' Kč' : '—' }}</td>
                        </tr>
                        <tr><th class="f-light">Platnost do</th><td>{{ $code->expires_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
                        <tr><th class="f-light">Popis</th><td>{{ $code->description ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-span-12 md:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Historie použití <span class="badge bg-secondary ms-2">{{ $usages->count() }}</span></h5>
                </div>
                <div class="card-body pt-0">
                    @if($usages->isEmpty())
                        <p class="text-center f-light py-4">Kód ještě nebyl použit.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Zákazník</th>
                                        <th>Objednávka</th>
                                        <th>Ušetřeno</th>
                                        <th>Datum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($usages as $usage)
                                    <tr>
                                        <td>
                                            @if($usage->customer)
                                                <a href="{{ route('admin.customers.show', $usage->customer) }}" class="f-w-500">
                                                    {{ $usage->customer->company_name ?: $usage->customer->email }}
                                                </a>
                                            @else
                                                <span class="f-light">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($usage->order)
                                                <a href="{{ route('admin.orders.show', $usage->order) }}" class="f-12">
                                                    #{{ strtoupper(substr($usage->order->uuid, 0, 8)) }}
                                                </a>
                                            @else
                                                <span class="f-light">—</span>
                                            @endif
                                        </td>
                                        <td class="f-12">
                                            @if($usage->saved_haler > 0)
                                                {{ number_format($usage->saved_haler / 100, 0, ',', ' ') }} Kč
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="f-12 f-light">{{ $usage->created_at?->format('d.m.Y H:i') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
