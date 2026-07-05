@extends('layouts.panel')

@section('title', 'Nový finanční export')

@section('content')
<div class="container-fluid py-4" style="max-width:600px">

    <h1 class="h4 mb-4">Nový finanční export</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.store') }}">
                @csrf

                @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Formát *</label>
                    <select name="format" class="form-select @error('format') is-invalid @enderror" required>
                        <option value="">— Vyberte formát —</option>
                        <option value="pohoda_xml"   @selected(old('format') === 'pohoda_xml')>POHODA XML (účetnictví)</option>
                        <option value="csv_invoices" @selected(old('format') === 'csv_invoices')>CSV – Faktury</option>
                        <option value="csv_payments" @selected(old('format') === 'csv_payments')>CSV – Platby</option>
                        <option value="pdf_summary"  @selected(old('format') === 'pdf_summary')>PDF – Přehled</option>
                    </select>
                    @error('format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        POHODA XML slouží pro import do účetního softwaru POHODA.<br>
                        CSV formáty lze otevřít v Excelu nebo Google Sheets.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Datum od</label>
                        <input type="date" name="date_from" value="{{ old('date_from') }}"
                               class="form-control @error('date_from') is-invalid @enderror">
                        @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Datum do</label>
                        <input type="date" name="date_to" value="{{ old('date_to') }}"
                               class="form-control @error('date_to') is-invalid @enderror">
                        @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-4" id="status-filter">
                    <label class="form-label">Filtr stavu faktury</label>
                    <select name="status" class="form-select">
                        <option value="">— Všechny stavy —</option>
                        <option value="paid"      @selected(old('status') === 'paid')>Zaplacené</option>
                        <option value="sent"      @selected(old('status') === 'sent')>Odeslané (neuhrazené)</option>
                        <option value="overdue"   @selected(old('status') === 'overdue')>Po splatnosti</option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>Stornované</option>
                    </select>
                    <div class="form-text">Platí pouze pro formáty CSV – Faktury a POHODA XML.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Generovat export</button>
                    <a href="{{ route('admin.exports.index') }}" class="btn btn-outline-secondary btn-sm">Zpět</a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
