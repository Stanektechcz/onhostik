@extends('layouts.panel')

@section('title', $rule->exists ? 'Upravit pravidlo' : 'Nové pravidlo')

@section('content')
<div class="container-fluid py-4">
    <div class="flex items-center mb-4">
        <a href="{{ route('admin.automation.index') }}" class="btn btn-sm btn-outline-secondary me-3">← Zpět</a>
        <h1 class="h4 mb-0">{{ $rule->exists ? 'Upravit pravidlo' : 'Nové pravidlo' }}</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ $rule->exists ? route('admin.automation.update', $rule) : route('admin.automation.store') }}" method="POST">
                @csrf
                @if($rule->exists) @method('PUT') @endif

                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-8">
                        <label class="form-label">Název pravidla *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $rule->name) }}" required maxlength="150">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Stav</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $rule->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Aktivní</label>
                        </div>
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Spouštěč (Trigger) *</label>
                        <select name="trigger" class="form-select @error('trigger') is-invalid @enderror" required>
                            <option value="">— vyberte —</option>
                            @foreach($triggers as $val => $label)
                                <option value="{{ $val }}" {{ old('trigger', $rule->trigger) === $val ? 'selected' : '' }}>
                                    {{ $label }} ({{ $val }})
                                </option>
                            @endforeach
                        </select>
                        @error('trigger')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Akce *</label>
                        <select name="action" class="form-select @error('action') is-invalid @enderror" required>
                            <option value="">— vyberte —</option>
                            @foreach($actions as $val => $label)
                                <option value="{{ $val }}" {{ old('action', $rule->action) === $val ? 'selected' : '' }}>
                                    {{ $label }} ({{ $val }})
                                </option>
                            @endforeach
                        </select>
                        @error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12">
                        <label class="form-label">Podmínky (JSON, volitelné)</label>
                        <small class="text-muted block mb-1">Příklad: <code>[{"field":"priority","operator":"=","value":"urgent"}]</code></small>
                        <textarea name="conditions_json" class="form-control font-monospace"
                                  rows="3" placeholder='[{"field":"...","operator":"=","value":"..."}]'>{{ old('conditions_json', $rule->exists ? json_encode($rule->conditions, JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>

                    <div class="col-span-12">
                        <label class="form-label">Parametry akce (JSON, volitelné)</label>
                        <small class="text-muted block mb-1">Příklad (send_notification): <code>{"to":"admin@example.com","subject":"Alert","body":"..."}</code></small>
                        <textarea name="action_params_json" class="form-control font-monospace"
                                  rows="3" placeholder='{"key":"value"}'>{{ old('action_params_json', $rule->exists ? json_encode($rule->action_params, JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        {{ $rule->exists ? 'Uložit změny' : 'Vytvořit pravidlo' }}
                    </button>
                    <a href="{{ route('admin.automation.index') }}" class="btn btn-outline-secondary">Zrušit</a>
                </div>
            </form>

            @if($rule->exists)
                <hr>
                <form action="{{ route('admin.automation.test', $rule) }}" method="POST" class="mt-3">
                    @csrf
                    <label class="form-label">Test-fire (volitelný JSON kontext)</label>
                    <div class="flex gap-2">
                        <input type="text" name="context" class="form-control font-monospace"
                               placeholder='{"priority":"urgent","user_email":"test@example.com"}'>
                        <button type="submit" class="btn btn-outline-warning text-nowrap">Spustit test</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
