@extends('layouts.panel')
@section('title', 'Devizové kurzy')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row">
            <div class="col-sm-6">
                <h3>Devizové kurzy</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item active">Kurzy</li>
                </ol>
            </div>
            <div class="col-sm-6 text-end">
                <a href="{{ route('admin.dac7.export', ['year' => now()->year]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i data-feather="download" class="me-1"></i>DAC7 {{ now()->year }}
                </a>
            </div>
        </div>
    </div>

    <x-panel.flash />

    <div class="row">
        @foreach ([['EUR', '€', 'Euro'], ['USD', '$', 'Americký dolar']] as [$code, $symbol, $label])
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><span class="badge bg-primary me-2">{{ $symbol }}</span>{{ $label }} ({{ $code }}/CZK)</h5>
                </div>
                <div class="card-body">
                    @if ($rates[$code] ?? null)
                    <p class="mb-2 text-muted">
                        Aktuální kurz: <strong>{{ number_format($rates[$code]->rate, 4) }} CZK</strong>
                        (platný od {{ $rates[$code]->valid_from }},
                        zdroj: {{ $rates[$code]->source }})
                    </p>
                    @else
                    <p class="mb-2 text-warning">Kurz nebyl nastaven.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.exchange-rates.update', $code) }}" class="row g-2 align-items-end">
                        @csrf @method('PUT')
                        <div class="col-auto">
                            <label class="form-label small">Nový kurz (CZK)</label>
                            <input type="number" name="rate" step="0.0001" min="0.01" max="9999"
                                   class="form-control form-control-sm @error('rate') is-invalid @enderror"
                                   value="{{ old('rate', $rates[$code]?->rate) }}" required>
                            @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Rate history --}}
    <div class="card mt-3">
        <div class="card-header"><h5>Historie kurzů (posledních 30)</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Měna</th><th>Kurz (CZK)</th><th>Zdroj</th><th>Platný od</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($history as $r)
                    <tr>
                        <td><span class="badge bg-secondary">{{ $r->currency }}</span></td>
                        <td>{{ number_format($r->rate, 4) }}</td>
                        <td>{{ $r->source }}</td>
                        <td>{{ $r->valid_from }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">Žádné záznamy.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
