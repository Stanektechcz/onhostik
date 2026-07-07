@extends('layouts.panel')

@section('title', 'Propagační bannery')

@section('content')
<x-panel.flash />

<div class="row g-4">
    <div class="col-lg-8">
        <x-panel.card title="Propagační bannery">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Typ</th>
                            <th>Umístění</th>
                            <th>Aktivní</th>
                            <th>Platnost od / do</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $banner)
                            @php
                                $typeBadge = match($banner->type) {
                                    'info'    => 'info',
                                    'success' => 'success',
                                    'warning' => 'warning',
                                    'danger'  => 'danger',
                                    default   => 'secondary',
                                };
                                $placementLabels = [
                                    'panel_top'       => 'Panel – nahoře',
                                    'panel_dashboard' => 'Panel – dashboard',
                                    'admin_top'       => 'Admin – nahoře',
                                ];
                            @endphp
                            <tr>
                                <td>{{ $banner->title }}</td>
                                <td><span class="badge bg-{{ $typeBadge }}">{{ $banner->type }}</span></td>
                                <td>{{ $placementLabels[$banner->placement] ?? $banner->placement }}</td>
                                <td>
                                    @if ($banner->is_active)
                                        <span class="badge bg-success">Aktivní</span>
                                    @else
                                        <span class="badge bg-secondary">Neaktivní</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $banner->starts_at ? \Carbon\Carbon::parse($banner->starts_at)->format('d.m.Y') : '—' }}
                                    /
                                    {{ $banner->ends_at ? \Carbon\Carbon::parse($banner->ends_at)->format('d.m.Y') : '—' }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.promotional-banners.update', $banner) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $banner->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ $banner->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.promotional-banners.destroy', $banner) }}" class="d-inline" onsubmit="return confirm('Opravdu smazat tento banner?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Žádné bannery nenalezeny.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($banners->hasPages())
                <div class="mt-3">
                    {{ $banners->links() }}
                </div>
            @endif
        </x-panel.card>
    </div>

    <div class="col-lg-4">
        <x-panel.card title="Nový banner">
            <form method="POST" action="{{ route('admin.promotional-banners.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="title" class="form-label">Název <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" maxlength="100" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">Text banneru <span class="text-danger">*</span></label>
                    <textarea id="body" name="body" rows="3" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="cta_text" class="form-label">Text tlačítka (CTA)</label>
                    <input type="text" id="cta_text" name="cta_text" class="form-control @error('cta_text') is-invalid @enderror"
                           value="{{ old('cta_text') }}" maxlength="60">
                    @error('cta_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="cta_url" class="form-label">URL tlačítka (CTA)</label>
                    <input type="url" id="cta_url" name="cta_url" class="form-control @error('cta_url') is-invalid @enderror"
                           value="{{ old('cta_url') }}" maxlength="255">
                    @error('cta_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Typ <span class="text-danger">*</span></label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">Vyberte typ</option>
                        <option value="info" @selected(old('type') === 'info')>Info</option>
                        <option value="success" @selected(old('type') === 'success')>Úspěch</option>
                        <option value="warning" @selected(old('type') === 'warning')>Varování</option>
                        <option value="danger" @selected(old('type') === 'danger')>Nebezpečí</option>
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="placement" class="form-label">Umístění <span class="text-danger">*</span></label>
                    <select id="placement" name="placement" class="form-select @error('placement') is-invalid @enderror" required>
                        <option value="">Vyberte umístění</option>
                        <option value="panel_top" @selected(old('placement') === 'panel_top')>Panel – nahoře</option>
                        <option value="panel_dashboard" @selected(old('placement') === 'panel_dashboard')>Panel – dashboard</option>
                        <option value="admin_top" @selected(old('placement') === 'admin_top')>Admin – nahoře</option>
                    </select>
                    @error('placement') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="starts_at" class="form-label">Platný od</label>
                    <input type="date" id="starts_at" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
                           value="{{ old('starts_at') }}">
                    @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="ends_at" class="form-label">Platný do</label>
                    <input type="date" id="ends_at" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
                           value="{{ old('ends_at') }}">
                    @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>

                <div class="mb-3 form-check">
                    <input type="hidden" name="is_dismissible" value="0">
                    <input type="checkbox" id="is_dismissible" name="is_dismissible" value="1" class="form-check-input" @checked(old('is_dismissible'))>
                    <label class="form-check-label" for="is_dismissible">Zavíratelný</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Vytvořit banner</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
