@extends('layouts.panel')

@section('title', 'Makra (Canned Responses)')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Makra (Canned Responses)">
        <x-slot name="headerRight">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createMacroModal">
                <i data-feather="plus" style="width:13px;height:13px"></i>
                Nové makro
            </button>
        </x-slot>

        @if($macros->isEmpty())
            <p class="text-muted mb-0">Zatím nejsou žádná makra.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Obsah (náhled)</th>
                            <th>Vytvořil</th>
                            <th class="text-end">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($macros as $macro)
                        <tr>
                            <td class="f-w-500">{{ $macro->title }}</td>
                            <td class="f-light f-13">{{ \Illuminate\Support\Str::limit($macro->body, 80) }}</td>
                            <td class="f-12">{{ $macro->creator?->name ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editMacroModal{{ $macro->id }}">
                                    <i data-feather="edit-2" style="width:12px;height:12px"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.support.macros.destroy', $macro) }}" class="d-inline"
                                      onsubmit="return confirm('Smazat makro?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel.card>
</div>

{{-- Create macro modal --}}
<div class="modal fade" id="createMacroModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.support.macros.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nové makro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Text odpovědi <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="6" maxlength="5000" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Edit macro modals --}}
@foreach($macros as $macro)
<div class="modal fade" id="editMacroModal{{ $macro->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.support.macros.update', $macro) }}">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upravit makro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ $macro->title }}" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Text odpovědi <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="6" maxlength="5000" required>{{ $macro->body }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary btn-sm">Uložit změny</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
