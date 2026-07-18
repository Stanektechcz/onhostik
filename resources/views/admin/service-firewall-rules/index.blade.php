@extends('layouts.panel')

@section('title', 'Pravidla firewallu služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter --}}
    <x-panel.card title="Filtr">
        <form method="GET" action="{{ route('admin.service-firewall-rules.index') }}" class="flex gap-2 items-end">
            <div>
                <label class="form-label mb-1 small">Service ID</label>
                <input type="number" name="service_id" class="form-control form-control-sm" value="{{ $serviceId }}" placeholder="všechny" style="width:140px">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filtrovat</button>
            @if($serviceId)
                <a href="{{ route('admin.service-firewall-rules.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit filtr</a>
            @endif
        </form>
    </x-panel.card>

    {{-- Table --}}
    <x-panel.card title="Pravidla firewallu{{ $serviceId ? ' – Service #'.$serviceId : '' }}">
        @if($rules->isEmpty())
            <p class="text-muted">Žádná pravidla.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service ID</th>
                        <th>Směr</th>
                        <th>Protokol</th>
                        <th>Port from–to</th>
                        <th>IP/CIDR</th>
                        <th>Akce</th>
                        <th>Aktivní</th>
                        <th>Popis</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $rule)
                    <tr>
                        <td>{{ $rule->id }}</td>
                        <td>{{ $rule->service_id }}</td>
                        <td>
                            @php
                                $dirColor = match($rule->direction) { 'in' => 'info', 'out' => 'warning', default => 'secondary' };
                            @endphp
                            <span class="badge bg-{{ $dirColor }}">{{ $rule->direction }}</span>
                        </td>
                        <td><span class="badge bg-dark">{{ $rule->protocol }}</span></td>
                        <td class="text-nowrap">
                            {{ $rule->port_from ?? '—' }}{{ ($rule->port_from || $rule->port_to) ? '–' : '' }}{{ $rule->port_to ?? ($rule->port_from ? '' : '') }}
                        </td>
                        <td><code>{{ $rule->ip_cidr }}</code></td>
                        <td>
                            <span class="badge bg-{{ $rule->action === 'allow' ? 'success' : 'danger' }}">{{ $rule->action }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.service-firewall-rules.update', $rule->id) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-{{ $rule->is_active ? 'success' : 'outline-secondary' }}">
                                    {{ $rule->is_active ? 'Ano' : 'Ne' }}
                                </button>
                            </form>
                        </td>
                        <td class="truncate" style="max-width:200px">{{ $rule->description ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.service-firewall-rules.destroy', $rule->id) }}" onsubmit="return confirm('Opravdu smazat pravidlo #{{ $rule->id }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rules->withQueryString()->links() }}</div>
        @endif
    </x-panel.card>

    {{-- Create form --}}
    <x-panel.card title="Přidat pravidlo firewallu">
        <form method="POST" action="{{ route('admin.service-firewall-rules.store') }}" class="grid grid-cols-12 gap-3">
            @csrf

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Service ID <span class="text-danger">*</span></label>
                <input type="number" name="service_id" class="form-control @error('service_id') is-invalid @enderror"
                       value="{{ old('service_id', $serviceId) }}" required>
                @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Směr <span class="text-danger">*</span></label>
                <select name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    @foreach(['in' => 'in (příchozí)', 'out' => 'out (odchozí)', 'both' => 'both (obojí)'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('direction') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Protokol <span class="text-danger">*</span></label>
                <select name="protocol" class="form-select @error('protocol') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    @foreach(['tcp', 'udp', 'icmp', 'any'] as $proto)
                        <option value="{{ $proto }}" @selected(old('protocol') === $proto)>{{ $proto }}</option>
                    @endforeach
                </select>
                @error('protocol')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-1">
                <label class="form-label">Port from</label>
                <input type="number" name="port_from" class="form-control @error('port_from') is-invalid @enderror"
                       value="{{ old('port_from') }}" min="1" max="65535" placeholder="1">
                @error('port_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-1">
                <label class="form-label">Port to</label>
                <input type="number" name="port_to" class="form-control @error('port_to') is-invalid @enderror"
                       value="{{ old('port_to') }}" min="1" max="65535" placeholder="65535">
                @error('port_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">IP/CIDR <span class="text-danger">*</span></label>
                <input type="text" name="ip_cidr" class="form-control @error('ip_cidr') is-invalid @enderror"
                       value="{{ old('ip_cidr') }}" placeholder="0.0.0.0/0" maxlength="50" required>
                @error('ip_cidr')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Akce <span class="text-danger">*</span></label>
                <select name="action" class="form-select @error('action') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    <option value="allow" @selected(old('action') === 'allow')>allow (povolit)</option>
                    <option value="deny"  @selected(old('action') === 'deny')>deny (zamítnout)</option>
                </select>
                @error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-4">
                <label class="form-label">Popis</label>
                <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                       value="{{ old('description') }}" maxlength="500">
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2 flex items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                           @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>
            </div>

            <div class="col-span-12">
                <button type="submit" class="btn btn-primary">Vytvořit pravidlo</button>
            </div>
        </form>
    </x-panel.card>
</div>
@endsection
