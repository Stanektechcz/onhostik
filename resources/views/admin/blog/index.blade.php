@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Blog';
    $breadcrumbItems = ['Blog' => ''];
@endphp

@section('title', 'Blog')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter bar --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('admin.blog.index') }}" class="flex gap-2 flex-wrap items-center">
                <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px;"
                       placeholder="Hledat název…" value="{{ $search }}">
                @if($categories->isNotEmpty())
                    <select name="cat" class="form-select form-select-sm w-auto">
                        <option value="">Všechny kategorie</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="form-select form-select-sm w-auto">
                    <option value="">Vše</option>
                    <option value="published" @selected($status === 'published')>Publikovaný</option>
                    <option value="draft" @selected($status === 'draft')>Koncept</option>
                </select>
                <select name="locale" class="form-select form-select-sm w-auto">
                    <option value="">Všechny jazyky</option>
                    <option value="cs" @selected(request('locale') === 'cs')>🇨🇿 CS</option>
                    <option value="en" @selected(request('locale') === 'en')>🇬🇧 EN</option>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Filtrovat</button>
                @if($search || $category || $status)
                    <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary btn-sm">Resetovat</a>
                @endif
                <a href="{{ route('admin.blog.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i data-feather="plus" style="width:14px;height:14px;"></i> Nový příspěvek
                </a>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            @forelse($posts as $post)
            @php
                $isFirst = $loop->first;
            @endphp

            @if($isFirst)
            {{-- First post: blog-shadow full-width --}}
            <div class="col-span-6 xl:col-span-12 set-col-span-12 box-col-span-12">
                <div class="card">
                    <div class="blog-box blog-shadow">
                        @if($post->featured_image)
                            <img class="max-w-full h-auto" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                        @else
                            <div style="height:280px;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;">
                                <i data-feather="file-text" style="width:64px;height:64px;opacity:.3;"></i>
                            </div>
                        @endif
                        <div class="blog-details">
                            <p>{{ $post->published_at?->format('d. M Y') ?? 'Koncept' }}</p>
                            <h5 class="text-white">{{ $post->title }}</h5>
                            <ul class="blog-social">
                                <li><i class="icofont icofont-user"></i>{{ $post->author?->name ?? 'Admin' }}</li>
                                @if($post->category)<li><i class="icofont icofont-tag"></i>{{ $post->category }}</li>@endif
                                <li>
                                    @if($post->is_published)
                                        <span class="badge badge-light-success">Publikován</span>
                                    @else
                                        <span class="badge badge-light-warning">Koncept</span>
                                    @endif
                                </li>
                                <li>
                                    <span class="badge badge-light-primary f-10">
                                        {{ strtoupper($post->locale ?? 'cs') }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <div style="position:absolute;top:12px;right:12px;display:flex;gap:6px;">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-primary">
                                <i data-feather="edit" style="width:12px;height:12px;"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline;"
                                  onsubmit="return confirm('Smazat příspěvek?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($loop->index === 1)
            {{-- Posts 2+3: blog-list side by side --}}
            <div class="col-span-6 xl:col-span-12 set-col-span-12 box-col-span-12">
            @endif

            @if($loop->index === 1 || $loop->index === 2)
                <div class="card {{ $loop->last ? '' : '' }}">
                    <div class="blog-box blog-list grid grid-cols-12 card-gap">
                        <div class="col-span-5 sm:col-span-12">
                            @if($post->featured_image)
                                <img class="max-w-full h-auto sm-100-w" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                            @else
                                <div style="height:140px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                                    <i data-feather="file-text" style="width:36px;height:36px;opacity:.3;"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-span-7 sm:col-span-12">
                            <div class="blog-details" style="position:relative;">
                                <div class="blog-date">
                                    <span>{{ $post->published_at?->format('d') ?? '--' }}</span>
                                    {{ $post->published_at?->format('M Y') ?? 'Koncept' }}
                                </div>
                                <h6>{{ $post->title }}</h6>
                                <div class="blog-bottom-content">
                                    <ul class="blog-social">
                                        <li>{{ $post->author?->name ?? 'Admin' }}</li>
                                        @if($post->category)<li>{{ $post->category }}</li>@endif
                                    </ul>
                                    <hr>
                                    <p class="mt-0">{{ Str::limit($post->excerpt, 80) }}</p>
                                    <div class="flex gap-2 mt-2">
                                        <a href="{{ route('admin.blog.show', $post) }}" class="btn btn-outline-secondary btn-xs">Náhled</a>
                                        <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-outline-primary btn-xs">Upravit</a>
                                        <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline;"
                                              onsubmit="return confirm('Smazat?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($loop->index === 2)
            </div>{{-- close col for posts 2+3 --}}
            @endif

            @if($loop->index >= 3)
            {{-- Remaining posts: blog-grid 3-col --}}
            <div class="col-span-3 xxl:col-span-6 md:col-span-12 box-col-span-6">
                <div class="card">
                    <div class="blog-box blog-grid text-center" style="position:relative;">
                        @if($post->featured_image)
                            <img class="max-w-full h-auto top-radius-blog" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                        @else
                            <div class="top-radius-blog" style="height:160px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                                <i data-feather="file-text" style="width:40px;height:40px;opacity:.3;"></i>
                            </div>
                        @endif
                        <div class="blog-details-main">
                            <ul class="blog-social">
                                <li>{{ $post->published_at?->format('d M Y') ?? 'Koncept' }}</li>
                                <li>{{ $post->author?->name ?? 'Admin' }}</li>
                                @if($post->is_published)
                                    <li><span class="badge badge-light-success f-11">Pub</span></li>
                                @else
                                    <li><span class="badge badge-light-warning f-11">Draft</span></li>
                                @endif
                            </ul>
                            <hr>
                            <h6 class="blog-bottom-details">{{ Str::limit($post->title, 60) }}</h6>
                        </div>
                        <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px;">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-primary py-1 px-2">
                                <i data-feather="edit-2" style="width:11px;height:11px;"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline;"
                                  onsubmit="return confirm('Smazat?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger py-1 px-2">
                                    <i data-feather="x" style="width:11px;height:11px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @empty
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i data-feather="file-text" style="width:48px;height:48px;" class="text-muted mb-3 block mx-auto"></i>
                        <h5 class="f-light">Žádné příspěvky zatím</h5>
                        <a href="{{ route('admin.blog.create') }}" class="btn btn-primary mt-3">Vytvořit první příspěvek</a>
                    </div>
                </div>
            </div>
            @endforelse

        </div>

        {{-- Pagination --}}
        @if($posts->hasPages())
        <div class="mt-4">{{ $posts->links() }}</div>
        @endif
    </div>
</div>
@endsection

@php use Illuminate\Support\Str; @endphp
