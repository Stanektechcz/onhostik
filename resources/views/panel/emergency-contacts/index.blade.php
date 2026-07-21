@extends('layouts.panel')

@section('title', 'Nouzové kontakty')

@section('content')
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-8">
        <x-panel.card title="Nouzové kontakty">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jméno</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Vztah</th>
                            <th>Pozastavení</th>
                            <th>Expiraci</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr>
                                <td>{{ $contact->name }}</td>
                                <td class="small">{{ $contact->email }}</td>
                                <td class="small text-muted">{{ $contact->phone ?? '—' }}</td>
                                <td class="small text-muted">{{ $contact->relationship ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($contact->notify_on_suspension)
                                        <span class="badge bg-success">✓</span>
                                    @else
                                        <span class="badge bg-secondary">✗</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($contact->notify_on_expiry)
                                        <span class="badge bg-success">✓</span>
                                    @else
                                        <span class="badge bg-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('panel.emergency-contacts.destroy', $contact) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-confirm="Smazat kontakt?">
                                            Smazat
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Žádné nouzové kontakty.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel.card>
    </div>

    <div class="col-span-12 lg:col-span-4">
        <x-panel.card title="Přidat kontakt">
            <form method="POST" action="{{ route('panel.emergency-contacts.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Jméno <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" maxlength="100" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}" maxlength="150" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                        value="{{ old('phone') }}" maxlength="30">
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Vztah</label>
                    <input type="text" name="relationship" class="form-control @error('relationship') is-invalid @enderror"
                        value="{{ old('relationship') }}" maxlength="60" placeholder="např. Správce, Manžel/ka">
                    @error('relationship') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2 form-check">
                    <input type="checkbox" name="notify_on_suspension" value="1" class="form-check-input"
                        id="notify_on_suspension" @checked(old('notify_on_suspension'))>
                    <label class="form-check-label" for="notify_on_suspension">Notifikovat při pozastavení</label>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="notify_on_expiry" value="1" class="form-check-input"
                        id="notify_on_expiry" @checked(old('notify_on_expiry'))>
                    <label class="form-check-label" for="notify_on_expiry">Notifikovat při expiraci</label>
                </div>

                <button type="submit" class="btn btn-primary w-full">Přidat kontakt</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
