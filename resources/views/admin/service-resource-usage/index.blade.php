@extends('layouts.panel')
@section('title', 'Přetížené služby — využití zdrojů')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Služby překračující práh využití zdrojů">
        @if($services->isEmpty())
            <p class="text-muted">Žádné přetížené služby.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Služba</th>
                        <th>Zákazník</th>
                        <th class="text-right">Disk využito</th>
                        <th class="text-right">Disk limit</th>
                        <th class="text-right">Využití %</th>
                        <th class="text-right">Práh %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($services as $s)
                    @php
                        $usedPct = $s->disk_limit_gb > 0
                            ? round(($s->disk_usage_gb / $s->disk_limit_gb) * 100, 1)
                            : 0;
                    @endphp
                    <tr>
                        <td>{{ $s->label }}</td>
                        <td>{{ $s->customer?->company_name }}</td>
                        <td class="text-right">{{ $s->disk_usage_gb }} GB</td>
                        <td class="text-right">{{ $s->disk_limit_gb }} GB</td>
                        <td class="text-right font-bold {{ $usedPct >= 95 ? 'text-danger' : 'text-warning' }}">
                            {{ $usedPct }} %
                        </td>
                        <td class="text-right text-muted">{{ $s->usage_alert_threshold }} %</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $services->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
