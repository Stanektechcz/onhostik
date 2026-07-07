@extends('layouts.panel')

@section('title', 'Aktivní relace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Aktivní relace">
        <x-slot name="headerRight">
            <form method="POST" action="{{ route('panel.account.sessions.destroy-others') }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('Ukončit všechny ostatní relace?')">
                    <i data-feather="log-out" style="width:13px;height:13px"></i>
                    Ukončit ostatní
                </button>
            </form>
        </x-slot>

        @if($sessions->isEmpty())
            <p class="text-muted mb-0">Žádné aktivní relace.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>IP adresa</th>
                            <th>Zařízení / Prohlížeč</th>
                            <th>Poslední aktivita</th>
                            <th>Stav</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessions as $session)
                        @php
                            $isCurrent  = $session->id === $currentId;
                            $lastActive = \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity);
                            $ua         = $session->user_agent ? \Illuminate\Support\Str::limit($session->user_agent, 60) : '—';
                        @endphp
                        <tr>
                            <td class="f-13">{{ $session->ip_address ?? '—' }}</td>
                            <td class="f-12 f-light">{{ $ua }}</td>
                            <td class="f-12">{{ $lastActive->diffForHumans() }}</td>
                            <td>
                                @if($isCurrent)
                                    <span class="badge badge-light-success">Aktuální</span>
                                @else
                                    <span class="badge badge-light-secondary">Jiná</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(!$isCurrent)
                                    <form method="POST" action="{{ route('panel.account.sessions.destroy', $session->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i data-feather="x" style="width:12px;height:12px"></i>
                                            Ukončit
                                        </button>
                                    </form>
                                @endif
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
