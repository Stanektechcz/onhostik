@extends('layouts.panel')
@section('title', 'Pravidla upomínek faktur')
@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">
        <div class="col-md-5">
            <x-panel.card title="Nové pravidlo">
                <form method="POST" action="{{ route('admin.invoice-reminder-rules.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Dní po splatnosti</label>
                        <input type="number" name="days_after_due" class="form-control" min="1" max="90" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kanál</label>
                        <select name="channel" class="form-select">
                            <option value="mail">E-mail</option>
                            <option value="database">Notifikace</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template klíč</label>
                        <input type="text" name="template_key" class="form-control" value="invoice.overdue" required>
                    </div>
                    <button class="btn btn-primary">Uložit</button>
                </form>
            </x-panel.card>
        </div>

        <div class="col-md-7">
            <x-panel.card title="Aktivní pravidla">
                @forelse($rules as $rule)
                <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                    <div>
                        <strong>D+{{ $rule->days_after_due }}</strong>
                        <span class="ms-2 badge bg-secondary">{{ $rule->channel }}</span>
                        <small class="ms-2 text-muted">{{ $rule->template_key }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.invoice-reminder-rules.update', $rule) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $rule->is_active ? '0' : '1' }}">
                            <input type="hidden" name="template_key" value="{{ $rule->template_key }}">
                            <button class="btn btn-sm {{ $rule->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                {{ $rule->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoice-reminder-rules.destroy', $rule) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Smazat?')">✕</button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted">Žádná pravidla.</p>
                @endforelse
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
