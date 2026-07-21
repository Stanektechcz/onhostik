@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Okna údržby';
    $breadcrumbItems = ['Nastavení' => route('admin.settings.index'), 'Okna údržby' => ''];
@endphp

@section('title', 'Okna údržby')

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-3">
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Nové okno údržby</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.maintenance-banners.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="title"
                                   class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="Plánovaná údržba serverů" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Zpráva *</label>
                            <textarea name="message" rows="3"
                                      class="form-control form-control-sm @error('message') is-invalid @enderror"
                                      placeholder="Systém bude dočasně nedostupný…" required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col">
                                <label class="form-label f-12">Začátek *</label>
                                <input type="datetime-local" name="starts_at"
                                       class="form-control form-control-sm @error('starts_at') is-invalid @enderror"
                                       value="{{ old('starts_at') }}" required>
                            </div>
                            <div class="col">
                                <label class="form-label f-12">Konec *</label>
                                <input type="datetime-local" name="ends_at"
                                       class="form-control form-control-sm @error('ends_at') is-invalid @enderror"
                                       value="{{ old('ends_at') }}" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Barva banneru</label>
                            <select name="color" class="form-select form-select-sm">
                                <option value="warning" @selected(old('color','warning')==='warning')>Upozornění (žlutá)</option>
                                <option value="danger"  @selected(old('color')==='danger')>Kritické (červená)</option>
                                <option value="info"    @selected(old('color')==='info')>Info (modrá)</option>
                                <option value="primary" @selected(old('color')==='primary')>Primární</option>
                            </select>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="show_on_frontend" value="1"
                                   id="showFront" @checked(old('show_on_frontend', true))>
                            <label class="form-check-label f-12" for="showFront">Zobrazit na webu</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="show_on_admin" value="1"
                                   id="showAdmin" @checked(old('show_on_admin', true))>
                            <label class="form-check-label f-12" for="showAdmin">Zobrazit v administraci</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="isActive" @checked(old('is_active', true))>
                            <label class="form-check-label f-12" for="isActive">Aktivní</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">Přidat</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border"><h5>Okna údržby ({{ $windows->count() }})</h5></div>
                <div class="card-body pt-0">
                    @if($windows->isEmpty())
                        <p class="text-center f-light py-4">Žádná okna údržby.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Název / zpráva</th>
                                    <th>Začátek</th>
                                    <th>Konec</th>
                                    <th>Zobrazení</th>
                                    <th>Stav</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($windows as $maint)
                                <tr>
                                    <td>
                                        <span class="badge badge-light-{{ $maint->color }} f-10 me-1">{{ ucfirst($maint->color) }}</span>
                                        <span class="f-w-500">{{ $maint->title }}</span>
                                        <div class="f-11 f-light mt-1"
                                             style="max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            {{ $maint->message }}
                                        </div>
                                    </td>
                                    <td class="f-12 text-nowrap">{{ $maint->starts_at->format('d.m.Y H:i') }}</td>
                                    <td class="f-12 text-nowrap">{{ $maint->ends_at->format('d.m.Y H:i') }}</td>
                                    <td class="f-11">
                                        @if($maint->show_on_frontend)
                                            <span class="badge badge-light-primary me-1">Web</span>
                                        @endif
                                        @if($maint->show_on_admin)
                                            <span class="badge badge-light-secondary">Admin</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$maint->is_active)
                                            <span class="badge badge-light-secondary f-10">Neaktivní</span>
                                        @elseif($maint->isCurrentlyActive())
                                            <span class="badge badge-light-danger f-10">Probíhá</span>
                                        @elseif($maint->isUpcoming())
                                            <span class="badge badge-light-warning f-10">Připravena</span>
                                        @else
                                            <span class="badge badge-light-success f-10">Skončila</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-outline-secondary btn-xs"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editMaint{{ $maint->id }}">
                                            Upravit
                                        </button>
                                        <form method="POST"
                                              action="{{ route('admin.maintenance-banners.destroy', $maint) }}"
                                              class="inline"
                                              data-confirm="Smazat okno údržby?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                        </form>
                                    </td>
                                </tr>

                                {{-- Edit modal --}}
                                <div class="modal fade" id="editMaint{{ $maint->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST"
                                              action="{{ route('admin.maintenance-banners.update', $maint) }}"
                                              class="modal-content">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h6 class="modal-title">Upravit okno údržby</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-2">
                                                    <label class="form-label f-12">Název *</label>
                                                    <input type="text" name="title"
                                                           class="form-control form-control-sm"
                                                           value="{{ $maint->title }}" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label f-12">Zpráva *</label>
                                                    <textarea name="message" rows="3"
                                                              class="form-control form-control-sm"
                                                              required>{{ $maint->message }}</textarea>
                                                </div>
                                                <div class="grid grid-cols-12 gap-2 mb-2">
                                                    <div class="col">
                                                        <label class="form-label f-12">Začátek *</label>
                                                        <input type="datetime-local" name="starts_at"
                                                               class="form-control form-control-sm"
                                                               value="{{ $maint->starts_at->format('Y-m-d\TH:i') }}"
                                                               required>
                                                    </div>
                                                    <div class="col">
                                                        <label class="form-label f-12">Konec *</label>
                                                        <input type="datetime-local" name="ends_at"
                                                               class="form-control form-control-sm"
                                                               value="{{ $maint->ends_at->format('Y-m-d\TH:i') }}"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label f-12">Barva</label>
                                                    <select name="color" class="form-select form-select-sm">
                                                        @foreach(\App\Models\MaintenanceWindow::COLORS as $c)
                                                            <option value="{{ $c }}" @selected($maint->color === $c)>{{ ucfirst($c) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="show_on_frontend" value="1"
                                                           @checked($maint->show_on_frontend)>
                                                    <label class="form-check-label f-12">Web</label>
                                                </div>
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="show_on_admin" value="1"
                                                           @checked($maint->show_on_admin)>
                                                    <label class="form-check-label f-12">Admin</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="is_active" value="1"
                                                           @checked($maint->is_active)>
                                                    <label class="form-check-label f-12">Aktivní</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
