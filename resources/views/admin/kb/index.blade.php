@extends('layouts.panel')

@php($breadcrumbTitle = 'Znalostní báze')

@section('title', 'Znalostní báze')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card title="Články znalostní báze">
            <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <form method="GET" action="{{ route('admin.kb.index') }}" class="d-flex gap-2 flex-wrap flex-grow-1 align-items-center">
                    <input type="text" name="q" class="form-control" style="max-width: 260px;"
                           placeholder="Hledat název…" value="{{ $search }}">
                    @if($categories->isNotEmpty())
                        <select name="cat" class="form-select w-auto">
                            <option value="">Všechny kategorie</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="status" class="form-select w-auto">
                        <option value="">Vše</option>
                        <option value="published" @selected($status === 'published')>Publikovaný</option>
                        <option value="draft" @selected($status === 'draft')>Skrytý</option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                    @if($search || $category || $status)
                        <a href="{{ route('admin.kb.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                    @endif
                </form>
                <a href="{{ route('admin.kb.create') }}" class="btn btn-primary btn-sm">
                    <i data-feather="plus" style="width:14px;height:14px"></i> Nový článek
                </a>
            </div>

            <x-panel.data-table :headers="['Název', 'Kategorie', 'Pořadí', 'Stav', '']">
                @forelse($articles as $article)
                    <tr>
                        <td class="f-w-500">{{ $article->title }}</td>
                        <td>
                            @if($article->category)
                                <span class="badge badge-light-info">{{ $article->category }}</span>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td class="f-12 f-light">{{ $article->sort_order }}</td>
                        <td>
                            @if($article->is_published)
                                <span class="badge badge-light-success">Publikován</span>
                            @else
                                <span class="badge badge-light-secondary">Skrytý</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('front.kb.show', $article->slug) }}" target="_blank"
                               class="btn btn-outline-secondary btn-sm">
                                <i data-feather="external-link" style="width:12px;height:12px"></i>
                            </a>
                            <a href="{{ route('admin.kb.edit', $article) }}" class="btn btn-outline-primary btn-sm">Upravit</a>
                            <form method="POST" action="{{ route('admin.kb.destroy', $article) }}" class="d-inline"
                                  onsubmit="return confirm('Smazat článek?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Smazat</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="f-light text-center py-3">Žádné články zatím.</td>
                    </tr>
                @endforelse
            </x-panel.data-table>
            {{ $articles->links() }}
        </x-panel.card>
    </div>
@endsection
