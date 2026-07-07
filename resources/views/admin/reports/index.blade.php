@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Report Builder';
    $breadcrumbItems = ['Report Builder' => ''];
@endphp

@section('title', 'Report Builder')

@section('content')
<div class="container-fluid">
    <div class="row g-3">

        {{-- Filters --}}
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header card-no-border"><h5>Parametry reportu</h5></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.reports.index') }}" id="report-form">
                        <div class="mb-3">
                            <label class="form-label f-12">Typ reportu *</label>
                            <select class="form-select form-select-sm" name="type" id="report-type"
                                    onchange="toggleGroupBy()">
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" @selected(($params['type'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Od *</label>
                            <input type="date" name="from" class="form-control form-control-sm"
                                   value="{{ $params['from'] }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Do *</label>
                            <input type="date" name="to" class="form-control form-control-sm"
                                   value="{{ $params['to'] }}">
                        </div>
                        <div class="mb-3" id="group-by-field">
                            <label class="form-label f-12">Seskupení</label>
                            <select class="form-select form-select-sm" name="group_by">
                                <option value="day" @selected(($params['group_by'] ?? '') === 'day')>Denně</option>
                                <option value="week" @selected(($params['group_by'] ?? '') === 'week')>Týdně</option>
                                <option value="month" @selected(($params['group_by'] ?? 'month') === 'month')>Měsíčně</option>
                            </select>
                        </div>
                        <div class="mb-3" id="status-field">
                            <label class="form-label f-12">Status <small class="f-light">(nepovinné)</small></label>
                            <input type="text" name="status" class="form-control form-control-sm"
                                   value="{{ $params['status'] ?? '' }}" placeholder="paid, active…">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">Sestavit report</button>
                        @if($hasQuery)
                        <a href="{{ route('admin.reports.download', $params) }}"
                           class="btn btn-outline-secondary btn-sm w-100">
                            <i data-feather="download" style="width:12px;height:12px;"></i>
                            Stáhnout CSV
                        </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        {{-- Report output --}}
        <div class="col-lg-9">
            @if(!$hasQuery)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i data-feather="bar-chart-2" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                        <h5 class="f-light">Zvolte typ reportu a zadejte parametry</h5>
                        <p class="f-light f-12">Report se zobrazí zde po kliknutí na Sestavit report.</p>
                    </div>
                </div>
            @elseif($report && $report['rows']->isNotEmpty())
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center gap-2">
                        <h5 class="mb-0">{{ $report['title'] }}</h5>
                        <span class="badge bg-secondary ms-2">{{ $report['rows']->count() }} řádků</span>
                        <a href="{{ route('admin.reports.download', $params) }}"
                           class="btn btn-outline-secondary btn-xs ms-auto">
                            <i data-feather="download" style="width:11px;height:11px;"></i> CSV
                        </a>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        @foreach($report['headers'] as $header)
                                            <th>{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report['rows'] as $row)
                                        <tr>
                                            @foreach((array) $row as $cell)
                                                <td class="f-12">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-4">
                        <p class="f-light mb-0">Žádná data pro zadané parametry.</p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleGroupBy() {
    var type = document.getElementById('report-type').value;
    var grouped = ['revenue', 'new_customers', 'cohort_revenue'];
    var statusTypes = ['services', 'invoices'];
    document.getElementById('group-by-field').style.display = grouped.includes(type) ? '' : 'none';
    document.getElementById('status-field').style.display = statusTypes.includes(type) ? '' : 'none';
}
toggleGroupBy();
</script>
@endpush
