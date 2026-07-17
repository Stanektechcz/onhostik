@extends('layouts.panel')

@section('title', 'Štítky služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nový štítek">
                <form method="POST" action="{{ route('admin.services.tags.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" maxlength="50" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barva</label>
                        <input type="color" name="color" class="form-control form-control-color"
                               value="{{ old('color', '#6c757d') }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Vytvořit štítek</button>
                </form>
            </x-panel.card>
        </div>

        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Všechny štítky">
                @if($tags->isEmpty())
                    <p class="text-muted mb-0">Žádné štítky.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Štítek</th>
                                    <th>Barva</th>
                                    <th>Počet služeb</th>
                                    <th class="text-end">Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tags as $tag)
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: {{ $tag->color }}; color:rgba(var(--white),1);">
                                            {{ $tag->name }}
                                        </span>
                                    </td>
                                    <td class="f-12">{{ $tag->color }}</td>
                                    <td class="f-12">{{ $tag->services_count }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.services.tags.destroy', $tag) }}"
                                              class="d-inline" onsubmit="return confirm('Smazat štítek?')">
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
    </div>
</div>
@endsection
