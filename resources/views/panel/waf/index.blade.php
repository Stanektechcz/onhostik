@extends('layouts.panel')

@section('title', 'WAF – ' . $service->label)

@section('content')
<div class="container py-4">

    <div class="flex items-center mb-4 gap-3">
        <a href="{{ route('panel.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0">WAF &amp; Ochrana přístupu</h1>
        <span class="text-muted small">{{ $service->label }}</span>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    {{-- Active Rules --}}
    <div class="card mb-4">
        <div class="card-header flex justify-between items-center">
            <strong>Aktivní pravidla</strong>
            <span class="badge bg-secondary">{{ $rules->count() }}</span>
        </div>

        @if($rules->isEmpty())
            <div class="card-body text-muted small">Žádná WAF pravidla. Přidejte první pravidlo níže.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Typ</th>
                            <th>Hodnota</th>
                            <th>Akce</th>
                            <th>Poznámka</th>
                            <th>Stav</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr class="{{ $rule->is_active ? '' : 'text-muted' }}">
                            <td>
                                <span class="badge bg-{{ $rule->type->color() }}">{{ $rule->type->label() }}</span>
                            </td>
                            <td><code>{{ $rule->value }}</code></td>
                            <td>{{ $rule->action }}</td>
                            <td class="text-muted small">{{ $rule->notes ?? '—' }}</td>
                            <td>
                                @if($rule->is_active)
                                    <span class="badge bg-success">Aktivní</span>
                                @else
                                    <span class="badge bg-secondary">Neaktivní</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('panel.waf.toggle', [$service, $rule]) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-xs btn-outline-secondary btn-sm">
                                        {{ $rule->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.waf.destroy', [$service, $rule]) }}" class="inline"
                                      data-confirm="Smazat pravidlo?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger btn-sm">Smazat</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Add Rule Form --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Přidat pravidlo</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('panel.waf.store', $service) }}">
                @csrf
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-3">
                        <label class="form-label">Typ pravidla</label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach(\App\Domains\Security\Enums\WafRuleType::cases() as $type)
                                <option value="{{ $type->value }}" {{ old('type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Hodnota</label>
                        <input type="text" name="value" value="{{ old('value') }}"
                               class="form-control @error('value') is-invalid @enderror"
                               placeholder="IP, CIDR, kód země nebo limit" required>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Poznámka (nepovinná)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                               class="form-control @error('notes') is-invalid @enderror"
                               placeholder="Důvod pravidla…">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-1 flex items-end">
                        <button type="submit" class="btn btn-primary w-full">Přidat</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent Events --}}
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <strong>Poslední bezpečnostní události</strong>
            <span class="badge bg-secondary">{{ $events->count() }}</span>
        </div>

        @if($events->isEmpty())
            <div class="card-body text-muted small">Žádné zaznamenané události.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>IP adresa</th>
                            <th>Země</th>
                            <th>URI</th>
                            <th>Metoda</th>
                            <th>Akce</th>
                            <th>Čas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                        <tr>
                            <td><code>{{ $event->ip_address }}</code></td>
                            <td>{{ $event->country_code ?? '—' }}</td>
                            <td class="truncate" style="max-width:200px">{{ $event->request_uri ?? '—' }}</td>
                            <td>{{ $event->method ?? '—' }}</td>
                            <td>
                                @if($event->action_taken === 'block')
                                    <span class="badge bg-danger">Blokováno</span>
                                @else
                                    <span class="badge bg-success">Povoleno</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $event->blocked_at->format('d.m. H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
