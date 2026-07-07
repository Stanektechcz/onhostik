@extends('layouts.panel')

@section('title', 'Žádosti o výplatu resellerů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Žádosti o výplatu resellerů">

        {{-- Filter --}}
        <form method="GET" action="{{ route('admin.reseller-payout-requests.index') }}" class="d-flex gap-2 mb-3">
            <select name="status" class="form-select form-select-sm w-auto">
                <option value="">Všechny statusy</option>
                @foreach(['pending','approved','paid','rejected'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filtrovat</button>
            @if($status)
                <a href="{{ route('admin.reseller-payout-requests.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit</a>
            @endif
        </form>

        @if($requests->isEmpty())
            <p class="text-muted">Žádné žádosti o výplatu.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reseller profil ID</th>
                        <th class="text-end">Částka</th>
                        <th>Měna</th>
                        <th>Status</th>
                        <th>Požadoval</th>
                        <th>Zpracoval</th>
                        <th>Zpracováno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                    @php
                        $statusMap = [
                            'pending'  => 'bg-warning text-dark',
                            'approved' => 'bg-primary',
                            'paid'     => 'bg-success',
                            'rejected' => 'bg-danger',
                        ];
                    @endphp
                    <tr>
                        <td class="f-12 text-muted">{{ $req->id }}</td>
                        <td>{{ $req->reseller_profile_id }}</td>
                        <td class="text-end f-w-500">
                            {{ number_format($req->amount / 100, 2, ',', ' ') }}
                        </td>
                        <td class="f-12">{{ strtoupper($req->currency ?? 'CZK') }}</td>
                        <td>
                            <span class="badge {{ $statusMap[$req->status] ?? 'bg-secondary' }}">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="f-12">{{ $req->requested_by ?? '—' }}</td>
                        <td class="f-12">{{ $req->processed_by ?? '—' }}</td>
                        <td class="f-12">
                            @if($req->processed_at)
                                {{ $req->processed_at instanceof \Carbon\Carbon ? $req->processed_at->format('d.m.Y H:i') : \Carbon\Carbon::parse($req->processed_at)->format('d.m.Y H:i') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.reseller-payout-requests.update', $req) }}"
                                  class="d-flex gap-1 flex-wrap align-items-center">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="form-select form-select-sm" style="width:110px">
                                    @foreach(['approved','rejected','paid'] as $s)
                                        <option value="{{ $s }}" {{ $req->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="note" class="form-control form-control-sm"
                                       placeholder="Poznámka" style="width:140px"
                                       value="{{ $req->note }}">
                                <button type="submit" class="btn btn-xs btn-outline-primary">Uložit</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->appends(request()->query())->links() }}</div>
        @endif

    </x-panel.card>
</div>
@endsection
