@extends('layouts.panel')

@section('title', 'Frekvence e-mailového digestu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Nastavení frekvence digestu">
        <p class="text-muted f-14">Digest je souhrnný e-mail o stavu vašich služeb, faktur a ticketů.</p>

        <form method="POST" action="{{ route('panel.account.digest-frequency.update') }}">
            @csrf
            @method('PATCH')
            <div class="mb-3">
                <label class="form-label">Frekvence</label>
                <select name="digest_frequency" class="form-select form-select-sm" style="max-width:200px">
                    <option value="daily" @selected($user->digest_frequency === 'daily')>Denně</option>
                    <option value="weekly" @selected($user->digest_frequency === 'weekly' || $user->digest_frequency === null)>Týdně</option>
                    <option value="never" @selected($user->digest_frequency === 'never')>Nikdy</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
        </form>
    </x-panel.card>
</div>
@endsection
