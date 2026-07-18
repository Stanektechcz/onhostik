@extends('layouts.panel')

@section('title', 'Sazby daní')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Sazby daní">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kód země</th>
                                <th>Název</th>
                                <th>Typ</th>
                                <th class="text-right">Sazba (%)</th>
                                <th>Platnost od</th>
                                <th>Aktivní</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rates as $rate)
                            <tr>
                                <td><strong>{{ $rate->country_code }}</strong></td>
                                <td>{{ $rate->name }}</td>
                                <td><span class="badge bg-secondary">{{ $rate->type }}</span></td>
                                <td class="text-right">{{ $rate->rate_percent }}%</td>
                                <td>{{ $rate->effective_from?->format('d.m.Y') ?? '—' }}</td>
                                <td>
                                    @if($rate->is_active)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.tax-rates.destroy', $rate) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Žádné sazby.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $rates->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat sazbu">
                <form method="POST" action="{{ route('admin.tax-rates.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Kód země (ISO 3166-1 alpha-2)</label>
                        <input type="text" name="country_code" class="form-control" maxlength="2" placeholder="CZ" value="{{ old('country_code') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Název</label>
                        <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Typ</label>
                        <input type="text" name="type" class="form-control" maxlength="50" value="{{ old('type', 'standard') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Sazba (%)</label>
                        <input type="number" name="rate_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('rate_percent') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Platnost od</label>
                        <input type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="tax_is_active" value="1" @checked(old('is_active', true))>
                        <label class="form-check-label" for="tax_is_active">Aktivní</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
