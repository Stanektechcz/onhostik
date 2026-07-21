@extends('layouts.panel')
@section('title', 'Oznámení o změnách cen')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Oznámení o změnách cen">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Platnost od</th>
                                <th>Stav</th>
                                <th>Odesláno</th>
                                <th>Vytvořil</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $notification)
                            <tr>
                                <td>{{ $notification->title }}</td>
                                <td>{{ $notification->effective_from ? \Carbon\Carbon::parse($notification->effective_from)->format('d.m.Y') : '—' }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($notification->status) {
                                            'draft'     => 'secondary',
                                            'scheduled' => 'warning',
                                            'sent'      => 'success',
                                            default     => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ $notification->status }}</span>
                                </td>
                                <td>{{ $notification->sent_at ? \Carbon\Carbon::parse($notification->sent_at)->format('d.m.Y H:i') : '—' }}</td>
                                <td>{{ $notification->created_by ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.price-change-notifications.destroy', $notification) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Opravdu smazat?">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $notifications->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat oznámení">
                <form method="POST" action="{{ route('admin.price-change-notifications.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="150" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Obsah</label>
                        <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="5" required>{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Platnost od</label>
                        <input type="date" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror" value="{{ old('effective_from') }}" required>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stav</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="">-- vyberte --</option>
                            <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Koncept</option>
                            <option value="scheduled" {{ old('status') === 'scheduled' ? 'selected' : '' }}>Naplánováno</option>
                            <option value="sent" {{ old('status') === 'sent' ? 'selected' : '' }}>Odesláno</option>
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
