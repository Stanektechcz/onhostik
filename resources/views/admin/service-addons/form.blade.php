@extends('layouts.panel')

@section('title', $addon->exists ? 'Upravit doplněk' : 'Nový doplněk')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.service-addons.index') }}" class="btn btn-sm btn-outline-secondary me-3">← Zpět</a>
        <h1 class="h4 mb-0">{{ $addon->exists ? 'Upravit doplněk' : 'Nový doplněk' }}</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ $addon->exists ? route('admin.service-addons.update', $addon) : route('admin.service-addons.store') }}" method="POST">
                @csrf
                @if($addon->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Název *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $addon->name) }}" required maxlength="100">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Slug *</label>
                        <input type="text" name="slug" class="form-control font-monospace @error('slug') is-invalid @enderror"
                               value="{{ old('slug', $addon->slug) }}" required maxlength="80">
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pořadí</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="{{ old('sort_order', $addon->sort_order ?? 0) }}" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Popis</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="500">{{ old('description', $addon->description) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cena (haléře/měsíc) *</label>
                        <div class="input-group">
                            <input type="number" name="price_czk" class="form-control @error('price_czk') is-invalid @enderror"
                                   value="{{ old('price_czk', $addon->price_czk ?? 0) }}" min="0" required>
                            <span class="input-group-text">hal/měs</span>
                        </div>
                        <small class="text-muted">Např. 5000 = 50 Kč/měs.</small>
                        @error('price_czk')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $addon->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Aktivní (viditelný zákazníkům)</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $addon->exists ? 'Uložit' : 'Vytvořit' }}</button>
                    <a href="{{ route('admin.service-addons.index') }}" class="btn btn-outline-secondary">Zrušit</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
