@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Požadavky na obnovu';
    $breadcrumbItems = ['Služby' => '', 'Požadavky na obnovu' => ''];
@endphp

@section('title', 'Požadavky na obnovu | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    @error('request')<div class="alert alert-light-danger">{{ $message }}</div>@enderror

    <x-panel.card title="Požadavky na obnovu ze zálohy" :subtitle="$pendingCount . ' čeká na vyřízení'">
        <div class="flex gap-2 flex-wrap mb-3">
            @foreach(['' => 'Vše', 'pending' => 'Čekající', 'approved' => 'Schválené', 'rejected' => 'Zamítnuté', 'completed' => 'Dokončené'] as $key => $label)
                <a href="{{ route('admin.snapshot-restore-requests.index', $key !== '' ? ['status' => $key] : []) }}"
                   class="btn btn-sm {{ $status === $key ? 'btn-primary text-white' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if($requests->isEmpty())
            <x-panel.empty-state icon="rotate-ccw" title="Žádné požadavky"
                subtitle="Zákazníci zatím o obnovu ze zálohy nepožádali." />
        @else
            <div class="table-responsive theme-scrollbar">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Služba</th>
                            <th>Zákazník</th>
                            <th>Bod obnovy</th>
                            <th>Stav</th>
                            <th>Poznámka</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.services.show', $req->service) }}" class="f-w-500">
                                        {{ $req->service?->label ?? '—' }}
                                    </a>
                                </td>
                                <td class="f-12">{{ $req->service?->customer?->company_name ?? '—' }}</td>
                                <td class="f-12">{{ $req->restore_point ?? $req->snapshot_id ?? '—' }}</td>
                                <td>
                                    @php
                                        $badge = match ($req->status) {
                                            'approved'  => 'badge-light-success',
                                            'rejected'  => 'badge-light-danger',
                                            'completed' => 'badge-light-primary',
                                            default     => 'badge-light-warning',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $req->status }}</span>
                                </td>
                                <td class="f-12 f-light">{{ $req->admin_note ?? $req->customer_note }}</td>
                                <td class="text-right">
                                    @if($req->status === 'pending')
                                        <div class="flex gap-1 justify-end">
                                            <form method="POST" action="{{ route('admin.snapshot-restore-requests.approve', $req) }}"
                                                  data-confirm="Schválit obnovu? Obnova přepíše aktuální data služby.">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-xs">Schválit</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.snapshot-restore-requests.reject', $req) }}"
                                                  data-prompt="Důvod zamítnutí (uvidí ho zákazník):"
                                                  data-prompt-target="admin_note"
                                                  data-prompt-min="3"
                                                  data-prompt-error="Uveďte prosím důvod (alespoň 3 znaky).">
                                                @csrf
                                                <input type="hidden" name="admin_note" value="">
                                                <button type="submit" class="btn btn-outline-danger btn-xs">Zamítnout</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="f-light f-12">Vyřízeno</span>
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
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    // A rejection must carry a reason — the customer sees it.
    function onhostRejectRestore(form) {
        const reason = prompt('Důvod zamítnutí (uvidí ho zákazník):');
        if (reason === null) return false;
        if (reason.trim().length < 3) {
            alert('Uveďte prosím důvod (alespoň 3 znaky).');
            return false;
        }
        form.querySelector('input[name="admin_note"]').value = reason.trim();
        return true;
    }
</script>
@endpush
