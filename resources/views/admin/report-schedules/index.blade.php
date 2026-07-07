@extends('layouts.panel')

@section('title', 'Plány reportů')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Plány reportů">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Typ</th>
                            <th>Frekvence</th>
                            <th>Formát</th>
                            <th>Příjemci</th>
                            <th>Aktivní</th>
                            <th>Poslední spuštění</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td>{{ $schedule->name }}</td>
                                <td class="text-muted small">{{ $schedule->report_type }}</td>
                                <td>
                                    @php
                                        $freqColors = ['daily' => 'primary', 'weekly' => 'info', 'monthly' => 'secondary'];
                                        $freqLabels = ['daily' => 'Denně', 'weekly' => 'Týdně', 'monthly' => 'Měsíčně'];
                                    @endphp
                                    <span class="badge bg-{{ $freqColors[$schedule->frequency] ?? 'secondary' }}">
                                        {{ $freqLabels[$schedule->frequency] ?? $schedule->frequency }}
                                    </span>
                                </td>
                                <td><span class="badge bg-dark text-uppercase">{{ $schedule->format }}</span></td>
                                <td>
                                    @php $recipients = is_array($schedule->recipients) ? $schedule->recipients : [] @endphp
                                    {{ count($recipients) }} příjemce/ů
                                </td>
                                <td>
                                    @if ($schedule->is_active)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    {{ $schedule->last_run_at ? $schedule->last_run_at->format('d.m.Y H:i') : '—' }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.report-schedules.destroy', $schedule) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Smazat plán reportu?')">
                                            Smazat
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">Žádné plány reportů.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($schedules->hasPages())
                <div class="mt-3">{{ $schedules->links() }}</div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Přidat plán reportu">
            <form method="POST" action="{{ route('admin.report-schedules.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Název <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" maxlength="100" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Typ reportu <span class="text-danger">*</span></label>
                    <input type="text" name="report_type" class="form-control @error('report_type') is-invalid @enderror"
                        value="{{ old('report_type') }}" maxlength="60" required>
                    @error('report_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Frekvence <span class="text-danger">*</span></label>
                    <select name="frequency" class="form-select @error('frequency') is-invalid @enderror" required>
                        <option value="">— vyberte —</option>
                        <option value="daily" @selected(old('frequency') === 'daily')>Denně</option>
                        <option value="weekly" @selected(old('frequency') === 'weekly')>Týdně</option>
                        <option value="monthly" @selected(old('frequency') === 'monthly')>Měsíčně</option>
                    </select>
                    @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Příjemci (e-maily oddělené čárkou) <span class="text-danger">*</span></label>
                    <textarea name="recipients_raw" class="form-control @error('recipients') is-invalid @enderror"
                        rows="3" placeholder="email@example.com, dalsi@example.com">{{ old('recipients_raw') }}</textarea>
                    @error('recipients') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @error('recipients.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Formát <span class="text-danger">*</span></label>
                    <select name="format" class="form-select @error('format') is-invalid @enderror" required>
                        <option value="">— vyberte —</option>
                        <option value="pdf" @selected(old('format') === 'pdf')>PDF</option>
                        <option value="csv" @selected(old('format') === 'csv')>CSV</option>
                        <option value="json" @selected(old('format') === 'json')>JSON</option>
                    </select>
                    @error('format') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                        id="is_active" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Vytvořit plán</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
