@extends('layouts.panel')

@section('title', 'Fakturační výkaz')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Stáhnout fakturační výkaz</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Vyberte měsíc a rok, pro který chcete vygenerovat PDF přehled vašich faktur.</p>
            <form method="GET" action="#" id="statement-form">
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Rok</label>
                        <select name="year" class="form-select" id="year-select">
                            @for($y = now()->year; $y >= now()->year - 3; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Měsíc</label>
                        <select name="month" class="form-select" id="month-select">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected($m === now()->month)>{{ str_pad((string)$m, 2, '0', STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-4 flex items-end">
                        <button type="submit" class="btn btn-primary" id="dl-btn">
                            <i class="bi bi-file-pdf me-1"></i> Stáhnout PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="{{ $cspNonce ?? '' }}">
    document.getElementById('statement-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var year  = document.getElementById('year-select').value;
        var month = document.getElementById('month-select').value;
        window.location.href = '/panel/fakturace/vykaz/' + year + '/' + month;
    });
</script>
@endsection
