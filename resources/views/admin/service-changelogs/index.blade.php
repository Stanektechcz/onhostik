@extends('layouts.panel')
@section('title', 'Záznamy změn služeb')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Záznamy změn služeb">
                <form method="GET" action="{{ route('admin.service-changelogs.index') }}" class="mb-3">
                    <div class="input-group">
                        <input type="number" name="service_id" class="form-control" placeholder="Filtrovat dle Služba ID" value="{{ request('service_id') }}">
                        <button type="submit" class="btn btn-outline-secondary">Filtrovat</button>
                        @if(request('service_id'))
                            <a href="{{ route('admin.service-changelogs.index') }}" class="btn btn-outline-danger">Zrušit</a>
                        @endif
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Služba</th>
                                <th>Typ události</th>
                                <th>Shrnutí</th>
                                <th>Způsobil</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($changelogs as $changelog)
                            <tr>
                                <td>{{ $changelog->service?->name ?? '#' . $changelog->service_id }}</td>
                                <td><code>{{ $changelog->event_type }}</code></td>
                                <td>{{ \Illuminate\Support\Str::limit($changelog->summary, 80) }}</td>
                                <td>{{ $changelog->caused_by ?? '—' }}</td>
                                <td>{{ $changelog->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $changelogs->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-md-4">
            <x-panel.card title="Přidat záznam">
                <form method="POST" action="{{ route('admin.service-changelogs.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Služba ID</label>
                        <input type="number" name="service_id" class="form-control @error('service_id') is-invalid @enderror" value="{{ old('service_id') }}" required>
                        @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Typ události</label>
                        <input type="text" name="event_type" class="form-control @error('event_type') is-invalid @enderror" value="{{ old('event_type') }}" maxlength="60" required>
                        @error('event_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shrnutí</label>
                        <textarea name="summary" class="form-control @error('summary') is-invalid @enderror" rows="3" maxlength="500" required>{{ old('summary') }}</textarea>
                        @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Způsobil</label>
                        <input type="text" name="caused_by" class="form-control @error('caused_by') is-invalid @enderror" value="{{ old('caused_by') }}" maxlength="100">
                        @error('caused_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
