@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Blog';
    $breadcrumbItems = ['Blog' => ''];
@endphp

@section('title', 'Blog')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Search bar --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('panel.blog.index') }}" class="flex gap-2 flex-wrap items-center">
                <input type="text" name="q" class="form-control form-control-sm" style="max-width:260px;"
                       placeholder="Hledat příspěvek…" value="{{ $search }}">
                @if($categories->isNotEmpty())
                    <select name="cat" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">Všechny kategorie</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="btn btn-outline-primary btn-sm">Hledat</button>
                @if($search || $category)
                    <a href="{{ route('panel.blog.index') }}" class="btn btn-outline-secondary btn-sm">Resetovat</a>
                @endif
            </form>
        </div>
    </div>

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            @forelse($posts as $post)

            @if($loop->first)
            {{-- Featured post: blog-shadow --}}
            <div class="col-span-6 xl:col-span-12 set-col-span-12 box-col-span-12">
                <div class="card">
                    <div class="blog-box blog-shadow">
                        @if($post->featured_image)
                            <img class="max-w-full h-auto" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                        @else
                            <div style="height:280px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;">
                                <i data-feather="book-open" style="width:60px;height:60px;opacity:.2;"></i>
                            </div>
                        @endif
                        <div class="blog-details">
                            <p>{{ $post->published_at?->format('d. M Y') }}</p>
                            <h5 class="text-white">{{ $post->title }}</h5>
                            <ul class="blog-social">
                                <li><i class="icofont icofont-user"></i>{{ $post->author?->name ?? 'OnHost' }}</li>
                                @if($post->category)<li><i class="icofont icofont-tag"></i>{{ $post->category }}</li>@endif
                            </ul>
                        </div>
                        <a href="{{ route('panel.blog.show', $post->slug) }}" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            @elseif($loop->index === 1)
            <div class="col-span-6 xl:col-span-12 set-col-span-12 box-col-span-12">
            @endif

            @if($loop->index === 1 || $loop->index === 2)
                <div class="card {{ $loop->index === 2 ? '' : '' }}">
                    <div class="blog-box blog-list grid grid-cols-12 card-gap">
                        <div class="col-span-5 sm:col-span-12">
                            @if($post->featured_image)
                                <img class="max-w-full h-auto sm-100-w" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                            @else
                                <div style="height:140px;background:linear-gradient(135deg,rgba(var(--theme-default),.08),rgba(var(--theme-default),.02));display:flex;align-items:center;justify-content:center;">
                                    <i data-feather="file-text" style="width:32px;height:32px;opacity:.25;"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-span-7 sm:col-span-12">
                            <div class="blog-details">
                                <div class="blog-date">
                                    <span>{{ $post->published_at?->format('d') ?? '--' }}</span>
                                    {{ $post->published_at?->format('M Y') ?? '' }}
                                </div>
                                <h6><a href="{{ route('panel.blog.show', $post->slug) }}">{{ $post->title }}</a></h6>
                                <div class="blog-bottom-content">
                                    <ul class="blog-social">
                                        <li>{{ $post->author?->name ?? 'OnHost' }}</li>
                                        @if($post->category)<li>{{ $post->category }}</li>@endif
                                    </ul>
                                    <hr>
                                    <p class="mt-0">{{ Str::limit($post->excerpt, 90) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($loop->index === 2)
            </div>
            @endif

            @if($loop->index >= 3)
            <div class="col-span-3 xxl:col-span-6 md:col-span-12 box-col-span-6">
                <div class="card">
                    <div class="blog-box blog-grid text-center">
                        @if($post->featured_image)
                            <img class="max-w-full h-auto top-radius-blog" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                        @else
                            <div class="top-radius-blog" style="height:160px;background:linear-gradient(135deg,rgba(var(--theme-default),.08),rgba(var(--theme-default),.02));display:flex;align-items:center;justify-content:center;">
                                <i data-feather="file-text" style="width:36px;height:36px;opacity:.25;"></i>
                            </div>
                        @endif
                        <div class="blog-details-main">
                            <ul class="blog-social">
                                <li>{{ $post->published_at?->format('d M Y') }}</li>
                                <li>{{ $post->author?->name ?? 'OnHost' }}</li>
                            </ul>
                            <hr>
                            <h6 class="blog-bottom-details">
                                <a href="{{ route('panel.blog.show', $post->slug) }}">{{ Str::limit($post->title, 60) }}</a>
                            </h6>
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
                        <h5 class="f-light">Žádné příspěvky k dispozici</h5>
                    </div>
                </div>
            </div>
            @endforelse

        </div>

        @if($posts->hasPages())
            <div class="mt-4">{{ $posts->links() }}</div>
        @endif
    </div>
</div>
@endsection

@php use Illuminate\Support\Str; @endphp
