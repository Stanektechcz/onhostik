@extends('layouts.panel')

@section('title', 'Výplata provize')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-10">
        <x-panel.flash />

        <div class="grid grid-cols-12 gap-3">
            {{-- Payout request form --}}
            <div class="col-span-12 md:col-span-4">
                <x-panel.card title="Nová žádost o výplatu">
                    <form method="POST" action="{{ route('panel.reseller-payout-requests.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Částka (Kč) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control form-control-sm @error('amount') is-invalid @enderror"
                                value="{{ old('amount') }}" min="100" step="1" placeholder="min. 100" required>
                            <div class="form-text">Minimální částka: 100 Kč</div>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Poznámka</label>
                            <textarea name="note" class="form-control form-control-sm @error('note') is-invalid @enderror"
                                rows="3" maxlength="500" placeholder="Volitelná poznámka...">{{ old('note') }}</textarea>
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-full">Podat žádost</button>
                    </form>
                </x-panel.card>
            </div>

            {{-- Requests table --}}
            <div class="col-span-12 md:col-span-8">
                <x-panel.card title="Žádosti o výplatu">
                    @if($requests->isEmpty())
                        <p class="text-muted">Žádné žádosti.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-right">Částka</th>
                                    <th>Měna</th>
                                    <th>Stav</th>
                                    <th>Vytvořeno</th>
                                    <th>Zpracováno</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $req)
                                <tr>
                                    <td class="text-right f-w-500">
                                        {{ number_format($req->amount / 100, 2, ',', ' ') }}
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $req->currency }}</span></td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending'   => 'warning',
                                                'approved'  => 'info',
                                                'paid'      => 'success',
                                                'rejected'  => 'danger',
                                                'cancelled' => 'secondary',
                                            ];
                                            $statusLabels = [
                                                'pending'   => 'Čeká',
                                                'approved'  => 'Schváleno',
                                                'paid'      => 'Vyplaceno',
                                                'rejected'  => 'Zamítnuto',
                                                'cancelled' => 'Zrušeno',
                                            ];
                                            $sc = $statusColors[$req->status] ?? 'secondary';
                                            $sl = $statusLabels[$req->status] ?? ucfirst($req->status);
                                        @endphp
                                        <span class="badge bg-{{ $sc }}">{{ $sl }}</span>
                                    </td>
                                    <td class="f-12 text-muted">{{ $req->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="f-12">
                                        @if($req->processed_at)
                                            {{ $req->processed_at->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $requests->links() }}</div>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
</div>
@endsection
