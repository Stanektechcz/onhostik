@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Znalostní báze';
    $breadcrumbItems = ['Znalostní báze' => ''];

    /* Group articles by category for browse-articles layout */
    $grouped = $articles->getCollection()->groupBy('category');
@endphp

@section('title', 'Znalostní báze')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- Hero search + stats --}}
            <div class="col-span-12">
                <div class="knowledgebase-bg">
                    <div style="height:260px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i data-feather="book-open" style="width:80px;height:80px;opacity:.15;"></i>
                    </div>
                </div>
                <div class="knowledgebase-search">
                    <div>
                        <h3 class="txt-dark">Správa znalostní báze</h3>
                        <form class="form-inline" method="GET" action="{{ route('admin.kb.index') }}">
                            <div class="form-group w-full d-flex gap-2">
                                <i data-feather="search"></i>
                                <input class="form-control-plaintext w-full" type="text" name="q"
                                       value="{{ $search }}" placeholder="Hledat článek…">
                                <button type="submit" class="btn btn-primary btn-sm">Hledat</button>
                                <a href="{{ route('admin.kb.create') }}" class="btn btn-success btn-sm">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i> Nový článek
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Stats widgets --}}
            <div class="col-span-4 xl:col-span-6 sm:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Celkem článků</h5>
                                <p>Publikovaných: {{ $articles->getCollection()->where('is_published', true)->count() }} /
                                   Skrytých: {{ $articles->getCollection()->where('is_published', false)->count() }}</p>
                            </div>
                            <i data-feather="book-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-4 xl:col-span-6 sm:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Kategorie</h5>
                                <p>{{ $grouped->keys()->filter()->count() }} kategorií s obsahem</p>
                            </div>
                            <i data-feather="aperture"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-4 xl:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Filtry</h5>
                                <div class="d-flex gap-2 flex-wrap mt-1">
                                    <form method="GET" action="{{ route('admin.kb.index') }}" class="d-flex gap-2">
                                        @if($categories->isNotEmpty())
                                        <select name="cat" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                            <option value="">Všechny kategorie</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                                            @endforeach
                                        </select>
                                        @endif
                                        <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                            <option value="">Vše</option>
                                            <option value="published" @selected($status === 'published')>Publikovaný</option>
                                            <option value="draft" @selected($status === 'draft')>Skrytý</option>
                                        </select>
                                        @if($search || $category || $status)
                                            <a href="{{ route('admin.kb.index') }}" class="btn btn-outline-light btn-sm">Resetovat</a>
                                        @endif
                                    </form>
                                </div>
                            </div>
                            <i data-feather="file-text"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Browse articles by category --}}
            <div class="col-span-12">
                <div class="header-faq">
                    <h5 class="mb-0">Procházet články dle kategorie</h5>
                </div>
                <div class="grid grid-cols-12">
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Články</h5>
                            </div>
                            <div class="card-body">
                                @if($articles->isEmpty())
                                    <div class="text-center py-5">
                                        <i data-feather="book-open" style="width:40px;height:40px;" class="text-muted d-block mx-auto mb-3"></i>
                                        <h5 class="f-light">Žádné články zatím</h5>
                                        <a href="{{ route('admin.kb.create') }}" class="btn btn-primary mt-2">Vytvořit první článek</a>
                                    </div>
                                @else
                                    <div class="grid grid-cols-12 gap-3 browse">
                                        @foreach($grouped as $cat => $catArticles)
                                        <div class="col-span-4 xl:col-span-6 md:col-span-12 xl-50">
                                            <div class="browse-articles {{ !$loop->first ? 'browse-bottom' : '' }}">
                                                <h6>
                                                    <span><i data-feather="archive"></i></span>
                                                    {{ $cat ?: 'Bez kategorie' }}
                                                    <small class="f-light ms-1">({{ $catArticles->count() }})</small>
                                                </h6>
                                                <ul>
                                                    @foreach($catArticles->take(4) as $article)
                                                    <li>
                                                        <a href="{{ route('admin.kb.edit', $article) }}">
                                                            <span><i data-feather="file-text"></i></span>
                                                            <span>{{ Str::limit($article->title, 50) }}</span>
                                                            @if(!$article->is_published)
                                                                <span class="badge badge-light-warning pull-right">Draft</span>
                                                            @else
                                                                <span class="badge badge-primary pull-right text-white">✓</span>
                                                            @endif
                                                        </a>
                                                    </li>
                                                    @endforeach
                                                    @if($catArticles->count() > 4)
                                                    <li>
                                                        <a href="{{ route('admin.kb.index') }}?cat={{ urlencode($cat) }}">
                                                            <span><i data-feather="arrow-right"></i></span>
                                                            <span>Zobrazit vše ({{ $catArticles->count() }})</span>
                                                        </a>
                                                    </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                        @endforeach

                                        @if($grouped->isEmpty())
                                        <div class="col-span-12">
                                            <div class="browse-articles">
                                                <h6><span><i data-feather="archive"></i></span>Všechny články</h6>
                                                <ul>
                                                    @foreach($articles as $article)
                                                    <li>
                                                        <a href="{{ route('admin.kb.edit', $article) }}">
                                                            <span><i data-feather="file-text"></i></span>
                                                            <span>{{ $article->title }}</span>
                                                        </a>
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Featured articles: product-box style --}}
            @if($articles->isNotEmpty())
            <div class="col-span-12">
                <div class="header-faq">
                    <h5>Nedávno přidané</h5>
                </div>
                <div class="grid grid-cols-12 card-gap">
                    @foreach($articles->take(4) as $article)
                    <div class="col-span-3 xl:col-span-6 md:col-span-12 xl-50 box-col-6">
                        <div class="card features-faq product-box" style="position:relative;">
                            <div class="faq-image product-img">
                                <div style="height:120px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                                    <i data-feather="file-text" style="width:40px;height:40px;opacity:.3;"></i>
                                </div>
                                <div class="product-hover">
                                    <ul>
                                        <li><a href="{{ route('front.kb.show', $article->slug) }}" target="_blank"><i class="icon-link"></i></a></li>
                                        <li><a href="{{ route('admin.kb.edit', $article) }}"><i class="icon-pencil-alt"></i></a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="pb-1">{{ Str::limit($article->title, 40) }}</h6>
                                <p class="c-light f-12">{{ Str::limit($article->excerpt, 60) }}</p>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span>{{ $article->updated_at?->format('d.m.Y') }}</span>
                                @if($article->is_published)
                                    <span class="badge badge-light-success">Publikován</span>
                                @else
                                    <span class="badge badge-light-warning">Skrytý</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        @if($articles->hasPages())
        <div class="mt-4">{{ $articles->links() }}</div>
        @endif
    </div>
</div>
@endsection

@php use Illuminate\Support\Str; @endphp
