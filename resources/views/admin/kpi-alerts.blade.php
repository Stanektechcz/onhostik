@extends('layouts.panel')

@php
    $breadcrumbTitle = 'KPI Upozornění';
    $breadcrumbItems = ['Admin' => route('admin.dashboard'), 'KPI Upozornění' => ''];
@endphp

@section('title', 'KPI Upozornění')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary --}}
    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-4">
            <x-panel.stat-widget label="Aktivní alerty" :value="$alerts->where('is_active', true)->count()" icon="bell" />
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.stat-widget label="Aktivovaná upozornění" :value="$triggeredCount" icon="alert-triangle" color="danger" />
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.stat-widget label="Metriky celkem" :value="count($metrics)" icon="bar-chart-2" color="info" />
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3">
        {{-- Existing alerts table --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header flex justify-between items-center py-3">
                    <h6 class="mb-0">Přehled KPI alertů</h6>
                </div>
                <div class="card-body p-0">
                    @if($alerts->isEmpty())
                        <div class="text-center py-4 text-muted f-12">
                            <i data-feather="bell-off" style="width:28px;height:28px;" class="mb-2 block mx-auto"></i>
                            Žádné alerty nejsou nakonfigurovány.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Metrika</th>
                                        <th>Podmínka</th>
                                        <th>Poslední hodnota</th>
                                        <th>Stav</th>
                                        <th>Zkontrolováno</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($alerts as $alert)
                                        <tr class="{{ $alert->isTriggered() ? 'table-danger' : '' }}">
                                            <td class="font-semibold f-12">{{ $alert->metricLabel() }}</td>
                                            <td class="f-12">
                                                {{ $alert->operatorLabel() }}
                                                <strong>{{ number_format($alert->threshold, 0, ',', ' ') }}</strong>
                                            </td>
                                            <td class="f-12">
                                                @if($alert->last_value !== null)
                                                    {{ number_format($alert->last_value, 0, ',', ' ') }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(! $alert->is_active)
                                                    <span class="badge bg-secondary">Neaktivní</span>
                                                @elseif($alert->isTriggered())
                                                    <span class="badge bg-danger">Aktivováno</span>
                                                @else
                                                    <span class="badge bg-success">OK</span>
                                                @endif
                                            </td>
                                            <td class="text-muted f-12">
                                                {{ $alert->last_checked_at?->format('d.m.Y H:i') ?? '—' }}
                                            </td>
                                            <td>
                                                <div class="flex gap-1">
                                                    {{-- Toggle active --}}
                                                    <form method="POST" action="{{ route('admin.kpi-alerts.update', $alert) }}">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="threshold" value="{{ $alert->threshold }}">
                                                        <input type="hidden" name="is_active" value="{{ $alert->is_active ? '0' : '1' }}">
                                                        <button type="submit" class="btn btn-xs btn-outline-{{ $alert->is_active ? 'secondary' : 'success' }}" title="{{ $alert->is_active ? 'Deaktivovat' : 'Aktivovat' }}">
                                                            <i data-feather="{{ $alert->is_active ? 'pause' : 'play' }}" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                    {{-- Delete --}}
                                                    <form method="POST" action="{{ route('admin.kpi-alerts.destroy', $alert) }}"
                                                          onsubmit="return confirm('Smazat tento alert?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Smazat">
                                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Add new alert form --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0">Přidat nový alert</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.kpi-alerts.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">Metrika</label>
                            <select name="metric" class="form-control form-control-sm @error('metric') is-invalid @enderror">
                                @foreach($metrics as $key => $label)
                                    <option value="{{ $key }}" {{ old('metric') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('metric')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">Operátor</label>
                            <select name="operator" class="form-control form-control-sm @error('operator') is-invalid @enderror">
                                <option value="gte" {{ old('operator', 'gte') === 'gte' ? 'selected' : '' }}>≥ (větší nebo rovno)</option>
                                <option value="lte" {{ old('operator') === 'lte' ? 'selected' : '' }}>≤ (menší nebo rovno)</option>
                            </select>
                            @error('operator')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">Práh</label>
                            <input type="number" name="threshold" step="0.01" min="0"
                                value="{{ old('threshold') }}"
                                class="form-control form-control-sm @error('threshold') is-invalid @enderror"
                                placeholder="Např. 10">
                            @error('threshold')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-full">
                            <i data-feather="plus" style="width:13px;height:13px"></i>
                            Přidat alert
                        </button>
                    </form>
                </div>
            </div>

            @if($triggeredCount > 0)
                <div class="alert alert-danger mt-3 f-12">
                    <i data-feather="alert-triangle" style="width:14px;height:14px"></i>
                    <strong>{{ $triggeredCount }}</strong> {{ Str::plural('alert', $triggeredCount) }}
                    {{ $triggeredCount === 1 ? 'je aktivovaný' : 'jsou aktivovány' }}.
                    Zkontrolujte přehled výše.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
