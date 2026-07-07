@extends('layouts.panel')

@section('title', 'Blokované IP adresy')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Blokované IP adresy">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>IP adresa</th>
                                <th>Důvod</th>
                                <th>Zablokoval</th>
                                <th>Platnost do</th>
                                <th>Vytvořeno</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)
                            <tr class="{{ $entry->isExpired() ? 'text-muted' : '' }}">
                                <td><code>{{ $entry->ip_address }}</code></td>
                                <td>{{ $entry->reason ?? '—' }}</td>
                                <td>{{ $entry->blocker?->email ?? '—' }}</td>
                                <td>
                                    @if($entry->expires_at)
                                        {{ $entry->expires_at->format('d.m.Y H:i') }}
                                        @if($entry->isExpired())
                                            <span class="badge bg-secondary">Expirováno</span>
                                        @endif
                                    @else
                                        <span class="badge bg-danger">Permanentní</span>
                                    @endif
                                </td>
                                <td>{{ $entry->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.ip-blocklist.destroy', $entry) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Odblokovat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Žádné blokované IP.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $entries->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-md-4">
            <x-panel.card title="Blokovat IP">
                <form method="POST" action="{{ route('admin.ip-blocklist.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">IP adresa</label>
                        <input type="text" name="ip_address" class="form-control" placeholder="1.2.3.4" value="{{ old('ip_address') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Důvod</label>
                        <input type="text" name="reason" class="form-control" maxlength="255" value="{{ old('reason') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Platnost do (prázdné = permanentní)</label>
                        <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Zablokovat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
