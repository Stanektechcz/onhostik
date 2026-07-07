@extends('layouts.panel')

@section('title', 'Přehled DPH')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3 d-flex align-items-center gap-3">
        <form method="GET" class="d-flex align-items-center gap-2">
            <select name="year" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
                @if(empty($availableYears))
                    <option value="{{ $year }}">{{ $year }}</option>
                @endif
            </select>
        </form>
    </div>

    <x-panel.card title="Přehled DPH {{ $year }} — po měsících">
        @if(empty($scenarios))
            <p class="text-muted">Žádné zaplacené faktury za rok {{ $year }}.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Měsíc</th>
                        @foreach($scenarios as $sc)
                        <th class="text-end">{{ $sc }}<br><small class="text-muted">DPH / celkem</small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for($m = 1; $m <= 12; $m++)
                    @php $hasData = isset($byMonth[$m]); @endphp
                    <tr class="{{ $hasData ? '' : 'text-muted' }}">
                        <td class="f-w-500">{{ \Carbon\Carbon::create($year, $m)->locale('cs')->monthName }}</td>
                        @foreach($scenarios as $sc)
                        <td class="text-end f-12">
                            @if(isset($byMonth[$m][$sc]))
                                {{ number_format($byMonth[$m][$sc]->tax_minor / 100, 2, ',', ' ') }} Kč<br>
                                <small class="text-muted">/ {{ number_format($byMonth[$m][$sc]->total_minor / 100, 2, ',', ' ') }} Kč</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endfor
                </tbody>
                <tfoot class="table-light f-w-600">
                    <tr>
                        <td>Celkem {{ $year }}</td>
                        @foreach($scenarios as $sc)
                        <td class="text-end">
                            @if(isset($totals[$sc]))
                                {{ number_format($totals[$sc]->tax_minor / 100, 2, ',', ' ') }} Kč<br>
                                <small>/ {{ number_format($totals[$sc]->total_minor / 100, 2, ',', ' ') }} Kč</small>
                            @else —
                            @endif
                        </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
