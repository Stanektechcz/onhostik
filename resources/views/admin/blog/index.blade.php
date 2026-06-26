@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Blog';
    $breadcrumbItems = ['Blog' => ''];
@endphp

@section('title', 'Blog')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card title="Příspěvky blogu">
            <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <form method="GET" action="{{ route('admin.blog.index') }}" class="d-flex gap-2 flex-wrap flex-grow-1 align-items-center">
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
                        <option value="draft" @selected($status === 'draft')>Koncept</option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('panel.admin.filter') }}</button>
                    @if($search || $category || $status)
                        <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                    @endif
                </form>
                <a href="{{ route('admin.blog.create') }}" class="btn btn-primary btn-sm">
                    <i data-feather="plus" style="width:14px;height:14px"></i> Nový příspěvek
                </a>
            </div>

            <x-panel.data-table :headers="['Název', 'Kategorie', 'Autor', 'Publikováno', 'Stav', '']">
                @forelse($posts as $post)
                    <tr>
                        <td class="f-w-500">{{ $post->title }}</td>
                        <td>
                            @if($post->category)
                                <span class="badge badge-light-secondary">{{ $post->category }}</span>
                            @else
                                <span class="f-light">—</span>
                            @endif
                        </td>
                        <td class="f-light f-12">{{ $post->author?->name ?? '—' }}</td>
                        <td class="f-12">{{ $post->published_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            @if($post->is_published)
                                <span class="badge badge-light-success">Publikován</span>
                            @else
                                <span class="badge badge-light-warning">Koncept</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-outline-primary btn-sm">Upravit</a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" class="d-inline"
                                  onsubmit="return confirm('Smazat příspěvek?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Smazat</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="f-light text-center py-3">Žádné příspěvky zatím.</td>
                    </tr>
                @endforelse
            </x-panel.data-table>
            {{ $posts->links() }}
        </x-panel.card>
    </div>
@endsection
