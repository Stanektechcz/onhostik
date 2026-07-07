@extends('layouts.panel')

@section('title', 'Komunikace')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <x-panel.flash />

        <x-panel.card title="Komunikace">
            @if($logs->isEmpty())
                <p class="text-muted">Žádné záznamy komunikace.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Datum</th>
                            <th>Kanál</th>
                            <th>Směr</th>
                            <th>Předmět</th>
                            <th>Zpráva</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td class="f-12 text-muted" style="white-space:nowrap;">
                                {{ $log->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td>
                                @php
                                    $channelColors = [
                                        'email'   => 'primary',
                                        'sms'     => 'success',
                                        'chat'    => 'info',
                                        'phone'   => 'warning',
                                        'ticket'  => 'secondary',
                                    ];
                                    $cc = $channelColors[$log->channel] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $cc }}">{{ ucfirst($log->channel) }}</span>
                            </td>
                            <td>
                                @if(($log->direction ?? '') === 'inbound')
                                    <span class="badge bg-light text-dark border">← Příchozí</span>
                                @elseif(($log->direction ?? '') === 'outbound')
                                    <span class="badge bg-light text-dark border">→ Odchozí</span>
                                @else
                                    <span class="text-muted f-12">{{ $log->direction ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="f-w-500">{{ $log->subject ?? '—' }}</td>
                            <td class="f-12 text-muted">
                                {{ Str::limit($log->body, 100) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $logs->links() }}</div>
            @endif
        </x-panel.card>
    </div>
</div>
@endsection
