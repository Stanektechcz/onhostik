@extends('layouts.panel')

@section('title', 'Incidenty zdraví služby')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-11">
        <x-panel.flash />

        <x-panel.card title="Incidenty zdraví služby">
            @if($incidents->isEmpty())
                <p class="text-muted">Žádné incidenty.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Service ID</th>
                            <th>Závažnost</th>
                            <th>Název</th>
                            <th>Stav</th>
                            <th>Vytvořeno</th>
                            <th>Vyřešeno</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($incidents as $incident)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $incident->service_id }}</span>
                            </td>
                            <td>
                                @php
                                    $severityColors = [
                                        'critical' => 'danger',
                                        'high'     => 'warning',
                                        'medium'   => 'info',
                                        'low'      => 'secondary',
                                    ];
                                    $sc = $severityColors[$incident->severity] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $sc }}">{{ ucfirst($incident->severity) }}</span>
                            </td>
                            <td class="f-w-500">{{ $incident->title }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'open'        => 'danger',
                                        'investigating'=> 'warning',
                                        'resolved'    => 'success',
                                        'closed'      => 'secondary',
                                    ];
                                    $stc = $statusColors[$incident->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $stc }}">{{ ucfirst($incident->status) }}</span>
                            </td>
                            <td class="f-12 text-muted">{{ $incident->created_at->format('d.m.Y H:i') }}</td>
                            <td class="f-12">
                                @if($incident->resolved_at)
                                    <span class="text-success">{{ $incident->resolved_at->format('d.m.Y H:i') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $incidents->links() }}</div>
            @endif
        </x-panel.card>
    </div>
</div>
@endsection
