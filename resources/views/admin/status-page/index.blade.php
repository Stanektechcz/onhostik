@extends('layouts.panel')
@section('title', 'Status Page — správa')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="grid grid-cols-12 items-center">
            <div class="col-span-12 sm:col-span-6">
                <h3>Status Page</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item active">Status Page</li>
                </ol>
            </div>
            <div class="col-span-12 sm:col-span-6 text-right">
                <a href="{{ route('front.status') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i data-feather="external-link" style="width:13px;height:13px" class="me-1"></i>
                    Zobrazit veřejnou stránku
                </a>
            </div>
        </div>
    </div>

    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">

        {{-- ── Components ─────────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="card">
                <div class="card-header flex justify-between items-center">
                    <h5 class="mb-0">Komponenty</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Název</th>
                                    <th>Skupina</th>
                                    <th>Monitor</th>
                                    <th>Viditelný</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($components as $comp)
                            <tr>
                                <td class="text-muted f-12">{{ $comp->sort_order }}</td>
                                <td class="font-semibold">{{ $comp->name }}</td>
                                <td class="text-muted f-12">{{ $comp->group_name ?? '—' }}</td>
                                <td class="f-12">{{ $comp->monitor?->label ?? $comp->monitor?->name ?? '—' }}</td>
                                <td>
                                    @if ($comp->is_visible)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST"
                                          action="{{ route('admin.status-page.components.destroy', $comp) }}"
                                          class="inline"
                                          data-confirm="Smazat?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs">
                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Žádné komponenty.</td>
                            </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Add component ───────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Přidat komponentu</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.status-page.components.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Skupina</label>
                            <input type="text" name="group_name" class="form-control form-control-sm"
                                   value="{{ old('group_name') }}" maxlength="100" placeholder="Volitelné">
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Monitor</label>
                            <select name="monitor_id" class="form-select form-select-sm">
                                <option value="">— bez monitoru —</option>
                                @foreach ($monitors as $m)
                                    <option value="{{ $m->id }}" {{ old('monitor_id') == $m->id ? 'selected' : '' }}>
                                        {{ $m->label ?? $m->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-6">
                                <label class="form-label f-12">Pořadí</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm"
                                       value="{{ old('sort_order', 0) }}" min="0">
                            </div>
                            <div class="col-span-6 flex items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_visible" value="1"
                                           id="is_visible" {{ old('is_visible', '1') === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label f-12" for="is_visible">Viditelný</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── Maintenances ────────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Plánovaná údržba</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13">
                            <thead class="table-light">
                                <tr>
                                    <th>Název</th>
                                    <th>Začátek</th>
                                    <th>Konec</th>
                                    <th>Stav</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($maintenances as $maint)
                            <tr>
                                <td class="font-semibold">{{ $maint->title }}</td>
                                <td class="text-muted f-12">{{ $maint->scheduled_start_at->format('d.m.Y H:i') }}</td>
                                <td class="text-muted f-12">{{ $maint->scheduled_end_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <span class="badge bg-{{ $maint->statusColor() }}">{{ $maint->statusLabel() }}</span>
                                </td>
                                <td class="text-right">
                                    <form method="POST"
                                          action="{{ route('admin.status-page.maintenances.destroy', $maint) }}"
                                          class="inline"
                                          data-confirm="Smazat?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs">
                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Žádná plánovaná údržba.</td>
                            </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($maintenances->hasPages())
                    <div class="p-3">{{ $maintenances->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Add maintenance ─────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Přidat údržbu</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.status-page.maintenances.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" required maxlength="200">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Popis</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2"
                                      maxlength="2000">{{ old('description') }}</textarea>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-6">
                                <label class="form-label f-12">Začátek *</label>
                                <input type="datetime-local" name="scheduled_start_at"
                                       class="form-control form-control-sm @error('scheduled_start_at') is-invalid @enderror"
                                       value="{{ old('scheduled_start_at') }}" required>
                                @error('scheduled_start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12">Konec *</label>
                                <input type="datetime-local" name="scheduled_end_at"
                                       class="form-control form-control-sm @error('scheduled_end_at') is-invalid @enderror"
                                       value="{{ old('scheduled_end_at') }}" required>
                                @error('scheduled_end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Stav</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="scheduled">Naplánováno</option>
                                <option value="in_progress">Probíhá</option>
                                <option value="completed">Dokončeno</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
