@extends('layouts.panel')

@section('title', 'Pravidla firewallu')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <x-panel.flash />

        {{-- Service selector --}}
        <x-panel.card title="Filtr podle služby">
            <form method="GET" action="{{ route('panel.service-firewall-rules.index') }}" class="d-flex gap-2 align-items-end flex-wrap">
                <div>
                    <label class="form-label form-label-sm mb-1">Služba</label>
                    <select name="service_id" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— Všechny služby —</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected((string)$serviceId === (string)$service->id)>
                                #{{ $service->id }} {{ $service->label ?? $service->domain ?? 'Služba' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filtrovat</button>
                @if($serviceId)
                    <a href="{{ route('panel.service-firewall-rules.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                @endif
            </form>
        </x-panel.card>

        {{-- Rules table --}}
        <x-panel.card title="Pravidla firewallu" class="mt-3">
            @if($rules->isEmpty())
                <p class="text-muted">Žádná pravidla.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Směr</th>
                            <th>Protokol</th>
                            <th>Port</th>
                            <th>IP / CIDR</th>
                            <th>Akce</th>
                            <th class="text-center">Aktivní</th>
                            <th>Popis</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr>
                            <td>
                                @php
                                    $dirColors = ['in' => 'primary', 'out' => 'info', 'both' => 'secondary'];
                                    $dc = $dirColors[$rule->direction] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $dc }}">{{ strtoupper($rule->direction) }}</span>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ strtoupper($rule->protocol) }}</span></td>
                            <td class="f-12">
                                @if($rule->port_from && $rule->port_to)
                                    {{ $rule->port_from }}–{{ $rule->port_to }}
                                @elseif($rule->port_from)
                                    {{ $rule->port_from }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="f-12 font-monospace">{{ $rule->ip_cidr }}</td>
                            <td>
                                @if($rule->action === 'allow')
                                    <span class="badge bg-success">Povolit</span>
                                @else
                                    <span class="badge bg-danger">Zamítnout</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($rule->is_active)
                                    <span class="badge bg-success">Ano</span>
                                @else
                                    <span class="badge bg-secondary">Ne</span>
                                @endif
                            </td>
                            <td class="f-12 text-muted">{{ Str::limit($rule->description, 60) ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('panel.service-firewall-rules.destroy', $rule) }}"
                                    onsubmit="return confirm('Odstranit pravidlo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Odstranit</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $rules->appends(request()->query())->links() }}</div>
            @endif
        </x-panel.card>

        {{-- Add rule form --}}
        <x-panel.card title="Přidat pravidlo" class="mt-3">
            <form method="POST" action="{{ route('panel.service-firewall-rules.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Služba <span class="text-danger">*</span></label>
                        <select name="service_id" class="form-select form-select-sm @error('service_id') is-invalid @enderror" required>
                            <option value="">— Vyberte —</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id', $serviceId) == $service->id)>
                                    #{{ $service->id }} {{ $service->label ?? $service->domain ?? 'Služba' }}
                                </option>
                            @endforeach
                        </select>
                        @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Směr <span class="text-danger">*</span></label>
                        <select name="direction" class="form-select form-select-sm @error('direction') is-invalid @enderror" required>
                            <option value="in"   @selected(old('direction') === 'in')>IN</option>
                            <option value="out"  @selected(old('direction') === 'out')>OUT</option>
                            <option value="both" @selected(old('direction') === 'both')>BOTH</option>
                        </select>
                        @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Protokol <span class="text-danger">*</span></label>
                        <select name="protocol" class="form-select form-select-sm @error('protocol') is-invalid @enderror" required>
                            <option value="tcp"  @selected(old('protocol') === 'tcp')>TCP</option>
                            <option value="udp"  @selected(old('protocol') === 'udp')>UDP</option>
                            <option value="icmp" @selected(old('protocol') === 'icmp')>ICMP</option>
                            <option value="any"  @selected(old('protocol') === 'any')>ANY</option>
                        </select>
                        @error('protocol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Port od</label>
                        <input type="number" name="port_from" class="form-control form-control-sm @error('port_from') is-invalid @enderror"
                            value="{{ old('port_from') }}" min="1" max="65535" placeholder="1">
                        @error('port_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Port do</label>
                        <input type="number" name="port_to" class="form-control form-control-sm @error('port_to') is-invalid @enderror"
                            value="{{ old('port_to') }}" min="1" max="65535" placeholder="65535">
                        @error('port_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">IP / CIDR <span class="text-danger">*</span></label>
                        <input type="text" name="ip_cidr" class="form-control form-control-sm @error('ip_cidr') is-invalid @enderror"
                            value="{{ old('ip_cidr') }}" maxlength="50" placeholder="192.168.1.0/24" required>
                        @error('ip_cidr')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Akce <span class="text-danger">*</span></label>
                        <select name="action" class="form-select form-select-sm @error('action') is-invalid @enderror" required>
                            <option value="allow" @selected(old('action') === 'allow')>Povolit</option>
                            <option value="deny"  @selected(old('action') === 'deny')>Zamítnout</option>
                        </select>
                        @error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Popis</label>
                        <input type="text" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror"
                            value="{{ old('description') }}" maxlength="500">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Přidat pravidlo</button>
                    </div>
                </div>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
