@extends('layouts.panel')

@section('title', 'Protokol aplikace DPH')

@section('content')
<x-panel.flash />

<x-panel.card title="Filtr">
    <form method="GET" action="{{ route('admin.tax-rate-applications.index') }}" class="row g-2 align-items-end">
        <div class="col-auto">
            <label for="invoice_id" class="form-label">Faktura ID</label>
            <input type="text" id="invoice_id" name="invoice_id" class="form-control" value="{{ request('invoice_id') }}" placeholder="ID faktury">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
            <a href="{{ route('admin.tax-rate-applications.index') }}" class="btn btn-secondary">Zrušit</a>
        </div>
    </form>
</x-panel.card>

<x-panel.card title="Protokol aplikace DPH">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Faktura ID</th>
                    <th>Zákazník</th>
                    <th>Sazba DPH</th>
                    <th>Výše DPH</th>
                    <th>Měna</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>{{ $application->invoice_id }}</td>
                        <td>
                            @if ($application->customer)
                                {{ $application->customer->name ?? $application->customer->id }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $application->rate_applied, 2) }} %</td>
                        <td>{{ number_format($application->tax_amount / 100, 2) }} {{ $application->currency }}</td>
                        <td>{{ $application->currency }}</td>
                        <td>{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Žádné záznamy nenalezeny.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($applications->hasPages())
        <div class="mt-3">
            {{ $applications->withQueryString()->links() }}
        </div>
    @endif
</x-panel.card>
@endsection
