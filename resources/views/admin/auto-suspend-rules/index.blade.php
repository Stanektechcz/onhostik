@extends('layouts.panel')

@section('title', 'Pravidla automatického pozastavení')

@section('content')
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-8">
        <x-panel.card title="Pravidla automatického pozastavení">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Spouštěč</th>
                            <th>Práh</th>
                            <th>Aktivní</th>
                            <th>Popis</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td>{{ $rule->name }}</td>
                                <td>
                                    @php
                                        $triggerLabels = [
                                            'overdue_days'    => 'Dny po splatnosti',
                                            'usage_percent'   => 'Využití (%)',
                                            'failed_payments' => 'Neúspěšné platby',
                                        ];
                                    @endphp
                                    {{ $triggerLabels[$rule->trigger] ?? $rule->trigger }}
                                </td>
                                <td>{{ $rule->threshold_value }}</td>
                                <td>
                                    @if ($rule->is_active)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $rule->description ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.auto-suspend-rules.update', $rule) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $rule->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                            {{ $rule->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.auto-suspend-rules.index') }}" class="inline ms-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-confirm="Opravdu smazat pravidlo?">
                                            Smazat
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Žádná pravidla.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rules->hasPages())
                <div class="mt-3">{{ $rules->links() }}</div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-span-12 lg:col-span-4">
        <x-panel.card title="Přidat pravidlo">
            <form method="POST" action="{{ route('admin.auto-suspend-rules.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Název <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" maxlength="100" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Spouštěč <span class="text-danger">*</span></label>
                    <select name="trigger" class="form-select @error('trigger') is-invalid @enderror" required>
                        <option value="">— vyberte —</option>
                        <option value="overdue_days" @selected(old('trigger') === 'overdue_days')>Dny po splatnosti</option>
                        <option value="usage_percent" @selected(old('trigger') === 'usage_percent')>Využití (%)</option>
                        <option value="failed_payments" @selected(old('trigger') === 'failed_payments')>Neúspěšné platby</option>
                    </select>
                    @error('trigger') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Práh <span class="text-danger">*</span></label>
                    <input type="number" name="threshold_value" class="form-control @error('threshold_value') is-invalid @enderror"
                        value="{{ old('threshold_value') }}" min="1" required>
                    @error('threshold_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Popis</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                        rows="3" maxlength="500">{{ old('description') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                        id="is_active" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>

                <button type="submit" class="btn btn-primary w-full">Vytvořit pravidlo</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
