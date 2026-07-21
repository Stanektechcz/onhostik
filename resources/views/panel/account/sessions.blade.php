@extends('layouts.panel')

@section('title', 'Aktivní relace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Aktivní relace">
        <x-slot name="headerRight">
            {{-- Password required: this also rotates the remember-me token, so
                 someone on a stolen session must not be able to trigger it. --}}
            <form method="POST" action="{{ route('panel.account.sessions.destroy-others') }}"
                  class="flex items-end gap-2">
                @csrf
                @method('DELETE')
                <div>
                    <label class="form-label f-11 mb-1" for="sessions-password">Vaše heslo</label>
                    <input type="password" name="password" id="sessions-password"
                           class="form-control form-control-sm @error('password') is-invalid @enderror"
                           autocomplete="current-password" required style="width:180px">
                </div>
                <button type="submit"
                        class="btn btn-outline-danger btn-sm"
                        data-confirm="Ukončit všechny ostatní relace? Budete odhlášeni na všech ostatních zařízeních.">
                    <i data-feather="log-out" style="width:13px;height:13px"></i>
                    Ukončit ostatní
                </button>
            </form>
        </x-slot>
        @error('password')<div class="alert alert-light-danger f-12">{{ $message }}</div>@enderror

        @php $sessionCap = (int) config('auth.max_concurrent_sessions', 0); @endphp
        @if($sessionCap > 0)
            <div class="alert alert-light-primary f-12 flex items-center gap-2">
                <i data-feather="shield" style="width:14px;height:14px"></i>
                <span>Z bezpečnostních důvodů může být současně přihlášeno nejvýše
                    <strong>{{ $sessionCap }}</strong> {{ trans_choice('zařízení|zařízení|zařízení', $sessionCap) }}.
                    Při dalším přihlášení se nejstarší relace automaticky ukončí.</span>
            </div>
        @endif

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
                            <td class="text-right">
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
