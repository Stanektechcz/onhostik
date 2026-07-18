@extends('layouts.panel')
@section('title', 'Segmentační štítky zákazníků')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Segmentační štítky zákazníků">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Štítek</th>
                                <th>Popis</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tags as $tag)
                            <tr>
                                <td>
                                    <span class="inline-flex items-center gap-2">
                                        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background-color:{{ $tag->color }};"></span>
                                        <strong>{{ $tag->name }}</strong>
                                    </span>
                                </td>
                                <td>{{ $tag->description ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.customer-segment-tags.destroy', $tag) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Opravdu smazat štítek?')">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $tags->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat štítek">
                <form method="POST" action="{{ route('admin.customer-segment-tags.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="80" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barva</label>
                        <div class="flex gap-2 items-center">
                            <input type="color" name="color" class="form-control form-control-color @error('color') is-invalid @enderror" value="{{ old('color', '#3b82f6') }}" required>
                            <span class="text-muted small">Vyberte barvu štítku</span>
                        </div>
                        @error('color')<div class="invalid-feedback block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
