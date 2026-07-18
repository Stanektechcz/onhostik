@extends('layouts.panel')

@section('title', 'Uložené vyhledávací filtry')

@section('content')
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-8">
        <x-panel.card title="Uložené vyhledávací filtry">
            <x-panel.flash />

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Kontext</th>
                            <th>Výchozí</th>
                            <th>Datum</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filters as $filter)
                            <tr>
                                <td>{{ $filter->name }}</td>
                                <td><code class="small">{{ $filter->context }}</code></td>
                                <td>
                                    @if ($filter->is_default)
                                        <span class="badge bg-success">Výchozí</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $filter->created_at?->format('d.m.Y') }}</td>
                                <td class="flex gap-1">
                                    @unless ($filter->is_default)
                                        <form method="POST" action="{{ route('admin.saved-search-filters.update', $filter) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_default" value="1">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Nastavit výchozí
                                            </button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.saved-search-filters.destroy', $filter) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Smazat filtr?')">
                                            Smazat
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Žádné uložené filtry.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel.card>
    </div>

    <div class="col-span-12 lg:col-span-4">
        <x-panel.card title="Uložit nový filtr">
            <form method="POST" action="{{ route('admin.saved-search-filters.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Název <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" maxlength="100" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Kontext <span class="text-danger">*</span></label>
                    <input type="text" name="context" class="form-control @error('context') is-invalid @enderror"
                        value="{{ old('context') }}" maxlength="50" required
                        placeholder="např. admin.customers">
                    @error('context') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Filtry (JSON) <span class="text-danger">*</span></label>
                    <textarea name="filters_raw" class="form-control font-monospace @error('filters') is-invalid @enderror"
                        rows="5" placeholder="{}">{{ old('filters_raw', '{}') }}</textarea>
                    @error('filters') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Zadejte platný JSON objekt s hodnotami filtrů.</div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_default" value="1" class="form-check-input"
                        id="is_default" @checked(old('is_default'))>
                    <label class="form-check-label" for="is_default">Nastavit jako výchozí</label>
                </div>

                <button type="submit" class="btn btn-primary w-full">Uložit filtr</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
