@extends('layouts.panel')

@section('title', 'Analytika voucherů')

@section('content')
<x-panel.flash />

<x-panel.card title="Analytika voucherů">
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Celkem voucherů</h5>
                    <p class="display-6">{{ $totalVouchers }}</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Aktivních</h5>
                    <p class="display-6 text-success">{{ $activeVouchers }}</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card text-center border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Celkem použití</h5>
                    <p class="display-6 text-primary">{{ $totalUsage }}</p>
                </div>
            </div>
        </div>
    </div>

    <h5 class="mb-3">Statistiky dle typu</h5>
    @if($typeStats->isEmpty())
        <p class="text-muted">Žádná data.</p>
    @else
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Typ</th>
                        <th>Počet</th>
                        <th>Celkem použití</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($typeStats as $stat)
                        <tr>
                            <td>{{ $stat->type }}</td>
                            <td>{{ $stat->count }}</td>
                            <td>{{ $stat->total_used ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h5 class="mb-3">Nejpoužívanější vouchery</h5>
    @if($topVouchers->isEmpty())
        <p class="text-muted">Žádné vouchery.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kód</th>
                        <th>Typ</th>
                        <th>Hodnota</th>
                        <th>Použito</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topVouchers as $voucher)
                        <tr>
                            <td><code>{{ $voucher->code }}</code></td>
                            <td>{{ $voucher->type }}</td>
                            <td>{{ $voucher->value ?? '—' }}</td>
                            <td>{{ $voucher->used_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
