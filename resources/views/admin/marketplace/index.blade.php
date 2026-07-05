@extends('layouts.panel')
@section('title', 'Marketplace — správa')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3>App Marketplace</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item active">Marketplace</li>
                </ol>
            </div>
        </div>
    </div>

    <x-panel.flash />

    <div class="row g-4">

        {{-- ── App list ─────────────────────────────────────────────── --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Název</th>
                                    <th>Slug</th>
                                    <th>Kategorie</th>
                                    <th>Instalace</th>
                                    <th>Aktivní</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($apps as $app)
                            <tr>
                                <td class="text-muted f-12">{{ $app->sort_order }}</td>
                                <td class="fw-semibold">{{ $app->name }}</td>
                                <td class="font-monospace f-12 text-muted">{{ $app->slug }}</td>
                                <td><span class="badge bg-secondary f-11">{{ $app->categoryLabel() }}</span></td>
                                <td class="text-muted f-12">{{ $app->installations_count }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.marketplace.toggle', $app) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs {{ $app->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                            {{ $app->is_active ? 'Aktivní' : 'Neaktivní' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.marketplace.destroy', $app) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Smazat?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs">
                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Žádné aplikace.</td>
                            </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Add app ──────────────────────────────────────────────── --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Přidat aplikaci</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.marketplace.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Slug * <small class="text-muted">(jen a-z, 0-9, pomlčky)</small></label>
                            <input type="text" name="slug" class="form-control form-control-sm font-monospace @error('slug') is-invalid @enderror"
                                   value="{{ old('slug') }}" required maxlength="64" placeholder="my-app">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Kategorie *</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="cms">CMS</option>
                                <option value="ecommerce">E-shop</option>
                                <option value="database">Databáze</option>
                                <option value="email">E-mail</option>
                                <option value="other">Ostatní</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Popis</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label f-12">Ikona (feather)</label>
                                <input type="text" name="icon" class="form-control form-control-sm"
                                       value="{{ old('icon', 'package') }}" maxlength="50">
                            </div>
                            <div class="col-6">
                                <label class="form-label f-12">Min. disk (GB)</label>
                                <input type="number" name="min_disk_gb" class="form-control form-control-sm"
                                       value="{{ old('min_disk_gb', 1) }}" min="1" max="100">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label f-12">Pořadí</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm"
                                       value="{{ old('sort_order', 0) }}" min="0">
                            </div>
                            <div class="col-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                           id="is_active" checked>
                                    <label class="form-check-label f-12" for="is_active">Aktivní</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
