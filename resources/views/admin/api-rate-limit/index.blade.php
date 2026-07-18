@extends('layouts.panel')

@section('title', 'API Rate Limit konfigurace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Konfigurace rate limitů">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Zákazník</th>
                                <th>Scope</th>
                                <th class="text-right">req/min</th>
                                <th class="text-right">req/den</th>
                                <th>Aktivní</th>
                                <th>Poznámka</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($configs as $cfg)
                            <tr>
                                <td>{{ $cfg->customer?->company_name ?? 'Globální' }}</td>
                                <td><code>{{ $cfg->scope }}</code></td>
                                <td class="text-right">{{ number_format($cfg->requests_per_minute) }}</td>
                                <td class="text-right">{{ number_format($cfg->requests_per_day) }}</td>
                                <td>
                                    @if($cfg->is_active)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ Str::limit($cfg->note, 50) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.api-rate-limit.destroy', $cfg) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Žádná konfigurace.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $configs->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat / upravit konfiguraci">
                <form method="POST" action="{{ route('admin.api-rate-limit.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Zákazník ID (prázdné = globální)</label>
                        <input type="number" name="customer_id" class="form-control" min="1" value="{{ old('customer_id') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Scope</label>
                        <input type="text" name="scope" class="form-control" value="{{ old('scope', 'global') }}" required maxlength="50">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Požadavků / minuta</label>
                        <input type="number" name="requests_per_minute" class="form-control" value="{{ old('requests_per_minute', 60) }}" min="1" max="10000" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Požadavků / den</label>
                        <input type="number" name="requests_per_day" class="form-control" value="{{ old('requests_per_day', 10000) }}" min="1" max="1000000" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Poznámka</label>
                        <input type="text" name="note" class="form-control" maxlength="255" value="{{ old('note') }}">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Aktivní</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Uložit</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
