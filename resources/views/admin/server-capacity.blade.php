@extends('layouts.panel')

@section('title', 'Kapacita serverů')

@section('content')
<div class="container-fluid">

    <div class="grid grid-cols-12 mb-3">
        <div class="col-span-12 md:col-span-4">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="f-w-700">{{ $totalUsed }} / {{ $totalMax }}</h4>
                    <p class="text-muted mb-1">Celkem služeb / kapacita</p>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar {{ ($totalUsed/$totalMax) >= 0.8 ? 'bg-danger' : 'bg-success' }}"
                             style="width:{{ min(100, round($totalUsed/$totalMax*100)) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Kapacita per server">
        @if($rows->isEmpty())
            <p class="text-muted">Žádné servery.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Server</th>
                        <th>Driver</th>
                        <th class="text-right">Služby</th>
                        <th>Obsazenost</th>
                        <th>CPU</th>
                        <th>RAM</th>
                        <th>Disk</th>
                        <th>Stav</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr class="{{ $row['alert'] ? 'table-warning' : '' }}">
                        <td class="f-w-500">{{ $row['server']->name }}</td>
                        <td class="f-12 text-muted">{{ $row['server']->driver->value }}</td>
                        <td class="text-right">{{ $row['used'] }} / {{ $row['max'] }}</td>
                        <td style="min-width:120px">
                            <div class="progress" style="height:8px">
                                <div class="progress-bar {{ $row['alert'] ? 'bg-danger' : 'bg-success' }}"
                                     style="width:{{ $row['pct'] }}%"></div>
                            </div>
                            <small class="text-muted">{{ $row['pct'] }}%</small>
                        </td>
                        <td class="f-12">{{ $row['cpu_cores'] !== null ? $row['cpu_cores'] . ' jader' : '—' }}</td>
                        <td class="f-12">{{ $row['ram_gb'] !== null ? $row['ram_gb'] . ' GB' : '—' }}</td>
                        <td class="f-12">{{ $row['disk_gb'] !== null ? $row['disk_gb'] . ' GB' : '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $row['server']->status === 'active' ? 'success' : 'warning' }}">
                                {{ $row['server']->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
