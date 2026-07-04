@extends('layouts.panel')
@section('title', 'Stáří pohledávek (Aging)')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3>Stáří pohledávek</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item active">Aging</li>
                </ol>
            </div>
            <div class="col-sm-6 text-end">
                <form method="GET" action="{{ route('admin.financial-report.revenue-csv') }}" class="d-inline-flex gap-2 align-items-center">
                    <input type="date" name="from" class="form-control form-control-sm"
                           value="{{ now()->startOfYear()->toDateString() }}">
                    <input type="date" name="to" class="form-control form-control-sm"
                           value="{{ now()->toDateString() }}">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="download" class="me-1"></i>Export CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <x-panel.flash />

    @php
    $bucketLabels = [
        'current' => ['label' => 'Aktuální (nesplatné)', 'color' => 'success'],
        'd1_30'   => ['label' => '1–30 dní po splatnosti', 'color' => 'warning'],
        'd31_60'  => ['label' => '31–60 dní po splatnosti', 'color' => 'danger'],
        'd61_90'  => ['label' => '61–90 dní po splatnosti', 'color' => 'danger'],
        'd90plus' => ['label' => '90+ dní po splatnosti', 'color' => 'dark'],
    ];
    @endphp

    {{-- Summary cards --}}
    <div class="row">
        @foreach ($bucketLabels as $key => $meta)
        <div class="col-sm-6 col-xl">
            <div class="card">
                <div class="card-body text-center py-3">
                    <span class="badge bg-{{ $meta['color'] }} mb-1">{{ count($buckets[$key]) }}</span>
                    <p class="mb-0 small text-muted">{{ $meta['label'] }}</p>
                    <p class="mb-0 fw-semibold">{{ number_format($totals[$key] / 100, 0, ',', ' ') }} Kč</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Tables per bucket --}}
    @foreach ($bucketLabels as $key => $meta)
    @if (count($buckets[$key]) > 0)
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between">
            <h5><span class="badge bg-{{ $meta['color'] }} me-2">{{ count($buckets[$key]) }}</span>{{ $meta['label'] }}</h5>
            <strong>{{ number_format($totals[$key] / 100, 2, ',', ' ') }} Kč</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead><tr>
                        <th>Číslo</th><th>Zákazník</th><th>Splatnost</th><th>Celkem</th><th>Měna</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($buckets[$key] as $invoice)
                    <tr>
                        <td><code>{{ $invoice->number }}</code></td>
                        <td>{{ $invoice->customer?->company_name ?: $invoice->snapshot_name }}</td>
                        <td>{{ $invoice->due_date?->format('d.m.Y') }}</td>
                        <td class="text-end">{{ number_format($invoice->total->getAmount()->toFloat(), 2, ',', ' ') }}</td>
                        <td>{{ $invoice->currency->value }}</td>
                        <td>
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-xs btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @endforeach

    @if (array_sum(array_map('count', $buckets)) === 0)
    <div class="alert alert-success mt-3">Žádné nezaplacené faktury.</div>
    @endif
</div>
@endsection
