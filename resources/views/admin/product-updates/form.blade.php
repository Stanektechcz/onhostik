@extends('layouts.panel')

@php
    use App\Domains\Communication\Models\ProductUpdate;

    $isEdit          = $update->exists;
    $breadcrumbTitle = $isEdit ? 'Upravit záznam' : 'Nový záznam';
    $breadcrumbItems = [
        'Administrace' => '#',
        'Changelog'    => route('admin.product-updates.index'),
        $breadcrumbTitle => '',
    ];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12 lg:col-span-8">
            <form method="POST"
                  action="{{ $isEdit
                      ? route('admin.product-updates.update', $update)
                      : route('admin.product-updates.store') }}">
                @csrf
                @if($isEdit) @method('PUT') @endif

                <x-panel.card :title="$breadcrumbTitle">
                    <div class="mb-3">
                        <label class="form-label" for="title">Název</label>
                        <input type="text" id="title" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $update->title) }}"
                               maxlength="160" required>
                        @error('title')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="body">Popis</label>
                        <textarea id="body" name="body" rows="8"
                                  class="form-control @error('body') is-invalid @enderror"
                                  maxlength="8000" required>{{ old('body', $update->body) }}</textarea>
                        @error('body')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="category">Kategorie</label>
                            <select id="category" name="category" class="form-select">
                                @foreach(ProductUpdate::CATEGORIES as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old('category', $update->category ?? 'feature') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <label class="form-label" for="version">Verze <span class="f-light">(volitelné)</span></label>
                            <input type="text" id="version" name="version" class="form-control"
                                   value="{{ old('version', $update->version) }}"
                                   maxlength="30" placeholder="např. 2.4.0">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="published_at">
                            Datum publikace <span class="f-light">(prázdné = teď)</span>
                        </label>
                        <input type="datetime-local" id="published_at" name="published_at" class="form-control"
                               value="{{ old('published_at', $update->published_at?->format('Y-m-d\TH:i')) }}"
                               style="max-width:260px;">
                        <div class="f-12 f-light mt-1">
                            Budoucí datum záznam naplánuje — zákazníkům se do té doby nezobrazí.
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="is_published"
                               name="is_published" value="1"
                               @checked(old('is_published', $update->is_published))>
                        <label class="form-check-label" for="is_published">Publikovat</label>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn btn-primary text-white">
                            <i data-feather="save" style="width:14px;height:14px;"></i>
                            Uložit
                        </button>
                        <a href="{{ route('admin.product-updates.index') }}" class="btn btn-light">Zrušit</a>
                    </div>
                </x-panel.card>
            </form>
        </div>
    </div>
</div>
@endsection
