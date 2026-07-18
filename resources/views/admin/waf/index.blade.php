@extends('layouts.panel')

@section('title', 'WAF Správa')

@section('content')
<div class="container-fluid py-4">

    <div class="flex items-center justify-between mb-4">
        <h1 class="h4 mb-0">WAF &amp; Bezpečnostní pravidla</h1>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-12">

        {{-- Rules Table --}}
        <div class="col-span-12 lg:col-span-8 mb-4">
            <div class="card">
                <div class="card-header flex justify-between items-center">
                    <strong>Všechna WAF pravidla</strong>
                    <span class="badge bg-secondary">{{ $rules->total() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Typ</th>
                                <th>Hodnota</th>
                                <th>Rozsah</th>
                                <th>Stav</th>
                                <th>Vytvořil</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rules as $rule)
                            <tr class="{{ $rule->is_active ? '' : 'table-secondary text-muted' }}">
                                <td>
                                    <span class="badge bg-{{ $rule->type->color() }}">{{ $rule->type->label() }}</span>
                                </td>
                                <td><code>{{ $rule->value }}</code></td>
                                <td>
                                    @if($rule->isGlobal())
                                        <span class="badge bg-dark">Globální</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $rule->service?->label ?? 'Služba' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rule->is_active)
                                        <span class="badge bg-success">Aktivní</span>
                                    @else
                                        <span class="badge bg-secondary">Neaktivní</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $rule->creator?->name ?? 'Systém' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.waf.toggle', $rule) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-xs btn-sm btn-outline-secondary">
                                            {{ $rule->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.waf.destroy', $rule) }}" class="inline"
                                          onsubmit="return confirm('Smazat pravidlo?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-sm btn-outline-danger">Smazat</button>
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
                @if($rules->hasPages())
                    <div class="card-footer">{{ $rules->links() }}</div>
                @endif
            </div>
        </div>

        {{-- Add Rule Form --}}
        <div class="col-span-12 lg:col-span-4 mb-4">
            <div class="card">
                <div class="card-header"><strong>Přidat globální pravidlo</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.waf.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Typ</label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach(\App\Domains\Security\Enums\WafRuleType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ old('type') === $type->value ? 'selected' : '' }}>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hodnota</label>
                            <input type="text" name="value" value="{{ old('value') }}"
                                   class="form-control @error('value') is-invalid @enderror"
                                   placeholder="IP / CIDR / kód země / limit" required>
                            @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Omezit na službu (nepovinné)</label>
                            <input type="number" name="service_id" value="{{ old('service_id') }}"
                                   class="form-control @error('service_id') is-invalid @enderror"
                                   placeholder="ID služby (prázdné = globální)">
                            @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Poznámka</label>
                            <input type="text" name="notes" value="{{ old('notes') }}"
                                   class="form-control @error('notes') is-invalid @enderror"
                                   placeholder="Důvod pravidla…">
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-full">Přidat pravidlo</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    {{-- Recent WAF Events --}}
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <strong>Poslední bezpečnostní události</strong>
            <span class="badge bg-secondary">{{ $recentEvents->count() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>IP adresa</th>
                        <th>Země</th>
                        <th>URI</th>
                        <th>Metoda</th>
                        <th>Akce</th>
                        <th>Služba</th>
                        <th>Čas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentEvents as $event)
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
                        <td class="small text-muted">{{ $event->service?->label ?? '—' }}</td>
                        <td class="text-muted small">{{ $event->blocked_at->format('d.m. H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Žádné události.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
