@extends('layouts.panel')
@section('title', 'Žádosti o přenos domén')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Žádosti o přenos domén">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Doména</th>
                        <th>Stav</th>
                        <th>Auth kód</th>
                        <th>Admin poznámka</th>
                        <th>Datum</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $transferRequest)
                    <tr>
                        <td>{{ $transferRequest->customer?->name ?? '—' }}</td>
                        <td>{{ $transferRequest->domain }}</td>
                        <td>
                            @php
                                $badgeClass = match($transferRequest->status) {
                                    'pending'    => 'secondary',
                                    'processing' => 'warning',
                                    'completed'  => 'success',
                                    'failed'     => 'danger',
                                    'cancelled'  => 'secondary',
                                    default      => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeClass }}">{{ $transferRequest->status }}</span>
                        </td>
                        <td><code>***</code></td>
                        <td>{{ $transferRequest->admin_note ? \Illuminate\Support\Str::limit($transferRequest->admin_note, 60) : '—' }}</td>
                        <td>{{ $transferRequest->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.domain-transfer-requests.update', $transferRequest) }}" class="d-flex gap-1 align-items-center">
                                @csrf @method('PATCH')
                                <select name="status" class="form-select form-select-sm" style="min-width:130px">
                                    @foreach(['pending','processing','completed','failed','cancelled'] as $s)
                                        <option value="{{ $s }}" {{ $transferRequest->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Poznámka" value="{{ $transferRequest->admin_note }}" maxlength="500" style="min-width:120px">
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Uložit</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Žádné záznamy.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->links() }}</div>
    </x-panel.card>
</div>
@endsection
