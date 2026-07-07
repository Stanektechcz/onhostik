@extends('layouts.panel')

@section('title', 'Plánované reporty')

@section('content')
<x-panel.flash />

<x-panel.card title="Plánované reporty">
    <p class="text-muted">Toto jsou plánované reporty nastavené administrátorem.</p>

    @if($schedules->isEmpty())
        <p class="text-muted">Žádné aktivní plánované reporty.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Typ</th>
                        <th>Frekvence</th>
                        <th>Formát</th>
                        <th>Příští spuštění</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $schedule)
                        <tr>
                            <td>{{ $schedule->name }}</td>
                            <td>{{ $schedule->report_type }}</td>
                            <td>
                                @php
                                    $freqLabel = match($schedule->frequency) {
                                        'daily'   => 'Denně',
                                        'weekly'  => 'Týdně',
                                        'monthly' => 'Měsíčně',
                                        default   => $schedule->frequency,
                                    };
                                @endphp
                                {{ $freqLabel }}
                            </td>
                            <td>{{ strtoupper($schedule->format) }}</td>
                            <td>{{ $schedule->next_run_at ? $schedule->next_run_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $schedules->links() }}
        </div>
    @endif
</x-panel.card>
@endsection
