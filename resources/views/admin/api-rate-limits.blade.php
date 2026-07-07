@extends('layouts.panel')

@php
    $breadcrumbTitle = 'API Rate Limity';
    $breadcrumbItems = ['API' => route('admin.api-usage.index'), 'Rate Limity' => ''];
@endphp

@section('title', 'API Rate Limity')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <x-panel.stat-widget label="Aktivní tokeny (24h)" :value="$summary['active_tokens']" icon="key" />
        </div>
        <div class="col-md-4">
            <x-panel.stat-widget label="Překročily limit" :value="$summary['exceeded_tokens']" icon="alert-circle" color="danger" />
        </div>
        <div class="col-md-4">
            <x-panel.stat-widget label="Blízko limitu (≥75%)" :value="$summary['near_limit_tokens']" icon="alert-triangle" color="warning" />
        </div>
    </div>

    <div class="row g-3">
        {{-- Token status table --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Aktivní tokeny — využití limitů</h5>
                    <p class="mb-0 f-12 f-light">Zobrazuje tokeny aktivní v posledních 24 hodinách.</p>
                </div>
                <div class="card-body pt-0">
                    @if($tokenStatuses->isEmpty())
                        <p class="text-center f-light py-4">Žádné aktivní tokeny v posledních 24 hodinách.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Token ID</th>
                                    <th>Název</th>
                                    <th>Za minutu</th>
                                    <th>Za hodinu</th>
                                    <th>Za den</th>
                                    <th>Stav</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tokenStatuses as $ts)
                                <tr>
                                    <td class="f-12 f-light">#{{ $ts->token_id }}</td>
                                    <td class="f-w-500">{{ $ts->token_name }}</td>
                                    <td>
                                        @php $pct = $ts->status['minute_pct']; $color = $pct >= 100 ? 'danger' : ($pct >= 75 ? 'warning' : 'success'); @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-1" style="height:6px;min-width:60px;">
                                                <div class="progress-bar bg-{{ $color }}" style="width:{{ min($pct, 100) }}%"></div>
                                            </div>
                                            <span class="f-11 f-light text-nowrap">{{ $ts->usage['minute'] }}/{{ $ts->limits['requests_per_minute'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @php $pct = $ts->status['hour_pct']; $color = $pct >= 100 ? 'danger' : ($pct >= 75 ? 'warning' : 'success'); @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-1" style="height:6px;min-width:60px;">
                                                <div class="progress-bar bg-{{ $color }}" style="width:{{ min($pct, 100) }}%"></div>
                                            </div>
                                            <span class="f-11 f-light text-nowrap">{{ $ts->usage['hour'] }}/{{ $ts->limits['requests_per_hour'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @php $pct = $ts->status['day_pct']; $color = $pct >= 100 ? 'danger' : ($pct >= 75 ? 'warning' : 'success'); @endphp
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-1" style="height:6px;min-width:60px;">
                                                <div class="progress-bar bg-{{ $color }}" style="width:{{ min($pct, 100) }}%"></div>
                                            </div>
                                            <span class="f-11 f-light text-nowrap">{{ $ts->usage['day'] }}/{{ $ts->limits['requests_per_day'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($ts->status['is_exceeded'])
                                            <span class="badge badge-light-danger f-10">Překročeno</span>
                                        @elseif($ts->status['minute_pct'] >= 75 || $ts->status['hour_pct'] >= 75 || $ts->status['day_pct'] >= 75)
                                            <span class="badge badge-light-warning f-10">Blízko limitu</span>
                                        @else
                                            <span class="badge badge-light-success f-10">V normě</span>
                                        @endif
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

        {{-- Configure limits --}}
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header card-no-border"><h5>Nastavit limity tokenu</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.api-rate-limits.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Token ID *</label>
                            <input type="number" name="token_id" class="form-control form-control-sm @error('token_id') is-invalid @enderror"
                                   value="{{ old('token_id') }}" min="1" required>
                            @error('token_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Req / minuta *</label>
                            <input type="number" name="requests_per_minute" class="form-control form-control-sm"
                                   value="{{ old('requests_per_minute', 30) }}" min="1" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Req / hodina *</label>
                            <input type="number" name="requests_per_hour" class="form-control form-control-sm"
                                   value="{{ old('requests_per_hour', 500) }}" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Req / den *</label>
                            <input type="number" name="requests_per_day" class="form-control form-control-sm"
                                   value="{{ old('requests_per_day', 5000) }}" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Uložit limity</button>
                    </form>
                </div>
            </div>

            @if($configured->isNotEmpty())
            <div class="card">
                <div class="card-header card-no-border"><h5>Konfigurované limity</h5></div>
                <div class="card-body pt-0">
                    <table class="table table-sm">
                        <thead><tr><th>Token</th><th>Min</th><th>Hod</th><th>Den</th><th></th></tr></thead>
                        <tbody>
                            @foreach($configured as $cfg)
                            <tr>
                                <td class="f-12">#{{ $cfg->token_id }}</td>
                                <td class="f-12">{{ $cfg->requests_per_minute }}</td>
                                <td class="f-12">{{ $cfg->requests_per_hour }}</td>
                                <td class="f-12">{{ $cfg->requests_per_day }}</td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.api-rate-limits.destroy', $cfg) }}"
                                          onsubmit="return confirm('Odebrat konfiguraci limitů?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
