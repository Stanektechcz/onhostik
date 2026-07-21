@extends('layouts.panel')
@section('title', 'Upozornění na využití zdrojů')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Upozornění na využití zdrojů">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Služba ID</th>
                                <th>Metrika</th>
                                <th>Práh (%)</th>
                                <th>Aktivní</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($alerts as $alert)
                            <tr>
                                <td>{{ $alert->service_id }}</td>
                                <td>
                                    @php
                                        $metricLabels = [
                                            'disk'      => 'Disk',
                                            'bandwidth' => 'Přenos dat',
                                            'cpu'       => 'CPU',
                                            'ram'       => 'RAM',
                                        ];
                                    @endphp
                                    {{ $metricLabels[$alert->metric] ?? $alert->metric }}
                                </td>
                                <td>{{ $alert->threshold_percent }} %</td>
                                <td>
                                    @if($alert->is_active)
                                        <span class="badge bg-success">Aktivní</span>
                                    @else
                                        <span class="badge bg-secondary">Neaktivní</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('panel.usage-alert-configs.destroy', $alert) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Opravdu smazat upozornění?">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Žádná upozornění nejsou nastavena.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Nastavit upozornění">
                <form method="POST" action="{{ route('panel.usage-alert-configs.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Služba ID</label>
                        <input type="number" name="service_id" class="form-control @error('service_id') is-invalid @enderror" value="{{ old('service_id') }}" required>
                        @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Metrika</label>
                        <select name="metric" class="form-select @error('metric') is-invalid @enderror" required>
                            <option value="">-- vyberte --</option>
                            @foreach($metrics as $metric)
                                <option value="{{ $metric }}" {{ old('metric') === $metric ? 'selected' : '' }}>
                                    {{ ucfirst($metric) }}
                                </option>
                            @endforeach
                        </select>
                        @error('metric')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Práh upozornění (%)</label>
                        <input type="number" name="threshold_percent" class="form-control @error('threshold_percent') is-invalid @enderror" value="{{ old('threshold_percent', 80) }}" min="1" max="100" required>
                        @error('threshold_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Aktivní</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Uložit upozornění</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
